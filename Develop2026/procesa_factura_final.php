<?php 
  
// ============================================================================
//  procesa_factura_final.php
//  Motor de extraccion definitivo con fallback automatico.   
//
//  Flujo:
//   1) Haiku doble sobre PDF directo (igual a procesa_factura_test.php).
//   2) Si mas del 20% de las lineas en CAJAS[].CONTENIDO[] tienen LARGO=null,
//      dispara fallback: GLM-OCR -> Haiku formateador.
//   3) Combina CABECERA + LOGISTICA + RESUMEN_EMPAQUE de Haiku doble con
//      CAJAS del fallback (cuando se dispara).
//   4) Devuelve JSON definitivo con campo METADATOS.TIPO_EXTRACCION.
//
//  Uso: procesa_factura_final.php?codigo=10
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(300);
ini_set("max_execution_time", "300");
ini_set("memory_limit", "512M");
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");
include("funciones_v2.php");  // compara_extracciones, limpia_json_decimales

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;

// API keys.
$ruta_anthropic = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";
$ruta_zai       = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";

if(!file_exists($ruta_anthropic))
    die("No existe el archivo de API key Anthropic: ".htmlspecialchars($ruta_anthropic, ENT_QUOTES, 'UTF-8'));
$ANTHROPIC_API_KEY = trim((string)file_get_contents($ruta_anthropic));
if($ANTHROPIC_API_KEY == "")
    die("API key Anthropic vacia");

if(!file_exists($ruta_zai))
    die("No existe el archivo de API key Z.AI: ".htmlspecialchars($ruta_zai, ENT_QUOTES, 'UTF-8'));
$ZAI_API_KEY = trim((string)file_get_contents($ruta_zai));
if($ZAI_API_KEY == "")
    die("API key Z.AI vacia");

$fecha_corrida = date("Ymd_His");

// Umbral disparador del fallback OCR.
$UMBRAL_PORCENTAJE_NULL = 20.0;

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
// Cuenta cuantas lineas en CAJAS[].CONTENIDO[] tienen LARGO=null y total.
// ----------------------------------------------------------------------------
function cuenta_largos_null($json)
    {
    $total = 0;
    $nulls = 0;
    if(isset($json["CAJAS"]) && is_array($json["CAJAS"]))
        {
        $nc = count($json["CAJAS"]);
        for($i = 0; $i < $nc; $i++)
            {
            $caja = $json["CAJAS"][$i];
            if(!isset($caja["CONTENIDO"]) || !is_array($caja["CONTENIDO"]))
                continue;
            $nl = count($caja["CONTENIDO"]);
            for($j = 0; $j < $nl; $j++)
                {
                $linea = $caja["CONTENIDO"][$j];
                $total++;
                $largo = isset($linea["LARGO"]) ? $linea["LARGO"] : null;
                if($largo === null || $largo === "" || $largo === "null")
                    $nulls++;
                }
            }
        }
    $pct = ($total > 0) ? (100.0 * $nulls / $total) : 0.0;
    return array(
        "total_lineas"    => $total,
        "lineas_null"     => $nulls,
        "porcentaje_null" => $pct
        );
    }

// ----------------------------------------------------------------------------
// PROMPT GRANDE (nowdoc - copiado LITERAL desde procesa_factura_test.php).
// ----------------------------------------------------------------------------
$PROMPT_GRANDE = <<<'PROMPT'
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
// Llama a Haiku con PDF directo (un solo bloque document + bloque text con
// cache_control + prefill assistant "{").
// ----------------------------------------------------------------------------
function _haiku_pdf($api_key, $pdf_base64, $prompt, $temperature)
    {
    $text_block = array(
        "type"          => "text",
        "text"          => $prompt,
        "cache_control" => array("type" => "ephemeral")
        );

    $document_block = array(
        "type"   => "document",
        "source" => array(
            "type"       => "base64",
            "media_type" => "application/pdf",
            "data"       => $pdf_base64
            )
        );

    $messages = array(
        array(
            "role"    => "user",
            "content" => array($text_block, $document_block)
            ),
        array(
            "role"    => "assistant",
            "content" => "{"
            )
        );
 
    $body = array(
        "model"       => "claude-haiku-4-5-20251001",
        "max_tokens"  => 32000,
        "temperature" => $temperature,
        "messages"    => $messages
        );
    $body_json = json_encode($body);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "x-api-key: ".$api_key,
        "anthropic-version: 2023-06-01",
        "content-type: application/json"
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body_json);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if($curl_err != "")
        return array("ok"=>false, "http_code"=>$http_code, "respuesta_cruda"=>"", "usage"=>array(), "texto"=>"", "error"=>$curl_err);
    if($http_code != 200)
        return array("ok"=>false, "http_code"=>$http_code, "respuesta_cruda"=>(string)$response, "usage"=>array(), "texto"=>"", "error"=>"HTTP ".$http_code);

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

// ----------------------------------------------------------------------------
// ejecuta_haiku_doble - dos llamadas Haiku PDF directo, temperatura 0 y 0.3.
// ----------------------------------------------------------------------------
function ejecuta_haiku_doble($pdf_binario, $api_anthropic)
    {
    global $PROMPT_GRANDE, $UMBRAL_PORCENTAJE_NULL;

    $pdf_b64 = base64_encode((string)$pdf_binario);

    // Primera llamada (temp=0).
    $t1_inicio = microtime(true);
    $r1        = _haiku_pdf($api_anthropic, $pdf_b64, $PROMPT_GRANDE, 0.0);
    $tiempo_1  = round(microtime(true) - $t1_inicio, 2);

    // Si la primera fue exitosa, parsearla y decidir si vale la pena la segunda.
    $json1_pre      = null;
    $texto1_pre     = "";
    $omitir_segunda = false;
    if($r1["ok"])
        {
        $texto1_pre = "{" . $r1["texto"];
        $texto1_pre = extraer_json($texto1_pre);
        $json1_pre  = json_decode($texto1_pre, true);
        if(is_array($json1_pre))
            {
            limpia_json_decimales($json1_pre);
            $conteo_1 = cuenta_largos_null($json1_pre);
            if($conteo_1["porcentaje_null"] > $UMBRAL_PORCENTAJE_NULL)
                $omitir_segunda = true;
            }
        }

    // Atajo: la primera ya delata >20% nulls -> no gastar tokens en la segunda.
    if($omitir_segunda)
        {
        $in1 = isset($r1["usage"]["input_tokens"])               ? (int)$r1["usage"]["input_tokens"]               : 0;
        $ou1 = isset($r1["usage"]["output_tokens"])              ? (int)$r1["usage"]["output_tokens"]              : 0;
        $cc1 = isset($r1["usage"]["cache_creation_input_tokens"])? (int)$r1["usage"]["cache_creation_input_tokens"]: 0;
        $cr1 = isset($r1["usage"]["cache_read_input_tokens"])    ? (int)$r1["usage"]["cache_read_input_tokens"]    : 0;
        $costo_1 = ($in1 / 1000000.0) * 1.00
                 + ($ou1 / 1000000.0) * 5.00
                 + ($cr1 / 1000000.0) * 0.10
                 + ($cc1 / 1000000.0) * 1.25;

        return array(
            "ok"                      => true,
            "json_1"                  => $json1_pre,
            "json_2"                  => null,
            "texto_1"                 => $texto1_pre,
            "texto_2"                 => null,
            "estado"                  => 4,
            "discrepancias"           => array("Segunda llamada Haiku omitida porque la primera detecto >".number_format($UMBRAL_PORCENTAJE_NULL, 0)."% largos null (probablemente grid posicional). Se dispara fallback OCR."),
            "tiempo"                  => $tiempo_1,
            "tokens_input"            => $in1,
            "tokens_output"           => $ou1,
            "tokens_cache_read"       => $cr1,
            "tokens_cache_creat"      => $cc1,
            "costo"                   => $costo_1,
            "respuesta1_cruda"        => $r1["respuesta_cruda"],
            "respuesta2_cruda"        => null,
            "omitida_segunda_llamada" => true
            );
        }

    // Segunda llamada (temp=0.3).
    $t2_inicio = microtime(true);
    $r2        = _haiku_pdf($api_anthropic, $pdf_b64, $PROMPT_GRANDE, 0.3);
    $tiempo_2  = round(microtime(true) - $t2_inicio, 2);

    $tiempo = round($tiempo_1 + $tiempo_2, 2);

    if(!$r1["ok"] && !$r2["ok"])
        return array(
            "ok"     => false,
            "error"  => "Ambas llamadas Haiku fallaron. R1: ".$r1["error"]." | R2: ".$r2["error"],
            "r1"     => $r1,
            "r2"     => $r2,
            "tiempo" => $tiempo
            );

    if(!$r1["ok"] || !$r2["ok"])
        return array(
            "ok"     => false,
            "error"  => "Una llamada Haiku fallo. R1 ok=".($r1["ok"]?"si":"no")." R2 ok=".($r2["ok"]?"si":"no"),
            "r1"     => $r1,
            "r2"     => $r2,
            "tiempo" => $tiempo
            );

    // Reusar json1 parseado arriba si ya esta listo; sino re-parsear.
    if($json1_pre !== null && is_array($json1_pre))
        {
        $texto1 = $texto1_pre;
        $json1  = $json1_pre;
        }
    else
        {
        $texto1 = "{" . $r1["texto"];
        $texto1 = extraer_json($texto1);
        $json1  = json_decode($texto1, true);
        if(is_array($json1))
            limpia_json_decimales($json1);
        }

    $texto2 = "{" . $r2["texto"];
    $texto2 = extraer_json($texto2);
    $json2  = json_decode($texto2, true);
    if(is_array($json2))
        limpia_json_decimales($json2);

    if(is_array($json1) && is_array($json2))
        $discrepancias = compara_extracciones($json1, $json2);
    else
        $discrepancias = array("Una de las respuestas no es JSON valido");

    $estado = empty($discrepancias) ? 3 : 4;

    // Tokens y costo (Haiku 4.5: input $1.00/M, output $5.00/M,
    // cache_read $0.10/M, cache_creation $1.25/M).
    $in1 = isset($r1["usage"]["input_tokens"])               ? (int)$r1["usage"]["input_tokens"]               : 0;
    $ou1 = isset($r1["usage"]["output_tokens"])              ? (int)$r1["usage"]["output_tokens"]              : 0;
    $cc1 = isset($r1["usage"]["cache_creation_input_tokens"])? (int)$r1["usage"]["cache_creation_input_tokens"]: 0;
    $cr1 = isset($r1["usage"]["cache_read_input_tokens"])    ? (int)$r1["usage"]["cache_read_input_tokens"]    : 0;
    $in2 = isset($r2["usage"]["input_tokens"])               ? (int)$r2["usage"]["input_tokens"]               : 0;
    $ou2 = isset($r2["usage"]["output_tokens"])              ? (int)$r2["usage"]["output_tokens"]              : 0;
    $cc2 = isset($r2["usage"]["cache_creation_input_tokens"])? (int)$r2["usage"]["cache_creation_input_tokens"]: 0;
    $cr2 = isset($r2["usage"]["cache_read_input_tokens"])    ? (int)$r2["usage"]["cache_read_input_tokens"]    : 0;

    $costo = ($in1 / 1000000.0) * 1.00
           + ($ou1 / 1000000.0) * 5.00
           + ($cr1 / 1000000.0) * 0.10
           + ($cc1 / 1000000.0) * 1.25
           + ($in2 / 1000000.0) * 1.00
           + ($ou2 / 1000000.0) * 5.00
           + ($cr2 / 1000000.0) * 0.10
           + ($cc2 / 1000000.0) * 1.25;

    return array(
        "ok"                      => true,
        "json_1"                  => $json1,
        "json_2"                  => $json2,
        "texto_1"                 => $texto1,
        "texto_2"                 => $texto2,
        "estado"                  => $estado,
        "discrepancias"           => $discrepancias,
        "tiempo"                  => $tiempo,
        "tokens_input"            => $in1 + $in2,
        "tokens_output"           => $ou1 + $ou2,
        "tokens_cache_read"       => $cr1 + $cr2,
        "tokens_cache_creat"      => $cc1 + $cc2,
        "costo"                   => $costo,
        "respuesta1_cruda"        => $r1["respuesta_cruda"],
        "respuesta2_cruda"        => $r2["respuesta_cruda"],
        "omitida_segunda_llamada" => false
        );
    }

// ----------------------------------------------------------------------------
// ejecuta_ocr_fallback - GLM-OCR -> Haiku formateador (markdown).
// ----------------------------------------------------------------------------
function ejecuta_ocr_fallback($pdf_binario, $api_zai, $api_anthropic)
    {
    global $PROMPT_GRANDE;

    $pdf_b64  = base64_encode((string)$pdf_binario);
    $data_uri = "data:application/pdf;base64,".$pdf_b64;

    // --- PASO 1: GLM-OCR -----------------------------------------------------
    $body_glm      = array("model" => "glm-ocr", "file" => $data_uri);
    $body_glm_json = json_encode($body_glm);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/layout_parsing");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer ".$api_zai,
        "Content-Type: application/json"
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body_glm_json);

    $t_inicio_glm = microtime(true);
    $resp_glm     = curl_exec($ch);
    $http_glm     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err_glm      = curl_error($ch);
    $tiempo_glm   = round(microtime(true) - $t_inicio_glm, 2);
    curl_close($ch);

    if($err_glm != "" || $http_glm != 200)
        return array(
            "ok"               => false,
            "error"            => "GLM-OCR fallo (HTTP ".$http_glm.($err_glm != "" ? " / cURL: ".$err_glm : "").")",
            "tiempo_glm"       => $tiempo_glm,
            "respuesta_glm"    => (string)$resp_glm
            );

    $data_glm = json_decode((string)$resp_glm, true);
    if($data_glm === null)
        return array(
            "ok"            => false,
            "error"         => "Respuesta GLM-OCR no es JSON valido",
            "tiempo_glm"    => $tiempo_glm,
            "respuesta_glm" => (string)$resp_glm
            );

    $md_results = isset($data_glm["md_results"]) ? (string)$data_glm["md_results"] : "";
    if(strlen($md_results) == 0)
        return array(
            "ok"            => false,
            "error"         => "GLM-OCR devolvio md_results vacio",
            "tiempo_glm"    => $tiempo_glm,
            "respuesta_glm" => (string)$resp_glm
            );

    $glm_usage     = isset($data_glm["usage"]) ? $data_glm["usage"] : array();
    $glm_total_t   = isset($glm_usage["total_tokens"]) ? (int)$glm_usage["total_tokens"] : 0;
    $costo_glm     = ($glm_total_t / 1000000.0) * 0.03;

    // --- PASO 2: Sonnet formateador (markdown -> JSON) ----------------------
    $encabezado = "Te paso a continuacion el contenido en markdown de una factura de flores extraido por OCR. Tu tarea es interpretarlo y extraer la informacion en el JSON estructurado segun las reglas que te doy.\n\n"
                . "MARKDOWN DE LA FACTURA:\n"
                . $md_results . "\n\n"
                . "FIN DEL MARKDOWN.\n\n"
                . "Ahora aplica estas reglas:\n\n";

    $prompt_sonnet = $encabezado.$PROMPT_GRANDE;

    $payload_sonnet = array(
        "model"       => "claude-sonnet-4-5-20250929",
        "max_tokens"  => 24000,
        "temperature" => 0,
        "messages"    => array(
            array(
                "role"    => "user",
                "content" => $prompt_sonnet
                )
            )
        );
    $payload_sonnet_json = json_encode($payload_sonnet);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "x-api-key: ".$api_anthropic,
        "anthropic-version: 2023-06-01",
        "content-type: application/json"
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_sonnet_json);

    $t_inicio_sonnet = microtime(true);
    $resp_sonnet     = curl_exec($ch);
    $http_sonnet     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err_sonnet      = curl_error($ch);
    $tiempo_sonnet   = round(microtime(true) - $t_inicio_sonnet, 2);
    curl_close($ch);

    if($err_sonnet != "" || $http_sonnet != 200)
        return array(
            "ok"             => false,
            "error"          => "Sonnet formateador fallo (HTTP ".$http_sonnet.($err_sonnet != "" ? " / cURL: ".$err_sonnet : "").")",
            "tiempo_glm"     => $tiempo_glm,
            "tiempo_sonnet"  => $tiempo_sonnet,
            "respuesta_glm"  => (string)$resp_glm,
            "respuesta_sonnet" => (string)$resp_sonnet,
            "md_results"     => $md_results
            );

    $data_sonnet = json_decode((string)$resp_sonnet, true);
    if($data_sonnet === null)
        return array(
            "ok"               => false,
            "error"            => "Respuesta Sonnet formateador no es JSON valido (envoltorio)",
            "tiempo_glm"       => $tiempo_glm,
            "tiempo_sonnet"    => $tiempo_sonnet,
            "respuesta_sonnet" => (string)$resp_sonnet,
            "md_results"       => $md_results
            );

    $texto_sonnet = isset($data_sonnet["content"][0]["text"]) ? (string)$data_sonnet["content"][0]["text"] : "";
    $json_str     = extraer_json($texto_sonnet);
    $extraccion   = json_decode($json_str, true);

    if(!is_array($extraccion))
        return array(
            "ok"               => false,
            "error"            => "Sonnet formateador devolvio JSON invalido",
            "tiempo_glm"       => $tiempo_glm,
            "tiempo_sonnet"    => $tiempo_sonnet,
            "respuesta_sonnet" => (string)$resp_sonnet,
            "texto_sonnet"     => $texto_sonnet,
            "md_results"       => $md_results
            );

    limpia_json_decimales($extraccion);

    $s_in    = isset($data_sonnet["usage"]["input_tokens"])  ? (int)$data_sonnet["usage"]["input_tokens"]  : 0;
    $s_out   = isset($data_sonnet["usage"]["output_tokens"]) ? (int)$data_sonnet["usage"]["output_tokens"] : 0;
    $costo_s = ($s_in / 1000000.0) * 3.00 + ($s_out / 1000000.0) * 15.00;

    return array(
        "ok"              => true,
        "json"            => $extraccion,
        "tiempo_glm"      => $tiempo_glm,
        "tiempo_sonnet"   => $tiempo_sonnet,
        "tokens_glm"      => array(
            "prompt"     => isset($glm_usage["prompt_tokens"])     ? (int)$glm_usage["prompt_tokens"]     : 0,
            "completion" => isset($glm_usage["completion_tokens"]) ? (int)$glm_usage["completion_tokens"] : 0,
            "total"      => $glm_total_t
            ),
        "tokens_sonnet"   => array(
            "input"  => $s_in,
            "output" => $s_out
            ),
        "costo"           => $costo_glm + $costo_s,
        "costo_glm"       => $costo_glm,
        "costo_sonnet"    => $costo_s,
        "md_results"      => $md_results,
        "respuesta_glm"   => (string)$resp_glm,
        "respuesta_sonnet"=> (string)$resp_sonnet
        );
    }

// ----------------------------------------------------------------------------
// HTML HEADER
// ----------------------------------------------------------------------------
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Procesa factura final - codigo '.(int)$codigo.'</title>';
echo '<style>';
echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; padding: 20px; color: #222; }';
echo 'h1 { margin:0; font-size:22px; color:#222; }';
echo 'h2 { color: #88010e; margin-top: 24px; border-bottom: 1px solid #eee; padding-bottom: 4px; }';
echo 'h3 { color: #555; margin-top: 16px; font-size: 14px; }';
echo 'pre { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; overflow: auto; max-height: 60vh; font-size: 12px; }';
echo 'pre.def { background:#fff8e1; border:2px solid #d4a017; }';
echo '.meta { font-size: 13px; line-height: 1.7; }';
echo '.meta b { color: #555; }';
echo '.err { color: #88010e; font-weight: bold; }';
echo '.badge { display:inline-block; padding:6px 14px; border-radius:14px; font-weight:bold; font-size:14px; margin:10px 0; }';
echo '.badge-green { background:#e9f7ec; color:#2e7d32; border:2px solid #2e7d32; }';
echo '.badge-orange { background:#fff3e0; color:#cc5500; border:2px solid #cc5500; }';
echo '.badge-red { background:#fdecec; color:#88010e; border:2px solid #88010e; }';
echo 'table.cajas { border-collapse: collapse; font-size: 12px; margin: 8px 0; width: 100%; }';
echo 'table.cajas th, table.cajas td { border: 1px solid #ccc; padding: 4px 8px; vertical-align: top; }';
echo 'table.cajas th { background: #f0f0f0; color:#333; text-align:left; }';
echo 'table.cajas td.num { text-align: right; }';
echo 'table.cajas tr.alerta td { background: #fdecec; }';
echo '.costo-total { font-size: 16px; font-weight: bold; color:#88010e; margin-top:24px; }';
echo 'details > summary { cursor: pointer; font-weight: bold; color: #555; margin: 6px 0; }';
echo '.estado-ok { color:#2e7d32; font-weight:bold; font-size:14px; }';
echo '.estado-rev { color:#88010e; font-weight:bold; font-size:14px; }';
echo '</style></head><body>';

echo '<h1>Procesa factura final - codigo '.(int)$codigo.'</h1>';

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

// ----------------------------------------------------------------------------
// EJECUTAR HAIKU DOBLE
// ----------------------------------------------------------------------------
$t_total_inicio = microtime(true);
$resultado_haiku = ejecuta_haiku_doble($archivo, $ANTHROPIC_API_KEY);

// Guardar respuestas crudas Haiku.
if(isset($resultado_haiku["respuesta1_cruda"]))
    file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_haiku1.json", $resultado_haiku["respuesta1_cruda"]);
if(isset($resultado_haiku["respuesta2_cruda"]))
    file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_haiku2.json", $resultado_haiku["respuesta2_cruda"]);

if(!$resultado_haiku["ok"])
    {
    echo '<div class="badge badge-red">ERROR: Haiku doble fallo</div>';
    echo '<p class="err">'.htmlspecialchars((string)$resultado_haiku["error"], ENT_QUOTES, 'UTF-8').'</p>';
    if(isset($resultado_haiku["r1"]["respuesta_cruda"]) && $resultado_haiku["r1"]["respuesta_cruda"] != "")
        {
        echo '<h3>Respuesta cruda llamada 1</h3>';
        echo '<pre>'.htmlspecialchars((string)$resultado_haiku["r1"]["respuesta_cruda"], ENT_QUOTES, 'UTF-8').'</pre>';
        }
    if(isset($resultado_haiku["r2"]["respuesta_cruda"]) && $resultado_haiku["r2"]["respuesta_cruda"] != "")
        {
        echo '<h3>Respuesta cruda llamada 2</h3>';
        echo '<pre>'.htmlspecialchars((string)$resultado_haiku["r2"]["respuesta_cruda"], ENT_QUOTES, 'UTF-8').'</pre>';
        }
    echo '</body></html>';
    exit;
    }

$json_1 = is_array($resultado_haiku["json_1"]) ? $resultado_haiku["json_1"] : array();
$json_2 = is_array($resultado_haiku["json_2"]) ? $resultado_haiku["json_2"] : array();

// ----------------------------------------------------------------------------
// DECIDIR FALLBACK
// ----------------------------------------------------------------------------
$conteo = cuenta_largos_null($json_1);
$porcentaje_null = $conteo["porcentaje_null"];

// Segunda señal: lineas con ALERTA mencionando grid posicional o alineacion.
// Haiku a veces asigna largos por defecto (tipico 60) sin marcar null y solo
// deja la pista en la ALERTA por linea. Sin esta verificacion el fallback no
// se dispara y el JSON definitivo queda con largos incorrectos silenciosamente.
$lineas_alerta_grid      = 0;
$lineas_total_grid_check = 0;
if(isset($json_1["CAJAS"]) && is_array($json_1["CAJAS"]))
    {
    $nc_g = count($json_1["CAJAS"]);
    for($ig = 0; $ig < $nc_g; $ig++)
        {
        $caja_g = $json_1["CAJAS"][$ig];
        if(!isset($caja_g["CONTENIDO"]) || !is_array($caja_g["CONTENIDO"]))
            continue;
        $nl_g = count($caja_g["CONTENIDO"]);
        for($jg = 0; $jg < $nl_g; $jg++)
            {
            $linea_g = $caja_g["CONTENIDO"][$jg];
            $lineas_total_grid_check++;
            $alerta_g = isset($linea_g["ALERTA"]) ? $linea_g["ALERTA"] : null;
            if($alerta_g === null || $alerta_g === "")
                continue;
            $alerta_lower = mb_strtolower((string)$alerta_g, "UTF-8");
            if(strpos($alerta_lower, "grid posicional") !== false
               || strpos($alerta_lower, "alineacion") !== false
               || strpos($alerta_lower, "alineación") !== false)
                $lineas_alerta_grid++;
            }
        }
    }
$porcentaje_alerta_grid = ($lineas_total_grid_check > 0)
    ? (100.0 * $lineas_alerta_grid / $lineas_total_grid_check)
    : 0.0;

// Tercera señal: array global ALERTAS del json_1 con mencion de grid posicional
// o alineacion. Haiku a veces pone la pista solo aqui (no en cada linea).
$alerta_grid_global = false;
if(isset($json_1["ALERTAS"]) && is_array($json_1["ALERTAS"]))
    {
    $na_g = count($json_1["ALERTAS"]);
    for($ka = 0; $ka < $na_g; $ka++)
        {
        $entrada = $json_1["ALERTAS"][$ka];
        if(!is_string($entrada) || $entrada === "")
            continue;
        $entrada_lower = mb_strtolower($entrada, "UTF-8");
        if(strpos($entrada_lower, "grid posicional") !== false
           || strpos($entrada_lower, "alineacion") !== false
           || strpos($entrada_lower, "alineación") !== false)
            {
            $alerta_grid_global = true;
            break;
            }
        }
    }

$disparar_fallback = ($porcentaje_null        > $UMBRAL_PORCENTAJE_NULL
                   || $porcentaje_alerta_grid > $UMBRAL_PORCENTAJE_NULL
                   || $alerta_grid_global === true);

// Motivo del disparo (cual de las señales, una o varias).
$motivos_disparo = array();
if($porcentaje_null > $UMBRAL_PORCENTAJE_NULL)
    $motivos_disparo[] = "null=".number_format($porcentaje_null, 1)."%";
if($porcentaje_alerta_grid > $UMBRAL_PORCENTAJE_NULL)
    $motivos_disparo[] = "alerta_grid=".number_format($porcentaje_alerta_grid, 1)."%";
if($alerta_grid_global)
    $motivos_disparo[] = "alerta_grid_global=si";
$motivo_disparo = implode(" + ", $motivos_disparo);

$resultado_fallback   = null;
$fallback_fallo       = false;
$json_definitivo      = $json_1;
$tipo_extraccion      = "HAIKU_DOBLE";
$alerta_fallback      = "";

if($disparar_fallback)
    {
    $resultado_fallback = ejecuta_ocr_fallback($archivo, $ZAI_API_KEY, $ANTHROPIC_API_KEY);

    // Guardar respuestas crudas del fallback.
    if(isset($resultado_fallback["respuesta_glm"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_glm.json", $resultado_fallback["respuesta_glm"]);
    if(isset($resultado_fallback["respuesta_sonnet"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_fallback_sonnet.json", $resultado_fallback["respuesta_sonnet"]);
    if(isset($resultado_fallback["md_results"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_md.txt", $resultado_fallback["md_results"]);

    if($resultado_fallback["ok"])
        {
        // Reemplazar SOLO CAJAS del json_1 con CAJAS del fallback.
        $cajas_fb = isset($resultado_fallback["json"]["CAJAS"]) && is_array($resultado_fallback["json"]["CAJAS"])
                  ? $resultado_fallback["json"]["CAJAS"]
                  : array();
        $json_definitivo["CAJAS"] = $cajas_fb;
        $tipo_extraccion = "HAIKU_DOBLE+OCR_FALLBACK";
        }
    else
        {
        // Fallback fallo: usar solo Haiku doble, marcar alerta.
        $fallback_fallo   = true;
        $tipo_extraccion  = "HAIKU_DOBLE";
        $alerta_fallback  = "Fallback OCR disparado pero fallo: ".(isset($resultado_fallback["error"]) ? $resultado_fallback["error"] : "?");
        }
    }

// ----------------------------------------------------------------------------
// METADATOS Y COSTO TOTAL
// ----------------------------------------------------------------------------
$tiempo_total = round(microtime(true) - $t_total_inicio, 2);

$costo_haiku    = (float)$resultado_haiku["costo"];
$costo_fallback = ($resultado_fallback !== null && $resultado_fallback["ok"]) ? (float)$resultado_fallback["costo"] : 0.0;
$costo_total    = $costo_haiku + $costo_fallback;

$json_definitivo["METADATOS"] = array(
    "TIPO_EXTRACCION"                => $tipo_extraccion,
    "PORCENTAJE_LARGOS_NULL_INICIAL" => $porcentaje_null,
    "PORCENTAJE_ALERTA_GRID_INICIAL" => $porcentaje_alerta_grid,
    "ALERTA_GRID_GLOBAL_INICIAL"     => $alerta_grid_global ? "si" : "no",
    "TIEMPO_TOTAL_SEGUNDOS"          => $tiempo_total,
    "COSTO_TOTAL_USD"                => $costo_total,
    "FECHA_PROCESAMIENTO"            => date("Y-m-d H:i:s")
    );
if($alerta_fallback != "")
    $json_definitivo["METADATOS"]["ALERTA_FALLBACK"] = $alerta_fallback;

// Guardar JSON definitivo.
$ruta_def = "/tmp/final_".$codigo."_".$fecha_corrida."_definitivo.json";
file_put_contents($ruta_def, json_encode($json_definitivo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// ----------------------------------------------------------------------------
// RENDER HTML
// ----------------------------------------------------------------------------

// Badge segun tipo de extraccion.
if($tipo_extraccion == "HAIKU_DOBLE")
    echo '<div class="badge badge-green">Revision: Haiku doble (suficiente)</div>';
else
    echo '<div class="badge badge-orange">Revision: Haiku doble + OCR fallback (disparado por '.htmlspecialchars($motivo_disparo, ENT_QUOTES, 'UTF-8').', umbral '.number_format($UMBRAL_PORCENTAJE_NULL, 0).'%)</div>';

if($alerta_fallback != "")
    echo '<div class="badge badge-red">ALERTA: '.htmlspecialchars($alerta_fallback, ENT_QUOTES, 'UTF-8').'</div>';

// Adjunto.
echo '<h2>Adjunto</h2>';
echo '<div class="meta">';
echo '<b>CODIGO:</b> '.(int)$codigo.'<br>';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano_pdf).' bytes ('.number_format($tamano_pdf / 1024, 1).' KB)';
echo '</div>';

// Metadatos.
echo '<h2>Metadatos</h2>';
echo '<div class="meta">';
echo '<b>Tipo extraccion:</b> '.htmlspecialchars($tipo_extraccion, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Porcentaje largos null inicial (json_1):</b> '.number_format($porcentaje_null, 2).'% &nbsp; ';
echo '('.(int)$conteo["lineas_null"].' null / '.(int)$conteo["total_lineas"].' lineas)<br>';
echo '<b>Porcentaje alerta grid inicial (json_1):</b> '.number_format($porcentaje_alerta_grid, 2).'% &nbsp; ';
echo '('.(int)$lineas_alerta_grid.' / '.(int)$lineas_total_grid_check.' lineas con alerta grid/alineacion)<br>';
echo '<b>Alerta grid en array global ALERTAS:</b> '.($alerta_grid_global ? 'si' : 'no').'<br>';
echo '<b>Tiempo total:</b> '.$tiempo_total.' s<br>';
echo '<b>Costo total:</b> ~$'.number_format($costo_total, 6).' USD';
echo '</div>';

// Bloque Haiku doble.
echo '<h2>Haiku doble (PDF directo)</h2>';
echo '<div class="meta">';
echo '<b>Tiempo:</b> '.$resultado_haiku["tiempo"].' s<br>';
echo '<b>Tokens input:</b> '.number_format((int)$resultado_haiku["tokens_input"]).' &nbsp; ';
echo '<b>output:</b> '.number_format((int)$resultado_haiku["tokens_output"]).'<br>';
echo '<b>Tokens cache read:</b> '.number_format((int)$resultado_haiku["tokens_cache_read"]).' &nbsp; ';
echo '<b>cache creation:</b> '.number_format((int)$resultado_haiku["tokens_cache_creat"]).'<br>';
echo '<b>Costo:</b> ~$'.number_format($costo_haiku, 6).' USD<br>';
if((int)$resultado_haiku["estado"] == 3)
    echo '<b>Estado comparacion:</b> <span class="estado-ok">OK coinciden (estado=3)</span>';
else
    {
    echo '<b>Estado comparacion:</b> <span class="estado-rev">REVISAR difieren (estado=4)</span><br>';
    echo '<b>Discrepancias:</b> '.count($resultado_haiku["discrepancias"]);
    }
echo '</div>';

if((int)$resultado_haiku["estado"] == 4 && !empty($resultado_haiku["discrepancias"]))
    {
    echo '<details><summary>Mostrar/ocultar discrepancias ('.count($resultado_haiku["discrepancias"]).')</summary>';
    echo '<pre>';
    $nd = count($resultado_haiku["discrepancias"]);
    for($i = 0; $i < $nd; $i++)
        echo htmlspecialchars($resultado_haiku["discrepancias"][$i], ENT_QUOTES, 'UTF-8')."\n";
    echo '</pre>';
    echo '</details>';
    }

echo '<details><summary>Mostrar/ocultar JSON 1 (temp=0)</summary>';
if(is_array($json_1))
    echo '<pre>'.htmlspecialchars(json_encode($json_1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
else
    echo '<pre>'.htmlspecialchars((string)$resultado_haiku["texto_1"], ENT_QUOTES, 'UTF-8').'</pre>';
echo '</details>';

if(isset($resultado_haiku["omitida_segunda_llamada"]) && $resultado_haiku["omitida_segunda_llamada"])
    {
    echo '<div style="background:#fff3e0; border-left:4px solid #cc5500; padding:10px 14px; margin:10px 0; color:#704000; font-size:13px;">';
    echo '<b>Segunda llamada Haiku omitida:</b> la primera extraccion detecto &gt;'.number_format($UMBRAL_PORCENTAJE_NULL, 0).'% largos null (probable grid posicional). Se dispara fallback OCR directamente.';
    echo '</div>';
    }
else
    {
    echo '<details><summary>Mostrar/ocultar JSON 2 (temp=0.3)</summary>';
    if(is_array($json_2))
        echo '<pre>'.htmlspecialchars(json_encode($json_2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
    else
        echo '<pre>'.htmlspecialchars((string)$resultado_haiku["texto_2"], ENT_QUOTES, 'UTF-8').'</pre>';
    echo '</details>';
    }

// Bloque OCR fallback (si se disparo).
if($resultado_fallback !== null)
    {
    echo '<h2>OCR fallback (GLM-OCR + Haiku formateador)</h2>';

    if($resultado_fallback["ok"])
        {
        $tokens_glm = isset($resultado_fallback["tokens_glm"]) ? $resultado_fallback["tokens_glm"] : array();
        $tokens_sn  = isset($resultado_fallback["tokens_sonnet"]) ? $resultado_fallback["tokens_sonnet"] : array();

        echo '<div class="meta">';
        echo '<b>GLM-OCR tiempo:</b> '.$resultado_fallback["tiempo_glm"].' s &nbsp; ';
        echo '<b>tokens total:</b> '.number_format((int)(isset($tokens_glm["total"]) ? $tokens_glm["total"] : 0)).' &nbsp; ';
        echo '<b>costo:</b> ~$'.number_format((float)$resultado_fallback["costo_glm"], 6).'<br>';
        echo '<b>Sonnet formateador tiempo:</b> '.$resultado_fallback["tiempo_sonnet"].' s &nbsp; ';
        echo '<b>tokens in/out:</b> '.number_format((int)(isset($tokens_sn["input"]) ? $tokens_sn["input"] : 0)).' / '.number_format((int)(isset($tokens_sn["output"]) ? $tokens_sn["output"] : 0)).' &nbsp; ';
        echo '<b>costo:</b> ~$'.number_format((float)$resultado_fallback["costo_sonnet"], 6).'<br>';
        echo '<b>Costo fallback total:</b> ~$'.number_format($costo_fallback, 6).' USD';
        echo '</div>';

        $md_results = isset($resultado_fallback["md_results"]) ? (string)$resultado_fallback["md_results"] : "";
        if($md_results != "")
            {
            echo '<details><summary>Mostrar/ocultar markdown crudo de GLM-OCR ('.number_format(strlen($md_results)).' chars)</summary>';
            echo '<pre>'.htmlspecialchars($md_results, ENT_QUOTES, 'UTF-8').'</pre>';
            echo '</details>';
            }

        $json_fb = isset($resultado_fallback["json"]) ? $resultado_fallback["json"] : array();
        echo '<details><summary>Mostrar/ocultar JSON del fallback</summary>';
        echo '<pre>'.htmlspecialchars(json_encode($json_fb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
        echo '</details>';
        }
    else
        {
        echo '<p class="err">Fallback OCR fallo: '.htmlspecialchars((string)$resultado_fallback["error"], ENT_QUOTES, 'UTF-8').'</p>';
        if(isset($resultado_fallback["respuesta_glm"]))
            {
            echo '<details><summary>Respuesta GLM-OCR cruda</summary>';
            echo '<pre>'.htmlspecialchars(substr((string)$resultado_fallback["respuesta_glm"], 0, 4000), ENT_QUOTES, 'UTF-8').'</pre>';
            echo '</details>';
            }
        if(isset($resultado_fallback["respuesta_sonnet"]))
            {
            echo '<details><summary>Respuesta Sonnet formateador cruda</summary>';
            echo '<pre>'.htmlspecialchars(substr((string)$resultado_fallback["respuesta_sonnet"], 0, 4000), ENT_QUOTES, 'UTF-8').'</pre>';
            echo '</details>';
            }
        }
    }

// JSON definitivo destacado.
echo '<h2>JSON DEFINITIVO</h2>';
echo '<pre class="def">'.htmlspecialchars(json_encode($json_definitivo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8').'</pre>';

// Tabla aplanada de CAJAS[].CONTENIDO.
$cajas_def     = isset($json_definitivo["CAJAS"]) && is_array($json_definitivo["CAJAS"]) ? $json_definitivo["CAJAS"] : array();
$total_cajas   = count($cajas_def);
$filas         = array();
$suma_total_lineas = 0.0;
for($i = 0; $i < $total_cajas; $i++)
    {
    $caja        = $cajas_def[$i];
    $numero_caja = isset($caja["NUMERO_CAJA"]) ? $caja["NUMERO_CAJA"] : "";
    $tipo_caja   = isset($caja["TIPO_CAJA"])   ? $caja["TIPO_CAJA"]   : "";
    $contenido   = isset($caja["CONTENIDO"]) && is_array($caja["CONTENIDO"]) ? $caja["CONTENIDO"] : array();
    $nc          = count($contenido);
    for($j = 0; $j < $nc; $j++)
        {
        $linea = $contenido[$j];
        $ptot  = isset($linea["PRECIO_TOTAL"]) ? (float)$linea["PRECIO_TOTAL"] : 0.0;
        $suma_total_lineas += $ptot;
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
            "PRECIO_TOTAL"    => $ptot,
            "ALERTA"          => isset($linea["ALERTA"])          ? $linea["ALERTA"]          : ""
            );
        }
    }

echo '<h3>Tabla de lineas extraidas ('.count($filas).' lineas en '.$total_cajas.' cajas)</h3>';

if(empty($filas))
    echo '<p>No hay lineas en CAJAS.</p>';
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

// Pie con resumen y costo.
echo '<div class="costo-total">';
echo 'Resumen: '.count($filas).' lineas en '.$total_cajas.' cajas &nbsp; | &nbsp; ';
echo 'Suma PRECIO_TOTAL lineas: ~$'.number_format($suma_total_lineas, 2).' &nbsp; | &nbsp; ';
echo 'COSTO TOTAL: ~$'.number_format($costo_total, 6).' USD';
echo '</div>';

echo '<h3>Archivos guardados</h3>';
echo '<div class="meta">';
echo '<b>JSON definitivo:</b> '.htmlspecialchars($ruta_def, ENT_QUOTES, 'UTF-8').'<br>';
echo '<b>Haiku 1 cruda:</b> /tmp/final_'.$codigo.'_'.$fecha_corrida.'_haiku1.json<br>';
echo '<b>Haiku 2 cruda:</b> /tmp/final_'.$codigo.'_'.$fecha_corrida.'_haiku2.json';
if($resultado_fallback !== null)
    {
    echo '<br><b>GLM-OCR cruda:</b> /tmp/final_'.$codigo.'_'.$fecha_corrida.'_glm.json<br>';
    echo '<b>GLM-OCR md:</b> /tmp/final_'.$codigo.'_'.$fecha_corrida.'_md.txt<br>';
    echo '<b>Sonnet fallback cruda:</b> /tmp/final_'.$codigo.'_'.$fecha_corrida.'_fallback_sonnet.json';
    }
echo '</div>';

echo '</body></html>';
