<?php 

// ============================================================================
//  test_glm46v.php
//  Prueba de extraccion DIRECTA de la factura PDF con GLM-4.6V de Z.AI
//  (modelo vision-language grande, NO el OCR especializado glm-ocr).
//  Una sola llamada al endpoint chat/completions (compatible OpenAI con vision).
//  GLM-4.6V NO acepta PDF base64 directo (HTTP 400, codigo 1210), asi que
//  convertimos el PDF a JPG por pagina con Ghostscript y enviamos las imagenes
//  como multiples bloques image_url en el payload.
//
//  Uso: test_glm46v.php?codigo=79
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

// API key Z.AI.
$ruta_key = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
if(!file_exists($ruta_key))
    die("No existe el archivo de API key Z.AI: ".htmlspecialchars($ruta_key, ENT_QUOTES, 'UTF-8'));
$ZAI_API_KEY = trim((string)file_get_contents($ruta_key));
if($ZAI_API_KEY == "")
    die("API key Z.AI vacia");

$fecha_corrida = date("Ymd_His");

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

// ----------------------------------------------------------------------------
// HTML HEADER
// ----------------------------------------------------------------------------
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Test GLM-4.6V - codigo '.(int)$codigo.'</title>';
echo '<style>';
echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; padding: 20px; color: #222; }';
echo 'h1 { margin:0; font-size:20px; color:#222; }';
echo 'h2 { color: #88010e; margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 4px; }';
echo 'h3 { color: #555; margin-top: 16px; font-size: 14px; }';
echo 'pre { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; overflow: auto; max-height: 60vh; font-size: 12px; }';
echo '.meta { font-size: 13px; line-height: 1.7; }';
echo '.meta b { color: #555; }';
echo '.err { color: #88010e; font-weight: bold; }';
echo 'table.cajas { border-collapse: collapse; font-size: 12px; margin: 8px 0; width: 100%; }';
echo 'table.cajas th, table.cajas td { border: 1px solid #ccc; padding: 4px 8px; }';
echo 'table.cajas th { background: #f0f0f0; color:#333; text-align:left; }';
echo 'table.cajas td.num { text-align: right; }';
echo 'table.cajas tr.alerta td { background: #fdecec; }';
echo '.costo-total { font-size: 16px; font-weight: bold; color:#88010e; margin-top:24px; }';
echo '</style></head><body>';

echo '<h1>Test GLM-4.6V (PDF -&gt; JPG) - codigo '.(int)$codigo.'</h1>';

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

// ----------------------------------------------------------------------------
// PASO 2: CONVERTIR PDF A JPG POR PAGINA (Ghostscript)
//  GLM-4.6V no acepta PDF base64 directo, hay que mandarle imagenes.
//  Renderizamos cada pagina del PDF a un JPG a 150 dpi con gs (resolucion baja
//  para reducir el tamano de los JPG y evitar timeout del servidor web).
// ----------------------------------------------------------------------------
$tamano_pdf = strlen((string)$archivo);

$pdf_tmp = "/tmp/glm46v_pdf_".$codigo."_".$fecha_corrida.".pdf";
file_put_contents($pdf_tmp, (string)$archivo);

$prefijo = "/tmp/glm46v_page_".$codigo."_".$fecha_corrida;
$cmd = "/bin/gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r150 -dJPEGQ=90 -sOutputFile=".escapeshellarg($prefijo."-%d.jpg")." ".escapeshellarg($pdf_tmp)." 2>&1";
$output = shell_exec($cmd);

$imagenes = glob($prefijo."-*.jpg");
sort($imagenes, SORT_NATURAL);

// PASO 7 (limpieza): borrar temporales (PDF + JPGs) en CUALQUIER salida del
// script, incluidos los exit() de error. NO se borra el JSON de respuesta cruda.
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

echo '<h2>Adjunto</h2>';
echo '<div class="meta">';
echo '<b>CODIGO:</b> '.(int)$codigo.'<br>';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano_pdf).' bytes ('.number_format($tamano_pdf / 1024, 1).' KB)';
echo '</div>';

if(empty($imagenes))
    {
    echo '<h2 class="err">Ghostscript no genero ninguna imagen JPG</h2>';
    echo '<p class="err">Salida del comando gs:</p>';
    echo '<pre>'.htmlspecialchars((string)$output, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

// Mostrar paginas convertidas y tamano de cada JPG.
$num_paginas = count($imagenes);

echo '<h2>Conversion PDF -&gt; JPG (Ghostscript)</h2>';
echo '<div class="meta">';
echo '<b>Paginas convertidas:</b> '.(int)$num_paginas.'<br>';
for($i = 0; $i < $num_paginas; $i++)
    {
    $tam_kb = file_exists($imagenes[$i]) ? (filesize($imagenes[$i]) / 1024) : 0;
    echo '<b>Pagina '.($i + 1).':</b> '.htmlspecialchars($imagenes[$i], ENT_QUOTES, 'UTF-8').' ('.number_format($tam_kb, 1).' KB)<br>';
    }
echo '</div>';

// ----------------------------------------------------------------------------
// PROMPT (nowdoc - texto literal, sin interpolacion)
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
// PASO 3: CONSTRUIR PAYLOAD (formato OpenAI con vision, un image_url por pagina)
//  Cada JPG se pasa a base64 y se agrega como un bloque image_url; el prompt
//  va al final como bloque text.
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
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/chat/completions");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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

// ----------------------------------------------------------------------------
// PASO 5: GUARDAR RESPUESTA CRUDA EN /tmp
// ----------------------------------------------------------------------------
$archivo_resp = "/tmp/glm46v_".$codigo."_".$fecha_corrida.".json";
$data         = json_decode((string)$response, true);
if($data !== null)
    file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_resp, (string)$response);

echo '<h2>Llamada a GLM-4.6V</h2>';
echo '<div class="meta">';
echo '<b>Endpoint:</b> https://api.z.ai/api/paas/v4/chat/completions<br>';
echo '<b>Modelo:</b> glm-4.6v<br>';
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
    echo '</body></html>';
    exit;
    }

if($http_code != 200)
    {
    echo '<p class="err">ERROR HTTP '.(int)$http_code.'</p>';
    echo '<pre>'.htmlspecialchars((string)$response, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

if($data === null)
    {
    echo '<p class="err">Respuesta no es JSON valido (envoltorio OpenAI). Primeros 1000 chars:</p>';
    echo '<pre>'.htmlspecialchars(substr((string)$response, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// PASO 7 (tokens / costo): usage en formato OpenAI
// ----------------------------------------------------------------------------
$input_t  = isset($data["usage"]["prompt_tokens"])     ? (int)$data["usage"]["prompt_tokens"]     : 0;
$output_t = isset($data["usage"]["completion_tokens"]) ? (int)$data["usage"]["completion_tokens"] : 0;
$total_t  = isset($data["usage"]["total_tokens"])      ? (int)$data["usage"]["total_tokens"]      : ($input_t + $output_t);
$costo    = ($input_t / 1000000.0) * 0.30
          + ($output_t / 1000000.0) * 0.90;

echo '<div class="meta">';
echo '<b>Tokens prompt:</b> '.number_format($input_t).' &nbsp; ';
echo '<b>completion:</b> '.number_format($output_t).' &nbsp; ';
echo '<b>total:</b> '.number_format($total_t).'<br>';
echo '<b>Costo GLM-4.6V estimado:</b> ~$'.number_format($costo, 6).' USD (input $0.30/M, output $0.90/M)';
echo '</div>';

// ----------------------------------------------------------------------------
// PASO 6: PARSEAR contenido (choices[0].message.content)
// ----------------------------------------------------------------------------
$texto = "";
if(isset($data["choices"][0]["message"]["content"]))
    $texto = (string)$data["choices"][0]["message"]["content"];

$json_str    = extraer_json($texto);
$extraccion  = json_decode($json_str, true);

// ----------------------------------------------------------------------------
// RESULTADO
// ----------------------------------------------------------------------------
echo '<h2>Extraccion (JSON GLM-4.6V)</h2>';

if($extraccion === null)
    {
    echo '<p class="err">GLM-4.6V devolvio JSON invalido. Texto crudo:</p>';
    echo '<pre>'.htmlspecialchars($texto, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '<div class="costo-total">COSTO: ~$'.number_format($costo, 6).' USD</div>';
    echo '</body></html>';
    exit;
    }

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
    echo '<th>#</th>';
    echo '<th>CAJA</th>';
    echo '<th>TIPO</th>';
    echo '<th>VARIEDAD</th>';
    echo '<th>LARGO</th>';
    echo '<th>STxB</th>';
    echo '<th>RAMOS</th>';
    echo '<th>TALLOS</th>';
    echo '<th>P.UNIT</th>';
    echo '<th>P.TOTAL</th>';
    echo '<th>ALERTA</th>';
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

echo '<div class="costo-total">COSTO: ~$'.number_format($costo, 6).' USD</div>';

echo '<h3>Archivo guardado</h3>';
echo '<div class="meta"><b>Respuesta cruda GLM-4.6V:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8').'</div>';

echo '</body></html>';
