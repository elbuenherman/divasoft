<?php

// ============================================================================
//  test_glmocr_cli.php
//  Prueba CLI de la API GLM-OCR de Z.AI para parsear PDFs de facturas.
//  Endpoint: https://api.z.ai/api/paas/v4/layout_parsing
//  Modelo:   glm-ocr
//  Uso:      php test_glmocr_cli.php <codigo_adjunto>   (por defecto codigo=79)
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL); 
set_time_limit(0);
ini_set("memory_limit", "512M");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Leer API key de Z.AI.
$ruta_key = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
if(!file_exists($ruta_key))
    die("No existe el archivo de API key: ".$ruta_key."\n");
$ZAI_API_KEY = trim((string)file_get_contents($ruta_key));
if($ZAI_API_KEY == "")
    die("API key de Z.AI vacia\n");

// Codigo del adjunto desde CLI (por defecto 79).
$codigo = isset($argv[1]) ? (int)$argv[1] : 79;

// Abrir archivo de log.
$archivo_log = "/tmp/resultado_glmocr_".$codigo."_".date("Ymd_His").".txt";
$fh_log      = fopen($archivo_log, "w");


// Imprime en pantalla y graba al mismo tiempo en el archivo de log si existe.
function log_dual($texto)
    {
    global $fh_log;
    echo $texto;
    if($fh_log)
        fwrite($fh_log, $texto);
    }


// Leer el adjunto.
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    log_dual("Adjunto codigo ".$codigo." no encontrado\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Verificar que sea PDF.
$ext_arch = strtolower(pathinfo((string)$nombrearchivo, PATHINFO_EXTENSION));
$es_pdf   = (stripos((string)$mimetype, 'pdf') !== false || $ext_arch == 'pdf');
if(!$es_pdf)
    {
    log_dual("El adjunto no es PDF (mime=".$mimetype.", ext=".$ext_arch.")\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }

$tamano_pdf = strlen((string)$archivo);
$pdf_base64 = base64_encode((string)$archivo);
$data_uri   = "data:application/pdf;base64,".$pdf_base64;

log_dual("=== TEST GLM-OCR CLI ===\n");
log_dual("Codigo adjunto: ".$codigo."\n");
log_dual("Nombre: ".$nombrearchivo."\n");
log_dual("Mime: ".$mimetype."\n");
log_dual("Tamano: ".number_format($tamano_pdf)." bytes\n");
log_dual("Tamano base64: ".number_format(strlen($pdf_base64))." chars\n");
log_dual("Inicio: ".date("Y-m-d H:i:s")."\n\n");

// Construir payload JSON.
$body = array(
    "model" => "glm-ocr",
    "file"  => $data_uri
    );
$body_json = json_encode($body);

// Llamada cURL al endpoint de layout_parsing.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/layout_parsing");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$ZAI_API_KEY,
    "Content-Type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $body_json);

log_dual("--- LLAMADA A GLM-OCR ---\n");
log_dual("Endpoint: https://api.z.ai/api/paas/v4/layout_parsing\n");
log_dual("Iniciando...\n");

$t_inicio  = microtime(true);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
$tiempo    = round(microtime(true) - $t_inicio, 2);
curl_close($ch);

log_dual("Duracion: ".$tiempo." s\n");
log_dual("HTTP: ".$http_code."\n");

if($curl_err != "")
    {
    log_dual("ERROR cURL: ".$curl_err."\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }

// Guardar respuesta cruda completa.
$archivo_resp = "/tmp/glmocr_respuesta_".$codigo."_".date("Ymd_His").".json";
$data         = json_decode((string)$response, true);
if($data !== null)
    file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_resp, (string)$response);

log_dual("Respuesta cruda guardada en: ".$archivo_resp."\n");

if($http_code != 200)
    {
    log_dual("\nERROR HTTP ".$http_code."\n");
    log_dual("Cuerpo de la respuesta:\n".(string)$response."\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }

if($data === null)
    {
    log_dual("\nERROR: la respuesta no es JSON valido\n");
    log_dual("Primeros 1000 chars:\n".substr((string)$response, 0, 1000)."\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }

// Mostrar usage / tokens.
$usage = isset($data["usage"]) ? $data["usage"] : array();
log_dual("\n--- USAGE ---\n");
log_dual("prompt_tokens:     ".(isset($usage["prompt_tokens"]) ? $usage["prompt_tokens"] : 0)."\n");
log_dual("completion_tokens: ".(isset($usage["completion_tokens"]) ? $usage["completion_tokens"] : 0)."\n");
log_dual("total_tokens:      ".(isset($usage["total_tokens"]) ? $usage["total_tokens"] : 0)."\n");

// data_info / num_pages.
$num_pages = isset($data["data_info"]["num_pages"]) ? $data["data_info"]["num_pages"] : "?";
log_dual("num_pages: ".$num_pages."\n");

// md_results.
$md_results = isset($data["md_results"]) ? (string)$data["md_results"] : "";
$len_md     = strlen($md_results);
log_dual("md_results length: ".number_format($len_md)." chars\n");

if($len_md > 0)
    {
    $archivo_md = "/tmp/glmocr_md_".$codigo."_".date("Ymd_His").".txt";
    file_put_contents($archivo_md, $md_results);
    log_dual("md_results guardado en: ".$archivo_md."\n");

    log_dual("\n--- PRIMEROS 2000 CHARS DE md_results ---\n");
    log_dual(substr($md_results, 0, 2000)."\n");
    if($len_md > 2000)
        log_dual("\n[... truncado, total ".number_format($len_md)." chars - ver ".$archivo_md." ...]\n");
    }
else
    {
    log_dual("\nmd_results vacio o no presente.\n");
    log_dual("Claves del JSON top-level: ".implode(", ", array_keys($data))."\n");
    }

log_dual("\n=== FIN ===\n");
log_dual("Tiempo total: ".$tiempo." s\n");

if($fh_log) fclose($fh_log);
echo "\nSalida guardada en: ".$archivo_log."\n";
