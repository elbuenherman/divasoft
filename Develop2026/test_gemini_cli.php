<?php

// ============================================================================
//  test_gemini_cli.php
//  Prueba CLI de Gemini 2.5 Pro de Google sobre facturas de flores.
//  Gemini soporta PDF directo (sin convertir a imagen). Una sola llamada.
// 
//  Uso: php test_gemini_cli.php <codigo>
//  Ejemplo: php test_gemini_cli.php 47
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

// API key Gemini.
$ruta_gemini = "/home/u154-6g3keph3vtcn/credenciales_gemini/api_key.txt";
if(!file_exists($ruta_gemini))
    die("No existe el archivo de API key Gemini: ".$ruta_gemini."\n");
$GEMINI_API_KEY = trim((string)file_get_contents($ruta_gemini));
if($GEMINI_API_KEY == "")
    die("API key Gemini vacia\n");

$fecha_corrida = date("Ymd_His");

// Log dual: imprime por stdout y graba a archivo en /tmp.
$archivo_log = "/tmp/gemini_log_".$codigo."_".$fecha_corrida.".txt";
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
// Extrae JSON del texto devuelto por Gemini (limpia fences markdown).
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

log_dual("=== TEST GEMINI 2.5 PRO CLI ===\n");
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

// ----------------------------------------------------------------------------
// PASO 2: PDF A BASE64 (puro, sin prefijo data URI)
// ----------------------------------------------------------------------------
$pdf_base64 = base64_encode((string)$archivo);

log_dual("--- ADJUNTO ---\n");
log_dual("Nombre:       ".$nombrearchivo."\n");
log_dual("Mime:         ".$mimetype."\n");
log_dual("Tamano:       ".number_format($tamano_pdf)." bytes (".number_format($tamano_pdf / 1024, 1)." KB)\n");
log_dual("Base64 chars: ".number_format(strlen($pdf_base64))."\n\n");

// ----------------------------------------------------------------------------
// PROMPT (nowdoc - copiado LITERAL desde procesa_factura_test.php)
// ----------------------------------------------------------------------------
$prompt = <<<'PROMPT'
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

// ----------------------------------------------------------------------------
// PASO 3: CONSTRUIR PAYLOAD (Gemini 2.5 Pro - inline_data + text)
// ----------------------------------------------------------------------------
$payload = array(
    "contents" => array(
        array(
            "role"  => "user",
            "parts" => array(
                array(
                    "inline_data" => array(
                        "mime_type" => "application/pdf",
                        "data"      => $pdf_base64
                        )
                    ),
                array(
                    "text" => $prompt
                    )
                )
            )
        ),
    "generationConfig" => array(
        "temperature"     => 0,
        "maxOutputTokens" => 16000
        )
    );
$payload_json = json_encode($payload);

// ----------------------------------------------------------------------------
// PASO 4: POST a Gemini 2.5 Pro
//  Endpoint: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent
//  API key como query param ?key=...
// ----------------------------------------------------------------------------
log_dual("--- LLAMADA A GEMINI 2.5 PRO ---\n");
log_dual("Endpoint: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent\n");
log_dual("Modelo:   gemini-2.5-pro\n");
log_dual("Iniciando...\n");

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=".urlencode($GEMINI_API_KEY);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
$archivo_resp = "/tmp/gemini_".$codigo."_".$fecha_corrida.".json";
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
    log_dual("\nERROR: respuesta no es JSON valido (envoltorio Gemini)\n");
    log_dual("Primeros 1000 chars:\n".substr((string)$response, 0, 1000)."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

// ----------------------------------------------------------------------------
// USAGE / COSTO (Gemini 2.5 Pro: $1.25/M input, $10.00/M output)
// ----------------------------------------------------------------------------
$input_t  = isset($data["usageMetadata"]["promptTokenCount"])     ? (int)$data["usageMetadata"]["promptTokenCount"]     : 0;
$output_t = isset($data["usageMetadata"]["candidatesTokenCount"]) ? (int)$data["usageMetadata"]["candidatesTokenCount"] : 0;
$total_t  = isset($data["usageMetadata"]["totalTokenCount"])      ? (int)$data["usageMetadata"]["totalTokenCount"]      : ($input_t + $output_t);
$costo    = ($input_t / 1000000.0) * 1.25
          + ($output_t / 1000000.0) * 10.00;

log_dual("\n--- USAGE ---\n");
log_dual("prompt_tokens:     ".number_format($input_t)."\n");
log_dual("output_tokens:     ".number_format($output_t)."\n");
log_dual("total_tokens:      ".number_format($total_t)."\n");
log_dual("Costo:             ~$".number_format($costo, 6)." USD (input $1.25/M, output $10.00/M)\n");

// ----------------------------------------------------------------------------
// PASO 6: PARSEAR contenido (candidates[0].content.parts[0].text)
// ----------------------------------------------------------------------------
$texto = "";
if(isset($data["candidates"][0]["content"]["parts"][0]["text"]))
    $texto = (string)$data["candidates"][0]["content"]["parts"][0]["text"];

$json_str    = extraer_json($texto);
$extraccion  = json_decode($json_str, true);

log_dual("\n--- RESULTADO ---\n");

if($extraccion === null)
    {
    log_dual("JSON invalido. Texto crudo devuelto por Gemini:\n");
    log_dual($texto."\n");
    }
else
    {
    log_dual(json_encode($extraccion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

    // Contar lineas en CAJAS[].CONTENIDO (estructura del prompt original).
    $total_lineas = 0;
    $total_cajas  = 0;
    if(isset($extraccion["CAJAS"]) && is_array($extraccion["CAJAS"]))
        {
        $total_cajas = count($extraccion["CAJAS"]);
        for($i = 0; $i < $total_cajas; $i++)
            {
            $caja = $extraccion["CAJAS"][$i];
            if(isset($caja["CONTENIDO"]) && is_array($caja["CONTENIDO"]))
                $total_lineas += count($caja["CONTENIDO"]);
            }
        }
    log_dual("\nCajas extraidas: ".$total_cajas."\n");
    log_dual("Lineas extraidas en CAJAS[].CONTENIDO: ".$total_lineas."\n");
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
