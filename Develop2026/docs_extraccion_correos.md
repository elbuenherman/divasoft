# Extracción de correos electrónicos — DivaSOFT 2026

Documento de referencia del subsistema que descarga correos con facturas de fincas desde Gmail y los persiste en base de datos.

## Contexto de negocio

DIVAFLOR (broker exportador de flores ecuatorianas) recibe diariamente correos de fincas proveedoras con facturas adjuntas (PDF, XLSX, XLS). Este subsistema:

1. Conecta a la casilla de Gmail vía Google API.
2. Filtra los correos que parecen ser facturas (por asunto, cuerpo o nombre de adjunto).
3. Descarta los que claramente NO son facturas (estados de cuenta, avisos de crédito, etc.).
4. Guarda cada correo en `correo_facturas_fincas` y cada adjunto en `archivo_correo` (LONGBLOB).
5. Muestra todo en una consola web para revisión humana antes de mandar a Claude/GLM/Sonnet para extracción de datos estructurados.

## Arquitectura de archivos

```
Develop2026/
├── consola_gestion_correos_facturas.php   ← UI principal (HTML+JS+CSS+PHP de init)
├── funciones_ajax.php                     ← Dispatcher AJAX (recibe función + parámetros)
├── funciones_v2.php                       ← Lógica nueva — TODA la extracción de correos
├── ver_cuerpo.php                         ← Vista standalone del cuerpo HTML de un correo
├── ver_adjunto.php                        ← Vista/descarga standalone de un adjunto LONGBLOB
├── oauth_callback.php                     ← One-shot para autorizar OAuth contra Gmail
├── vendor/                                ← Google API client (composer)
└── /home/u154-6g3keph3vtcn/credenciales_correos/
    ├── client_secret.json                 ← Credenciales OAuth de la app Google
    └── token.json                         ← Token de acceso + refresh token (lo refresca solo)
```

## Tablas MySQL involucradas

### `correo_facturas_fincas`

Un registro por correo procesado. Columnas clave:

| Columna | Tipo / propósito |
|---|---|
| `CODIGO` | PK autoincrement |
| `CODIGOFINCA` | FK a finca (null al inicio, se asigna después) |
| `CODIGOCONSOLIDADO` | FK a consolidado/embarque (null al inicio) |
| `IDCORREO` | ID único del mensaje en Gmail (`$msg->getId()`). Sirve como clave de deduplicación |
| `MESSAGEID` | Header `Message-Id` del correo (string) |
| `THREADID` | Thread de Gmail al que pertenece |
| `FECHAHORA` | Fecha del correo según header `Date`, convertida a `America/Guayaquil` |
| `FECHAPROCESADO` | Cuándo lo marcó el operador como procesado |
| `DE`, `PARA`, `CC`, `BCC` | Headers correspondientes (puede haber múltiples destinatarios separados por coma) |
| `ASUNTO` | Header `Subject` |
| `CUERPOTEXTO` | Cuerpo `text/plain` (decodificado de base64url) |
| `CUERPOHTML` | Cuerpo `text/html` (decodificado de base64url) |
| `ESTADO` | Estado del correo en el flujo (1 = recién extraído) |
| `CODIGOUSUARIOPROCESO` | Usuario que lo proceso (null al inicio) |
| `OBSERVACIONES` | Texto libre |

Deduplicación: antes de insertar se busca por `IDCORREO`. Si ya existe, el correo se cuenta como "saltado".

### `archivo_correo`

Un registro por adjunto. Columnas clave:

| Columna | Tipo / propósito |
|---|---|
| `CODIGO` | PK autoincrement |
| `IDCORREO` | Mismo `IDCORREO` que el correo padre (no FK explícita, vínculo lógico) |
| `CODIGOFINCA`, `CODIGOCONSOLIDADO` | FKs (null al inicio) |
| `NOMBREARCHIVO` | Nombre original del archivo (ej. `factura_12345.pdf`) |
| `MIMETYPE` | `application/pdf`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, etc. |
| `TAMANOBYTES` | Tamaño del binario en bytes |
| `HASHARCHIVO` | `md5($binario)` — para detectar duplicados |
| `ARCHIVO` | **LONGBLOB** con el binario crudo del adjunto |
| `RUTA` | Reservado (null) |
| `FECHAGUARDADO` | Reservado (null) |

Deduplicación: antes de insertar se busca por la combinación `IDCORREO + NOMBREARCHIVO`. Si existe, se salta sin error.

El binario se inserta con **sentencia preparada** y `mysqli_stmt_send_long_data` (NO se concatena el binario en el SQL).

## Flujo de extracción — paso a paso

### 1. Disparo desde la consola

`consola_gestion_correos_facturas.php` tiene un botón `EXTRAER` (línea ~666) que invoca el JS:

```javascript
function extraer_correos() {
    var fechas = $("#id_rango_fechas").val().split(" to ");
    var fecha_desde = fechas[0];
    var fecha_hasta = fechas[1];
    var url = "funciones_ajax.php?funcion=extraer_correos_facturas"
            + "&parametro1=" + fecha_desde
            + "&parametro2=" + fecha_hasta;
    // AJAX GET ... muestra el mensaje resultado y refresca el listado
}
```

El rango se elige con un Flatpickr de modo `range`. Formato esperado: `YYYY-MM-DD to YYYY-MM-DD`.

### 2. Dispatcher AJAX

`funciones_ajax.php` recibe `funcion=extraer_correos_facturas` con `parametro1` (desde) y `parametro2` (hasta) y hace:

```php
if($funcion == 'extraer_correos_facturas')
    echo extraer_correos_facturas($parametro1, $parametro2);
```

### 3. Función principal: `extraer_correos_facturas($fecha_desde, $fecha_hasta)`

Vive en `funciones_v2.php` (~línea 326). Pasos internos:

#### 3.1 Validación

Ambas fechas deben matchear `^\d{4}-\d{2}-\d{2}$`. Si no, retorna texto `"Por favor seleccione un rango de fechas valido"`.

#### 3.2 Setup del cliente Gmail

```php
require_once __DIR__ . '/vendor/autoload.php';

$ruta_client_secret = '/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json';
$ruta_token         = '/home/u154-6g3keph3vtcn/credenciales_correos/token.json';

$client = new Google\Client();
$client->setAuthConfig($ruta_client_secret);
$client->addScope(Google\Service\Gmail::GMAIL_READONLY);
$client->setAccessType('offline');
```

Si no existe `token.json` → retorna error pidiendo correr `oauth_callback.php` para autorizar.

Si el token está expirado → lo refresca con el `refresh_token` y reescribe `token.json`.

```php
$service = new Google\Service\Gmail($client);
$tz = new DateTimeZone('America/Guayaquil');
```

#### 3.3 Iteración día por día dentro del rango

El rango se recorre **día a día**, no todo de un golpe, para evitar resultados truncados por la API:

```php
for($d=1; $d<=$numero_dias; $d++) {
    $ts_inicio = inicio_del_dia($dia)->getTimestamp();   // 00:00:00 América/Guayaquil
    $ts_fin    = fin_del_dia($dia)->getTimestamp();      // 23:59:59 América/Guayaquil

    $query = 'in:anywhere -from:compras2@divaflor.com has:attachment '
           . 'after:' . $ts_inicio . ' before:' . $ts_fin;

    // ... bucle de paginación interno (maxResults=100) ...

    $dia->modify('+1 day');
}
```

Notar:
- `in:anywhere` mira inbox + carpetas + spam + trash.
- `-from:compras2@divaflor.com` excluye correos enviados por la propia oficina.
- `has:attachment` filtra los que no tienen adjunto en Gmail (segundo filtro más fino viene después).
- El query usa **timestamps Unix**, no strings de fecha, para precisión con timezone.

#### 3.4 Paginación dentro del día

Bucle interno con `pageToken` por si un día tiene >100 mensajes. La API devuelve `nextPageToken` mientras haya más.

#### 3.5 Procesar cada mensaje

Por cada `$msg` listado, se llama:

```php
$estado_msg = procesa_mensaje_factura($service, $msg, $tz, $payload_msg);
```

Retorna uno de tres estados:
- `'descartado'` — pasó los filtros y NO parece factura, se omite.
- `'saltado'`  — ya existe en `correo_facturas_fincas` (deduplicación por `IDCORREO`).
- `'guardado'` — pasó filtros, se insertó. A continuación se llama:

```php
$total_adjuntos += guarda_adjuntos_correo($service, $msg->getId(), $payload_msg);
```

(Importante: el `$payload` se pasa por referencia desde `procesa_mensaje_factura` para no descargar el mensaje dos veces.)

#### 3.6 Retorno final

```
"Se procesaron N correos, se guardaron X nuevos, se saltaron Y ya existentes.
 Adjuntos guardados: Z. Tiempo: T segundos"
```

### 4. Procesamiento de un mensaje: `procesa_mensaje_factura`

Vive en `funciones_v2.php` (~línea 213).

#### 4.1 Descarga del mensaje completo

```php
$detalle = $service->users_messages->get('me', $msg->getId(), array('format' => 'full'));
$payload = $detalle->getPayload();
```

`format=full` trae headers + cuerpo + estructura de partes (sin descargar binarios de adjuntos todavía).

#### 4.2 Extracción de headers

Recorre `$payload->getHeaders()` buscando: `Date`, `From`, `To`, `Cc`, `Bcc`, `Subject`, `Message-Id`.

#### 4.3 Filtros — en este orden estricto

**a) Filtro duro por remitente:** si `From` contiene `compras2@divaflor.com` → `descartado` inmediato (es la cuenta de DIVAFLOR enviando, no recibiendo).

**b) Recolección de adjuntos PDF/XLSX/XLS:** recorre el árbol de `parts` recursivamente buscando archivos con esas extensiones (función auxiliar `buscar_adjuntos_correo`). Devuelve lista de nombres de archivo.

**c) Extracción de cuerpos:** `extraer_cuerpo_mime_correo` recorre el árbol buscando `text/plain` y `text/html`, decodifica base64url y concatena.

**d) Override por "factura"/"invoice":** si el asunto, los cuerpos o los nombres de adjuntos contienen `factura`, `facturas`, `invoice` o `invoices` (insensible a tildes y mayúsculas) → marca `$es_factura = true` y **se salta los filtros de prohibición**.

**e) Si NO es factura por override**, aplicar filtros normales:
- Si no hay adjuntos PDF/XLSX/XLS → `descartado`.
- Si el asunto contiene palabra prohibida → `descartado`.
- Si los nombres de adjuntos contienen palabra prohibida → `descartado`.

#### 4.4 Palabras prohibidas (función `texto_es_prohibido_correo`)

Cualquiera de estas suelta:
- `credito`
- `disponible`
- `disponibilidad`
- `availability`
- `statement`

O bien la combinación `estado` + `cuenta` en el mismo texto.

Razón: descartar avisos de tarjeta de crédito, statements bancarios, mails de saldo disponible.

Toda comparación pasa por `normalizar_texto_correo` (`mb_strtolower` + reemplazo de tildes y eñes).

#### 4.5 Palabras gatillo de factura (función `texto_es_factura_correo`)

- `factura`
- `facturas`
- `invoice`
- `invoices`

#### 4.6 Deduplicación

```php
SELECT CODIGO FROM correo_facturas_fincas WHERE IDCORREO = ?
```

Si ya existe → `'saltado'`. Si no → se inserta.

#### 4.7 Inserción del correo

`FECHAHORA` se construye desde el header `Date` convertido a `America/Guayaquil`. Si el parse falla, usa `date('Y-m-d H:i:s')` actual como fallback.

Todos los textos pasan por `mysqli_real_escape_string`. El INSERT pone:
- `CODIGOFINCA = NULL`
- `CODIGOCONSOLIDADO = NULL`
- `FECHAPROCESADO = NULL`
- `ESTADO = 1`
- `CODIGOUSUARIOPROCESO = NULL`
- `OBSERVACIONES = NULL`

Estos campos se llenan luego en el flujo de revisión humana.

### 5. Guardado de adjuntos: `guarda_adjuntos_correo`

Vive en `funciones_v2.php` (~línea 145).

#### 5.1 Recolección de "parts" de adjuntos

`recolecta_parts_adjuntos_correo` recorre recursivamente el `$payload` buscando partes cuyo `filename` termine en `.pdf`, `.xlsx` o `.xls`. Acumula los objetos `part` (no solo los nombres).

#### 5.2 Por cada part adjunto

a) Verificar duplicado:
```php
SELECT CODIGO FROM archivo_correo
WHERE IDCORREO = ? AND NOMBREARCHIVO = ?
```
Si existe, saltar.

b) Descargar el binario:
```php
$attachment_id = $part->getBody()->getAttachmentId();
$adjunto = $service->users_messages_attachments->get('me', $id_mensaje, $attachment_id);
$data    = $adjunto->getData();
$binario = base64_decode(strtr($data, '-_', '+/'));   // base64url -> base64 estándar
```

c) Calcular metadata:
- `$tamano   = strlen($binario)`
- `$hash     = md5($binario)`
- `$mimetype = $part->getMimeType()`

d) INSERT con sentencia preparada y `send_long_data` para el blob:
```php
$sql = "INSERT INTO archivo_correo
    (IDCORREO, CODIGOFINCA, CODIGOCONSOLIDADO, NOMBREARCHIVO, MIMETYPE,
     TAMANOBYTES, HASHARCHIVO, ARCHIVO, RUTA, FECHAGUARDADO)
    VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, NULL, NULL)";

$stmt = mysqli_prepare($link, $sql);
$blob_nulo = NULL;
mysqli_stmt_bind_param($stmt, "sssisb",
    $id_mensaje, $filename, $mimetype, $tamano, $hash, $blob_nulo);
mysqli_stmt_send_long_data($stmt, 5, $binario);
mysqli_stmt_execute($stmt);
```

Notar el `"sssisb"`: el último `b` es para el blob, que se envía aparte con `send_long_data` en posición 5 (índice base 0 entre los `?`). El binding al `$blob_nulo` es solo un placeholder; el binario real llega por `send_long_data`.

Retorna el contador de adjuntos guardados.

## Visualización en la consola

### Listado: `lista_correos_facturas($campo_orden, $direccion_orden)`

Vive en `funciones_v2.php` (~línea 469). Genera el HTML de la tabla:

- SELECT a `correo_facturas_fincas` con `WHERE FECHAHORA >= DATE_SUB(NOW(), INTERVAL 5 DAY)` (siempre solo últimos 5 días).
- Ordenamiento validado contra lista blanca: `CODIGO`, `CODIGOFINCA`, `CODIGOCONSOLIDADO`, `ASUNTO`, `FECHAHORA`, `DE`, `PARA`, `ESTADO`. Cualquier otro valor cae al default `FECHAHORA DESC`.
- En **una sola consulta** adicional trae todos los adjuntos de esos correos (`archivo_correo` con `IDCORREO IN (...)`).
- Renderiza una fila por correo + una fila por cada adjunto del correo.

Columnas visibles: `COD`, `FINCA`, `CONS`, `ASUNTO`, `FH REC` (fecha-hora recepción formato `MM-DD HH:MM:SS`), `E` (estado), `OPC` (botones de acción).

Ordenamiento por click en encabezado de columna — usa indicador triángulo (`▲` `▼`) generado por `indicador_orden`.

### Ver cuerpo del correo: `ver_cuerpo.php?codigo=N`

Standalone (no usa funciones_ajax). Lee `$_GET["codigo"]` (PK de `correo_facturas_fincas`), hace SELECT, muestra:
- Header: `De`, `Para`, `Cc`, `Asunto`, `Fecha` (con `limpia_email` para extraer solo el address de campos como `"Nombre" <email@dom.com>`).
- Cuerpo HTML directo (con `preg_replace` para quitar `<script>` y `<iframe>` por seguridad).
- Si solo hay `CUERPOTEXTO`, lo muestra dentro de un `<pre>`.

Se abre en un dialog modal de jQuery UI desde la consola.

### Ver adjunto: `ver_adjunto.php?codigo=N`

Standalone. Lee `$_GET["codigo"]` (PK de `archivo_correo`), hace SELECT del LONGBLOB usando sentencia preparada con `mysqli_stmt_store_result`, manda headers `Content-Type: <MIMETYPE>` y `Content-Disposition: inline; filename=<NOMBREARCHIVO>` y hace `echo` del binario.

Se renderiza en un iframe dentro de un dialog modal — para PDFs, el navegador usa su viewer nativo.

## Configuración OAuth (one-shot)

Para autorizar la app contra Gmail la primera vez (o si se revoca):

1. Crear app en Google Cloud Console con scope `gmail.readonly`.
2. Descargar `client_secret.json` y subirlo a `/home/u154-6g3keph3vtcn/credenciales_correos/`.
3. Correr `oauth_callback.php` en navegador, autorizar con la cuenta Gmail correspondiente.
4. El callback escribe `token.json` con `access_token` + `refresh_token` en la misma carpeta.

A partir de ahí, `extraer_correos_facturas` refresca el token solo cuando expira. No hace falta volver a autorizar mientras no se revoque el consent.

## Funciones auxiliares en `funciones_v2.php` — resumen

| Función | Propósito |
|---|---|
| `normalizar_texto_correo($texto)` | minúsculas + quitar tildes/eñes |
| `texto_es_prohibido_correo($texto)` | true si contiene palabra de descarte |
| `texto_es_factura_correo($texto)` | true si contiene palabra gatillo de factura |
| `extraer_cuerpo_mime_correo($payload, $mime)` | recorre árbol de parts y concatena cuerpos del mime pedido |
| `buscar_adjuntos_correo($parts)` | recursivo, devuelve lista de nombres PDF/XLSX/XLS |
| `recolecta_parts_adjuntos_correo($payload, &$acumulado)` | recursivo, devuelve los objetos `part` de adjuntos |
| `guarda_adjuntos_correo($service, $id_mensaje, $payload)` | descarga + INSERT idempotente de adjuntos |
| `procesa_mensaje_factura($service, $msg, $tz, &$payload_out)` | filtra + dedup + INSERT del correo |
| `extraer_correos_facturas($fecha_desde, $fecha_hasta)` | orquesta todo el rango |
| `limpia_email($texto)` | extrae solo `email@dom.com` de strings tipo `"Nombre" <e@d.c>, "Otro" <e2@d.c>` |
| `indicador_orden($campo, $orden, $direccion)` | triángulo ▲/▼ para encabezado |
| `lista_correos_facturas($campo, $dir)` | HTML del listado + adjuntos anidados |

## Estilo de código del proyecto (referencia rápida para entender el código)

Conviven dos estilos:

- **Estilo viejo:** claves de arrays PHP en minúsculas con guión bajo (`$fila['codigo']`).
- **Estilo nuevo (este subsistema):** claves de arrays PHP/JSON SIEMPRE en MAYÚSCULAS sin guiones (`$fila['CODIGO']`). Columnas reales de MySQL siguen en minúsculas con guión bajo y se mapean con `AS MAYUSCULAS` en el SELECT.

Reglas que verás en `funciones_v2.php`:
- Llaves `{` `}` en su propia línea, mismo nivel del bloque que abren.
- Loops siempre `for($i=1; $i<=$total; $i++)`. **No** se usa `foreach` ni `while`.
- Toda función que toca BD pone `global $link;` como primera línea.
- Escape inline con `mysqli_real_escape_string($link, $valor)` antes de insertar en strings SQL (o reasignación a la misma variable). No se crean variables sufijadas `$nombre_sql`.

## Resumen del comportamiento

| Disparador | Acción |
|---|---|
| Usuario aprieta EXTRAER con rango de fechas | Recorre Gmail día por día con `has:attachment`, descarta `compras2@divaflor.com` |
| Mensaje pasa filtros (es factura o tiene adjunto válido sin palabra prohibida) | INSERT en `correo_facturas_fincas` (dedup por `IDCORREO`) |
| Mensaje insertado | Por cada adjunto PDF/XLSX/XLS: INSERT en `archivo_correo` con LONGBLOB (dedup por `IDCORREO+NOMBREARCHIVO`) |
| Usuario aprieta ACTUALIZAR o cambia ordenamiento | AJAX a `lista_correos_facturas`, devuelve HTML de tabla con últimos 5 días |
| Usuario clickea icono mail | Dialog modal con iframe a `ver_cuerpo.php?codigo=N` |
| Usuario clickea icono PDF | Dialog modal con iframe a `ver_adjunto.php?codigo=N`, navegador renderiza nativo |

## Lo que este subsistema NO hace (y vive en otra parte)

- **Extracción estructurada de los datos de la factura** (cabecera, líneas, totales) → eso lo hacen los scripts `procesa_factura_*.php` (Haiku / Sonnet / GLM / Gemini). Ese paso lee `archivo_correo.ARCHIVO` por `CODIGO`, manda el binario a la API de IA, y devuelve JSON estructurado.
- **Asignación `CODIGOFINCA`** al correo → hoy en día se hace manual desde la consola (queda en null al insertar).
- **Marcado como procesado** (`FECHAPROCESADO`, `ESTADO != 1`) → flujo posterior de revisión humana.
