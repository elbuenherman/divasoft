<?php
  
// ============================================================================
//  cron_procesar_facturas.php  (CLI - cron cada 3 minutos en SiteGround)
//  Procesa con IA UN adjunto pendiente por corrida (el mas reciente).
// 
//  CONTROL DE COSTO (cada procesamiento cuesta dinero real):
//   - Solo adjuntos de los ULTIMOS 7 DIAS.
//   - Maximo 3 intentos por adjunto (CAMPOE1). Al 3er fallo se descarta
//     (ESTADOIA=9) para no reintentar en bucle quemando saldo.
//   - Salta packing/statement y tipos no procesables.
//
//  Corre cada 3 min (*/3): mientras haya trabajo continuo el cache de prompt de
//  Anthropic (5 min, se renueva en cada lectura) se mantiene caliente -> mas barato.
//
//  Campos de archivo_correo usados (existentes, sin DDL):
//   - CAMPOE1        : contador de intentos fallidos.
//   - ESTADOIA       : 1 pendiente (default) / 2 OK / 9 descartado.
//   - FECHAPROCESADOIA: fecha del procesamiento OK.
//
//  Uso: php cron_procesar_facturas.php
//  Cron (SiteGround, cada 3 minutos):
//    */3 * * * * /usr/local/bin/php /home/.../Develop2026/cron_procesar_facturas.php
// ============================================================================

// Solo por CLI (este script dispara el CLI de IA, que gasta dinero).
if(php_sapi_name() != "cli")
    {
    header("HTTP/1.1 403 Forbidden");
    echo "Este script solo se ejecuta por linea de comandos (cron).";
    exit;
    }

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(0);
ini_set("memory_limit", "512M");
date_default_timezone_set("America/Guayaquil"); // Ecuador

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Binario PHP del server (AJUSTAR a la ruta real que muestra Site Tools) y el
// CLI de IA a invocar (mismo directorio que este script).
$php_bin  = "/usr/local/bin/php";
$ruta_cli = __DIR__ . "/procesa_factura_final_cli_haiku.php";

$ruta_lock  = "/tmp/lock_cron_procesar.txt";
$ruta_log   = "/tmp/cron_procesar_".date("Ymd").".log";
$ruta_pausa = "/tmp/pausa_cron_ia.txt"; // pausa automatica por error de saldo/key

// ----------------------------------------------------------------------------
// Log del dia (append) + stdout.
// ----------------------------------------------------------------------------
function log_cron($ruta_log, $mensaje)
    {
    $linea = "[".date("Y-m-d H:i:s")."] ".$mensaje."\n";
    file_put_contents($ruta_log, $linea, FILE_APPEND);
    echo $linea;
    }

// ----------------------------------------------------------------------------
// PAUSA por error de API (saldo/key): si se activo hace menos de 1 hora, no
// intentar nada (todos los adjuntos fallarian igual y solo llenarian el log).
// Se reanuda solo al cumplir la hora. La activa el post-proceso de mas abajo.
// ----------------------------------------------------------------------------
if(file_exists($ruta_pausa))
    {
    $edad_pausa = time() - filemtime($ruta_pausa);
    if($edad_pausa < 3600)
        {
        log_cron($ruta_log, "EN PAUSA por error de API (saldo/key) hace ".$edad_pausa."s. No se intenta (se reanuda al cumplir 1h).");
        exit(0);
        }
    unlink($ruta_pausa);
    log_cron($ruta_log, "Pausa de API vencida (".$edad_pausa."s). Se reanuda el procesamiento.");
    }

// ----------------------------------------------------------------------------
// LOCK de 5 minutos (una factura pesada puede pasar de 3 min; no queremos dos
// corridas a la vez). Menor a esa edad -> hay otra activa, salir.
// ----------------------------------------------------------------------------
$segundos_lock = 5 * 60;
if(file_exists($ruta_lock))
    {
    $edad = time() - filemtime($ruta_lock);
    if($edad < $segundos_lock)
        {
        log_cron($ruta_log, "SALTADO: ya hay una corrida activa (lock de ".$edad."s).");
        exit(0);
        }
    log_cron($ruta_log, "AVISO: lock huerfano de ".$edad."s. Se continua.");
    }
file_put_contents($ruta_lock, date("Y-m-d H:i:s")." pid=".getmypid());

// ----------------------------------------------------------------------------
// Procesar. try/catch/finally para no dejar el lock colgado.
// ----------------------------------------------------------------------------
try
    {
    // SELECCION: el adjunto pendiente mas reciente de la ultima semana, no
    // descartado y sin agotar reintentos, de un tipo procesable.
    $sql_sel = "SELECT
        ac.CODIGO AS CODIGO,
        ac.NOMBREARCHIVO AS NOMBREARCHIVO,
        COALESCE(ac.CAMPOE1, 0) AS INTENTOS
        FROM archivo_correo ac
        INNER JOIN correo_facturas_fincas cf ON cf.IDCORREO = ac.IDCORREO
        LEFT JOIN factura_finca ff ON ff.CODIGOADJUNTO = ac.CODIGO
        WHERE ff.CODIGO IS NULL
          AND cf.FECHAHORA >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND LOWER(ac.NOMBREARCHIVO) NOT LIKE '%packing%'
          AND LOWER(ac.NOMBREARCHIVO) NOT LIKE '%statement%'
          AND LOWER(SUBSTRING_INDEX(ac.NOMBREARCHIVO, '.', -1)) IN ('pdf', 'xlsx', 'xls')
          AND (ac.CAMPOE1 IS NULL OR ac.CAMPOE1 < 3)
          AND (ac.ESTADOIA IS NULL OR ac.ESTADOIA <> 9)
        ORDER BY cf.FECHAHORA DESC
        LIMIT 1";
    $res_sel = mysqli_query($link, $sql_sel);

    if(!$res_sel || mysqli_num_rows($res_sel) == 0)
        {
        log_cron($ruta_log, "Sin adjuntos pendientes procesables (ultimos 7 dias).");
        }
    else
        {
        $fila          = mysqli_fetch_assoc($res_sel);
        $codigo        = (int)$fila["CODIGO"];
        $nombre        = (string)$fila["NOMBREARCHIVO"];
        $intentos_prev = (int)$fila["INTENTOS"];
        $intento       = $intentos_prev + 1;

        log_cron($ruta_log, "INICIO codigo ".$codigo." (".$nombre.") - intento ".$intento."/3.");

        // Incrementar el contador ANTES de procesar: si el CLI muere con fatal,
        // el intento ya quedo contado y no se cae en bucle infinito.
        mysqli_query($link, "UPDATE archivo_correo SET CAMPOE1 = COALESCE(CAMPOE1, 0) + 1 WHERE CODIGO = ".$codigo);

        // Cerrar la conexion antes del exec (el CLI puede tardar minutos: evita
        // "MySQL server has gone away" por wait_timeout).
        mysqli_close($link);

        // Ejecutar el CLI y ESPERAR (secuencial, NO en segundo plano).
        $t0      = microtime(true);
        $salida  = array();
        $retorno = 0;
        exec($php_bin." ".escapeshellarg($ruta_cli)." ".$codigo." 2>&1", $salida, $retorno);
        $tiempo  = round(microtime(true) - $t0, 1);

        // Reconectar para verificar el resultado y actualizar el estado.
        $link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
        mysqli_query($link, "SET CHARACTER SET utf8");

        // Si el CLI salteo (el adjunto ya estaba lockeado por el flujo manual u
        // otra corrida), NO es un fallo: revertir el incremento para no acercarlo
        // al descarte por 3 fallos sin razon.
        $texto_salida = implode("\n", $salida);
        if(strpos($texto_salida, "SALTADO") !== false)
            {
            mysqli_query($link, "UPDATE archivo_correo SET CAMPOE1 = GREATEST(COALESCE(CAMPOE1, 0) - 1, 0) WHERE CODIGO = ".$codigo);
            log_cron($ruta_log, "SALTADO codigo ".$codigo." (".$nombre."): ya en proceso por otro flujo. Intento NO contado.");
            }
        else if(strpos($texto_salida, "ERROR_API:") !== false)
            {
            // Fallo EXTERNO de la API (sin saldo, rate limit, API caida/timeout,
            // key vencida): NO es culpa del archivo. Revertir el incremento para
            // que el adjunto quede pendiente y se reintente al resolverse.
            mysqli_query($link, "UPDATE archivo_correo SET CAMPOE1 = GREATEST(COALESCE(CAMPOE1, 0) - 1, 0) WHERE CODIGO = ".$codigo);
            $detalle_api = "";
            if(preg_match('/ERROR_API:([^\r\n]*)/', $texto_salida, $m_api))
                $detalle_api = trim($m_api[1]);
            log_cron($ruta_log, "Intento NO contado - error de API: ".$detalle_api." (codigo ".$codigo.", ".$nombre.").");

            // Si es saldo o key, todos los adjuntos fallarian igual cada 3 min:
            // pausar el cron 1 hora (se reanuda solo). Los transitorios (rate,
            // caida, conexion) NO pausan: se reintenta en la proxima corrida.
            if(strpos($texto_salida, "ERROR_API:SALDO") !== false || strpos($texto_salida, "ERROR_API:AUTH") !== false)
                {
                file_put_contents($ruta_pausa, date("Y-m-d H:i:s")." ".$detalle_api);
                log_cron($ruta_log, "PAUSA: error de saldo/key. Cron en pausa 1h (".$ruta_pausa.").");
                }
            }
        else
            {
            // Exito = se creo la fila en factura_finca con ese CODIGOADJUNTO.
            $res_ok = mysqli_query($link, "SELECT CODIGO FROM factura_finca WHERE CODIGOADJUNTO = ".$codigo." LIMIT 1");
            $exito  = ($res_ok && mysqli_num_rows($res_ok) > 0);

            if($exito)
                {
                mysqli_query($link, "UPDATE archivo_correo SET ESTADOIA = 2, FECHAPROCESADOIA = NOW(), CAMPOE1 = 0 WHERE CODIGO = ".$codigo);
                log_cron($ruta_log, "OK codigo ".$codigo." (".$nombre.") en ".$tiempo."s (retorno CLI=".$retorno.").");
                }
            else
                {
                // Fallo. Si ya agoto los 3 intentos, descartar (ESTADOIA=9).
                $ultimas = implode(" | ", array_slice($salida, -6));
                if($intento >= 3)
                    {
                    mysqli_query($link, "UPDATE archivo_correo SET ESTADOIA = 9 WHERE CODIGO = ".$codigo);
                    log_cron($ruta_log, "DESCARTADO codigo ".$codigo." (".$nombre.") tras 3 fallos - REVISAR MANUAL. retorno=".$retorno.", ".$tiempo."s.");
                    }
                else
                    {
                    log_cron($ruta_log, "FALLO codigo ".$codigo." (intento ".$intento."/3), se reintentara. retorno=".$retorno.", ".$tiempo."s.");
                    }
                log_cron($ruta_log, "  Ultimas lineas CLI: ".$ultimas);
                }
            }
        }
    }
catch(\Throwable $e)
    {
    log_cron($ruta_log, "ERROR FATAL: ".$e->getMessage()." (".$e->getFile().":".$e->getLine().").");
    }
finally
    {
    if(file_exists($ruta_lock))
        unlink($ruta_lock);
    }

exit(0);
