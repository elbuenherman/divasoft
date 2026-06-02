<?php 

// ============================================================================
//  test_glmocr_formatear.php
//  Pipeline GLM-OCR + Haiku formateador, para fincas SENCILLAS (sin grid
//  posicional).
//  Paso 1: GLM-OCR de Z.ai extrae el markdown del PDF.
//  Paso 2: Haiku 4.5 recibe el markdown CRUDO y lo CONVIERTE directamente al
//          JSON estructurado (CABECERA, LOGISTICA, RESUMEN_EMPAQUE, CAJAS).
//          NO valida sumas. Solo formatea.
//
//  Uso: test_glmocr_formatear.php?codigo=10
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(300);
ini_set("max_execution_time", "300");
ini_set("memory_limit", "512M");
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 10;

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
echo '<title>Test GLM-OCR + Haiku formateador - codigo '.(int)$codigo.'</title>';
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
echo 'table.cajas th, table.cajas td { border: 1px solid #ccc; padding: 4px 8px; vertical-align: top; }';
echo 'table.cajas th { background: #f0f0f0; color:#333; text-align:left; }';
echo 'table.cajas td.num { text-align: right; }';
echo 'table.cajas tr.alerta td { background: #fdecec; }';
echo '.costo-total { font-size: 16px; font-weight: bold; color:#88010e; margin-top:24px; }';
echo 'details > summary { cursor: pointer; font-weight: bold; color: #555; margin: 6px 0; }';
echo '</style></head><body>';

echo '<h1>Pipeline GLM-OCR + Haiku formateador - codigo '.(int)$codigo.'</h1>';

// ----------------------------------------------------------------------------
// PASO 1 - LEER ADJUNTO
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
// PASO 2 - GLM-OCR (Z.AI) - mismo flujo que test_glmocr_validar.php
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
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer ".$ZAI_API_KEY,
    "Content-Type: application/json"
    ));
curl_setopt($ch, CURLOPT_POSTFIELDS, $body_glm_json);

$t_inicio_glm = microtime(true);
$response_glm = curl_exec($ch);
$http_glm     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err_glm = curl_error($ch);
$tiempo_glm   = round(microtime(true) - $t_inicio_glm, 2);
curl_close($ch);

$archivo_glm_resp = "/tmp/glmocrform_".$codigo."_".$fecha_corrida."_glmrespuesta.json";
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
    echo '<p class="err">ERROR cURL GLM-OCR: '.htmlspecialchars($curl_err_glm, ENT_QUOTES, 'UTF-8').'</p>';
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

// Costo GLM-OCR: $0.03/M tokens (input y output mismo precio).
$costo_glm = ($glm_total_t / 1000000.0) * 0.03;

$archivo_md = "/tmp/glmocrform_".$codigo."_".$fecha_corrida."_md.txt";
if($len_md > 0)
    file_put_contents($archivo_md, $md_results);

echo '<div class="meta">';
echo '<b>Tokens prompt:</b> '.number_format($glm_prompt_t).' &nbsp; ';
echo '<b>completion:</b> '.number_format($glm_completion_t).' &nbsp; ';
echo '<b>total:</b> '.number_format($glm_total_t).'<br>';
echo '<b>num_pages:</b> '.htmlspecialchars((string)$glm_num_pages, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>md_results length:</b> '.number_format($len_md).' chars<br>';
echo '<b>md guardado en:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Costo GLM-OCR estimado:</b> ~$'.number_format($costo_glm, 6).' USD ($0.03/M tokens)';
echo '</div>';

if($len_md == 0)
    {
    echo '<p class="err">md_results vacio. No se puede continuar.</p>';
    echo '</body></html>';
    exit;
    }

echo '<h3>Markdown crudo de GLM-OCR</h3>';
echo '<details><summary>Mostrar/ocultar markdown ('.number_format($len_md).' chars)</summary>';
echo '<pre>'.htmlspecialchars($md_results, ENT_QUOTES, 'UTF-8').'</pre>';
echo '</details>';

// ----------------------------------------------------------------------------
// PASO 3 - HAIKU FORMATEADOR
//   El prompt grande es el MISMO de procesa_factura_test.php (lineas 185-504),
//   copiado byte por byte. Antes se le antepone un encabezado que inyecta el
//   markdown de GLM-OCR.
// ----------------------------------------------------------------------------
$prompt_grande = <<<'PROMPT'
Eres un extractor experto de facturas de fincas ecuatorianas de flores
emitidas a DIVAFLOR (broker exportador). Tu tarea es leer la factura PDF
y devolver un JSON estructurado con TODOS los datos extraídos, sin
inventar nada.

═══════════════════════════════════════════════════════════════════
CONTEXTO DE NEGOCIO
═══════════════════════════════════════════════════════════════════
- DIVAFLOR (RUC 1707490098001, razón social "IP Herman Diener") es
  nuestra empresa. Las facturas vienen dirigidas A DIVAFLOR. NUNCA
  extraer DIVAFLOR/DIVA FLOR como cliente.
- El CLIENTE_MARCACION es el destinatario final del envio (YANOK,
  RYADOM, FLORAOLA, BESTFLORA, BIZON, LEPESTOK, etc.). Cada finca lo
  pone con un nombre de campo distinto: "Mark", "Label", "Consignee",
  "Ship Customer", "To", "Marcacion", "Notify", "Customer", "Box
  Marking". REGLA: el cliente que NO es DIVAFLOR. NO partir por
  guiones automaticamente.
- PRIORIDAD DE FUENTE: si el PDF trae un campo explicito llamado
  "Box Marking", "Marcado", o "Mark" -> usar ESE como fuente primaria
  (es la marcacion oficial de la caja). Solo si NO existe ese campo,
  usar "Ship To" / "Consignee" / "Notify".
- Si "Ship To" o "Consignee" trae prefijos como "DV", "DIVA" o
  "DIVAFLOR" antes del nombre del cliente real, son referencias al
  broker (nosotros), NO parte del nombre del cliente final. Quitar
  esos prefijos.
- Ejemplo: PDF tiene "Ship To: DV LEPESTOK" y "Box Marking: LEPESTOK"
  -> la marcacion correcta es LEPESTOK.

═══════════════════════════════════════════════════════════════════
REGLA DE ORO (CRÍTICA — no falles esto)
═══════════════════════════════════════════════════════════════════
Algunas fincas usan GRID POSICIONAL: el header tiene columnas de
largos (30 | 40 | 50 | 60 | 70 | 80 | 90 | 100 | 110 | 120) y el largo
de cada flor NO está escrito como dato, sino que se deduce por la
POSICIÓN del número de ramos bajo la columna correspondiente.

Para cada número del cuerpo del grid, identifica EXACTAMENTE qué
número del header está alineado verticalmente con esa celda, siguiendo
la línea vertical. NUNCA inferir la columna por orden de aparición, ni
contando columnas a ojo, ni por el precio. Mira la página COMPLETA con
el header visible.

Fincas con grid posicional conocidas: OLIMPO, STANDARD, MONTEBELLO,
ROSE BLOOM, MEGAFLOR. En facturas multipágina, el header solo aparece
en la 1ª página; úsalo como referencia para todas las páginas.

El "25" o "25X" en estas fincas NO es un largo (no existe el largo
25 cm). Es la cantidad de tallos por bonche. El largo real es el de
la columna donde cae el número de ramos.

El footer de totales por columna que traen algunas fincas (OLIMPO,
STANDARD) NO es confiable. Sirve solo de pista secundaria, nunca para
validar.

═══════════════════════════════════════════════════════════════════
PROTOCOLO ESTRICTO PARA GRID POSICIONAL
═══════════════════════════════════════════════════════════════════
Cuando una factura tenga un grid posicional (columnas de largo
30/40/50/60/70/80/90/100/110/120 como encabezados y el numero de ramos
aparece DEBAJO de la columna del largo correspondiente), ejecuta este
protocolo MENTAL paso a paso para CADA linea:

PASO 1 - Identifica el orden EXACTO de las columnas de largo en el
header de la tabla. De izquierda a derecha. Ejemplo si el header dice
"30 40 50 60 70 80 90 100 110 120", el orden es ese.

PASO 2 - Para CADA fila de variedad, identifica TODOS los numeros
enteros positivos (sin punto decimal) que aparecen en las columnas de
largo. Normalmente solo hay UNO por linea, pero puede haber dos si la
variedad ocupa dos largos en una misma caja.

PASO 3 - Cuenta la POSICION del numero en relacion a las columnas del
header. Si el numero esta en la PRIMERA posicion de las columnas de
largo, el LARGO es el primero del header (ej 30). Si esta en la
TERCERA posicion, el largo es el tercero (ej 50). Y asi.

PASO 4 - VERIFICACION OBLIGATORIA: RAMOS x TALLOS_POR_RAMO debe igualar
TALLOS_TOTAL. Si no cuadra, REVISA tu lectura.

PASO 5 - Si genuinamente no puedes determinar la posicion (porque la
tabla esta visualmente desalineada o el PDF tiene ruido), marca
LARGO=null Y agrega ALERTA explicita "Grid posicional ilegible para
esta linea".

REGLAS DURAS:
- NO inventes LARGOs. Si dudas, marca null + alerta.
- NO uses el PRECIO_UNITARIO para inferir el largo (aunque haya
  correlacion historica, no la apliques aqui).
- NO uses el orden alfabetico de variedades.
- El unico criterio valido es la POSICION HORIZONTAL del numero RAMOS
  bajo cada columna de largo del header.

EJEMPLO concreto para STANDARD FLOWERS:
- Header: PIEZAS, Full Box, Tipo, Marca, BOX, 30, 40, 50, 60, 70, 80,
  90, 100, 110, 120, PRECIO
- Si una fila dice "TYCOON ... 2 ... 0.30 15.00", busca el "2" en las
  columnas 30 a 120.
- Si el "2" esta en la columna del 60, LARGO=60.
- Si el "2" esta en la columna del 80, LARGO=80.

NUNCA dejes TODAS las lineas con LARGO=null. Si una factura es de
grid posicional, AL MENOS la mitad de las lineas debe tener LARGO
numerico. Si todas salen null, es señal de que no leiste el grid;
vuelve a intentarlo.

FIN DEL PROTOCOLO.

═══════════════════════════════════════════════════════════════════
REGLAS DE EXTRACCIÓN
═══════════════════════════════════════════════════════════════════

PRODUCTOS Y SECCIONES:
- ROSA / SPRAY (rosa spray) → categoría "ROSA" o "SPRAY"
- GYPSO / GYPSOPHILA → "GYPSO"
- ALSTROEMERIA → "ALSTROEMERIA"
- CLAVEL / CARNATION → "CARNATION"
- MATTHIOLA → "MATTHIOLA"
- Otra flor → "OTRO" + alerta

SPRAY (rosa spray):
- SPRAY se detalla LÍNEA POR LÍNEA, igual que rosa normal. NUNCA
  consolidar en "MIX" ni en "SPRAY MIX" / "GARDEN MIX".
- NO inventar categoría "GARDEN". Si el PDF dice SPRAY, va SPRAY.
- TESSA factura por bonche de 10 tallos; precio TAL CUAL viene en la
  factura (0.50/0.60/0.70), NO dividir entre tallos. La cantidad en
  tallos = bonches × 10, y precio × tallos = total. Verificar que
  cuadre.

GYPSO:
Identificacion del LARGO en GYPSO (con cuidado):
- Si la columna de largo o grado trae un numero con sufijo "GR" o "G"
  (gramos), eso es el PESO del bonche, NO el largo en cm. Ejemplos:
  "40 GR", "1000 GR", "500G" -> todos son peso, no largo.
- Si solo hay peso en gramos y NINGUNA columna trae largo en cm ->
  asumir 80cm + alerta "GYPSO sin largo explicito; asumido 80cm".
- Si viene 85 cm -> bajar a 80 + alerta.
- Si viene un largo numerico explicito EN cm (con "cm" o columna
  rotulada "LARGO/LENGTH/CM") -> respetarlo tal cual.

Convencion de precio en GYPSO:
- Verificar haciendo la cuenta:
  - Si TALLOS x PRECIO_UNITARIO aproximado al TOTAL -> la factura es
    POR TALLO, dejar tal cual.
  - Si BONCHES x PRECIO_UNITARIO aproximado al TOTAL -> la factura es
    POR BONCHE, convertir: precio_tallo = precio_bonche / 25,
    tallos = bonches x 25, emitir alerta "GYPSO convertido bonche->tallo (/25)".
  Ejemplos POR BONCHE: MYSTIC, NINA FLOWERS.
  Ejemplo POR TALLO: UTOPIA FARMS.

CARNATIONS (clavel):
- Columnas por GRADO, no por cm directos:
  · STANDARD = 50 o 55 cm
  · FANCY    = 60 o 65 cm
  · SELECT   = 70 o 75 cm
- Devolver tanto LARGO (en cm si está en el PDF) como GRADO.
- Si no hay largo en cm pero el nombre indica grado (ej "MINI MIX
  SELECT"), usar ese grado y marcar alerta de "sin largo explícito".

EXPORTAFLOR:
- Por defecto va a CARNATION. Si la factura claramente indica rosa
  (variedad de rosa reconocible, o dice ROSE/ROSA en el texto),
  clasificar como ROSA y marcar alerta REVISAR.

VARIEDADES:
- Respetar el nombre TAL CUAL aparece en el PDF. NO traducir, NO
  renombrar.
  · "MIX COLOR" si la finca lo puso así (FINE PETALS, INFINITY, WACI,
    GARDA): se respeta literal.
  · "MIXTA" (ROSE BLOOM): se respeta literal.
  · Sufijos como "[EXP]" (JET FRESH): se respetan tal cual.
- Normalización "X-": pegar la X a la palabra siguiente:
  · CANDY X-PRESSION   → CANDY XPRESSION
  · CREAM X-PRESSION   → CREAM XPRESSION
  · WHITE O HARA       → WHITE OHARA
  · PINK O HARA        → PINK OHARA

LARGOS:
- Siempre en cm como entero (40, 50, 60, 70, 80, 90, 100, 110, 120,
  130, 140, 150).
- Las columnas pueden llamarse CM, GRADE, LENGTH, Len, Largo. Mismo
  significado, alinear con la fila correcta.

CAJAS Y FB:
- Tipos: FB (Full Box=1), HB (Half Box=0.5), QB (Quarter Box=0.25),
  EB (Eighth/Octave Box=0.125).
- 2 HB = 1 FB. 5 HB = 2.5 FB.
- NUMERO_CAJA es el número físico de caja (1, 2, 3...).

═══════════════════════════════════════════════════════════════════
IDENTIFICACION DEL NUMERO DE CAJA EN CADA LINEA
═══════════════════════════════════════════════════════════════════
Cada linea de detalle (cada variedad) pertenece a una caja fisica
especifica. NUNCA agrupes lineas en la misma caja por error.

Como identificar a que caja pertenece cada linea:

REGLA PRINCIPAL: busca en la fila una columna que identifique el
numero de caja. Las fincas usan nombres distintos para esta columna:
  - "Desde / Hasta" (SUMAK SISA): los dos numeros que dicen
    "1 1", "2 2", "3 3", "4 4". Ese par identifica la caja.
  - "BOX" o "# BOX" (la mayoria)
  - "BX" (JARDINOR, WACI)
  - "Order" (FLORICOLA, JET FRESH)
  - "BOX No" (ATTAR, GARDA, MYSTIC)
  - "Boxs Order" (VALLEVERDE, SOL PACIFIC)

El numero de caja se REPITE en todas las lineas que pertenecen a esa
caja. Por ejemplo, si la caja 3 tiene 11 variedades, las 11 filas
tendran "3" en esa columna.

QUE NO USAR para identificar la caja:
- NO uses la columna "PIEZAS" o "Pieces": esa columna marca CUANTAS
  cajas fisicas son (1, 1, 1...). Su valor "1" aparece al inicio de
  cada caja nueva, pero NO indica el numero de caja.
- NO uses "Full Box" o "FB": esa columna marca el valor FB de la caja
  (0.5 para HB, 0.25 para QB, etc.). Tampoco indica el numero.
- NO uses guiones "-" en columnas: significan "misma caja que la
  anterior", son secundarios.

VERIFICACION INTERNA OBLIGATORIA: antes de cerrar el JSON, valida que
el numero total de cajas fisicas coincida con el resumen de empaque:
  cantidad de objetos en CAJAS[] = FULL_BOX + HALF_BOX + QUARTER_BOX + OCTAVE_BOX
Si no cuadra, REVISA la asignacion de cada linea a su caja.

EJEMPLO concreto SUMAK SISA: si una fila tiene "Desde=3, Hasta=3",
pertenece a la CAJA 3, no a la caja 2 ni a la 4. El valor "1" en la
columna "PIEZAS" de esa misma fila NO significa caja 1; significa que
empieza una nueva caja fisica en ese punto.

EJEMPLO de error a EVITAR: en una factura con 4 cajas, si las dos
ultimas lineas que abren caja (caja 2 y caja 3) tienen ambas "PIEZAS=1, FB=0.50",
NO las agrupes en la misma caja por ese parecido visual. Mira la
columna del numero de caja, que es la unica verdad.

NO HAY:
- AWB + PRECOOLING: esto NO sale de la factura de la finca. Las
  facturas son FOB/FCA, solo precio de la flor en Ecuador. Ese cargo
  es externo del envío.

═══════════════════════════════════════════════════════════════════
ESTRUCTURA JSON DE SALIDA (estricta)
═══════════════════════════════════════════════════════════════════
Devuelve EXACTAMENTE este esquema, con TODOS los campos presentes.
Los campos que no aparezcan en el PDF van como null (no inventes).
Los strings se devuelven tal como aparecen en la factura.

{
  "CABECERA": {
    "FINCA_PROVEEDOR": "string",
    "RUC_PROVEEDOR": "string|null",
    "NUMERO_FACTURA": "string",
    "FECHA_FACTURACION": "YYYY-MM-DD",
    "CLIENTE_MARCACION": "string",
    "SUBTOTAL": 0.00,
    "DESCUENTO": 0.00,
    "IVA_PORCENTAJE": 0,
    "IVA_VALOR": 0.00,
    "CARGO_FLETE": null,
    "CARGO_CAJAS": null,
    "CARGO_OTROS": null,
    "TOTAL": 0.00
  },
  "LOGISTICA": {
    "PAIS_DESTINO": "string",
    "MAWB": "string",
    "HAWB": "string",
    "DAE": "string",
    "AEROLINEA": "string|null",
    "FORWARDER": "string|null"
  },
  "RESUMEN_EMPAQUE": {
    "FULL_BOX": 0,
    "HALF_BOX": 0,
    "QUARTER_BOX": 0,
    "OCTAVE_BOX": 0,
    "TOTAL_CAJAS_EQUIVALENTES": 0.000,
    "TOTAL_RAMOS": 0,
    "TOTAL_TALLOS": 0,
    "PESO_BRUTO_KG": null,
    "PESO_NETO_KG": null
  },
  "CAJAS": [
    {
      "NUMERO_CAJA": 1,
      "TIPO_CAJA": "HB",
      "CONTENIDO": [
        {
          "PRODUCTO": "ROSA",
          "VARIEDAD": "EXPLORER",
          "LARGO": 60,
          "GRADO": null,
          "TALLOS_POR_RAMO": 25,
          "RAMOS": 16,
          "TALLOS_TOTAL": 400,
          "PRECIO_UNITARIO": 0.30,
          "PRECIO_TOTAL": 120.00,
          "ALERTA": null
        }
      ]
    }
  ],
  "ALERTAS": ["lista de strings con motivos generales de REVISAR"]
}

REGLAS DE FORMATO JSON:
- Devuelve SOLO el JSON, sin texto antes ni después, sin markdown.
- Números como números, no como strings.
- Fechas como YYYY-MM-DD.
- Campos faltantes en el PDF → null (no "N/A", no string vacío).
- Si encuentras algo ambiguo, NO inventes: ponlo en ALERTAS o
  ALERTA de línea, según corresponda.

ALERTAS típicas a emitir:
- "GYPSO convertido bonche→tallo (/25)"
- "Variedad sin largo explícito; asumido GRADO=X por nombre"
- "Producto sin clasificar: [nombre]"
- "Texto OCR ilegible en línea X, REVISAR"
- "EXPORTAFLOR: clasificado como ROSA por variedad reconocible
   (revisar)"
- "Grid posicional: ambigüedad en alineación, REVISAR"
PROMPT;

$encabezado = "Te paso a continuacion el contenido en markdown de una factura de flores extraido por OCR. Tu tarea es interpretarlo y extraer la informacion en el JSON estructurado segun las reglas que te doy.\n\n"
            . "MARKDOWN DE LA FACTURA:\n"
            . $md_results . "\n\n"
            . "FIN DEL MARKDOWN.\n\n"
            . "Ahora aplica estas reglas:\n\n";

$prompt_haiku = $encabezado.$prompt_grande;

$payload_haiku = array(
    "model"       => "claude-haiku-4-5-20251001",
    "max_tokens"  => 16000,
    "temperature" => 0,
    "messages"    => array(
        array(
            "role"    => "user",
            "content" => $prompt_haiku
            )
        )
    );
$payload_haiku_json = json_encode($payload_haiku);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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

$archivo_haiku = "/tmp/glmocrform_".$codigo."_".$fecha_corrida."_haiku.json";
$data_haiku    = json_decode((string)$response_haiku, true);
if($data_haiku !== null)
    file_put_contents($archivo_haiku, json_encode($data_haiku, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    file_put_contents($archivo_haiku, (string)$response_haiku);

echo '<h2>Paso 2 - Haiku formateador</h2>';
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

$haiku_input_t  = ($data_haiku !== null && isset($data_haiku["usage"]["input_tokens"]))  ? (int)$data_haiku["usage"]["input_tokens"]  : 0;
$haiku_output_t = ($data_haiku !== null && isset($data_haiku["usage"]["output_tokens"])) ? (int)$data_haiku["usage"]["output_tokens"] : 0;
$costo_haiku    = ($haiku_input_t / 1000000.0) * 1.00
                + ($haiku_output_t / 1000000.0) * 5.00;

echo '<div class="meta">';
echo '<b>Tokens input:</b> '.number_format($haiku_input_t).' &nbsp; ';
echo '<b>output:</b> '.number_format($haiku_output_t).'<br>';
echo '<b>Costo Haiku estimado:</b> ~$'.number_format($costo_haiku, 6).' USD (input $1.00/M, output $5.00/M)';
echo '</div>';

$haiku_falla   = false;
$texto_haiku   = "";
$extraccion    = null;

if($curl_err_haiku != "")
    {
    echo '<p class="err">ERROR cURL Haiku: '.htmlspecialchars($curl_err_haiku, ENT_QUOTES, 'UTF-8').'</p>';
    $haiku_falla = true;
    }
else if($http_haiku != 200)
    {
    echo '<p class="err">ERROR HTTP Haiku '.(int)$http_haiku.'</p>';
    echo '<pre>'.htmlspecialchars((string)$response_haiku, ENT_QUOTES, 'UTF-8').'</pre>';
    $haiku_falla = true;
    }
else if($data_haiku === null)
    {
    echo '<p class="err">Respuesta Haiku no es JSON valido (envoltorio Anthropic). Primeros 1000 chars:</p>';
    echo '<pre>'.htmlspecialchars(substr((string)$response_haiku, 0, 1000), ENT_QUOTES, 'UTF-8').'</pre>';
    $haiku_falla = true;
    }
else
    {
    if(isset($data_haiku["content"][0]["text"]))
        $texto_haiku = (string)$data_haiku["content"][0]["text"];

    $json_str   = extraer_json($texto_haiku);
    $extraccion = json_decode($json_str, true);

    if($extraccion === null)
        {
        echo '<p class="err">Haiku devolvio JSON invalido. Texto crudo:</p>';
        echo '<pre>'.htmlspecialchars($texto_haiku, ENT_QUOTES, 'UTF-8').'</pre>';
        }
    }

// ----------------------------------------------------------------------------
// PASO 4 - RESULTADO
// ----------------------------------------------------------------------------
echo '<h2>Resultado</h2>';

if($extraccion !== null)
    {
    echo '<h3>JSON extraido</h3>';
    echo '<pre>'.htmlspecialchars(json_encode($extraccion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8').'</pre>';

    // Tabla aplanada de las lineas en CAJAS[].CONTENIDO.
    $cajas         = isset($extraccion["CAJAS"]) && is_array($extraccion["CAJAS"]) ? $extraccion["CAJAS"] : array();
    $total_cajas   = count($cajas);
    $filas         = array();
    for($i = 0; $i < $total_cajas; $i++)
        {
        $caja        = $cajas[$i];
        $numero_caja = isset($caja["NUMERO_CAJA"]) ? $caja["NUMERO_CAJA"] : "";
        $tipo_caja   = isset($caja["TIPO_CAJA"])   ? $caja["TIPO_CAJA"]   : "";
        $contenido   = isset($caja["CONTENIDO"]) && is_array($caja["CONTENIDO"]) ? $caja["CONTENIDO"] : array();
        $nc          = count($contenido);
        for($j = 0; $j < $nc; $j++)
            {
            $linea = $contenido[$j];
            $filas[] = array(
                "NUMERO_CAJA"     => $numero_caja,
                "TIPO_CAJA"       => $tipo_caja,
                "PRODUCTO"        => isset($linea["PRODUCTO"])        ? $linea["PRODUCTO"]        : "",
                "VARIEDAD"        => isset($linea["VARIEDAD"])        ? $linea["VARIEDAD"]        : "",
                "LARGO"           => isset($linea["LARGO"])           ? $linea["LARGO"]           : "",
                "GRADO"           => isset($linea["GRADO"])           ? $linea["GRADO"]           : "",
                "TALLOS_POR_RAMO" => isset($linea["TALLOS_POR_RAMO"]) ? $linea["TALLOS_POR_RAMO"] : "",
                "RAMOS"           => isset($linea["RAMOS"])           ? $linea["RAMOS"]           : "",
                "TALLOS_TOTAL"    => isset($linea["TALLOS_TOTAL"])    ? $linea["TALLOS_TOTAL"]    : "",
                "PRECIO_UNITARIO" => isset($linea["PRECIO_UNITARIO"]) ? $linea["PRECIO_UNITARIO"] : "",
                "PRECIO_TOTAL"    => isset($linea["PRECIO_TOTAL"])    ? $linea["PRECIO_TOTAL"]    : "",
                "ALERTA"          => isset($linea["ALERTA"])          ? $linea["ALERTA"]          : ""
                );
            }
        }

    echo '<h3>Tabla de lineas extraidas ('.count($filas).' lineas en '.$total_cajas.' cajas)</h3>';

    if(empty($filas))
        echo '<p>No se extrajo ninguna linea.</p>';
    else
        {
        echo '<table class="cajas">';
        echo '<tr>';
        echo '<th>#</th><th>CAJA</th><th>TIPO</th><th>PROD</th><th>VARIEDAD</th><th>LARGO</th><th>GRADO</th><th>STxB</th><th>RAMOS</th><th>TALLOS</th><th>P.UNIT</th><th>P.TOTAL</th><th>ALERTA</th>';
        echo '</tr>';

        $nf = count($filas);
        for($i = 0; $i < $nf; $i++)
            {
            $f      = $filas[$i];
            $alerta = (string)$f["ALERTA"];
            $clase  = ($alerta != "") ? ' class="alerta"' : '';

            echo '<tr'.$clase.'>';
            echo '<td class="num">'.($i + 1).'</td>';
            echo '<td>'.htmlspecialchars((string)$f["NUMERO_CAJA"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$f["TIPO_CAJA"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$f["PRODUCTO"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$f["VARIEDAD"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["LARGO"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars((string)$f["GRADO"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["TALLOS_POR_RAMO"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["RAMOS"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["TALLOS_TOTAL"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["PRECIO_UNITARIO"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td class="num">'.htmlspecialchars((string)$f["PRECIO_TOTAL"], ENT_QUOTES, 'UTF-8').'</td>';
            echo '<td>'.htmlspecialchars($alerta, ENT_QUOTES, 'UTF-8').'</td>';
            echo '</tr>';
            }
        echo '</table>';
        }
    }
else if(!$haiku_falla)
    {
    echo '<p class="err">No se obtuvo extraccion valida (sin errores HTTP).</p>';
    }

// ----------------------------------------------------------------------------
// COSTO TOTAL Y ARCHIVOS
// ----------------------------------------------------------------------------
$costo_total = $costo_glm + $costo_haiku;

echo '<div class="costo-total">COSTO TOTAL: ~$'.number_format($costo_total, 6).' USD ';
echo '(GLM-OCR ~$'.number_format($costo_glm, 6).' + Haiku ~$'.number_format($costo_haiku, 6).')</div>';

echo '<h3>Archivos guardados</h3>';
echo '<div class="meta">';
echo '<b>GLM-OCR respuesta cruda:</b> '.htmlspecialchars($archivo_glm_resp, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>GLM-OCR md_results:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Haiku respuesta cruda:</b> '.htmlspecialchars($archivo_haiku, ENT_QUOTES, 'UTF-8');
echo '</div>';

echo '</body></html>';
