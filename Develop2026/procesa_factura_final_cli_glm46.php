<?php
  
// ============================================================================
//  procesa_factura_final_cli_glm46.php  
//  Variante CLI usando GLM-4.6 de Z.ai como formateador del fallback OCR
//  (en lugar de Sonnet/Haiku de Anthropic). Misma logica: Haiku doble sobre
//  PDF directo + fallback GLM-OCR -> GLM-4.6 formateador si >20% nulls.
//  Sin HTML, salida texto plano y log dual a archivo.
//
//  Nota: GLM-4.6 tiene thinking AUTOMATICO (decide solo si pensar), a
//  diferencia de GLM-4.7 que piensa compulsivamente.
//
//  Uso: php procesa_factura_final_cli_glm46.php <codigo>
//  Ejemplo: php procesa_factura_final_cli_glm46.php 79
// ============================================================================
 
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
set_time_limit(0);
ini_set("memory_limit", "512M");
ini_set("serialize_precision", "14");
ini_set("precision", "14");

include("variables_globales.php");
include("funciones_v2.php");  // compara_extracciones, limpia_json_decimales

// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($argv[1]) ? (int)$argv[1] : 79;

// API keys.
$ruta_anthropic = "/home/u154-6g3keph3vtcn/credenciales_claude/api_key.txt";
$ruta_zai       = "/home/u154-6g3keph3vtcn/credenciales_zai/api_key.txt";

if(!file_exists($ruta_anthropic))
    die("No existe el archivo de API key Anthropic: ".$ruta_anthropic."\n");
$ANTHROPIC_API_KEY = trim((string)file_get_contents($ruta_anthropic));
if($ANTHROPIC_API_KEY == "")
    die("API key Anthropic vacia\n");

if(!file_exists($ruta_zai))
    die("No existe el archivo de API key Z.AI: ".$ruta_zai."\n");
$ZAI_API_KEY = trim((string)file_get_contents($ruta_zai));
if($ZAI_API_KEY == "")
    die("API key Z.AI vacia\n");

$fecha_corrida = date("Ymd_His");

// Umbral disparador del fallback OCR.
$UMBRAL_PORCENTAJE_NULL = 20.0;

// Variante del formateador OCR.
$variante           = "GLM46-FORMATEADOR";
$modelo_formateador = "glm-4.6";

// Log dual: imprime por stdout y graba a archivo en /tmp.
$archivo_log = "/tmp/final_log_".$codigo."_".$fecha_corrida.".txt";
$fh_log      = fopen($archivo_log, "w");

function log_dual($texto)
    {
    global $fh_log;
    echo $texto;
    if($fh_log)
        fwrite($fh_log, $texto);
    }

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
            "tiempo_1"                => $tiempo_1,
            "tiempo_2"                => 0,
            "tokens_input"            => $in1,
            "tokens_output"           => $ou1,
            "tokens_cache_read"       => $cr1,
            "tokens_cache_creat"      => $cc1,
            "costo"                   => $costo_1,
            "r1_usage"                => $r1["usage"],
            "r2_usage"                => array(),
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
        "tiempo_1"                => $tiempo_1,
        "tiempo_2"                => $tiempo_2,
        "tokens_input"            => $in1 + $in2,
        "tokens_output"           => $ou1 + $ou2,
        "tokens_cache_read"       => $cr1 + $cr2,
        "tokens_cache_creat"      => $cc1 + $cc2,
        "costo"                   => $costo,
        "r1_usage"                => $r1["usage"],
        "r2_usage"                => $r2["usage"],
        "respuesta1_cruda"        => $r1["respuesta_cruda"],
        "respuesta2_cruda"        => $r2["respuesta_cruda"],
        "omitida_segunda_llamada" => false
        );
    }

// ----------------------------------------------------------------------------
// ejecuta_ocr_fallback - GLM-OCR -> GLM-4.6 formateador (markdown).
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

    // --- PASO 2: GLM-4.6 formateador (markdown -> JSON) ---------------------
    // Endpoint OpenAI-compatible de Z.ai. Auth con Bearer $api_zai.
    // NO usar x-api-key ni anthropic-version (esos son de Anthropic).
    $encabezado = "Te paso a continuacion el contenido en markdown de una factura de flores extraido por OCR. Tu tarea es interpretarlo y extraer la informacion en el JSON estructurado segun las reglas que te doy.\n\n"
                . "MARKDOWN DE LA FACTURA:\n"
                . $md_results . "\n\n"
                . "FIN DEL MARKDOWN.\n\n"
                . "Ahora aplica estas reglas:\n\n";

    $prompt_glm_fmt = $encabezado.$PROMPT_GRANDE;

    $payload_glm_fmt = array(
        "model"       => "glm-4.6",
        "max_tokens"  => 24000,
        "temperature" => 0,
        "messages"    => array(
            array(
                "role"    => "user",
                "content" => $prompt_glm_fmt
                )
            )
        );
    $payload_glm_fmt_json = json_encode($payload_glm_fmt);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.z.ai/api/paas/v4/chat/completions");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer ".$api_zai,
        "Content-Type: application/json"
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_glm_fmt_json);

    $t_inicio_glm_fmt = microtime(true);
    $resp_glm_fmt     = curl_exec($ch);
    $http_glm_fmt     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err_glm_fmt      = curl_error($ch);
    $tiempo_glm_fmt   = round(microtime(true) - $t_inicio_glm_fmt, 2);
    curl_close($ch);

    if($err_glm_fmt != "" || $http_glm_fmt != 200)
        return array(
            "ok"               => false,
            "error"            => "GLM-4.6 formateador fallo (HTTP ".$http_glm_fmt.($err_glm_fmt != "" ? " / cURL: ".$err_glm_fmt : "").")",
            "tiempo_glm"       => $tiempo_glm,
            "tiempo_glm_fmt"   => $tiempo_glm_fmt,
            "respuesta_glm"    => (string)$resp_glm,
            "respuesta_glm_fmt"=> (string)$resp_glm_fmt,
            "md_results"       => $md_results
            );

    $data_glm_fmt = json_decode((string)$resp_glm_fmt, true);
    if($data_glm_fmt === null)
        return array(
            "ok"                => false,
            "error"             => "Respuesta GLM-4.6 formateador no es JSON valido (envoltorio)",
            "tiempo_glm"        => $tiempo_glm,
            "tiempo_glm_fmt"    => $tiempo_glm_fmt,
            "respuesta_glm_fmt" => (string)$resp_glm_fmt,
            "md_results"        => $md_results
            );

    // Z.ai sigue formato OpenAI: choices[0].message.content (NO content[0].text).
    $texto_glm_fmt = isset($data_glm_fmt["choices"][0]["message"]["content"]) ? (string)$data_glm_fmt["choices"][0]["message"]["content"] : "";
    $json_str      = extraer_json($texto_glm_fmt);
    $extraccion    = json_decode($json_str, true);

    if(!is_array($extraccion))
        return array(
            "ok"                => false,
            "error"             => "GLM-4.6 formateador devolvio JSON invalido",
            "tiempo_glm"        => $tiempo_glm,
            "tiempo_glm_fmt"    => $tiempo_glm_fmt,
            "respuesta_glm_fmt" => (string)$resp_glm_fmt,
            "texto_glm_fmt"     => $texto_glm_fmt,
            "md_results"        => $md_results
            );

    limpia_json_decimales($extraccion);

    // Usage en formato OpenAI: prompt_tokens / completion_tokens.
    // Pricing GLM-4.6: $0.60/M input + $2.20/M output.
    $g_in    = isset($data_glm_fmt["usage"]["prompt_tokens"])     ? (int)$data_glm_fmt["usage"]["prompt_tokens"]     : 0;
    $g_out   = isset($data_glm_fmt["usage"]["completion_tokens"]) ? (int)$data_glm_fmt["usage"]["completion_tokens"] : 0;
    $costo_g = ($g_in / 1000000.0) * 0.60 + ($g_out / 1000000.0) * 2.20;

    return array(
        "ok"                => true,
        "json"              => $extraccion,
        "tiempo_glm"        => $tiempo_glm,
        "tiempo_glm_fmt"    => $tiempo_glm_fmt,
        "md_results"        => $md_results,
        "md_results_len"    => strlen($md_results),
        "tokens_glm"        => array(
            "prompt"     => isset($glm_usage["prompt_tokens"])     ? (int)$glm_usage["prompt_tokens"]     : 0,
            "completion" => isset($glm_usage["completion_tokens"]) ? (int)$glm_usage["completion_tokens"] : 0,
            "total"      => $glm_total_t
            ),
        "tokens_glm_fmt"    => array(
            "input"  => $g_in,
            "output" => $g_out
            ),
        "costo"             => $costo_glm + $costo_g,
        "costo_glm"         => $costo_glm,
        "costo_glm_fmt"     => $costo_g,
        "respuesta_glm"     => (string)$resp_glm,
        "respuesta_glm_fmt" => (string)$resp_glm_fmt
        );
    }

// ============================================================================
//  MAIN FLOW (CLI)
// ============================================================================

$t_total_inicio = microtime(true);

log_dual("=== PROCESA FACTURA FINAL CLI ===\n");
log_dual("Variante:       ".$variante."\n");
log_dual("Codigo adjunto: ".$codigo."\n");
log_dual("Inicio:         ".date("Y-m-d H:i:s")."\n\n");

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

log_dual("--- ADJUNTO ---\n");
log_dual("Nombre:  ".$nombrearchivo."\n");
log_dual("Mime:    ".$mimetype."\n");
log_dual("Tamano:  ".number_format($tamano_pdf)." bytes (".number_format($tamano_pdf / 1024, 1)." KB)\n\n");

// ----------------------------------------------------------------------------
// EJECUTAR HAIKU DOBLE
// ----------------------------------------------------------------------------
log_dual("--- HAIKU DOBLE (PDF directo) ---\n");
log_dual("Llamando Haiku (1 o 2 llamadas, segun atajo)...\n");

$resultado_haiku = ejecuta_haiku_doble($archivo, $ANTHROPIC_API_KEY);

// Guardar respuestas crudas Haiku.
if(isset($resultado_haiku["respuesta1_cruda"]) && $resultado_haiku["respuesta1_cruda"] !== null)
    file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_haiku1.json", $resultado_haiku["respuesta1_cruda"]);
if(isset($resultado_haiku["respuesta2_cruda"]) && $resultado_haiku["respuesta2_cruda"] !== null)
    file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_haiku2.json", $resultado_haiku["respuesta2_cruda"]);

if(!$resultado_haiku["ok"])
    {
    log_dual("\nERROR Haiku doble: ".(string)$resultado_haiku["error"]."\n");
    if(isset($resultado_haiku["r1"]["respuesta_cruda"]) && $resultado_haiku["r1"]["respuesta_cruda"] != "")
        log_dual("Respuesta cruda llamada 1 (primeros 2000 chars):\n".substr((string)$resultado_haiku["r1"]["respuesta_cruda"], 0, 2000)."\n");
    if(isset($resultado_haiku["r2"]["respuesta_cruda"]) && $resultado_haiku["r2"]["respuesta_cruda"] != "")
        log_dual("Respuesta cruda llamada 2 (primeros 2000 chars):\n".substr((string)$resultado_haiku["r2"]["respuesta_cruda"], 0, 2000)."\n");
    if($fh_log) fclose($fh_log);
    echo "\nLog guardado en: ".$archivo_log."\n";
    exit(1);
    }

// Detalle por llamada (parsear respuestas para stop_reason).
$r1_data = ($resultado_haiku["respuesta1_cruda"] !== null) ? json_decode((string)$resultado_haiku["respuesta1_cruda"], true) : null;
$r2_data = ($resultado_haiku["respuesta2_cruda"] !== null) ? json_decode((string)$resultado_haiku["respuesta2_cruda"], true) : null;

$r1_in        = isset($resultado_haiku["r1_usage"]["input_tokens"])  ? (int)$resultado_haiku["r1_usage"]["input_tokens"]  : 0;
$r1_out       = isset($resultado_haiku["r1_usage"]["output_tokens"]) ? (int)$resultado_haiku["r1_usage"]["output_tokens"] : 0;
$r1_stop      = ($r1_data !== null && isset($r1_data["stop_reason"])) ? (string)$r1_data["stop_reason"] : "?";
$tiempo_1     = isset($resultado_haiku["tiempo_1"]) ? (float)$resultado_haiku["tiempo_1"] : 0.0;

log_dual("\nLlamada 1 (temp=0):\n");
log_dual("  Tiempo:        ".$tiempo_1." s\n");
log_dual("  Tokens in/out: ".number_format($r1_in)." / ".number_format($r1_out)."\n");
log_dual("  Stop reason:   ".$r1_stop."\n");

if(isset($resultado_haiku["omitida_segunda_llamada"]) && $resultado_haiku["omitida_segunda_llamada"])
    {
    log_dual("\n=== SEGUNDA LLAMADA OMITIDA: >".number_format($UMBRAL_PORCENTAJE_NULL, 0)."% largos null detectados ===\n");
    log_dual("Motivo: la primera extraccion ya detecto grid posicional. Se ahorra una llamada Haiku y se dispara fallback OCR.\n");
    }
else
    {
    $r2_in    = isset($resultado_haiku["r2_usage"]["input_tokens"])  ? (int)$resultado_haiku["r2_usage"]["input_tokens"]  : 0;
    $r2_out   = isset($resultado_haiku["r2_usage"]["output_tokens"]) ? (int)$resultado_haiku["r2_usage"]["output_tokens"] : 0;
    $r2_stop  = ($r2_data !== null && isset($r2_data["stop_reason"])) ? (string)$r2_data["stop_reason"] : "?";
    $tiempo_2 = isset($resultado_haiku["tiempo_2"]) ? (float)$resultado_haiku["tiempo_2"] : 0.0;

    log_dual("\nLlamada 2 (temp=0.3):\n");
    log_dual("  Tiempo:        ".$tiempo_2." s\n");
    log_dual("  Tokens in/out: ".number_format($r2_in)." / ".number_format($r2_out)."\n");
    log_dual("  Stop reason:   ".$r2_stop."\n");

    log_dual("\nEstado comparacion: ");
    if((int)$resultado_haiku["estado"] == 3)
        log_dual("OK coinciden (estado=3)\n");
    else
        log_dual("REVISAR difieren (estado=4) - ".count($resultado_haiku["discrepancias"])." discrepancias\n");
    }

$costo_haiku = (float)$resultado_haiku["costo"];
log_dual("\nHaiku doble: tiempo total ".$resultado_haiku["tiempo"]." s, costo ~$".number_format($costo_haiku, 6)." USD\n");

$json_1 = is_array($resultado_haiku["json_1"]) ? $resultado_haiku["json_1"] : array();
$json_2 = (isset($resultado_haiku["json_2"]) && is_array($resultado_haiku["json_2"])) ? $resultado_haiku["json_2"] : array();

// ----------------------------------------------------------------------------
// DECIDIR FALLBACK
// ----------------------------------------------------------------------------
$conteo                = cuenta_largos_null($json_1);
$porcentaje_null       = $conteo["porcentaje_null"];

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

$resultado_fallback    = null;
$fallback_fallo        = false;
$json_definitivo       = $json_1;
$tipo_extraccion       = "HAIKU_DOBLE";
$alerta_fallback       = "";

if($disparar_fallback)
    {
    log_dual("\n--- FALLBACK OCR ---\n");
    log_dual("Porcentaje largos null en json_1: ".number_format($porcentaje_null, 2)."% (umbral ".number_format($UMBRAL_PORCENTAJE_NULL, 0)."%)\n");
    log_dual("Porcentaje lineas con alerta grid en json_1: ".number_format($porcentaje_alerta_grid, 2)."% (umbral ".number_format($UMBRAL_PORCENTAJE_NULL, 0)."%)\n");
    log_dual("Alerta grid en array global ALERTAS: ".($alerta_grid_global ? "si" : "no")."\n");
    log_dual("Motivo disparo: ".$motivo_disparo."\n");
    log_dual("Disparando GLM-OCR + GLM-4.6 formateador...\n");

    $resultado_fallback = ejecuta_ocr_fallback($archivo, $ZAI_API_KEY, $ANTHROPIC_API_KEY);

    // Guardar respuestas crudas del fallback.
    if(isset($resultado_fallback["respuesta_glm"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_glm.json", $resultado_fallback["respuesta_glm"]);
    if(isset($resultado_fallback["respuesta_glm_fmt"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_fallback_glm_fmt.json", $resultado_fallback["respuesta_glm_fmt"]);
    if(isset($resultado_fallback["md_results"]))
        file_put_contents("/tmp/final_".$codigo."_".$fecha_corrida."_md.txt", $resultado_fallback["md_results"]);

    if($resultado_fallback["ok"])
        {
        $tokens_glm = isset($resultado_fallback["tokens_glm"]) ? $resultado_fallback["tokens_glm"] : array();
        $tokens_gf  = isset($resultado_fallback["tokens_glm_fmt"]) ? $resultado_fallback["tokens_glm_fmt"] : array();

        log_dual("\nGLM-OCR llamada:\n");
        log_dual("  Tiempo:           ".$resultado_fallback["tiempo_glm"]." s\n");
        log_dual("  Tokens total:     ".number_format((int)(isset($tokens_glm["total"]) ? $tokens_glm["total"] : 0))."\n");
        log_dual("  md_results chars: ".number_format(isset($resultado_fallback["md_results_len"]) ? (int)$resultado_fallback["md_results_len"] : 0)."\n");
        log_dual("  Costo:            ~$".number_format((float)$resultado_fallback["costo_glm"], 6)."\n");

        log_dual("\nGLM-4.6 formateador llamada:\n");
        log_dual("  Tiempo:        ".$resultado_fallback["tiempo_glm_fmt"]." s\n");
        log_dual("  Tokens in/out: ".number_format((int)(isset($tokens_gf["input"]) ? $tokens_gf["input"] : 0))." / ".number_format((int)(isset($tokens_gf["output"]) ? $tokens_gf["output"] : 0))."\n");
        log_dual("  Costo:         ~$".number_format((float)$resultado_fallback["costo_glm_fmt"], 6)."\n");

        // Reemplazar SOLO CAJAS del json_1 con CAJAS del fallback.
        $cajas_fb = isset($resultado_fallback["json"]["CAJAS"]) && is_array($resultado_fallback["json"]["CAJAS"])
                  ? $resultado_fallback["json"]["CAJAS"]
                  : array();
        $json_definitivo["CAJAS"] = $cajas_fb;
        $tipo_extraccion = "HAIKU_DOBLE+OCR_FALLBACK";
        }
    else
        {
        $fallback_fallo  = true;
        $tipo_extraccion = "HAIKU_DOBLE";
        $alerta_fallback = "Fallback OCR disparado pero fallo: ".(isset($resultado_fallback["error"]) ? $resultado_fallback["error"] : "?");
        log_dual("\nERROR fallback: ".$alerta_fallback."\n");
        }
    }
else
    {
    log_dual("\n--- FALLBACK OCR: NO disparado ---\n");
    log_dual("Porcentaje largos null en json_1: ".number_format($porcentaje_null, 2)."% (umbral ".number_format($UMBRAL_PORCENTAJE_NULL, 0)."%)\n");
    log_dual("Porcentaje lineas con alerta grid en json_1: ".number_format($porcentaje_alerta_grid, 2)."% (umbral ".number_format($UMBRAL_PORCENTAJE_NULL, 0)."%)\n");
    log_dual("Alerta grid en array global ALERTAS: ".($alerta_grid_global ? "si" : "no")."\n");
    }

// ----------------------------------------------------------------------------
// METADATOS Y COSTO TOTAL
// ----------------------------------------------------------------------------
$tiempo_total   = round(microtime(true) - $t_total_inicio, 2);
$costo_fallback = ($resultado_fallback !== null && $resultado_fallback["ok"]) ? (float)$resultado_fallback["costo"] : 0.0;
$costo_total    = $costo_haiku + $costo_fallback;

// Desglose granular de tiempo por etapa.
$tiempo_haiku_1     = isset($resultado_haiku["tiempo_1"]) ? (float)$resultado_haiku["tiempo_1"] : 0.0;
$tiempo_haiku_2     = isset($resultado_haiku["tiempo_2"]) ? (float)$resultado_haiku["tiempo_2"] : 0.0;
$tiempo_glm_ocr     = ($resultado_fallback !== null && isset($resultado_fallback["tiempo_glm"]))     ? (float)$resultado_fallback["tiempo_glm"]     : 0.0;
$tiempo_formateador = ($resultado_fallback !== null && isset($resultado_fallback["tiempo_glm_fmt"])) ? (float)$resultado_fallback["tiempo_glm_fmt"] : 0.0;

// Desglose granular de costo por etapa.
$r1u = isset($resultado_haiku["r1_usage"]) ? $resultado_haiku["r1_usage"] : array();
$r2u = isset($resultado_haiku["r2_usage"]) ? $resultado_haiku["r2_usage"] : array();
$in1 = isset($r1u["input_tokens"])                ? (int)$r1u["input_tokens"]                : 0;
$ou1 = isset($r1u["output_tokens"])               ? (int)$r1u["output_tokens"]               : 0;
$cc1 = isset($r1u["cache_creation_input_tokens"]) ? (int)$r1u["cache_creation_input_tokens"] : 0;
$cr1 = isset($r1u["cache_read_input_tokens"])     ? (int)$r1u["cache_read_input_tokens"]     : 0;
$in2 = isset($r2u["input_tokens"])                ? (int)$r2u["input_tokens"]                : 0;
$ou2 = isset($r2u["output_tokens"])               ? (int)$r2u["output_tokens"]               : 0;
$cc2 = isset($r2u["cache_creation_input_tokens"]) ? (int)$r2u["cache_creation_input_tokens"] : 0;
$cr2 = isset($r2u["cache_read_input_tokens"])     ? (int)$r2u["cache_read_input_tokens"]     : 0;
$costo_haiku_1 = ($in1 / 1000000.0) * 1.00 + ($ou1 / 1000000.0) * 5.00 + ($cr1 / 1000000.0) * 0.10 + ($cc1 / 1000000.0) * 1.25;
$costo_haiku_2 = ($in2 / 1000000.0) * 1.00 + ($ou2 / 1000000.0) * 5.00 + ($cr2 / 1000000.0) * 0.10 + ($cc2 / 1000000.0) * 1.25;
$costo_glm_ocr     = ($resultado_fallback !== null && isset($resultado_fallback["costo_glm"]))     ? (float)$resultado_fallback["costo_glm"]     : 0.0;
$costo_formateador = ($resultado_fallback !== null && isset($resultado_fallback["costo_glm_fmt"])) ? (float)$resultado_fallback["costo_glm_fmt"] : 0.0;

$json_definitivo["METADATOS"] = array(
    "TIPO_EXTRACCION"                => $tipo_extraccion,
    "PORCENTAJE_LARGOS_NULL_INICIAL" => $porcentaje_null,
    "PORCENTAJE_ALERTA_GRID_INICIAL" => $porcentaje_alerta_grid,
    "ALERTA_GRID_GLOBAL_INICIAL"     => $alerta_grid_global ? "si" : "no",
    "TIEMPO_TOTAL_SEGUNDOS"          => $tiempo_total,
    "TIEMPO_HAIKU_1_SEG"             => $tiempo_haiku_1,
    "TIEMPO_HAIKU_2_SEG"             => $tiempo_haiku_2,
    "TIEMPO_GLM_OCR_SEG"             => $tiempo_glm_ocr,
    "TIEMPO_FORMATEADOR_SEG"         => $tiempo_formateador,
    "COSTO_TOTAL_USD"                => $costo_total,
    "COSTO_HAIKU_1_USD"              => $costo_haiku_1,
    "COSTO_HAIKU_2_USD"              => $costo_haiku_2,
    "COSTO_GLM_OCR_USD"              => $costo_glm_ocr,
    "COSTO_FORMATEADOR_USD"          => $costo_formateador,
    "MODELO_FORMATEADOR"             => $modelo_formateador,
    "FECHA_PROCESAMIENTO"            => date("Y-m-d H:i:s")
    );
if($alerta_fallback != "")
    $json_definitivo["METADATOS"]["ALERTA_FALLBACK"] = $alerta_fallback;

// Guardar JSON definitivo en /tmp.
$nombre_definitivo = "final_".$codigo."_".$fecha_corrida."_definitivo.json";
$ruta_tmp_def      = "/tmp/".$nombre_definitivo;
$ruta_def          = $ruta_tmp_def;
file_put_contents($ruta_tmp_def, json_encode($json_definitivo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Copiar el JSON al directorio publico de web para descarga directa.
$dir_publico  = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026";
$ruta_pub_def = $dir_publico."/".$nombre_definitivo;
$copia_ok     = @copy($ruta_tmp_def, $ruta_pub_def);

if($copia_ok)
    {
    $url_descarga   = "https://www.dienersoft.com/carpeta/divasoft1/Develop2026/".$nombre_definitivo;
    $comando_borrar = "rm ".$ruta_pub_def;
    }
else
    {
    $url_descarga   = "(fallo copia a web: ".$ruta_pub_def.")";
    $comando_borrar = "";
    }

log_dual("\n--- METADATOS ---\n");
log_dual("TIPO_EXTRACCION:                ".$tipo_extraccion."\n");
log_dual("PORCENTAJE_LARGOS_NULL_INICIAL: ".number_format($porcentaje_null, 2)."%\n");
log_dual("PORCENTAJE_ALERTA_GRID_INICIAL: ".number_format($porcentaje_alerta_grid, 2)."%\n");
log_dual("ALERTA_GRID_GLOBAL_INICIAL:     ".($alerta_grid_global ? "si" : "no")."\n");
log_dual("TIEMPO_TOTAL:                   ".$tiempo_total." s\n");
log_dual("COSTO_TOTAL:                    ~$".number_format($costo_total, 6)." USD\n");
if($alerta_fallback != "")
    log_dual("ALERTA_FALLBACK:                ".$alerta_fallback."\n");

log_dual("\n--- DESGLOSE TIEMPO ---\n");
log_dual("Haiku 1:       ".number_format($tiempo_haiku_1, 2)." s\n");
log_dual("Haiku 2:       ".number_format($tiempo_haiku_2, 2)." s\n");
log_dual("GLM-OCR:       ".number_format($tiempo_glm_ocr, 2)." s\n");
log_dual("Formateador:   ".number_format($tiempo_formateador, 2)." s\n");
log_dual("TOTAL:         ".number_format($tiempo_total, 2)." s\n");

log_dual("\n--- DESGLOSE COSTO ---\n");
log_dual("Haiku 1:       ~$".number_format($costo_haiku_1, 6)." USD\n");
log_dual("Haiku 2:       ~$".number_format($costo_haiku_2, 6)." USD\n");
log_dual("GLM-OCR:       ~$".number_format($costo_glm_ocr, 6)." USD\n");
log_dual("Formateador:   ~$".number_format($costo_formateador, 6)." USD\n");
log_dual("TOTAL:         ~$".number_format($costo_total, 6)." USD\n");

log_dual("\nModelo formateador: ".$modelo_formateador."\n");

log_dual("\nJSON guardado en disco (ver URL de descarga al final).\n");

// ----------------------------------------------------------------------------
// RESUMEN
// ----------------------------------------------------------------------------
$cajas_def         = isset($json_definitivo["CAJAS"]) && is_array($json_definitivo["CAJAS"]) ? $json_definitivo["CAJAS"] : array();
$total_cajas       = count($cajas_def);
$total_lineas      = 0;
$suma_total_lineas = 0.0;
for($i = 0; $i < $total_cajas; $i++)
    {
    $caja      = $cajas_def[$i];
    $contenido = isset($caja["CONTENIDO"]) && is_array($caja["CONTENIDO"]) ? $caja["CONTENIDO"] : array();
    $nc        = count($contenido);
    for($j = 0; $j < $nc; $j++)
        {
        $linea = $contenido[$j];
        $total_lineas++;
        $ptot  = isset($linea["PRECIO_TOTAL"]) ? (float)$linea["PRECIO_TOTAL"] : 0.0;
        $suma_total_lineas += $ptot;
        }
    }

log_dual("\n--- RESUMEN ---\n");
log_dual($total_lineas." lineas en ".$total_cajas." cajas\n");
log_dual("Suma PRECIO_TOTAL lineas: $".number_format($suma_total_lineas, 2)."\n");

// ----------------------------------------------------------------------------
// FIN
// ----------------------------------------------------------------------------
log_dual("\n=== FIN ===\n");
log_dual("Tiempo total: ".number_format($tiempo_total, 2)." s\n");
log_dual("\nLog guardado en: ".$archivo_log."\n");
log_dual("\n--- JSON DEFINITIVO DESCARGABLE ---\n");
log_dual("URL: ".$url_descarga."\n");
if($comando_borrar !== "")
    log_dual("\nIMPORTANTE: borrar el archivo despues de revisarlo:\n".$comando_borrar."\n");
log_dual("\nArchivos crudos en /tmp/: final_".$codigo."_".$fecha_corrida."_*\n");

if($fh_log) fclose($fh_log);
