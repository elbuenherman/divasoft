<?php

// ============================================================================
//  test_glmocr.php
//  Version WEB de la prueba de GLM-OCR de Z.AI para parsear PDFs de facturas.
//  Mismo flujo que test_glmocr_cli.php pero con salida HTML y boton de descarga.
//
//  Uso normal:  test_glmocr.php?codigo=79
//  Descarga md: test_glmocr.php?codigo=79&descargar=md
// ============================================================================

ini_set("display_errors", "1"); 
error_reporting(E_ALL);
set_time_limit(120);
ini_set("max_execution_time", "120");
ini_set("memory_limit", "512M");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Codigo del adjunto desde GET (por defecto 79).
$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;

// ----------------------------------------------------------------------------
// MODO DESCARGA: ?codigo=X&descargar=md
// Sirve el archivo .md mas reciente de /tmp para este codigo como adjunto.
// ----------------------------------------------------------------------------
if(isset($_GET["descargar"]) && $_GET["descargar"] === "md")
    {
    $patron   = "/tmp/glmocr_md_".$codigo."_*.txt";
    $archivos = glob($patron);
    if(empty($archivos))
        die("No hay archivo md guardado para el codigo ".$codigo.". Procesa la factura primero.");
    usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
    $archivo_md_descarga = $archivos[0];

    header("Content-Type: text/markdown; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"factura_".$codigo."_glmocr.md\"");
    echo file_get_contents($archivo_md_descarga);
    exit;
    }

// ----------------------------------------------------------------------------
// FLUJO NORMAL: procesar la factura y mostrar HTML.
// ----------------------------------------------------------------------------

// API key.
$ruta_key = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
if(!file_exists($ruta_key))
    die("No existe el archivo de API key: ".htmlspecialchars($ruta_key, ENT_QUOTES, 'UTF-8'));
$ZAI_API_KEY = trim((string)file_get_contents($ruta_key));
if($ZAI_API_KEY == "")
    die("API key de Z.AI vacia");

// Leer el adjunto de la base.
$adjunto_error = "";
$nombrearchivo = "";
$mimetype      = "";
$archivo       = "";

$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    $adjunto_error = "Adjunto codigo ".$codigo." no encontrado";
    }
else
    {
    mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $ext_arch = strtolower(pathinfo((string)$nombrearchivo, PATHINFO_EXTENSION));
    $es_pdf   = (stripos((string)$mimetype, 'pdf') !== false || $ext_arch == 'pdf');
    if(!$es_pdf)
        $adjunto_error = "El adjunto no es PDF (mime=".$mimetype.", ext=".$ext_arch.")";
    }

// Llamada a GLM-OCR (solo si el adjunto es valido).
$exito        = false;
$response     = "";
$http_code    = 0;
$curl_err     = "";
$tiempo       = 0;
$data         = null;
$archivo_resp = "";
$archivo_md   = "";
$tamano_pdf   = 0;
$pdf_base64   = "";

if($adjunto_error == "")
    {
    $tamano_pdf = strlen((string)$archivo);
    $pdf_base64 = base64_encode((string)$archivo);
    $data_uri   = "data:application/pdf;base64,".$pdf_base64;

    $body = array(
        "model" => "glm-ocr",
        "file"  => $data_uri
        );
    $body_json = json_encode($body);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/layout_parsing");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer ".$ZAI_API_KEY,
        "Content-Type: application/json"
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body_json);

    $t_inicio  = microtime(true);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    $tiempo    = round(microtime(true) - $t_inicio, 2);
    curl_close($ch);

    // Guardar respuesta cruda completa en /tmp.
    $archivo_resp = "/tmp/glmocr_respuesta_".$codigo."_".date("Ymd_His").".json";
    $data         = json_decode((string)$response, true);
    if($data !== null)
        file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    else
        file_put_contents($archivo_resp, (string)$response);

    if($http_code == 200 && $data !== null)
        {
        $md_results_tmp = isset($data["md_results"]) ? (string)$data["md_results"] : "";
        if(strlen($md_results_tmp) > 0)
            {
            $archivo_md = "/tmp/glmocr_md_".$codigo."_".date("Ymd_His").".txt";
            file_put_contents($archivo_md, $md_results_tmp);
            $exito = true;
            }
        }
    }

// Extraer campos para renderizar.
$usage             = ($data !== null && isset($data["usage"]))                  ? $data["usage"]                  : array();
$num_pages         = ($data !== null && isset($data["data_info"]["num_pages"])) ? $data["data_info"]["num_pages"] : "?";
$md_results        = ($data !== null && isset($data["md_results"]))             ? (string)$data["md_results"]     : "";
$len_md            = strlen($md_results);

$prompt_tokens     = isset($usage["prompt_tokens"])     ? (int)$usage["prompt_tokens"]     : 0;
$completion_tokens = isset($usage["completion_tokens"]) ? (int)$usage["completion_tokens"] : 0;
$total_tokens      = isset($usage["total_tokens"])      ? (int)$usage["total_tokens"]      : 0;

// Costo aproximado (tarifa GLM, estimacion - pricing real de glm-ocr sin confirmar).
$costo_estimado = ($prompt_tokens / 1000000.0) * 0.60
                + ($completion_tokens / 1000000.0) * 1.92;

// Render HTML.
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Test GLM-OCR Web</title>';
echo '<style>';
echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; padding: 20px; color: #222; }';
echo 'h1 { margin:0; font-size:20px; color:#222; }';
echo 'h2 { color: #88010e; margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 4px; }';
echo 'h3 { color: #555; margin-top: 16px; font-size: 14px; }';
echo 'pre { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; overflow: auto; max-height: 80vh; font-size: 12px; }';
echo '.meta { font-size: 13px; line-height: 1.7; }';
echo '.meta b { color: #555; }';
echo '.err { color: #88010e; font-weight: bold; }';
echo '.btn-descarga { display:inline-block; padding:8px 16px; background:#88010e; color:white; text-decoration:none; border-radius:4px; font-weight:bold; }';
echo '.btn-descarga:hover { background:#a30214; }';
echo '</style></head><body>';

echo '<h1>Test GLM-OCR (Z.AI) - codigo '.(int)$codigo.'</h1>';

// Boton de descarga (solo si la corrida fue exitosa).
if($exito)
    echo '<p><a class="btn-descarga" href="?codigo='.(int)$codigo.'&descargar=md">Descargar md_results como .md</a></p>';

// Si el adjunto fallo, mostrar error y terminar.
if($adjunto_error != "")
    {
    echo '<h2 class="err">'.htmlspecialchars($adjunto_error, ENT_QUOTES, 'UTF-8').'</h2>';
    echo '</body></html>';
    exit;
    }

echo '<h2>Adjunto</h2>';
echo '<div class="meta">';
echo '<b>CODIGO:</b> '.(int)$codigo.'<br>';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano_pdf).' bytes ('.number_format($tamano_pdf / 1024, 1).' KB)<br>';
echo '<b>BASE64 chars:</b> '.number_format(strlen($pdf_base64));
echo '</div>';

echo '<h2>Llamada GLM-OCR</h2>';
echo '<div class="meta">';
echo '<b>Endpoint:</b> https://api.z.ai/api/paas/v4/layout_parsing<br>';
echo '<b>Modelo:</b> glm-ocr<br>';
echo '<b>Duracion:</b> '.$tiempo.' s<br>';
echo '<b>HTTP:</b> '.(int)$http_code;
echo '</div>';

if($curl_err != "")
    {
    echo '<h2 class="err">ERROR cURL</h2>';
    echo '<pre>'.htmlspecialchars($curl_err, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

if($http_code != 200)
    {
    echo '<h2 class="err">ERROR HTTP '.(int)$http_code.'</h2>';
    echo '<pre>'.htmlspecialchars((string)$response, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '<p><b>Respuesta cruda guardada en:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8').'</p>';
    echo '</body></html>';
    exit;
    }

if($data === null)
    {
    echo '<h2 class="err">Respuesta no es JSON valido</h2>';
    echo '<pre>'.htmlspecialchars(substr((string)$response, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    echo '<p><b>Respuesta cruda guardada en:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8').'</p>';
    echo '</body></html>';
    exit;
    }

echo '<h2>Usage</h2>';
echo '<div class="meta">';
echo '<b>prompt_tokens:</b> '.number_format($prompt_tokens).'<br>';
echo '<b>completion_tokens:</b> '.number_format($completion_tokens).'<br>';
echo '<b>total_tokens:</b> '.number_format($total_tokens);
echo '</div>';

echo '<h2>Estructura</h2>';
echo '<div class="meta">';
echo '<b>num_pages:</b> '.htmlspecialchars((string)$num_pages, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>md_results length:</b> '.number_format($len_md).' chars<br>';
echo '<b>Costo estimado:</b> ~$'.number_format($costo_estimado, 6).' USD (GLM aprox: input $0.60/M, output $1.92/M)<br>';
echo '<b>Respuesta cruda:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8');
if($archivo_md != "")
    echo '<br><b>md_results:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8');
echo '</div>';

echo '<h2>md_results COMPLETO</h2>';
if($len_md > 0)
    {
    echo '<pre>'.htmlspecialchars($md_results, ENT_QUOTES, 'UTF-8').'</pre>';
    }
else
    {
    echo '<p class="err">md_results vacio o no presente.</p>';
    echo '<p><b>Claves del JSON top-level:</b> '.htmlspecialchars(implode(", ", array_keys($data)), ENT_QUOTES, 'UTF-8').'</p>';
    }

echo '</body></html>';
