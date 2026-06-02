<?php 

// ============================================================================
//  test_haiku_visual.php
//  Prueba de extraccion DIRECTA de la factura PDF con Haiku 4.5,
//  usando una tecnica de alineacion visual del header con cada fila para 
//  resolver el grid posicional de columnas de largo. Sin GLM-OCR previo.
//
//  Uso: test_haiku_visual.php?codigo=47
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

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 47;

// API key Anthropic.
$ruta_anthropic = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";
if(!file_exists($ruta_anthropic))
    die("No existe el archivo de API key Anthropic: ".htmlspecialchars($ruta_anthropic, ENT_QUOTES, 'UTF-8'));
$ANTHROPIC_API_KEY = trim((string)file_get_contents($ruta_anthropic));
if($ANTHROPIC_API_KEY == "")
    die("API key Anthropic vacia");

$fecha_corrida = date("Ymd_His");

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
echo '<title>Test Haiku Visual - codigo '.(int)$codigo.'</title>';
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

echo '<h1>Test Haiku Visual (PDF directo) - codigo '.(int)$codigo.'</h1>';

// ----------------------------------------------------------------------------
// LEER ADJUNTO
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
$pdf_base64 = base64_encode((string)$archivo);

echo '<h2>Adjunto</h2>';
echo '<div class="meta">';
echo '<b>CODIGO:</b> '.(int)$codigo.'<br>';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano_pdf).' bytes ('.number_format($tamano_pdf / 1024, 1).' KB)<br>';
echo '<b>Base64 chars:</b> '.number_format(strlen($pdf_base64));
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
Cuando veas muchas filas seguidas (5 o mas) con el mismo numero en la misma columna, NO asumas que esta en la columna que crees por inercia. RE-VERIFICA la columna explicitamente para CADA fila como si fuera la primera. El cerebro tiende a "perder" la referencia del header cuando hay muchas filas iguales seguidas. Cuenta visualmente las columnas desde "OTR." (.00) hasta llegar al numero: cada columna vacia cuenta uno. Si hay 4 columnas vacias entre OTR. y el numero, el largo es 70 (porque 30,40,50,60 son las 4 columnas vacias y el numero esta en 70). Si hay 3 columnas vacias, el largo es 60. Y asi sucesivamente.

Aplicar esa misma logica de "contar columnas vacias desde OTR." en CADA fila independientemente, sobre todo en bloques de filas repetidas (caja 1 con muchas variedades del mismo largo, caja 2 idem, etc).

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
- LARGO: numero del largo en cm (30, 40, 50, 60, 70, 80, 90, 100, 110, 120). APLICA LA TECNICA VISUAL DESCRITA ARRIBA.
- TALLOS_POR_RAMO: cuantos tallos vienen por ramo (en STxB, ej "25X" -> 25)
- RAMOS: cantidad de ramos en esa columna de largo
- TALLOS_TOTAL: total de tallos en esa linea
- PRECIO_UNITARIO: precio por tallo o por ramo
- PRECIO_TOTAL: precio total de la linea
- ALERTA: vacio si todo cuadra, o texto corto explicando duda

REGLAS:
1. Si una caja aparece en multiples filas con distintos largos o precios, genera una entrada por cada fila.
2. NUMERO_CAJA puede ser un rango como "1-2" o "31-33". Mantenlo literal como aparece.
3. Si el header tiene la columna "35" (algunas fincas la usan), inclukela en los largos validos.
4. Si no puedes determinar el LARGO con certeza, ponlo en null y agrega ALERTA explicando.
5. NO inventes datos. Si algo no aparece en el PDF, deja el campo vacio o null.

VALIDACION FINAL (incluye en la respuesta):
- TOTAL_CAJAS_DETECTADAS: numero total de cajas fisicas
- TOTAL_LINEAS_DETECTADAS: numero de lineas extraidas en CAJAS
- TOTAL_RAMOS_CALCULADO: suma de todos los RAMOS
- TOTAL_TALLOS_CALCULADO: suma de todos los TALLOS_TOTAL
- TOTAL_DOLAR_CALCULADO: suma de todos los PRECIO_TOTAL
- Si el PDF tiene totales en el footer (TOT.BOUNCH, TOT.STEMS, TOTAL), comparalos con los calculados y reporta diferencias en un campo DISCREPANCIAS (string corto).

FORMATO DE RESPUESTA: JSON estricto, sin markdown, sin ```json. Estructura:

{
  "CABECERA": {...},
  "LOGISTICA": {...},
  "CAJAS": [...],
  "VALIDACION": {
    "TOTAL_CAJAS_DETECTADAS": N,
    "TOTAL_LINEAS_DETECTADAS": N,
    "TOTAL_RAMOS_CALCULADO": N,
    "TOTAL_TALLOS_CALCULADO": N,
    "TOTAL_DOLAR_CALCULADO": X.XX,
    "TOTAL_RAMOS_FOOTER": N o null,
    "TOTAL_TALLOS_FOOTER": N o null,
    "TOTAL_DOLAR_FOOTER": X.XX o null,
    "DISCREPANCIAS": "..."
  },
  "ALERTAS_GLOBALES": ["...", "..."]
}

Devuelve SOLO el JSON, sin texto antes ni despues, sin fences.
PROMPT;

// ----------------------------------------------------------------------------
// LLAMADA A HAIKU (PDF como document base64 + prompt como text)
// ----------------------------------------------------------------------------
$payload = array(
    "model"       => "claude-haiku-4-5-20251001",
    "max_tokens"  => 16000,
    "temperature" => 0,
    "messages"    => array(
        array(
            "role"    => "user",
            "content" => array(
                array(
                    "type"   => "document",
                    "source" => array(
                        "type"       => "base64",
                        "media_type" => "application/pdf",
                        "data"       => $pdf_base64
                        )
                    ),
                array(
                    "type" => "text",
                    "text" => $prompt
                    )
                )
            )
        )
    );
$payload_json = json_encode($payload);

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
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);

$t_inicio  = microtime(true);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
$tiempo    = round(microtime(true) - $t_inicio, 2);
curl_close($ch);

$archivo_resp = "/tmp/haikuvis_".$codigo."_".$fecha_corrida.".json";
$data         = json_decode((string)$response, true);
if($data !== null)
    file_put_contents($archivo_resp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_resp, (string)$response);

echo '<h2>Llamada a Haiku</h2>';
echo '<div class="meta">';
echo '<b>Endpoint:</b> https://api.anthropic.com/v1/messages<br>';
echo '<b>Modelo:</b> claude-haiku-4-5-20251001<br>';
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
    echo '<p class="err">Respuesta no es JSON valido (envoltorio Anthropic). Primeros 1000 chars:</p>';
    echo '<pre>'.htmlspecialchars(substr((string)$response, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

$input_t  = isset($data["usage"]["input_tokens"])  ? (int)$data["usage"]["input_tokens"]  : 0;
$output_t = isset($data["usage"]["output_tokens"]) ? (int)$data["usage"]["output_tokens"] : 0;
$total_t  = $input_t + $output_t;
$costo    = ($input_t / 1000000.0) * 1.00
          + ($output_t / 1000000.0) * 5.00;

echo '<div class="meta">';
echo '<b>Tokens input:</b> '.number_format($input_t).' &nbsp; ';
echo '<b>output:</b> '.number_format($output_t).' &nbsp; ';
echo '<b>total:</b> '.number_format($total_t).'<br>';
echo '<b>Costo Haiku estimado:</b> ~$'.number_format($costo, 6).' USD (input $1.00/M, output $5.00/M)';
echo '</div>';

$texto = "";
if(isset($data["content"][0]["text"]))
    $texto = (string)$data["content"][0]["text"];

$json_str    = extraer_json($texto);
$extraccion  = json_decode($json_str, true);

// ----------------------------------------------------------------------------
// RESULTADO
// ----------------------------------------------------------------------------
echo '<h2>Extraccion (JSON Haiku)</h2>';

if($extraccion === null)
    {
    echo '<p class="err">Haiku devolvio JSON invalido. Texto crudo:</p>';
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
echo '<div class="meta"><b>Respuesta cruda Haiku:</b> '.htmlspecialchars($archivo_resp, ENT_QUOTES, 'UTF-8').'</div>';

echo '</body></html>';
