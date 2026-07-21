<?php
 
// ============================================================================
//  cron_extraer_correos.php  (CLI - cron horario en SiteGround)
//  Extrae automaticamente los correos de facturas de los ULTIMOS 2 DIAS.
//  SOLO extraccion de correos + adjuntos (NO procesa facturas con IA: eso
//  sigue siendo manual, con revision humana, en otro flujo).
//
//  Corre CADA HORA. La ventana de 2 dias solapada cubre caidas del cron de
//  hasta 48h; la deduplicacion (procesa_mensaje_factura verifica IDCORREO y
//  devuelve 'saltado:duplicado') hace que el solape sea seguro: solo entra
//  lo nuevo.
//
//  Uso: php cron_extraer_correos.php
//  Cron (SiteGround, minuto 15 de cada hora):
//    15 * * * * /usr/local/bin/php /home/.../Develop2026/cron_extraer_correos.php
//  (ver ruta exacta al final de este archivo / en la respuesta).
// ============================================================================

// Solo por CLI. Si se intenta abrir por web, no hacer nada.
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
date_default_timezone_set("America/Guayaquil"); // Ecuador (fincas / correos)

include("variables_globales.php");
include("funciones.php");
include("funciones_v2.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Rutas de lock y log (en /tmp: siempre escribible en el hosting).
$ruta_lock = "/tmp/lock_cron_correos.txt";
$ruta_log  = "/tmp/cron_correos_".date("Ymd").".log";

// ----------------------------------------------------------------------------
// Escribe una linea en el log del dia (append) y tambien a stdout (el cron
// puede capturar la salida por mail).
// ----------------------------------------------------------------------------
function log_cron($ruta_log, $mensaje)
    {
    $linea = "[".date("Y-m-d H:i:s")."] ".$mensaje."\n";
    file_put_contents($ruta_log, $linea, FILE_APPEND);
    echo $linea;
    }

// ----------------------------------------------------------------------------
// LOCK: evita que dos corridas se solapen (importante con corridas horarias).
// Si el lock tiene menos de 50 minutos, hay otra corrida activa -> salir.
// Si tiene mas de 50 minutos, se considera huerfano y se continua.
// ----------------------------------------------------------------------------
$segundos_lock = 50 * 60; // 50 minutos
if(file_exists($ruta_lock))
    {
    $edad = time() - filemtime($ruta_lock);
    if($edad < $segundos_lock)
        {
        log_cron($ruta_log, "SALTADO: ya hay una corrida activa (lock de ".$edad."s < ".$segundos_lock."s).");
        exit(0);
        }
    log_cron($ruta_log, "AVISO: lock huerfano de ".$edad."s (> ".$segundos_lock."s). Se continua.");
    }

// Crear/renovar el lock.
file_put_contents($ruta_lock, date("Y-m-d H:i:s")." pid=".getmypid());

// ----------------------------------------------------------------------------
// Ventana de 2 dias: desde hoy-2 hasta hoy (Y-m-d).
// ----------------------------------------------------------------------------
$fecha_hasta = date("Y-m-d");
$fecha_desde = date("Y-m-d", strtotime("-2 days"));

// ----------------------------------------------------------------------------
// Extraccion. try/catch/finally para que un fallo NO deje el lock colgado ni
// mate el cron en silencio.
// ----------------------------------------------------------------------------
try
    {
    log_cron($ruta_log, "INICIO extraccion. Rango: ".$fecha_desde." a ".$fecha_hasta.".");
    $resultado = extraer_correos_facturas($fecha_desde, $fecha_hasta);
    log_cron($ruta_log, "RESULTADO: ".$resultado);
    log_cron($ruta_log, "FIN extraccion.");
    }
catch(\Throwable $e)
    {
    log_cron($ruta_log, "ERROR FATAL: ".$e->getMessage()." (".$e->getFile().":".$e->getLine().").");
    }
finally
    {
    // Siempre liberar el lock, haya o no error.
    if(file_exists($ruta_lock))
        unlink($ruta_lock);
    }

exit(0);
