<?php

// ============================================================================
//  procesa_factura_test_cli.php
//  Version CLI del script de prueba de doble extraccion via Claude API.
//  Modelo: Sonnet 4.6 (sin prefill). Doble extraccion + comparacion.
//  Uso:  php procesa_factura_test_cli.php <codigo_adjunto>
// ============================================================================
   
set_time_limit(0);
ini_set("max_execution_time", "0");
// Solo por CLI: estos scripts viven en public_html (alcanzables por URL) y
// llaman a APIs de pago. Si se abren por web -> 403 y salir.
if(php_sapi_name() != "cli")
    {
    header("HTTP/1.1 403 Forbidden");
    echo "Este script solo se ejecuta por linea de comandos (CLI).";
    exit;
    }

ini_set("display_errors", "1");
error_reporting(E_ALL);
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");
include("funciones.php");
include("funciones_v2.php");

// Modelo a usar. Sonnet NO soporta prefill del assistant; Haiku si. La logica se ajusta sola.
$modelo_actual    = "claude-haiku-4-5-20251001";
$es_sonnet_actual = stripos($modelo_actual, "sonnet") !== false;


// Limpia la respuesta de Claude: quita fences markdown y recorta desde el primer { al ultimo }.
function extraer_json($respuesta)
    {
    $r = trim($respuesta);
    $r = preg_replace("/^```(?:json)?\\s*/i", "", $r);
    $r = preg_replace("/\\s*```\\s*$/", "", $r);
    $i = strpos($r, "{");
    $j = strrpos($r, "}");
    if($i !== false && $j !== false && $j > $i)
        {
        $r = substr($r, $i, $j - $i + 1);
        }
    return $r;
    }


// Imprime en pantalla y graba al mismo tiempo en el archivo de log si existe.
function log_dual($texto)
    {
    global $fh_log;
    echo $texto;
    if($fh_log)
        fwrite($fh_log, $texto);
    }


// Llama a la API de Claude con un PDF + prompt. Retorna array con ok/http/usage/texto/error.
function llamar_api_claude($api_key, $pdf_base64, $prompt, $temperature, $usar_cache)
    {
    // Orden importante: el prompt (estable, se cachea) PRIMERO con cache_control,
    // el PDF (cambia por factura) DESPUES sin cache_control.
    $text_block = array(
        "type" => "text",
        "text" => $prompt
        );
    if($usar_cache)
        $text_block["cache_control"] = array("type" => "ephemeral");

    $document_block = array(
        "type"   => "document",
        "source" => array(
            "type"       => "base64",
            "media_type" => "application/pdf",
            "data"       => $pdf_base64
            )
        );

    global $modelo_actual;
    $es_sonnet = stripos($modelo_actual, "sonnet") !== false;

    $messages = array(
        array(
            "role"    => "user",
            "content" => array(
                $text_block,
                $document_block
                )
            )
        );

    // Solo agregar prefill del assistant si el modelo lo soporta (no Sonnet).
    if(!$es_sonnet)
        {
        $messages[] = array(
            "role"    => "assistant",
            "content" => "{"
            );
        }

    $body = array(
        "model"       => $modelo_actual,
        "max_tokens"  => 16000,
        "temperature" => $temperature,
        "messages"    => $messages
        );
    $body_json = json_encode($body);

    $headers = array(
        "x-api-key: ".$api_key,
        "anthropic-version: 2023-06-01",
        "content-type: application/json"
        );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body_json);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if($curl_err != "")
        return array("ok" => false, "http_code" => $http_code, "respuesta_cruda" => "", "usage" => array(), "texto" => "", "error" => $curl_err);
    if($http_code != 200)
        return array("ok" => false, "http_code" => $http_code, "respuesta_cruda" => (string)$response, "usage" => array(), "texto" => "", "error" => "HTTP ".$http_code);

    $data  = json_decode((string)$response, true);
    $usage = isset($data["usage"]) ? $data["usage"] : array();
    $texto = isset($data["content"][0]["text"]) ? (string)$data["content"][0]["text"] : "";
    return array(
        "ok"              => true,
        "http_code"       => $http_code,
        "respuesta_cruda" => (string)$response,
        "usage"           => $usage,
        "texto"           => $texto,
        "error"           => null
        );
    }


function calcula_costo_sonnet($usage)
    {
    // Precios aproximados Haiku 4.5 (USD / 1M tok): 1.00 / 5.00 / 0.10 / 1.25.
    $in  = isset($usage["input_tokens"]) ? (int)$usage["input_tokens"] : 0;
    $out = isset($usage["output_tokens"]) ? (int)$usage["output_tokens"] : 0;
    $cc  = isset($usage["cache_creation_input_tokens"]) ? (int)$usage["cache_creation_input_tokens"] : 0;
    $cr  = isset($usage["cache_read_input_tokens"]) ? (int)$usage["cache_read_input_tokens"] : 0;
    return ($in / 1000000.0) * 1.00
         + ($out / 1000000.0) * 5.00
         + ($cr / 1000000.0) * 0.10
         + ($cc / 1000000.0) * 1.25;
    }


// Validar argumento CLI.
$codigo = isset($argv[1]) ? (int)$argv[1] : 0;
if($codigo <= 0)
    {
    echo "Uso: php procesa_factura_test_cli.php <codigo_adjunto>\n";
    exit(1);
    }

// Abrir archivo de log en /tmp para guardar la salida en paralelo a la pantalla.
$archivo_log = "/tmp/resultado_factura_".$codigo."_".date("Ymd_His").".txt";
$fh_log      = fopen($archivo_log, "w");

// Leer el adjunto.
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    log_dual("No existe el adjunto con codigo ".$codigo."\n");
    exit(1);
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$ext_arch = strtolower(pathinfo((string)$nombrearchivo, PATHINFO_EXTENSION));
$es_pdf   = (stripos((string)$mimetype, 'pdf') !== false || $ext_arch == 'pdf');
if(!$es_pdf)
    {
    log_dual("El adjunto no es PDF\n");
    exit(1);
    }

// Leer API key.
$ruta_api_key = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";
if(!file_exists($ruta_api_key))
    {
    log_dual("No se encontro el archivo de API key: ".$ruta_api_key."\n");
    exit(1);
    }
$api_key = trim((string)file_get_contents($ruta_api_key));
if($api_key == "")
    {
    log_dual("API key vacia\n");
    exit(1);
    }

// Prompt de extraccion (nowdoc - mismo que la version web).
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


$tamano_pdf = strlen((string)$archivo);
$pdf_base64 = base64_encode((string)$archivo);

log_dual("=== PROCESA FACTURA CLI ===\n");
log_dual("Modelo: ".$modelo_actual."\n");
log_dual("Codigo adjunto: ".$codigo."\n");
log_dual("Inicio: ".date("Y-m-d H:i:s")."\n\n");

log_dual("--- ADJUNTO ---\n");
log_dual("Nombre: ".$nombrearchivo."\n");
log_dual("Mime: ".$mimetype."\n");
log_dual("Tamano: ".number_format($tamano_pdf)." bytes\n\n");

$t_inicio = microtime(true);

log_dual("--- LLAMADA 1 (temp=0) ---\n");
log_dual("Iniciando...\n");
$t1_ini = microtime(true);
$resp1  = llamar_api_claude($api_key, $pdf_base64, $prompt, 0, true);
$t1     = round(microtime(true) - $t1_ini, 2);
log_dual("Duracion: ".$t1." s\n");
log_dual("HTTP: ".$resp1["http_code"]."\n");
if(!$resp1["ok"])
    {
    log_dual("ERROR: ".$resp1["error"]."\n");
    if($resp1["respuesta_cruda"] != "")
        log_dual("Respuesta cruda:\n".$resp1["respuesta_cruda"]."\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }
log_dual("Tokens input: ".(isset($resp1["usage"]["input_tokens"]) ? $resp1["usage"]["input_tokens"] : 0)."\n");
log_dual("Tokens output: ".(isset($resp1["usage"]["output_tokens"]) ? $resp1["usage"]["output_tokens"] : 0)."\n");
log_dual("Cache_creation: ".(isset($resp1["usage"]["cache_creation_input_tokens"]) ? $resp1["usage"]["cache_creation_input_tokens"] : 0)."\n");
log_dual("Cache_read: ".(isset($resp1["usage"]["cache_read_input_tokens"]) ? $resp1["usage"]["cache_read_input_tokens"] : 0)."\n");

log_dual("\n--- LLAMADA 2 (temp=0.3) ---\n");
log_dual("Iniciando...\n");
$t2_ini = microtime(true);
$resp2  = llamar_api_claude($api_key, $pdf_base64, $prompt, 0.3, true);
$t2     = round(microtime(true) - $t2_ini, 2);
log_dual("Duracion: ".$t2." s\n");
log_dual("HTTP: ".$resp2["http_code"]."\n");
if(!$resp2["ok"])
    {
    log_dual("ERROR: ".$resp2["error"]."\n");
    if($resp2["respuesta_cruda"] != "")
        log_dual("Respuesta cruda:\n".$resp2["respuesta_cruda"]."\n");
    if($fh_log) fclose($fh_log);
    echo "\nSalida guardada en: ".$archivo_log."\n";
    exit(1);
    }
log_dual("Tokens input: ".(isset($resp2["usage"]["input_tokens"]) ? $resp2["usage"]["input_tokens"] : 0)."\n");
log_dual("Tokens output: ".(isset($resp2["usage"]["output_tokens"]) ? $resp2["usage"]["output_tokens"] : 0)."\n");
log_dual("Cache_creation: ".(isset($resp2["usage"]["cache_creation_input_tokens"]) ? $resp2["usage"]["cache_creation_input_tokens"] : 0)."\n");
log_dual("Cache_read: ".(isset($resp2["usage"]["cache_read_input_tokens"]) ? $resp2["usage"]["cache_read_input_tokens"] : 0)."\n");

// Procesar respuestas (prefijo "{" condicional).
if($es_sonnet_actual)
    $texto1 = $resp1["texto"];
else
    $texto1 = "{" . $resp1["texto"];
$texto1 = extraer_json($texto1);
$json1  = json_decode($texto1, true);
if(is_array($json1))
    limpia_json_decimales($json1);

if($es_sonnet_actual)
    $texto2 = $resp2["texto"];
else
    $texto2 = "{" . $resp2["texto"];
$texto2 = extraer_json($texto2);
$json2  = json_decode($texto2, true);
if(is_array($json2))
    limpia_json_decimales($json2);

// Comparar.
if(is_array($json1) && is_array($json2))
    $discrepancias = compara_extracciones($json1, $json2);
else
    $discrepancias = array("Una de las respuestas no es JSON valido");

$estado = empty($discrepancias) ? 3 : 4;

log_dual("\n--- COMPARACION ---\n");
log_dual("Estado: ".$estado." (".($estado == 3 ? "OK coinciden" : "REVISAR discrepan").")\n");
log_dual("Discrepancias: ".count($discrepancias)."\n");
foreach($discrepancias as $d)
    log_dual("  - ".$d."\n");

// Costo.
$costo1      = calcula_costo_sonnet($resp1["usage"]);
$costo2      = calcula_costo_sonnet($resp2["usage"]);
$costo_total = $costo1 + $costo2;

log_dual("\n--- COSTO (Haiku 4.5: 1.00/5.00/0.10/1.25 por 1M tok) ---\n");
log_dual("Llamada 1: ~$".number_format($costo1, 6)." USD\n");
log_dual("Llamada 2: ~$".number_format($costo2, 6)." USD\n");
log_dual("Total:     ~$".number_format($costo_total, 6)." USD\n");

log_dual("\n--- JSON EXTRACCION 1 (temp=0) ---\n");
if(is_array($json1))
    log_dual(json_encode($json1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    log_dual("[Respuesta 1 no es JSON valido]\n".$texto1);

log_dual("\n\n--- JSON EXTRACCION 2 (temp=0.3) ---\n");
if(is_array($json2))
    log_dual(json_encode($json2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
else
    log_dual("[Respuesta 2 no es JSON valido]\n".$texto2);

$tiempo_total = round(microtime(true) - $t_inicio, 2);

log_dual("\n\n=== FIN ===\n");
log_dual("Tiempo total: ".$tiempo_total." segundos\n");

if($fh_log) fclose($fh_log);
echo "\nSalida guardada en: ".$archivo_log."\n";
