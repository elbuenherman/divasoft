<?php

// ============================================================================
//  test_glm46v_cli.php
//  Version CLI de test_glm46v.php. Misma logica (endpoint, modelo glm-4.6v,
//  conversion PDF -> JPG con Ghostscript -r150, payload con bloques image_url,
//  prompt y costos identicos). Sin HTML: salida por texto plano y log dual a
//  archivo en /tmp para no perder la salida si la termina VSCode/SSH.
//
//  Uso: php test_glm46v_cli.php <codigo>
//  Ejemplo: php test_glm46v_cli.php 47
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(0);
ini_set("memory_limit", "512M");
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($argv[1]) ? (int)$argv[1] : 79;

// API key Z.AI.
$ruta_key = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
if(!file_exists($ruta_key))
    die("No existe el archivo de API key Z.AI: ".$ruta_key."\n");
$ZAI_API_KEY = trim((string)file_get_contents($ruta_key));
if($ZAI_API_KEY == "")
    die("API key Z.AI vacia\n");

$fecha_corrida = date("Ymd_His");

// Log dual: imprime por stdout y graba a archivo en /tmp.
$archivo_log = "/tmp/glm46v_log_".$codigo."_".$fecha_corrida.".txt";
$fh_log      = fopen($archivo_log, "w");

function log_dual($texto)
    {
    global $fh_log;
    echo $texto;
    if($fh_log)
        fwrite($fh_log, $texto);
    }

// Tiempo total del script.
$t_total_inicio = microtime(true);

// ----------------------------------------------------------------------------
// Extrae JSON del texto devuelto por el modelo (limpia fences markdown).
// ----------------------------------------------------------------------------
function extraer_json($texto)
    {
    $t = trim((string)$texto);
    if(strpos($t, "```") === 0)
        {
        $t = preg_replace('/^```(?:json)?\s*/', '', $t);
        $t = preg_replace('/\s*```\s*$/', '', $t);
        $t = trim($t);
        }
    $ini = strpos($t, "{");
    $fin = strrpos($t, "}");
    if($ini === false || $fin === false || $fin < $ini)
        return $t;
    return substr($t, $ini, $fin - $ini + 1);
    }

log_dual("=== TEST GLM-4.6V CLI ===\n");
log_dual("Codigo adjunto: ".$codigo."\n");
log_dual("Inicio:         ".date("Y-m-d H:i:s")."\n\n");

// ----------------------------------------------------------------------------
// PASO 1: LEER ADJUNTO
// ----------------------------------------------------------------------------
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    log_dual("ERROR: adjunto codigo ".$codigo." no encontrado\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$ext_arch = strtolower(pathinfo((string)$nombrearchivo, PATHINFO_EXTENSION));
$es_pdf   = (stripos((string)$mimetype, 'pdf') !== false || $ext_arch == 'pdf');
if(!$es_pdf)
    {
    log_dual("ERROR: el adjunto no es PDF (mime=".$mimetype.", ext=".$ext_arch.")\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

$tamano_pdf = strlen((string)$archivo);

log_dual("--- ADJUNTO ---\n");
log_dual("Nombre: ".$nombrearchivo."\n");
log_dual("Mime:   ".$mimetype."\n");
log_dual("Tamano: ".number_format($tamano_pdf)." bytes (".number_format($tamano_pdf / 1024, 1)." KB)\n\n");

// ----------------------------------------------------------------------------
// PASO 2: CONVERTIR PDF A JPG POR PAGINA (Ghostscript -r150)
// ----------------------------------------------------------------------------
$pdf_tmp = "/tmp/glm46v_pdf_".$codigo."_".$fecha_corrida.".pdf";
file_put_contents($pdf_tmp, (string)$archivo);

$prefijo = "/tmp/glm46v_page_".$codigo."_".$fecha_corrida;
$cmd = "/bin/gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -dJPEGQ=90 -sOutputFile=".escapeshellarg($prefijo."-%d.jpg")." ".escapeshellarg($pdf_tmp)." 2>&1";

$t_gs_inicio = microtime(true);
$output      = shell_exec($cmd);
$t_gs        = round(microtime(true) - $t_gs_inicio, 2);

$imagenes = glob($prefijo."-*.jpg");
sort($imagenes, SORT_NATURAL);

// Cleanup: borrar PDF temporal + JPGs al final del script, incluido en errores.
// NO se borra el JSON de respuesta cruda ni el log.
register_shutdown_function(function()
    {
    global $pdf_tmp, $imagenes;
    if(isset($pdf_tmp) && $pdf_tmp != "" && file_exists($pdf_tmp))
        @unlink($pdf_tmp);
    if(isset($imagenes) && is_array($imagenes))
        {
        $total_limpieza = count($imagenes);
        for($i = 0; $i < $total_limpieza; $i++)
            if(file_exists($imagenes[$i]))
                @unlink($imagenes[$i]);
        }
    });

log_dual("--- CONVERSION PDF -> JPG (Ghostscript -r150) ---\n");
log_dual("Tiempo gs:        ".$t_gs." s\n");
log_dual("Paginas generadas: ".count($imagenes)."\n");

if(empty($imagenes))
    {
    log_dual("\nERROR: Ghostscript no genero ninguna imagen JPG\n");
    log_dual("Salida gs:\n".(string)$output."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

$num_paginas = count($imagenes);
for($i = 0; $i < $num_paginas; $i++)
    {
    $tam_kb = file_exists($imagenes[$i]) ? (filesize($imagenes[$i]) / 1024) : 0;
    log_dual("  Pagina ".($i + 1).": ".$imagenes[$i]." (".number_format($tam_kb, 1)." KB)\n");
    }
log_dual("\n");

// ----------------------------------------------------------------------------
// PROMPT (nowdoc - identico al web)
// ----------------------------------------------------------------------------
$prompt = <<<'PROMPT'
Eres un experto en facturas de flores ecuatorianas. Te paso un PDF de factura.

TECNICA VISUAL OBLIGATORIA para leer la tabla principal de cajas:

Para cada linea de la tabla, ANTES de leer sus valores: imagina graficamente que copias la fila del header (con los numeros 30/35/40/50/60/70/80/90/100/110/120) y la pegas justo encima de la linea que estas analizando. De esta forma puedes ver con claridad bajo que numero esta cada valor. Luego identifica el LARGO de la caja segun la columna donde aparece el numero de ramos.

Esta tecnica es critica porque las tablas tienen muchas filas y el header esta solo arriba. Sin esta alineacion mental es muy facil leer un valor en la columna equivocada.

REGLA CRITICA PARA GRUPOS REPETIDOS:
Cuando veas muchas filas seguidas (5 o mas) con el mismo numero en la misma columna, NO asumas que esta en la columna que crees por inercia. RE-VERIFICA la columna explicitamente para CADA fila como si fuera la primera.

EXTRAE LA SIGUIENTE INFORMACION del PDF y devolvela como JSON estricto:

CABECERA:
- FINCA: nombre de la finca emisora
- RUC: ruc o identificacion fiscal de la finca
- NUMERO_FACTURA: numero de factura
- FECHA: fecha de emision (YYYY-MM-DD)
- CLIENTEMARCACION: marca o cliente (Box Marking, no Ship To)
- SUBTOTAL: numerico
- DESCUENTO: numerico
- IVA: numerico
- TOTAL: numerico

LOGISTICA:
- PAIS: pais destino
- MAWB: master air waybill
- HAWB: house air waybill
- DAE: declaracion aduanera
- AEROLINEA: aerolinea (si aparece)
- FORWARDER: forwarder o carrier

CAJAS (array de objetos, una entrada por LINEA del detalle):
- NUMERO_CAJA: numero o rango (ej "1", "1-2", "31-33")
- TIPO_CAJA: FB, HB, QB, EB, etc.
- VARIEDAD: nombre de la variedad
- LARGO: numero del largo en cm (30, 35, 40, 50, 60, 70, 80, 90, 100, 110, 120). APLICA LA TECNICA VISUAL.
- TALLOS_POR_RAMO: en STxB, ej "25X" -> 25
- RAMOS: cantidad de ramos en esa columna
- TALLOS_TOTAL: total de tallos
- PRECIO_UNITARIO: precio unitario
- PRECIO_TOTAL: precio total de la linea
- ALERTA: vacio o texto corto

REGLAS:
1. Si una caja aparece en multiples filas con distintos largos o precios, genera una entrada por cada fila.
2. NUMERO_CAJA puede ser rango como "1-2" o "31-33". Mantenlo literal.
3. Si no puedes determinar el LARGO con certeza, ponlo en null y agrega ALERTA.
4. NO inventes datos.

VALIDACION FINAL:
- TOTAL_CAJAS_DETECTADAS
- TOTAL_LINEAS_DETECTADAS
- TOTAL_RAMOS_CALCULADO
- TOTAL_TALLOS_CALCULADO
- TOTAL_DOLAR_CALCULADO
- TOTAL_RAMOS_FOOTER (del PDF, o null)
- TOTAL_TALLOS_FOOTER
- TOTAL_DOLAR_FOOTER
- DISCREPANCIAS

FORMATO DE RESPUESTA: JSON estricto, sin markdown, sin fences. Estructura:

{
  "CABECERA": {...},
  "LOGISTICA": {...},
  "CAJAS": [...],
  "VALIDACION": {...},
  "ALERTAS_GLOBALES": [...]
}

Devuelve SOLO el JSON, sin texto antes ni despues.
PROMPT;

// ----------------------------------------------------------------------------
// PASO 3: CONSTRUIR PAYLOAD (un image_url por pagina + bloque text con prompt)
// ----------------------------------------------------------------------------
$content = array();
for($i = 0; $i < $num_paginas; $i++)
    {
    $b64 = base64_encode((string)file_get_contents($imagenes[$i]));
    $content[] = array(
        "type"      => "image_url",
        "image_url" => array(
            "url" => "data:image/jpeg;base64,".$b64
            )
        );
    }
$content[] = array(
    "type" => "text",
    "text" => $prompt
    );

$payload = array(
    "model"    => "glm-4.6v",
    "messages" => array(
        array(
            "role"    => "user",
            "content" => $content
            )
        ),
    "temperature" => 0,
    "max_tokens"  => 16000
    );
$payload_json = json_encode($payload);

// ----------------------------------------------------------------------------
// PASO 4: POST a GLM-4.6V
// ----------------------------------------------------------------------------
log_dual("--- LLAMADA A GLM-4.6V ---\n");
log_dual("Endpoint: https://api.z.ai/api/paas/v4/chat/completions\n");
log_dual("Modelo:   glm-4.6v\n");
log_dual("Iniciando...\n");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/chat/completions");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$ZAI_API_KEY,
    "Content-Type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);

$t_inicio  = microtime(true);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
$tiempo    = round(microtime(true) - $t_inicio, 2);
curl_close($ch);

log_dual("Tiempo:   ".$tiempo." s\n");
log_dual("HTTP:     ".$http_code."\n");

// ----------------------------------------------------------------------------
// PASO 5: GUARDAR RESPUESTA CRUDA EN /tmp
// ----------------------------------------------------------------------------
$archivo_resp = "/tmp/glm46v_".$codigo."_".$fecha_corrida.".json";
$data         = json_decode((string)$response, true);
if($data !== null)
    file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_resp, (string)$response);

log_dual("Respuesta cruda: ".$archivo_resp."\n");

if($curl_err != "")
    {
    log_dual("\nERROR cURL: ".$curl_err."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

if($http_code != 200)
    {
    log_dual("\nERROR HTTP ".$http_code."\n");
    log_dual("Cuerpo respuesta:\n".(string)$response."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

if($data === null)
    {
    log_dual("\nERROR: respuesta no es JSON valido (envoltorio OpenAI)\n");
    log_dual("Primeros 1000 chars:\n".substr((string)$response, 0, 1000)."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

// ----------------------------------------------------------------------------
// USAGE / COSTO (input $0.30/M, output $0.90/M)
// ----------------------------------------------------------------------------
$input_t  = isset($data["usage"]["prompt_tokens"])     ? (int)$data["usage"]["prompt_tokens"]     : 0;
$output_t = isset($data["usage"]["completion_tokens"]) ? (int)$data["usage"]["completion_tokens"] : 0;
$total_t  = isset($data["usage"]["total_tokens"])      ? (int)$data["usage"]["total_tokens"]      : ($input_t + $output_t);
$costo    = ($input_t / 1000000.0) * 0.30
          + ($output_t / 1000000.0) * 0.90;

log_dual("\n--- USAGE ---\n");
log_dual("prompt_tokens:     ".number_format($input_t)."\n");
log_dual("completion_tokens: ".number_format($output_t)."\n");
log_dual("total_tokens:      ".number_format($total_t)."\n");
log_dual("Costo:             ~$".number_format($costo, 6)." USD (input $0.30/M, output $0.90/M)\n");

// ----------------------------------------------------------------------------
// PASO 6: PARSEAR contenido (choices[0].message.content)
// ----------------------------------------------------------------------------
$texto = "";
if(isset($data["choices"][0]["message"]["content"]))
    $texto = (string)$data["choices"][0]["message"]["content"];

$json_str    = extraer_json($texto);
$extraccion  = json_decode($json_str, true);

log_dual("\n--- RESULTADO ---\n");

if($extraccion === null)
    {
    log_dual("JSON invalido. Texto crudo devuelto por GLM-4.6V:\n");
    log_dual($texto."\n");
    }
else
    {
    log_dual(json_encode($extraccion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

    $cajas = isset($extraccion["CAJAS"]) && is_array($extraccion["CAJAS"]) ? $extraccion["CAJAS"] : array();
    log_dual("\nLineas extraidas en CAJAS: ".count($cajas)."\n");
    }

// ----------------------------------------------------------------------------
// FIN
// ----------------------------------------------------------------------------
$t_total = round(microtime(true) - $t_total_inicio, 2);

log_dual("\n=== FIN ===\n");
log_dual("Tiempo total: ".$t_total." s\n");

if($fh_log) fclose($fh_log);
echo "\nLog guardado en: ".$archivo_log."\n";
echo "Respuesta cruda: ".$archivo_resp."\n";
