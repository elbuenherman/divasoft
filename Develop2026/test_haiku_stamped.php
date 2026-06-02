<?php

// ============================================================================
//  test_haiku_stamped.php
//  Pipeline visual completo:
//   1) Convierte el PDF a JPG (300 DPI) con Ghostscript.
//   2) Haiku LLAMADA 1: detecta coordenadas Y del header en la primera pagina. 
//   3) PHP/GD: recorta la tira del header y la ESTAMPA repetidamente sobre
//      cada imagen, intercalada entre las filas de datos.
//   4) Haiku LLAMADA 2: recibe las imagenes estampadas y extrae la factura
//      completa (cabecera, logistica, cajas, validacion).
//
//  Uso normal: test_haiku_stamped.php?codigo=79 
//  Modo debug (no llama Haiku la 2da vez, muestra imagenes estampadas):
//              test_haiku_stamped.php?codigo=79&debug=1
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(600);
ini_set("max_execution_time", "600");
ini_set("memory_limit", "512M");
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;
$debug  = (isset($_GET["debug"]) && $_GET["debug"] == "1");

// API key Anthropic.
$ruta_anthropic = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";
if(!file_exists($ruta_anthropic))
    die("No existe el archivo de API key Anthropic: ".htmlspecialchars($ruta_anthropic, ENT_QUOTES, 'UTF-8'));
$ANTHROPIC_API_KEY = trim((string)file_get_contents($ruta_anthropic));
if($ANTHROPIC_API_KEY == "")
    die("API key Anthropic vacia");

$fecha_corrida = date("Ymd_His");

if(!function_exists("imagecreatefromjpeg"))
    die("La extension GD no esta disponible en este servidor");

// ----------------------------------------------------------------------------
// Extrae JSON del texto devuelto por Haiku (limpia fences markdown).
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

// ----------------------------------------------------------------------------
// HTML HEADER
// ----------------------------------------------------------------------------
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Test Haiku Stamped - codigo '.(int)$codigo.'</title>';
echo '<style>';
echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; padding: 20px; color: #222; }';
echo 'h1 { margin:0; font-size:20px; color:#222; }';
echo 'h2 { color: #88010e; margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 4px; }';
echo 'h3 { color: #555; margin-top: 16px; font-size: 14px; }';
echo 'pre { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; overflow: auto; max-height: 60vh; font-size: 12px; }';
echo '.meta { font-size: 13px; line-height: 1.7; }';
echo '.meta b { color: #555; }';
echo '.err { color: #88010e; font-weight: bold; }';
echo '.btn { display:inline-block; padding:8px 16px; background:#88010e; color:white; text-decoration:none; border-radius:4px; font-weight:bold; }';
echo '.btn:hover { background:#a30214; }';
echo 'table.cajas { border-collapse: collapse; font-size: 12px; margin: 8px 0; width: 100%; }';
echo 'table.cajas th, table.cajas td { border: 1px solid #ccc; padding: 4px 8px; }';
echo 'table.cajas th { background: #f0f0f0; color:#333; text-align:left; }';
echo 'table.cajas td.num { text-align: right; }';
echo 'table.cajas tr.alerta td { background: #fdecec; }';
echo '.costo-total { font-size: 16px; font-weight: bold; color:#88010e; margin-top:24px; }';
echo 'img.preview { max-width: 100%; border: 1px solid #888; margin: 8px 0; }';
echo '</style></head><body>';

echo '<h1>Test Haiku Stamped (PDF -> JPG estampado -> Haiku) - codigo '.(int)$codigo;
if($debug)
    echo ' &nbsp; <span style="color:#88010e;">[DEBUG]</span>';
echo '</h1>';

// ----------------------------------------------------------------------------
// PASO 1 - LEER PDF DE LA BD
// ----------------------------------------------------------------------------
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

// ----------------------------------------------------------------------------
// PASO 2 - PDF A JPG CON GHOSTSCRIPT
// ----------------------------------------------------------------------------
$tiempo_unique = time();
$pdf_tmp       = "/tmp/stamped_pdf_".$codigo."_".$tiempo_unique.".pdf";
file_put_contents($pdf_tmp, (string)$archivo);

$prefijo_orig = "/tmp/stamped_orig_".$codigo."_".$tiempo_unique."_page";
$cmd = "/bin/gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -dJPEGQ=90 -sOutputFile=".escapeshellarg($prefijo_orig."-%d.jpg")." ".escapeshellarg($pdf_tmp)." 2>&1";

$t_gs_inicio = microtime(true);
$output_gs   = shell_exec($cmd);
$t_gs        = round(microtime(true) - $t_gs_inicio, 2);

$imagenes_orig = glob($prefijo_orig."-*.jpg");
sort($imagenes_orig, SORT_NATURAL);

echo '<h2>Paso 2 - Conversion PDF a JPG (Ghostscript 300 DPI)</h2>';
echo '<div class="meta">';
echo '<b>Tiempo Ghostscript:</b> '.$t_gs.' s<br>';
echo '<b>Paginas generadas:</b> '.count($imagenes_orig);
echo '</div>';

if(empty($imagenes_orig))
    {
    echo '<h2 class="err">Ghostscript no genero imagenes</h2>';
    echo '<pre>'.htmlspecialchars((string)$output_gs, ENT_QUOTES, 'UTF-8').'</pre>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// PASO 3 - LLAMADA 1 A HAIKU: detectar coordenadas Y del header
// ----------------------------------------------------------------------------
$prompt_coords = <<<'PROMPT'
Te paso una imagen de una factura de flores. Mira la tabla principal de cajas. Devuelveme SOLO un JSON con las coordenadas Y (pixeles desde el borde superior de la imagen) donde empieza y termina la FILA DEL HEADER de columnas (la fila que tiene #BOX, BOX T, VARIEDAD, STxB, OTR., 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, T.TALLOS, etc.). Tambien dame la Y donde empieza la primera fila de datos y la altura aproximada de UNA fila de datos en pixeles.

Formato JSON estricto sin markdown:
{"header_y_inicio": N, "header_y_fin": N, "primera_fila_y": N, "altura_fila": N}

Donde N son enteros en pixeles. Devuelve SOLO el JSON, nada antes ni despues.
PROMPT;

$imagen_pag1_bin    = file_get_contents($imagenes_orig[0]);
$imagen_pag1_base64 = base64_encode($imagen_pag1_bin);

$payload_1 = array(
    "model"       => "claude-haiku-4-5-20251001",
    "max_tokens"  => 200,
    "temperature" => 0,
    "messages"    => array(
        array(
            "role"    => "user",
            "content" => array(
                array(
                    "type"   => "image",
                    "source" => array(
                        "type"       => "base64",
                        "media_type" => "image/jpeg",
                        "data"       => $imagen_pag1_base64
                        )
                    ),
                array(
                    "type" => "text",
                    "text" => $prompt_coords
                    )
                )
            )
        )
    );
$payload_1_json = json_encode($payload_1);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "x-api-key: ".$ANTHROPIC_API_KEY,
    "anthropic-version: 2023-06-01",
    "content-type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_1_json);

$t1_inicio  = microtime(true);
$response_1 = curl_exec($ch);
$http_1     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err_1 = curl_error($ch);
$tiempo_1   = round(microtime(true) - $t1_inicio, 2);
curl_close($ch);

$data_1 = json_decode((string)$response_1, true);

echo '<h2>Paso 3 - Haiku LLAMADA 1 (deteccion de coordenadas del header)</h2>';
echo '<div class="meta">';
echo '<b>Tiempo:</b> '.$tiempo_1.' s<br>';
if($http_1 == 200)
    echo '<b>HTTP:</b> '.(int)$http_1.'<br>';
else
    echo '<span class="err"><b>HTTP:</b> '.(int)$http_1.'</span><br>';
echo '</div>';

if($curl_err_1 != "")
    {
    echo '<p class="err">ERROR cURL llamada 1: '.htmlspecialchars($curl_err_1, ENT_QUOTES, 'UTF-8').'</p>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

if($http_1 != 200 || $data_1 === null)
    {
    echo '<p class="err">ERROR HTTP '.(int)$http_1.' o JSON envoltorio invalido</p>';
    echo '<pre>'.htmlspecialchars((string)$response_1, ENT_QUOTES, 'UTF-8').'</pre>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

$input_1  = isset($data_1["usage"]["input_tokens"])  ? (int)$data_1["usage"]["input_tokens"]  : 0;
$output_1 = isset($data_1["usage"]["output_tokens"]) ? (int)$data_1["usage"]["output_tokens"] : 0;
$costo_1  = ($input_1 / 1000000.0) * 1.00 + ($output_1 / 1000000.0) * 5.00;

$texto_1   = isset($data_1["content"][0]["text"]) ? (string)$data_1["content"][0]["text"] : "";
$coords    = json_decode(extraer_json($texto_1), true);

if($coords === null || !isset($coords["header_y_inicio"]) || !isset($coords["header_y_fin"]) || !isset($coords["primera_fila_y"]) || !isset($coords["altura_fila"]))
    {
    echo '<p class="err">No se pudo parsear las coordenadas devueltas por Haiku. Texto crudo:</p>';
    echo '<pre>'.htmlspecialchars($texto_1, ENT_QUOTES, 'UTF-8').'</pre>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

$h_inicio     = (int)$coords["header_y_inicio"];
$h_fin        = (int)$coords["header_y_fin"];
$h_alto       = $h_fin - $h_inicio;
$primera_fila = (int)$coords["primera_fila_y"];
$altura_fila  = (int)$coords["altura_fila"];

echo '<div class="meta">';
echo '<b>Coordenadas detectadas (px):</b><br>';
echo '&nbsp;&nbsp;header_y_inicio = '.$h_inicio.'<br>';
echo '&nbsp;&nbsp;header_y_fin    = '.$h_fin.' &nbsp; (altura tira = '.$h_alto.')<br>';
echo '&nbsp;&nbsp;primera_fila_y  = '.$primera_fila.'<br>';
echo '&nbsp;&nbsp;altura_fila     = '.$altura_fila.'<br>';
echo '<b>Tokens:</b> input '.number_format($input_1).' / output '.number_format($output_1).'<br>';
echo '<b>Costo llamada 1:</b> ~$'.number_format($costo_1, 6).' USD';
echo '</div>';

if($h_alto <= 0 || $altura_fila <= 0 || $primera_fila <= 0)
    {
    echo '<p class="err">Coordenadas invalidas (algun valor <= 0). No se puede continuar.</p>';
    @unlink($pdf_tmp);
    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// PASO 4 - ESTAMPAR HEADER EN CADA IMAGEN
// ----------------------------------------------------------------------------
$imagenes_mod = array();
$prefijo_mod  = "/tmp/stamped_mod_".$codigo."_".$tiempo_unique."_page";

$num_orig = count($imagenes_orig);
for($p = 0; $p < $num_orig; $p++)
    {
    $img = @imagecreatefromjpeg($imagenes_orig[$p]);
    if($img === false)
        continue;

    $ancho = imagesx($img);
    $alto  = imagesy($img);

    // Recortar la tira del header de esta imagen.
    $tira = imagecreatetruecolor($ancho, $h_alto);
    imagecopy($tira, $img, 0, 0, 0, $h_inicio, $ancho, $h_alto);

    // Imagen nueva = copia de la original.
    $nueva = imagecreatetruecolor($ancho, $alto);
    imagecopy($nueva, $img, 0, 0, 0, 0, $ancho, $alto);

    // Estampar la tira INTERCALADA cada N filas, sin pisar datos.
    // Cada estampa avanza $h_alto + N*$altura_fila pixeles: la tira se inserta
    // ENTRE bloques de filas, no encima.
    $cada_n_filas = 5;
    $y = $primera_fila + ($altura_fila * $cada_n_filas);
    while($y + $h_alto < $alto)
        {
        imagecopy($nueva, $tira, 0, $y, 0, 0, $ancho, $h_alto);
        $y += $h_alto + ($altura_fila * $cada_n_filas);
        }

    $ruta_mod = $prefijo_mod."-".($p + 1).".jpg";
    imagejpeg($nueva, $ruta_mod, 90);

    imagedestroy($img);
    imagedestroy($tira);
    imagedestroy($nueva);

    $imagenes_mod[] = $ruta_mod;
    }

echo '<h2>Paso 4 - Estampado del header con GD</h2>';
echo '<div class="meta">';
echo '<b>Imagenes generadas:</b> '.count($imagenes_mod).'<br>';
for($i = 0; $i < count($imagenes_mod); $i++)
    {
    $t = filesize($imagenes_mod[$i]);
    echo '&nbsp;&nbsp;'.htmlspecialchars(basename($imagenes_mod[$i]), ENT_QUOTES, 'UTF-8').' &mdash; '.number_format($t / 1024, 1).' KB<br>';
    }
echo '</div>';

// ----------------------------------------------------------------------------
// PASO 5 - MODO DEBUG: mostrar imagenes estampadas y NO llamar Haiku 2da vez
// ----------------------------------------------------------------------------
if($debug)
    {
    echo '<h2>Modo DEBUG: imagenes estampadas (no se llama Haiku la segunda vez)</h2>';
    for($i = 0; $i < count($imagenes_mod); $i++)
        {
        $bin    = file_get_contents($imagenes_mod[$i]);
        $base64 = base64_encode($bin);
        echo '<h3>Pagina '.($i + 1).'</h3>';
        echo '<img class="preview" src="data:image/jpeg;base64,'.$base64.'" alt="pagina '.($i + 1).' estampada">';
        }

    echo '<p style="margin-top:16px;"><a class="btn" href="?codigo='.(int)$codigo.'">Reejecutar SIN debug (llama Haiku 2da vez)</a></p>';

    // Limpieza parcial: PDF y originales se borran; las estampadas se quedan para inspeccion.
    @unlink($pdf_tmp);
    for($i = 0; $i < count($imagenes_orig); $i++)
        @unlink($imagenes_orig[$i]);

    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// PASO 6 - LLAMADA 2 A HAIKU CON LAS IMAGENES ESTAMPADAS
// ----------------------------------------------------------------------------
$prompt_extraccion = <<<'PROMPT'
Te paso paginas de una factura de flores ecuatoriana. CADA PAGINA tiene el header de la tabla COPIADO Y PEGADO repetidamente a lo largo, encima de cada fila de datos. Eso es para que veas siempre el header alineado verticalmente con la fila que estas leyendo. USA esa alineacion visual para identificar la columna de cada valor.

Extrae el contenido completo de la factura como JSON estricto.

CABECERA: FINCA, RUC, NUMERO_FACTURA, FECHA (YYYY-MM-DD), CLIENTEMARCACION (Box Marking, no Ship To), SUBTOTAL, DESCUENTO, IVA, TOTAL.

LOGISTICA: PAIS, MAWB, HAWB, DAE, AEROLINEA, FORWARDER.

CAJAS (array, una entrada por LINEA del detalle):
- NUMERO_CAJA (puede ser rango como 1-2 o 31-33)
- TIPO_CAJA (FB, HB, QB, EB)
- VARIEDAD
- LARGO (30, 35, 40, 50, 60, 70, 80, 90, 100, 110, 120). Identificalo viendo la columna donde aparece el numero de ramos USANDO EL HEADER ESTAMPADO INMEDIATAMENTE ARRIBA DE CADA FILA.
- TALLOS_POR_RAMO (en STxB)
- RAMOS
- TALLOS_TOTAL
- PRECIO_UNITARIO
- PRECIO_TOTAL
- ALERTA (vacio o duda corta)

VALIDACION:
- TOTAL_CAJAS_DETECTADAS
- TOTAL_LINEAS_DETECTADAS
- TOTAL_RAMOS_CALCULADO (suma RAMOS)
- TOTAL_TALLOS_CALCULADO (suma TALLOS_TOTAL)
- TOTAL_DOLAR_CALCULADO (suma PRECIO_TOTAL)
- TOTAL_RAMOS_FOOTER (del PDF, null si no aparece)
- TOTAL_TALLOS_FOOTER (del PDF)
- TOTAL_DOLAR_FOOTER (del PDF)
- DISCREPANCIAS (string corto)

ALERTAS_GLOBALES (array de strings).

REGLAS:
1. NO inventes datos.
2. Si una caja tiene mas de una linea con largos distintos o precios distintos, una entrada por cada.
3. Si no estas seguro del LARGO, ponlo en null y marca ALERTA.
4. Devuelve SOLO el JSON sin markdown ni texto antes/despues.

{
  "CABECERA": {...},
  "LOGISTICA": {...},
  "CAJAS": [...],
  "VALIDACION": {...},
  "ALERTAS_GLOBALES": [...]
}
PROMPT;

$content_2 = array();
for($i = 0; $i < count($imagenes_mod); $i++)
    {
    $bin    = file_get_contents($imagenes_mod[$i]);
    $base64 = base64_encode($bin);
    $content_2[] = array(
        "type"   => "image",
        "source" => array(
            "type"       => "base64",
            "media_type" => "image/jpeg",
            "data"       => $base64
            )
        );
    }
$content_2[] = array(
    "type" => "text",
    "text" => $prompt_extraccion
    );

$payload_2 = array(
    "model"       => "claude-haiku-4-5-20251001",
    "max_tokens"  => 16000,
    "temperature" => 0,
    "messages"    => array(
        array(
            "role"    => "user",
            "content" => $content_2
            )
        )
    );
$payload_2_json = json_encode($payload_2);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "x-api-key: ".$ANTHROPIC_API_KEY,
    "anthropic-version: 2023-06-01",
    "content-type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_2_json);

$t2_inicio  = microtime(true);
$response_2 = curl_exec($ch);
$http_2     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err_2 = curl_error($ch);
$tiempo_2   = round(microtime(true) - $t2_inicio, 2);
curl_close($ch);

$archivo_resp_2 = "/tmp/haikustamped_".$codigo."_".$fecha_corrida.".json";
$data_2         = json_decode((string)$response_2, true);
if($data_2 !== null)
    file_put_contents($archivo_resp_2, json_encode($data_2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_resp_2, (string)$response_2);

echo '<h2>Paso 6 - Haiku LLAMADA 2 (extraccion con imagenes estampadas)</h2>';
echo '<div class="meta">';
echo '<b>Tiempo:</b> '.$tiempo_2.' s<br>';
if($http_2 == 200)
    echo '<b>HTTP:</b> '.(int)$http_2.'<br>';
else
    echo '<span class="err"><b>HTTP:</b> '.(int)$http_2.'</span><br>';
echo '<b>Respuesta cruda:</b> '.htmlspecialchars($archivo_resp_2, ENT_QUOTES, 'UTF-8');
echo '</div>';

if($curl_err_2 != "")
    {
    echo '<p class="err">ERROR cURL llamada 2: '.htmlspecialchars($curl_err_2, ENT_QUOTES, 'UTF-8').'</p>';
    @unlink($pdf_tmp);
    for($i = 0; $i < count($imagenes_orig); $i++)
        @unlink($imagenes_orig[$i]);
    echo '</body></html>';
    exit;
    }

$input_2  = ($data_2 !== null && isset($data_2["usage"]["input_tokens"]))  ? (int)$data_2["usage"]["input_tokens"]  : 0;
$output_2 = ($data_2 !== null && isset($data_2["usage"]["output_tokens"])) ? (int)$data_2["usage"]["output_tokens"] : 0;
$costo_2  = ($input_2 / 1000000.0) * 1.00 + ($output_2 / 1000000.0) * 5.00;

echo '<div class="meta">';
echo '<b>Tokens:</b> input '.number_format($input_2).' / output '.number_format($output_2).'<br>';
echo '<b>Costo llamada 2:</b> ~$'.number_format($costo_2, 6).' USD';
echo '</div>';

if($http_2 != 200 || $data_2 === null)
    {
    echo '<p class="err">ERROR HTTP '.(int)$http_2.' o JSON envoltorio invalido</p>';
    echo '<pre>'.htmlspecialchars((string)$response_2, ENT_QUOTES, 'UTF-8').'</pre>';
    @unlink($pdf_tmp);
    for($i = 0; $i < count($imagenes_orig); $i++)
        @unlink($imagenes_orig[$i]);
    echo '</body></html>';
    exit;
    }

$texto_2     = isset($data_2["content"][0]["text"]) ? (string)$data_2["content"][0]["text"] : "";
$extraccion  = json_decode(extraer_json($texto_2), true);

echo '<h2>Extraccion (JSON Haiku)</h2>';

if($extraccion === null)
    {
    echo '<p class="err">Haiku devolvio JSON invalido. Texto crudo:</p>';
    echo '<pre>'.htmlspecialchars($texto_2, ENT_QUOTES, 'UTF-8').'</pre>';
    }
else
    {
    echo '<pre>'.htmlspecialchars(json_encode($extraccion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';

    // Tabla de cajas.
    $cajas = isset($extraccion["CAJAS"]) && is_array($extraccion["CAJAS"]) ? $extraccion["CAJAS"] : array();

    echo '<h2>Tabla de lineas extraidas ('.count($cajas).')</h2>';

    if(empty($cajas))
        echo '<p>No se extrajo ninguna linea.</p>';
    else
        {
        echo '<table class="cajas">';
        echo '<tr>';
        echo '<th>#</th><th>CAJA</th><th>TIPO</th><th>VARIEDAD</th><th>LARGO</th><th>STxB</th><th>RAMOS</th><th>TALLOS</th><th>P.UNIT</th><th>P.TOTAL</th><th>ALERTA</th>';
        echo '</tr>';

        $n = count($cajas);
        for($i = 0; $i < $n; $i++)
            {
            $c       = $cajas[$i];
            $caja    = isset($c["NUMERO_CAJA"])     ? $c["NUMERO_CAJA"]     : "";
            $tipo    = isset($c["TIPO_CAJA"])       ? $c["TIPO_CAJA"]       : "";
            $var     = isset($c["VARIEDAD"])        ? $c["VARIEDAD"]        : "";
            $largo   = isset($c["LARGO"])           ? $c["LARGO"]           : "";
            $stxb    = isset($c["TALLOS_POR_RAMO"]) ? $c["TALLOS_POR_RAMO"] : "";
            $ramos   = isset($c["RAMOS"])           ? $c["RAMOS"]           : "";
            $tall    = isset($c["TALLOS_TOTAL"])    ? $c["TALLOS_TOTAL"]    : "";
            $punit   = isset($c["PRECIO_UNITARIO"]) ? $c["PRECIO_UNITARIO"] : "";
            $ptot    = isset($c["PRECIO_TOTAL"])    ? $c["PRECIO_TOTAL"]    : "";
            $alerta  = isset($c["ALERTA"])          ? $c["ALERTA"]          : "";

            $clase = ($alerta != "") ? ' class="alerta"' : '';

            echo '<tr'.$clase.'>';
            echo '<td class="num">'.($i + 1).'</td>';
            echo '<td>'.htmlspecialchars((string)$caja, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$tipo, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$largo, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$stxb, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$ramos, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$tall, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$punit, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$ptot, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$alerta, ENT_QUOTES, 'UTF-8').'</td>';
            echo '</tr>';
            }
        echo '</table>';
        }
    }

// ----------------------------------------------------------------------------
// PASO 7 - COSTO TOTAL Y CLEANUP
// ----------------------------------------------------------------------------
$costo_total = $costo_1 + $costo_2;

echo '<div class="costo-total">COSTO TOTAL: ~$'.number_format($costo_total, 6).' USD ';
echo '(LLAMADA1 ~$'.number_format($costo_1, 6).' + LLAMADA2 ~$'.number_format($costo_2, 6).')</div>';

echo '<h3>Archivos guardados</h3>';
echo '<div class="meta">';
echo '<b>Haiku LLAMADA 2 cruda:</b> '.htmlspecialchars($archivo_resp_2, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Imagenes estampadas:</b> '.count($imagenes_mod).' archivos con prefijo '.htmlspecialchars($prefijo_mod, ENT_QUOTES, 'UTF-8');
echo '</div>';

// Limpieza: PDF + imagenes originales. NO borrar las estampadas ni el JSON de Haiku.
@unlink($pdf_tmp);
for($i = 0; $i < count($imagenes_orig); $i++)
    @unlink($imagenes_orig[$i]);

echo '</body></html>';
