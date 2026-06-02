<?php

// ============================================================================
//  test_glmocr_paginado.php
//  Version WEB paginada: convierte el PDF a JPG (300 DPI con Ghostscript) y
//  procesa CADA pagina con una llamada separada a GLM-OCR. Concatena los
//  md_results en un solo archivo y ofrece descarga.
//
//  Uso normal:  test_glmocr_paginado.php?codigo=79
//  Descarga:    test_glmocr_paginado.php?codigo=79&descargar=completo
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL); 
set_time_limit(600);
ini_set("max_execution_time", "600");
ini_set("memory_limit", "512M");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Codigo del adjunto desde GET (por defecto 79).
$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;

// ----------------------------------------------------------------------------
// MODO DESCARGA: ?codigo=X&descargar=completo
// Sirve el archivo .md concatenado mas reciente de /tmp para este codigo.
// ----------------------------------------------------------------------------
if(isset($_GET["descargar"]) && $_GET["descargar"] === "completo")
    {
    $patron   = "/tmp/glmocr_completo_".$codigo."_*.md";
    $archivos = glob($patron);
    if(empty($archivos))
        die("No hay archivo md completo para el codigo ".$codigo.". Procesa la factura primero.");
    usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
    $archivo_descarga = $archivos[0];

    header("Content-Type: text/markdown; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"factura_".$codigo."_paginado.md\"");
    echo file_get_contents($archivo_descarga);
    exit;
    }

// ----------------------------------------------------------------------------
// FLUJO NORMAL: procesar y mostrar HTML.
// ----------------------------------------------------------------------------

// API key.
$ruta_key = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
if(!file_exists($ruta_key))
    die("No existe el archivo de API key: ".htmlspecialchars($ruta_key, ENT_QUOTES, 'UTF-8'));
$ZAI_API_KEY = trim((string)file_get_contents($ruta_key));
if($ZAI_API_KEY == "")
    die("API key de Z.AI vacia");

// Render HTML header (estilo igual a test_glmocr.php).
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Test GLM-OCR Paginado</title>';
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

echo '<h1>Test GLM-OCR Paginado (Z.AI) - codigo '.(int)$codigo.'</h1>';

// Leer el adjunto.
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    echo '<h2 class="err">Adjunto codigo '.(int)$codigo.' no encontrado</h2>';
    echo '</body></html>';
    exit;
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$ext_arch = strtolower(pathinfo((string)$nombrearchivo, PATHINFO_EXTENSION));
$es_pdf   = (stripos((string)$mimetype, 'pdf') !== false || $ext_arch == 'pdf');
if(!$es_pdf)
    {
    echo '<h2 class="err">El adjunto no es PDF (mime='.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').', ext='.htmlspecialchars($ext_arch, ENT_QUOTES, 'UTF-8').')</h2>';
    echo '</body></html>';
    exit;
    }

$tamano_pdf = strlen((string)$archivo);

echo '<h2>Adjunto</h2>';
echo '<div class="meta">';
echo '<b>CODIGO:</b> '.(int)$codigo.'<br>';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano_pdf).' bytes ('.number_format($tamano_pdf / 1024, 1).' KB)';
echo '</div>';

// Guardar PDF temporal en disco.
$tiempo_unique = time();
$pdf_tmp       = "/tmp/factura_".$codigo."_".$tiempo_unique.".pdf";
file_put_contents($pdf_tmp, (string)$archivo);

// Convertir a JPG con Ghostscript (300 DPI).
$prefijo_img = "/tmp/factura_".$codigo."_".$tiempo_unique."_page";
$cmd = "/bin/gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -dJPEGQ=90 -sOutputFile=".escapeshellarg($prefijo_img."-%d.jpg")." ".escapeshellarg($pdf_tmp)." 2>&1";

echo '<h2>Conversion PDF a JPG (Ghostscript 300 DPI)</h2>';
echo '<div class="meta">';
echo '<b>Comando:</b> /bin/gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -dJPEGQ=90 ...<br>';
echo '<b>PDF temp:</b> '.htmlspecialchars($pdf_tmp, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Prefijo imagenes:</b> '.htmlspecialchars($prefijo_img, ENT_QUOTES, 'UTF-8').'-N.jpg';
echo '</div>';

$t_gs_inicio = microtime(true);
$output_gs   = shell_exec($cmd);
$t_gs        = round(microtime(true) - $t_gs_inicio, 2);

echo '<div class="meta" style="margin-top:8px;"><b>Tiempo Ghostscript:</b> '.$t_gs.' s</div>';

// Listar imagenes generadas.
$imagenes = glob($prefijo_img."-*.jpg");
sort($imagenes, SORT_NATURAL);

if(empty($imagenes))
    {
    echo '<h2 class="err">ERROR: Ghostscript no genero imagenes</h2>';
    echo '<pre>'.htmlspecialchars((string)$output_gs, ENT_QUOTES, 'UTF-8').'</pre>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

$num_imagenes = count($imagenes);

echo '<div class="meta" style="margin-top:8px;"><b>Generadas '.$num_imagenes.' imagenes JPG a 300 DPI:</b></div>';
echo '<ul class="meta" style="margin-top:4px;">';
for($i = 0; $i < $num_imagenes; $i++)
    {
    $t = filesize($imagenes[$i]);
    echo '<li>'.htmlspecialchars(basename($imagenes[$i]), ENT_QUOTES, 'UTF-8').' &mdash; '.number_format($t / 1024, 1).' KB</li>';
    }
echo '</ul>';

// Procesar cada pagina con GLM-OCR.
$fecha_corrida    = date("Ymd_His");
$md_paginas       = array();
$total_tiempo     = 0;
$total_prompt     = 0;
$total_completion = 0;
$total_tokens_acc = 0;

for($p = 1; $p <= $num_imagenes; $p++)
    {
    $imagen       = $imagenes[$p - 1];
    $img_binario  = file_get_contents($imagen);
    $img_base64   = base64_encode($img_binario);
    $img_data_uri = "data:image/jpeg;base64,".$img_base64;

    $body = array(
        "model" => "glm-ocr",
        "file"  => $img_data_uri
        );
    $body_json = json_encode($body);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/layout_parsing");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 150);
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

    $total_tiempo += $tiempo;

    // Guardar respuesta cruda.
    $archivo_resp = "/tmp/glmocr_pag".$p."_".$codigo."_".$fecha_corrida.".json";
    $data         = json_decode((string)$response, true);
    if($data !== null)
        file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    else
        file_put_contents($archivo_resp, (string)$response);

    echo '<h2>Pagina '.$p.' / '.$num_imagenes.'</h2>';
    echo '<div class="meta">';
    echo '<b>Imagen:</b> '.htmlspecialchars(basename($imagen), ENT_QUOTES, 'UTF-8').' ('.number_format(filesize($imagen) / 1024, 1).' KB)<br>';
    echo '<b>Tiempo:</b> '.$tiempo.' s<br>';
    if($http_code == 200)
        echo '<b>HTTP:</b> '.(int)$http_code.'<br>';
    else
        echo '<span class="err"><b>HTTP:</b> '.(int)$http_code.'</span><br>';
    echo '<b>Respuesta cruda:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8');
    echo '</div>';

    if($curl_err != "")
        {
        echo '<p class="err">ERROR cURL: '.htmlspecialchars($curl_err, ENT_QUOTES, 'UTF-8').'</p>';
        $md_paginas[$p] = "[ERROR cURL: ".$curl_err."]";
        continue;
        }

    if($http_code != 200)
        {
        echo '<p class="err">ERROR HTTP '.(int)$http_code.'</p>';
        echo '<pre>'.htmlspecialchars((string)$response, ENT_QUOTES, 'UTF-8').'</pre>';
        $md_paginas[$p] = "[ERROR HTTP ".$http_code."]";
        continue;
        }

    if($data === null)
        {
        echo '<p class="err">Respuesta no es JSON valido</p>';
        echo '<pre>'.htmlspecialchars(substr((string)$response, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
        $md_paginas[$p] = "[ERROR JSON invalido]";
        continue;
        }

    $usage             = isset($data["usage"]) ? $data["usage"] : array();
    $prompt_tokens     = isset($usage["prompt_tokens"])     ? (int)$usage["prompt_tokens"]     : 0;
    $completion_tokens = isset($usage["completion_tokens"]) ? (int)$usage["completion_tokens"] : 0;
    $tokens_p          = isset($usage["total_tokens"])      ? (int)$usage["total_tokens"]      : 0;

    $total_prompt     += $prompt_tokens;
    $total_completion += $completion_tokens;
    $total_tokens_acc += $tokens_p;

    $md_p  = isset($data["md_results"]) ? (string)$data["md_results"] : "";
    $len_p = strlen($md_p);

    // Guardar md de esta pagina.
    $archivo_md_p = "/tmp/glmocr_pag".$p."_".$codigo."_".$fecha_corrida.".md";
    file_put_contents($archivo_md_p, $md_p);

    echo '<div class="meta">';
    echo '<b>Tokens prompt:</b> '.number_format($prompt_tokens).' &nbsp; ';
    echo '<b>completion:</b> '.number_format($completion_tokens).' &nbsp; ';
    echo '<b>total:</b> '.number_format($tokens_p).'<br>';
    echo '<b>md_results length:</b> '.number_format($len_p).' chars<br>';
    echo '<b>md guardado en:</b> '.htmlspecialchars($archivo_md_p, ENT_QUOTES, 'UTF-8');
    echo '</div>';

    echo '<h3>md_results pagina '.$p.' (completo)</h3>';
    if($len_p > 0)
        echo '<pre>'.htmlspecialchars($md_p, ENT_QUOTES, 'UTF-8').'</pre>';
    else
        echo '<p class="err">md_results vacio para esta pagina.</p>';

    $md_paginas[$p] = $md_p;
    }

// Concatenar todos los md_results en un solo archivo.
$archivo_completo = "/tmp/glmocr_completo_".$codigo."_".$fecha_corrida.".md";
$contenido_total  = "";
for($p = 1; $p <= $num_imagenes; $p++)
    {
    $contenido_total .= "===== PAGINA ".$p." =====\n";
    $contenido_total .= isset($md_paginas[$p]) ? $md_paginas[$p] : "";
    $contenido_total .= "\n\n";
    }
file_put_contents($archivo_completo, $contenido_total);

// Costo total estimado.
$costo_total = ($total_prompt     / 1000000.0) * 0.60
             + ($total_completion / 1000000.0) * 1.92;

echo '<h2>Resumen final</h2>';
echo '<div class="meta">';
echo '<b>Total paginas procesadas:</b> '.$num_imagenes.'<br>';
echo '<b>Tiempo total (suma llamadas):</b> '.round($total_tiempo, 2).' s<br>';
echo '<b>Tokens prompt totales:</b> '.number_format($total_prompt).'<br>';
echo '<b>Tokens completion totales:</b> '.number_format($total_completion).'<br>';
echo '<b>Tokens totales:</b> '.number_format($total_tokens_acc).'<br>';
echo '<b>Costo total estimado:</b> ~$'.number_format($costo_total, 6).' USD (GLM aprox: input $0.60/M, output $1.92/M)<br>';
echo '<b>Archivo concatenado:</b> '.htmlspecialchars($archivo_completo, ENT_QUOTES, 'UTF-8');
echo '</div>';

echo '<p style="margin-top:16px;"><a class="btn-descarga" href="?codigo='.(int)$codigo.'&descargar=completo">Descargar md completo concatenado</a></p>';

// Limpiar temporales (PDF + JPGs). NO borrar los /tmp/glmocr_*.
@unlink($pdf_tmp);
for($i = 0; $i < $num_imagenes; $i++)
    @unlink($imagenes[$i]);

echo '</body></html>';
