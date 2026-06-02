<?php

// ============================================================================
//  test_glmocr_validar.php
//  Pipeline hibrido GLM-OCR + Haiku validador.
//  Paso 1: GLM-OCR extrae markdown del PDF (PDF directo, sin paginar).
//  Paso 2: PHP parsea las tablas HTML del markdown a MATRIZ CRUDA, sin
//          interpretar semantica (no mapea columnas por nombre).
//  Paso 3: Haiku 4.5 valida cuadres ramos/tallos/dolares y marca filas
//          sospechosas. NO corrige nada, solo alerta para revision humana.
//  
//  Uso: test_glmocr_validar.php?codigo=79
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

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;

// API keys.
$ruta_zai       = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";
$ruta_anthropic = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";

if(!file_exists($ruta_zai))
    die("No existe el archivo de API key Z.AI: ".htmlspecialchars($ruta_zai, ENT_QUOTES, 'UTF-8'));
$ZAI_API_KEY = trim((string)file_get_contents($ruta_zai));
if($ZAI_API_KEY == "")
    die("API key Z.AI vacia");

if(!file_exists($ruta_anthropic))
    die("No existe el archivo de API key Anthropic: ".htmlspecialchars($ruta_anthropic, ENT_QUOTES, 'UTF-8'));
$ANTHROPIC_API_KEY = trim((string)file_get_contents($ruta_anthropic));
if($ANTHROPIC_API_KEY == "")
    die("API key Anthropic vacia");

$fecha_corrida = date("Ymd_His");

// ----------------------------------------------------------------------------
// FUNCIONES DE PARSEO (GENERICAS, sin semantica)
// ----------------------------------------------------------------------------

// Devuelve array de tablas: cada una {headers:[...], rows:[[...],...]}.
function parsea_tablas_html($md)
    {
    $tablas = array();
    preg_match_all('/<table[^>]*>(.*?)<\/table>/s', (string)$md, $matches);
    if(empty($matches[0]))
        return $tablas;

    $num = count($matches[0]);
    for($t = 0; $t < $num; $t++)
        {
        $tabla_html = $matches[0][$t];

        $dom = new DOMDocument();
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$tabla_html.'</body></html>';
        @$dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Headers: todos los <th> en orden.
        $headers   = array();
        $th_nodes  = $dom->getElementsByTagName('th');
        $num_th    = $th_nodes->length;
        for($i = 0; $i < $num_th; $i++)
            {
            $txt = trim((string)$th_nodes->item($i)->textContent);
            $txt = preg_replace('/\s+/', ' ', $txt);
            $headers[] = $txt;
            }

        // Filas: cada <tr> con <td>.
        $rows                          = array();
        $tr_nodes                      = $dom->getElementsByTagName('tr');
        $num_tr                        = $tr_nodes->length;
        $primera_fila_como_header_used = false;
        for($i = 0; $i < $num_tr; $i++)
            {
            $tr       = $tr_nodes->item($i);
            $td_nodes = $tr->getElementsByTagName('td');
            $num_td   = $td_nodes->length;
            if($num_td == 0)
                continue;

            $row = array();
            for($j = 0; $j < $num_td; $j++)
                {
                $txt = trim((string)$td_nodes->item($j)->textContent);
                $txt = preg_replace('/\s+/', ' ', $txt);
                $row[] = $txt;
                }

            // Si no hay <th> y es la primera fila, usarla como header.
            if(empty($headers) && !$primera_fila_como_header_used)
                {
                $headers = $row;
                $primera_fila_como_header_used = true;
                continue;
                }

            $rows[] = $row;
            }

        $tablas[] = array(
            "headers" => $headers,
            "rows"    => $rows
            );
        }

    return $tablas;
    }

// Devuelve la tabla con mas filas. Si hay otras tablas grandes con headers
// iguales, concatena las filas. NO interpreta semantica.
function encuentra_tabla_principal($tablas)
    {
    $vacia = array(
        "headers"                  => array(),
        "rows"                     => array(),
        "total_filas"              => 0,
        "num_tablas_concatenadas"  => 0
        );

    if(empty($tablas))
        return $vacia;

    $n = count($tablas);

    // Buscar la tabla con mas filas.
    $idx_max   = 0;
    $max_filas = -1;
    for($i = 0; $i < $n; $i++)
        {
        $cnt = count($tablas[$i]["rows"]);
        if($cnt > $max_filas)
            {
            $max_filas = $cnt;
            $idx_max   = $i;
            }
        }

    if($max_filas <= 0)
        return $vacia;

    $headers_ref = $tablas[$idx_max]["headers"];
    $rows_concat = $tablas[$idx_max]["rows"];
    $num_concat  = 1;

    // Umbral: otras tablas con al menos 30% de filas de la principal y headers
    // identicos (mismo conteo y mismos textos) se concatenan.
    $umbral = max(3, (int)($max_filas * 0.3));

    for($i = 0; $i < $n; $i++)
        {
        if($i == $idx_max)
            continue;
        $t = $tablas[$i];
        if(count($t["rows"]) < $umbral)
            continue;
        if(count($t["headers"]) != count($headers_ref))
            continue;

        $match = true;
        $h_n   = count($headers_ref);
        for($j = 0; $j < $h_n; $j++)
            {
            $a = strtoupper(trim($t["headers"][$j]));
            $b = strtoupper(trim($headers_ref[$j]));
            if($a != $b)
                {
                $match = false;
                break;
                }
            }
        if(!$match)
            continue;

        $rn = count($t["rows"]);
        for($k = 0; $k < $rn; $k++)
            $rows_concat[] = $t["rows"][$k];
        $num_concat++;
        }

    return array(
        "headers"                  => $headers_ref,
        "rows"                     => $rows_concat,
        "total_filas"              => count($rows_concat),
        "num_tablas_concatenadas"  => $num_concat
        );
    }

// Busca patrones tipicos de footer en el markdown completo. NO parsea valores,
// devuelve el bloque de texto como contexto crudo. Tambien devuelve la ultima
// fila de la tabla principal (que en muchas facturas trae la distribucion por
// columna o el total por linea).
function extrae_footer_y_totales($md, $tabla_principal)
    {
    $patrones = array(
        "TOT.BOX",   "TOT BOX",   "TOT_BOX",
        "TOT.BOUNCH","TOT.BUNCH", "TOT BUNCH", "TOT BOUNCH",
        "TOT.STEMS", "TOT STEMS", "TOT_STEMS", "TOT.TALLOS", "TOT TALLOS",
        "SUB TOTAL", "SUBTOTAL",  "SUB-TOTAL",
        "TOTAL"
        );

    $lineas    = explode("\n", (string)$md);
    $cant      = count($lineas);
    $contexto  = array();

    for($i = 0; $i < $cant; $i++)
        {
        $up = strtoupper($lineas[$i]);
        $hit = false;
        $pn  = count($patrones);
        for($p = 0; $p < $pn; $p++)
            {
            if(strpos($up, $patrones[$p]) !== false)
                {
                $hit = true;
                break;
                }
            }
        if(!$hit)
            continue;

        $desde = max(0, $i - 2);
        $hasta = min($cant - 1, $i + 3);
        for($k = $desde; $k <= $hasta; $k++)
            $contexto[$k] = $lineas[$k];
        }

    ksort($contexto);
    $bloque = trim(implode("\n", $contexto));

    $ultima_fila = array();
    if(isset($tabla_principal["rows"]) && count($tabla_principal["rows"]) > 0)
        {
        $idx = count($tabla_principal["rows"]) - 1;
        $ultima_fila = $tabla_principal["rows"][$idx];
        }

    return array(
        "ultima_fila_tabla"     => $ultima_fila,
        "bloque_totales_texto"  => $bloque
        );
    }

// Extrae JSON del texto de Haiku (limpia fences markdown si los hay).
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
echo '<title>Test GLM-OCR + Haiku Validador</title>';
echo '<style>';
echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; padding: 20px; color: #222; }';
echo 'h1 { margin:0; font-size:20px; color:#222; }';
echo 'h2 { color: #88010e; margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 4px; }';
echo 'h3 { color: #555; margin-top: 16px; font-size: 14px; }';
echo 'pre { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; overflow: auto; max-height: 50vh; font-size: 12px; }';
echo '.meta { font-size: 13px; line-height: 1.7; }';
echo '.meta b { color: #555; }';
echo '.err { color: #88010e; font-weight: bold; }';
echo '.ok-box { background:#e9f7ec; border:2px solid #2e7d32; padding:14px 16px; margin:16px 0; border-radius:6px; }';
echo '.ok-box .titulo { color:#2e7d32; font-weight:bold; font-size:18px; }';
echo '.rev-box { background:#fdecec; border:2px solid #88010e; padding:14px 16px; margin:16px 0; border-radius:6px; }';
echo '.rev-box .titulo { color:#88010e; font-weight:bold; font-size:18px; }';
echo 'table.diag { border-collapse: collapse; font-size: 13px; margin: 8px 0; }';
echo 'table.diag th, table.diag td { border: 1px solid #ccc; padding: 4px 8px; }';
echo 'table.diag th { background: #f0f0f0; color:#333; }';
echo 'table.diag tr.diff td { background: #fdecec; }';
echo '.costo-total { font-size: 16px; font-weight: bold; color:#88010e; margin-top:24px; }';
echo '</style></head><body>';

echo '<h1>Pipeline GLM-OCR + Haiku validador - codigo '.(int)$codigo.'</h1>';

// ----------------------------------------------------------------------------
// LEER EL ADJUNTO
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
// PASO 1: GLM-OCR
// ----------------------------------------------------------------------------
$pdf_base64 = base64_encode((string)$archivo);
$data_uri   = "data:application/pdf;base64,".$pdf_base64;

$body_glm = array(
    "model" => "glm-ocr",
    "file"  => $data_uri
    );
$body_glm_json = json_encode($body_glm);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/layout_parsing");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$ZAI_API_KEY,
    "Content-Type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $body_glm_json);

$t_inicio_glm   = microtime(true);
$response_glm   = curl_exec($ch);
$http_glm       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err_glm   = curl_error($ch);
$tiempo_glm     = round(microtime(true) - $t_inicio_glm, 2);
curl_close($ch);

$archivo_glm_resp = "/tmp/glmval_glmrespuesta_".$codigo."_".$fecha_corrida.".json";
$data_glm         = json_decode((string)$response_glm, true);
if($data_glm !== null)
    file_put_contents($archivo_glm_resp, json_encode($data_glm, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_glm_resp, (string)$response_glm);

echo '<h2>Paso 1 - GLM-OCR</h2>';
echo '<div class="meta">';
echo '<b>Endpoint:</b> https://api.z.ai/api/paas/v4/layout_parsing<br>';
echo '<b>Modelo:</b> glm-ocr<br>';
echo '<b>Tiempo:</b> '.$tiempo_glm.' s<br>';
if($http_glm == 200)
    echo '<b>HTTP:</b> '.(int)$http_glm.'<br>';
else
    echo '<span class="err"><b>HTTP:</b> '.(int)$http_glm.'</span><br>';
echo '<b>Respuesta cruda:</b> '.htmlspecialchars($archivo_glm_resp, ENT_QUOTES, 'UTF-8');
echo '</div>';

if($curl_err_glm != "")
    {
    echo '<p class="err">ERROR cURL: '.htmlspecialchars($curl_err_glm, ENT_QUOTES, 'UTF-8').'</p>';
    echo '</body></html>';
    exit;
    }

if($http_glm != 200)
    {
    echo '<p class="err">ERROR HTTP '.(int)$http_glm.'</p>';
    echo '<pre>'.htmlspecialchars((string)$response_glm, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

if($data_glm === null)
    {
    echo '<p class="err">Respuesta GLM no es JSON valido. Primeros 1000 chars:</p>';
    echo '<pre>'.htmlspecialchars(substr((string)$response_glm, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

$glm_usage         = isset($data_glm["usage"]) ? $data_glm["usage"] : array();
$glm_prompt_t      = isset($glm_usage["prompt_tokens"])     ? (int)$glm_usage["prompt_tokens"]     : 0;
$glm_completion_t  = isset($glm_usage["completion_tokens"]) ? (int)$glm_usage["completion_tokens"] : 0;
$glm_total_t       = isset($glm_usage["total_tokens"])      ? (int)$glm_usage["total_tokens"]      : 0;
$glm_num_pages     = isset($data_glm["data_info"]["num_pages"]) ? $data_glm["data_info"]["num_pages"] : "?";
$md_results        = isset($data_glm["md_results"]) ? (string)$data_glm["md_results"] : "";
$len_md            = strlen($md_results);

$costo_glm = ($glm_prompt_t / 1000000.0) * 0.60
           + ($glm_completion_t / 1000000.0) * 1.92;

$archivo_md = "/tmp/glmval_md_".$codigo."_".$fecha_corrida.".md";
if($len_md > 0)
    file_put_contents($archivo_md, $md_results);

echo '<div class="meta">';
echo '<b>Tokens prompt:</b> '.number_format($glm_prompt_t).' &nbsp; ';
echo '<b>completion:</b> '.number_format($glm_completion_t).' &nbsp; ';
echo '<b>total:</b> '.number_format($glm_total_t).'<br>';
echo '<b>num_pages:</b> '.htmlspecialchars((string)$glm_num_pages, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>md_results length:</b> '.number_format($len_md).' chars<br>';
echo '<b>md guardado en:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Costo GLM estimado:</b> ~$'.number_format($costo_glm, 6).' USD (input $0.60/M, output $1.92/M)';
echo '</div>';

if($len_md == 0)
    {
    echo '<p class="err">md_results vacio. No se puede continuar.</p>';
    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// PASO 2: PHP PARSER (matriz cruda, sin semantica)
// ----------------------------------------------------------------------------
$tablas         = parsea_tablas_html($md_results);
$num_tablas_det = count($tablas);

$tabla_principal = encuentra_tabla_principal($tablas);
$footer          = extrae_footer_y_totales($md_results, $tabla_principal);

$matriz_data = array(
    "tabla_principal"       => array(
        "headers"                  => $tabla_principal["headers"],
        "rows"                     => $tabla_principal["rows"],
        "total_filas"              => $tabla_principal["total_filas"],
        "num_tablas_concatenadas"  => $tabla_principal["num_tablas_concatenadas"]
        ),
    "ultima_fila_tabla"     => $footer["ultima_fila_tabla"],
    "bloque_totales_texto"  => $footer["bloque_totales_texto"]
    );

$archivo_matriz = "/tmp/glmval_matriz_".$codigo."_".$fecha_corrida.".json";
file_put_contents($archivo_matriz, json_encode($matriz_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo '<h2>Paso 2 - PHP parser (matriz cruda)</h2>';
echo '<div class="meta">';
echo '<b>Tablas HTML detectadas:</b> '.$num_tablas_det.'<br>';
echo '<b>Tablas concatenadas en la principal:</b> '.(int)$tabla_principal["num_tablas_concatenadas"].'<br>';
echo '<b>Filas en tabla principal:</b> '.(int)$tabla_principal["total_filas"].'<br>';
echo '<b>Headers detectados:</b> '.count($tabla_principal["headers"]).'<br>';
echo '<b>Matriz guardada en:</b> '.htmlspecialchars($archivo_matriz, ENT_QUOTES, 'UTF-8');
echo '</div>';

if($num_tablas_det == 0)
    echo '<p class="err">No se detecto ninguna tabla HTML en el markdown. Haiku recibira input vacio.</p>';

echo '<h3>Preview headers</h3>';
echo '<pre>'.htmlspecialchars(json_encode($tabla_principal["headers"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8').'</pre>';

echo '<h3>Preview primeras 3 filas</h3>';
$preview_rows = array_slice($tabla_principal["rows"], 0, 3);
echo '<pre>'.htmlspecialchars(json_encode($preview_rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8').'</pre>';

echo '<h3>Ultima fila de la tabla</h3>';
echo '<pre>'.htmlspecialchars(json_encode($footer["ultima_fila_tabla"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8').'</pre>';

echo '<h3>Bloque de totales en texto crudo</h3>';
echo '<pre>'.htmlspecialchars($footer["bloque_totales_texto"], ENT_QUOTES, 'UTF-8').'</pre>';

// ----------------------------------------------------------------------------
// PASO 3: HAIKU VALIDADOR
// ----------------------------------------------------------------------------

$prompt_template = <<<'PROMPT'
Eres un validador de extracciones de facturas de flores ecuatorianas.
Recibes la salida cruda de un OCR ya parseada como tabla. NO ves el PDF original. Tu unica tarea: detectar inconsistencias.

CONTEXTO:
Las facturas tienen una tabla principal con columnas que incluyen numero de caja, tipo de caja, variedad de flor, tallos por ramo, varias columnas de LARGO en cm (tipicamente 30, 40, 50, 60, 70, 80, 90, 100, 110, 120) donde se anota la cantidad de RAMOS en la columna del largo correspondiente, y al final columnas de tallos totales, precio unitario y precio total.

Al final de la factura hay un FOOTER con totales generales y TIPICAMENTE una fila resumen con la cantidad de RAMOS POR CADA LARGO.

DATOS QUE TE PASO:

A) Headers de la tabla principal (orden y nombre tal cual los lee el OCR):
{INSERTAR HEADERS}

B) Filas de la tabla principal (cada fila es un array, en el mismo orden que los headers):
{INSERTAR ROWS}

C) Ultima fila de la tabla (posiblemente totales por columna):
{INSERTAR ULTIMA_FILA}

D) Bloque de texto con totales del footer:
{INSERTAR BLOQUE_TOTALES_TEXTO}

TAREAS:

1. Identifica cuales son las columnas relevantes:
   - Cual es la columna de NUMERO_CAJA
   - Cual es VARIEDAD
   - Cuales son las columnas de LARGO (30, 40, 50, 60, etc.)
   - Cual es TALLOS_POR_RAMO
   - Cual es TALLOS_TOTAL
   - Cual es PRECIO_UNITARIO
   - Cual es PRECIO_TOTAL

2. Para cada fila de detalle (excluyendo posibles headers duplicados por multipagina), determina:
   - En que columna de largo hay un valor (debe haber UNA con valor)
   - Si hay mas de una columna con valor, considerar UNA LINEA por cada largo con valor (compartiendo caja/variedad)
   - Si no hay ninguna columna de largo con valor, marcar como SOSPECHOSA (largo no detectado)

3. Suma todos los RAMOS por largo. Compara con la ultima fila o el bloque de totales. Si difieren, ALERTA por largo.

4. Suma todos los TALLOS_TOTAL. Compara con TOT.STEMS / TOT_TALLOS del footer.

5. Suma todos los PRECIO_TOTAL. Compara con SUB TOTAL / TOTAL del footer (tolerancia 0.01).

6. Para cada fila verifica que: RAMOS * TALLOS_POR_RAMO == TALLOS_TOTAL.

REGLAS ESTRICTAS:
- NO inventes correcciones ni sugieras valores alternativos.
- Si detectas incongruencia, listas el indice de la fila sospechosa y el motivo.
- Si todo cuadra, devuelves estado=OK.
- Si hay alguna diferencia (incluso de 1 ramo o 1 centavo), devuelves estado=REVISAR.

FORMATO DE SALIDA (JSON estricto, sin markdown, sin ```json):

{
  "estado": "OK" | "REVISAR",
  "mapeo_columnas": {
    "numero_caja": indice_columna,
    "variedad": indice_columna,
    "tallos_por_ramo": indice_columna,
    "largos": {"30": indice, "40": indice, ...},
    "tallos_total": indice_columna,
    "precio_unitario": indice_columna,
    "precio_total": indice_columna
  },
  "totales_calculados": {
    "total_lineas": N,
    "total_ramos": N,
    "total_tallos": N,
    "total_dolar": X.XX
  },
  "totales_footer": {
    "total_ramos": N o null,
    "total_tallos": N o null,
    "total_dolar": X.XX o null
  },
  "diferencias_globales": {
    "ramos": N,
    "tallos": N,
    "dolar": X.XX
  },
  "distribucion_por_largo": {
    "disponible_en_footer": true|false,
    "comparacion": [
      {"largo": 60, "suma_lineas": N, "footer": N, "diferencia": N},
      ...
    ]
  },
  "lineas_sospechosas": [
    {"indice_fila": N, "motivo": "...", "datos": {caja:..., variedad:...}}
  ],
  "resumen_humano": "Una frase corta describiendo el estado general"
}

Devuelve SOLO el JSON. Nada antes ni despues.
PROMPT;

$headers_json     = json_encode($tabla_principal["headers"], JSON_UNESCAPED_UNICODE);
$rows_json        = json_encode($tabla_principal["rows"], JSON_UNESCAPED_UNICODE);
$ultima_fila_json = json_encode($footer["ultima_fila_tabla"], JSON_UNESCAPED_UNICODE);
$bloque_totales   = $footer["bloque_totales_texto"];

$prompt = $prompt_template;
$prompt = str_replace("{INSERTAR HEADERS}",                $headers_json,     $prompt);
$prompt = str_replace("{INSERTAR ROWS}",                   $rows_json,        $prompt);
$prompt = str_replace("{INSERTAR ULTIMA_FILA}",            $ultima_fila_json, $prompt);
$prompt = str_replace("{INSERTAR BLOQUE_TOTALES_TEXTO}",   $bloque_totales,   $prompt);

$payload_haiku = array(
    "model"       => "claude-haiku-4-5-20251001",
    "max_tokens"  => 4000,
    "temperature" => 0,
    "messages"    => array(
        array(
            "role"    => "user",
            "content" => $prompt
            )
        )
    );
$payload_haiku_json = json_encode($payload_haiku);

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
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_haiku_json);

$t_inicio_haiku  = microtime(true);
$response_haiku  = curl_exec($ch);
$http_haiku      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err_haiku  = curl_error($ch);
$tiempo_haiku    = round(microtime(true) - $t_inicio_haiku, 2);
curl_close($ch);

$archivo_haiku = "/tmp/glmval_haiku_".$codigo."_".$fecha_corrida.".json";
$data_haiku    = json_decode((string)$response_haiku, true);
if($data_haiku !== null)
    file_put_contents($archivo_haiku, json_encode($data_haiku, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_haiku, (string)$response_haiku);

echo '<h2>Paso 3 - Haiku validador</h2>';
echo '<div class="meta">';
echo '<b>Endpoint:</b> https://api.anthropic.com/v1/messages<br>';
echo '<b>Modelo:</b> claude-haiku-4-5-20251001<br>';
echo '<b>Tiempo:</b> '.$tiempo_haiku.' s<br>';
if($http_haiku == 200)
    echo '<b>HTTP:</b> '.(int)$http_haiku.'<br>';
else
    echo '<span class="err"><b>HTTP:</b> '.(int)$http_haiku.'</span><br>';
echo '<b>Respuesta cruda:</b> '.htmlspecialchars($archivo_haiku, ENT_QUOTES, 'UTF-8');
echo '</div>';

if($curl_err_haiku != "")
    {
    echo '<p class="err">ERROR cURL Haiku: '.htmlspecialchars($curl_err_haiku, ENT_QUOTES, 'UTF-8').'</p>';
    echo '</body></html>';
    exit;
    }

if($http_haiku != 200)
    {
    echo '<p class="err">ERROR HTTP Haiku '.(int)$http_haiku.'</p>';
    echo '<pre>'.htmlspecialchars((string)$response_haiku, ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

if($data_haiku === null)
    {
    echo '<p class="err">Respuesta Haiku no es JSON valido. Primeros 1000 chars:</p>';
    echo '<pre>'.htmlspecialchars(substr((string)$response_haiku, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</body></html>';
    exit;
    }

$haiku_input_t  = isset($data_haiku["usage"]["input_tokens"])  ? (int)$data_haiku["usage"]["input_tokens"]  : 0;
$haiku_output_t = isset($data_haiku["usage"]["output_tokens"]) ? (int)$data_haiku["usage"]["output_tokens"] : 0;
$costo_haiku    = ($haiku_input_t / 1000000.0) * 1.00
                + ($haiku_output_t / 1000000.0) * 5.00;

echo '<div class="meta">';
echo '<b>Tokens input:</b> '.number_format($haiku_input_t).' &nbsp; ';
echo '<b>output:</b> '.number_format($haiku_output_t).'<br>';
echo '<b>Costo Haiku estimado:</b> ~$'.number_format($costo_haiku, 6).' USD (input $1.00/M, output $5.00/M)';
echo '</div>';

$texto_haiku = "";
if(isset($data_haiku["content"][0]["text"]))
    $texto_haiku = (string)$data_haiku["content"][0]["text"];

$json_str   = extraer_json($texto_haiku);
$validacion = json_decode($json_str, true);

if($validacion === null)
    {
    echo '<p class="err">Haiku devolvio JSON invalido. Texto crudo:</p>';
    echo '<pre>'.htmlspecialchars($texto_haiku, ENT_QUOTES, 'UTF-8').'</pre>';
    }

// ----------------------------------------------------------------------------
// PASO 4: RESULTADO FINAL
// ----------------------------------------------------------------------------
echo '<h2>Resultado final</h2>';

if($validacion !== null)
    {
    $estado          = isset($validacion["estado"]) ? (string)$validacion["estado"] : "?";
    $resumen_humano  = isset($validacion["resumen_humano"]) ? (string)$validacion["resumen_humano"] : "";

    if(strtoupper($estado) == "OK")
        {
        echo '<div class="ok-box">';
        echo '<div class="titulo">ESTADO: OK</div>';
        echo '<div style="margin-top:6px;">'.htmlspecialchars($resumen_humano, ENT_QUOTES, 'UTF-8').'</div>';
        echo '</div>';
        }
    else
        {
        echo '<div class="rev-box">';
        echo '<div class="titulo">ESTADO: REVISAR</div>';
        echo '<div style="margin-top:6px;">'.htmlspecialchars($resumen_humano, ENT_QUOTES, 'UTF-8').'</div>';
        echo '</div>';

        // Tabla: totales calculados vs footer.
        $tc = isset($validacion["totales_calculados"]) ? $validacion["totales_calculados"] : array();
        $tf = isset($validacion["totales_footer"])     ? $validacion["totales_footer"]     : array();
        $dg = isset($validacion["diferencias_globales"]) ? $validacion["diferencias_globales"] : array();

        echo '<h3>Totales calculados vs footer</h3>';
        echo '<table class="diag">';
        echo '<tr><th>Campo</th><th>Calculado</th><th>Footer</th><th>Diferencia</th></tr>';

        $filas_resumen = array(
            array("Ramos",  "total_ramos",  "ramos"),
            array("Tallos", "total_tallos", "tallos"),
            array("Dolar",  "total_dolar",  "dolar")
            );
        for($i = 0; $i < count($filas_resumen); $i++)
            {
            $etiq = $filas_resumen[$i][0];
            $kc   = $filas_resumen[$i][1];
            $kd   = $filas_resumen[$i][2];
            $vc   = isset($tc[$kc]) ? $tc[$kc] : "-";
            $vf   = isset($tf[$kc]) ? $tf[$kc] : "-";
            $vd   = isset($dg[$kd]) ? $dg[$kd] : "-";

            $clase = "";
            if(is_numeric($vd) && abs((float)$vd) > 0.001)
                $clase = ' class="diff"';

            echo '<tr'.$clase.'>';
            echo '<td><b>'.$etiq.'</b></td>';
            echo '<td>'.htmlspecialchars((string)$vc, ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)($vf === null ? "null" : $vf), ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$vd, ENT_QUOTES, 'UTF-8').'</td>';
            echo '</tr>';
            }
        echo '</table>';

        // Tabla distribucion por largo.
        $dist     = isset($validacion["distribucion_por_largo"]) ? $validacion["distribucion_por_largo"] : array();
        $disp     = isset($dist["disponible_en_footer"]) ? (bool)$dist["disponible_en_footer"] : false;
        $comp     = isset($dist["comparacion"]) && is_array($dist["comparacion"]) ? $dist["comparacion"] : array();

        if($disp && !empty($comp))
            {
            echo '<h3>Distribucion por largo (ramos)</h3>';
            echo '<table class="diag">';
            echo '<tr><th>Largo</th><th>Suma lineas</th><th>Footer</th><th>Diferencia</th></tr>';
            $cn = count($comp);
            for($i = 0; $i < $cn; $i++)
                {
                $c       = $comp[$i];
                $diff    = isset($c["diferencia"]) ? $c["diferencia"] : 0;
                $clase   = (is_numeric($diff) && abs((float)$diff) > 0.001) ? ' class="diff"' : '';
                echo '<tr'.$clase.'>';
                echo '<td>'.htmlspecialchars((string)(isset($c["largo"]) ? $c["largo"] : ""), ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars((string)(isset($c["suma_lineas"]) ? $c["suma_lineas"] : ""), ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars((string)(isset($c["footer"]) ? $c["footer"] : ""), ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars((string)$diff, ENT_QUOTES, 'UTF-8').'</td>';
                echo '</tr>';
                }
            echo '</table>';
            }
        else
            {
            echo '<h3>Distribucion por largo</h3>';
            echo '<div class="meta">No disponible en el footer (segun Haiku).</div>';
            }

        // Tabla lineas sospechosas.
        $sosp = isset($validacion["lineas_sospechosas"]) && is_array($validacion["lineas_sospechosas"]) ? $validacion["lineas_sospechosas"] : array();
        if(!empty($sosp))
            {
            echo '<h3>Lineas sospechosas</h3>';
            echo '<table class="diag">';
            echo '<tr><th>Indice fila</th><th>Motivo</th><th>Caja</th><th>Variedad</th></tr>';
            $sn = count($sosp);
            for($i = 0; $i < $sn; $i++)
                {
                $s     = $sosp[$i];
                $idx   = isset($s["indice_fila"]) ? $s["indice_fila"] : "?";
                $mot   = isset($s["motivo"]) ? (string)$s["motivo"] : "";
                $datos = isset($s["datos"]) && is_array($s["datos"]) ? $s["datos"] : array();
                $caja  = isset($datos["caja"]) ? $datos["caja"] : "";
                $var   = isset($datos["variedad"]) ? $datos["variedad"] : "";
                echo '<tr>';
                echo '<td>'.htmlspecialchars((string)$idx, ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars($mot, ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars((string)$caja, ENT_QUOTES, 'UTF-8').'</td>';
                echo '<td>'.htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8').'</td>';
                echo '</tr>';
                }
            echo '</table>';
            }
        else
            {
            echo '<h3>Lineas sospechosas</h3>';
            echo '<div class="meta">Ninguna marcada.</div>';
            }
        }

    echo '<h3>JSON completo de validacion</h3>';
    echo '<pre>'.htmlspecialchars(json_encode($validacion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
    }

// ----------------------------------------------------------------------------
// COSTO TOTAL Y ARCHIVOS
// ----------------------------------------------------------------------------
$costo_total = $costo_glm + $costo_haiku;

echo '<div class="costo-total">COSTO TOTAL: ~$'.number_format($costo_total, 6).' USD ';
echo '(GLM ~$'.number_format($costo_glm, 6).' + Haiku ~$'.number_format($costo_haiku, 6).')</div>';

echo '<h3>Archivos guardados</h3>';
echo '<div class="meta">';
echo '<b>GLM respuesta cruda:</b> '.htmlspecialchars($archivo_glm_resp, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>GLM md_results:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Matriz cruda PHP:</b> '.htmlspecialchars($archivo_matriz, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Haiku respuesta cruda:</b> '.htmlspecialchars($archivo_haiku, ENT_QUOTES, 'UTF-8');
echo '</div>';

echo '</body></html>';
