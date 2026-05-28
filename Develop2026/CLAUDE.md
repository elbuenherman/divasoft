---
name: mi-estilo-php-v3
description: "Estilo de programación PHP/MySQL/JS/jQuery v3 (actualizado 2026-05) para el proyecto DivaSOFT. Stack: PHP 8, MySQL, mysqli, jQuery, Select2, Flatpickr. SIEMPRE usar este skill para cualquier código PHP, MySQL, JavaScript, jQuery, HTML o CSS de este proyecto. Aplica a crear consolas nuevas, funciones, llamadas AJAX, queries SQL, formularios HTML, validaciones, trazabilidad, calendarios desplegables, selects con buscador. OBLIGATORIO para programación en este proyecto."
---

# Estilo de Programación

Stack: PHP 8, MySQL, mysqli, JavaScript, jQuery, HTML, CSS.
Arquitectura: archivo de consola (`.php`) + dispatcher (`funciones_ajax.php`) + librería de funciones (`funciones.php`).

---

## 0. Convivencia de estilos — LÉASE PRIMERO (CRÍTICO)

El proyecto tiene código antiguo (estilo viejo) y código nuevo (este estilo). Conviven. No se migra todo de golpe.

### Reglas de convivencia
- **Código nuevo → estilo nuevo. Código viejo → se queda como está** hasta que haya que tocarlo por otra razón.
- **NUNCA reescribir funciones existentes solo por estilo.** Convivencia, no migración masiva.
- **PROHIBIDO** hacer cambios tipo "cámbiame todos los SELECT del proyecto" o "convierte todo a mayúsculas" de un tirón. Eso dispersa el cambio por muchos archivos y rompe código que ya funciona (típicamente con `null` silencioso, sin lanzar error).
- Si el usuario pide migrar algo viejo, se hace **una función completa a la vez, con toda su cola** (ver abajo), y SIEMPRE mostrando primero la lista de lo que se va a tocar antes de tocar nada.

### Diferencia clave de estilos: claves de arreglo
- **Estilo viejo:** claves de arreglos PHP y JSON en minúsculas con guión bajo (`$respuesta['codigo_pais']`).
- **Estilo nuevo:** claves de arreglos PHP y JSON SIEMPRE en MAYÚSCULAS sin guiones (`$respuesta['CODIGOPAIS']`).
- **Las columnas y tablas reales de MySQL NO se renombran.** Siguen en minúsculas con guión bajo (`codigo_pais`, `nombre_proveedor`). El puente entre la columna real y la clave nueva se hace en el SELECT con `AS`.

```php
$sql = "SELECT
    codigo_pais AS CODIGOPAIS,
    nombre_pais AS NOMBREPAIS
    FROM pais
    WHERE codigo_pais = ".$codigo;
$fila = mysqli_fetch_array($resultado);
$respuesta['CODIGOPAIS'] = $fila['CODIGOPAIS'];
$respuesta['NOMBREPAIS'] = $fila['NOMBREPAIS'];
```

### Migrar una función vieja = función + TODA su cola
Cambiar un SELECT NO es un cambio aislado. Toca toda la cadena dentro de la función Y todo lo que la consume:
1. El `SELECT` (agregar `AS MAYUSCULAS`).
2. TODOS los `$fila[...]` que leen ese resultado.
3. TODOS los `$respuesta[...]` que arman la salida.
4. TODO el código que LLAMA a la función o que lee sus llaves: otras funciones PHP, el dispatcher, y el **JavaScript de las consolas** que consume el JSON (ej. `datos.codigo_proveedor` → `datos.CODIGOPROVEEDOR`).

**Procedimiento obligatorio al migrar:** primero buscar y MOSTRAR al usuario la lista completa de lugares afectados (PHP y JS). No cambiar nada hasta que el usuario confirme. Migrar de a una función, probar, y recién seguir con la siguiente.

---

## 1. PHP 8 + mysqli — Base de datos

La API antigua `mysql_*` NO existe en PHP 8. Usar SIEMPRE `mysqli_*`. **Ojo con el orden de parámetros**, es distinto al de `mysql_*`:

| Operación        | mysqli (PHP 8)                              |
|------------------|---------------------------------------------|
| Query            | `mysqli_query($link, $sql)`                 |
| Escape           | `mysqli_real_escape_string($link, $valor)`  |
| Fetch            | `mysqli_fetch_array($resultado)`            |
| Num. filas       | `mysqli_num_rows($resultado)`               |
| Filas afectadas  | `mysqli_affected_rows($link)`               |
| Último ID        | `mysqli_insert_id($link)`                   |

`$link` siempre primero en `query` y `real_escape_string`. Conexión ya establecida globalmente:
```php
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");
```

En cada función que toca BD: `global $link;` como primera línea.

---

## 2. Arquitectura de archivos

### Consola (`consola_entidad.php`)
Un solo archivo: PHP de inicialización, HTML, CSS inline, JavaScript y jQuery.
```
<?php includes y verificación de permisos ?>
<!DOCTYPE html ...>
<head> ... CSS ... </head>
<style> ... estilos de la consola ... </style>
<script language="javascript">
... variables globales JS ...
$(document).ready(function() { ... });
... funciones JS/jQuery ...
</script>
<body class="metro">
... HTML de la consola ...
</body>
```

### Dispatcher (`funciones_ajax.php`)
Recibe `$funcion`, `$parametro1` ... `$parametroN` por GET y hace echo del resultado:
```php
if($funcion == 'lista_entidad')
    echo lista_entidad($parametro1, $parametro2);
if($funcion == 'devuelve_entidad')
    echo devuelve_entidad($parametro1);
if($funcion == 'grabar_entidad')
    echo grabar_entidad($parametro1, $parametro2, $parametro3, $parametro4, $parametro5);
```

### Funciones (`funciones.php`)
Funciones PHP puras que reciben parámetros y retornan HTML o JSON.

---

## 3. Convenciones de nomenclatura

### PHP — variables
- Nombre completo, sin abreviaturas.
- Minúsculas, palabras separadas por guión bajo.
- `$codigo_agencia`, `$resultado_agencias`, `$numero_agencias`.

### JavaScript — variables
- Siempre declaradas con `var`.
- Nombre completo, minúsculas, separadas con guión bajo.
- `var codigo_persona`, `var nombre_agencia`, `var url`.

### IDs de HTML
- Siempre con prefijo `id_`.
- Minúsculas, separados por guión bajo.
- `id_codigo_agencia`, `id_listado_agencias`, `id_formulario_agencia`, `id_espera`.

### Columnas SQL y claves de arreglos PHP (código NUEVO)
- Claves de arreglos PHP y JSON: SIEMPRE en MAYÚSCULAS sin guiones (`CODIGO`, `NOMBREAGENCIA`).
- Columnas/tablas reales de MySQL: minúsculas con guión bajo, NO se renombran.
- Puente con `AS` en el SELECT.

---

## 4. Indentación y llaves — CRÍTICO

Las llaves de apertura y cierre van en su **propia línea**, al mismo nivel de indentación que el bloque que abren. El contenido va indentado 4 espacios hacia adentro.

```php
function grabar_agencia($codigo, $nombre)
    {
    global $link;
    if($codigo != 0)
        {
        $sql = "SELECT ...";
        $resultado = mysqli_query($link, $sql);
        }
    else
        {
        $campos_modificados = "NUEVO REGISTRO";
        }
    }
```

```javascript
function actualiza_listado()
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=lista_entidad";
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado").html(data);
        });
    }
```

Aplica a: funciones, if/else, for, callbacks jQuery — **todas las llaves**.

---

## 5. Bucles — SIEMPRE `for`, índice desde 1

**Nunca usar `while`, `foreach`, ni ningún otro bucle.** El índice del contador **siempre empieza en 1**, no en 0.

```php
for($i=1; $i<=$numero_agencias; $i++)
    {
    $fila = mysqli_fetch_array($resultado_agencias);
    $arreglo[$i]['CODIGO'] = $fila['CODIGO'];
    $arreglo[$i]['NOMBRE'] = $fila['NOMBRE'];
    }
```

```javascript
for(var i=1; i<=total; i++)
    {
    // procesamiento
    }
```

---

## 6. SQL

### Formato de SELECT (código nuevo, con AS en MAYÚSCULAS)
```php
$sql_agencia = "SELECT
    codigo AS CODIGO,
    nombreagencia AS NOMBREAGENCIA,
    direccionagencia AS DIRECCIONAGENCIA
    FROM agencia
    WHERE codigo = ".$codigo_agencia;
```

Reglas:
- Siempre usar `AS` con alias en MAYÚSCULAS (aunque el alias se parezca a la columna).
- Columnas/tablas reales en minúsculas (como están en la BD).
- Cada columna en su propia línea, indentada.
- `FROM`, `WHERE`, `ORDER BY` en su propia línea.
- **No usar JOINs** salvo que sea absolutamente imprescindible. Preferir múltiples queries simples.

### INSERT con ON DUPLICATE KEY UPDATE (upsert)
```php
$nombre    = mysqli_real_escape_string($link, $nombre);
$direccion = mysqli_real_escape_string($link, $direccion);
$sql = "INSERT INTO agencia
    (codigo, nombreagencia, direccionagencia)
    VALUES
    (".$codigo.", '".$nombre."', '".$direccion."')
    ON DUPLICATE KEY UPDATE
    nombreagencia = '".$nombre."',
    direccionagencia = '".$direccion."'";
```

### Arreglos de resultados
```php
$arreglo_agencias = array();
for($i=1; $i<=$numero_agencias; $i++)
    {
    $fila = mysqli_fetch_array($resultado_agencias);
    $arreglo_agencias[$i]['CODIGO']        = $fila['CODIGO'];
    $arreglo_agencias[$i]['NOMBREAGENCIA'] = $fila['NOMBREAGENCIA'];
    }
```

### Escape de valores para SQL — NO crear variables sufijadas
NUNCA crear variables temporales con sufijos como `$nombre_sql`. Dos opciones válidas:

**Opción 1 (preferida):** sobrescribir la misma variable con el valor escapado:
```php
$nombre = mysqli_real_escape_string($link, $nombre);
$sql = "UPDATE agencia SET nombre = '".$nombre."' WHERE codigo = ".$codigo;
```

**Opción 2:** `mysqli_real_escape_string()` inline:
```php
$sql = "UPDATE agencia SET nombre = '".mysqli_real_escape_string($link, $nombre)."' WHERE codigo = ".$codigo;
```

### Valores NULL en SQL
```php
$valor_codigo_destino = ($codigo_destino == 0) ? "NULL" : $codigo_destino;
$sql = "INSERT INTO vuelo (codigolocalidad) VALUES (".$valor_codigo_destino.")";
```

### Validación de ordenamiento
Siempre validar contra un array de campos permitidos antes de usar en ORDER BY:
```php
$campos_permitidos = array(1 => 'codigo', 'nombreagencia', 'direccionagencia');
$total_campos = count($campos_permitidos);
$ordenamiento_valido = 'nombreagencia';
for($i=1; $i<=$total_campos; $i++)
    {
    if($campos_permitidos[$i] == $ordenamiento)
        {
        $ordenamiento_valido = $ordenamiento;
        break;
        }
    }
```

---

## 7. AJAX en JavaScript/jQuery

Patrón estándar — siempre igual:
```javascript
function devuelve_agencia(codigo)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_agencia&parametro1="+codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        var datos = JSON.parse(data);
        $("#id_codigo_agencia").val(datos.CODIGO);
        $("#id_nombre_agencia").val(datos.NOMBREAGENCIA);
        $("#id_nombre_agencia").focus();
        });
    }
```

Para llamadas que graban y reciben respuesta `OK:` o `ERROR:`:
```javascript
obj_ajax.success(function(data, status)
    {
    $("#id_espera").hide();
    if(data.substring(0, 2) == "OK")
        {
        messageBox("Registro grabado correctamente");
        boton_nuevo();
        actualiza_listado();
        }
    else
        {
        messageBox("Error al grabar: " + data);
        }
    });
```

---

## 8. HTML de consolas

### Layout principal — dos columnas con flexbox
```html
<div style="display: flex; flex-direction: row; margin-top:10px; margin-left:5px; gap: 5px;">
    <!-- LISTADO A LA IZQUIERDA -->
    <div id="id_listado_1" class="aida" style="width: 830px; height: 800px; overflow: scroll;">
        <div class="ribbed-crimson" style="height: 2px;"></div>
        <span><center><strong>LISTADO DE ENTIDADES</strong></center></span>
        ...
    </div>
    <!-- COLUMNA DERECHA -->
    <div style="display: flex; flex-direction: column; gap: 0px;">
        <div id="id_formulario_entidad" class="aida" style="width: 350px; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong>DATOS DE LA ENTIDAD</strong></center></span>
            ...
        </div>
    </div>
</div>
```

### Formularios — tabla de dos columnas
```html
<table style="width: 100%; font-size: 12px;">
    <colgroup>
        <col style="width: 35%;">
        <col style="width: 65%;">
    </colgroup>
    <tr>
        <td style="text-align: right; padding-right: 5px; padding-bottom: 3px;">NOMBRE:</td>
        <td style="padding-bottom: 3px;">
            <input type="text" id="id_nombre_entidad" style="width: 95%; font-size: 12px;" maxlength="128" />
        </td>
    </tr>
</table>
```

### Campo CÓDIGO (siempre readonly, estilo especial)
```html
<input type="text" id="id_codigo_entidad" readonly
    style="border: none; font-weight: bold; background: transparent; padding: 0; margin: 0;
           width: 60px; outline: none; font-size: 13px; color: #88010e;" value="0" />
```

### Campos numéricos — validación inline en onkeypress
```html
<input type="text" id="id_campoc1" style="width: 95%; font-size: 12px; text-align: right;"
    value="0.00" onfocus="this.select()"
    onkeypress="return ((event.charCode >= 48 && event.charCode <= 57) || (event.charCode == 46 && this.value.indexOf('.') == -1))" />
```

### Botones
```html
<div style="text-align: right; margin-top: 10px;">
    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_entidad();">Grabar</button>
    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">Nuevo</button>
</div>
```

### Tabla de listado generada en PHP
```php
$html = '<table style="width: 100%; border-collapse: collapse;">';
$html .= '<thead style="background-color: #970202ff; color:white;">';
$html .= '<tr>';
$html .= '<th class="text-center" style="width:5%; font-size:11px; padding:4px; cursor:pointer;" onclick="ordenar_por(\'CODIGO\')">COD';
if($ordenamiento_valido == 'CODIGO') $html .= ($direccion_valida == 'ASC') ? " &#9650;" : " &#9660;";
$html .= '</th>';
$html .= '</thead>';
$html .= '<tbody>';
for($i=1; $i<=$numero; $i++)
    {
    $html .= '<tr class="fila_entidad">';
    $html .= '<td class="text-center" style="font-size:11px; padding:3px;">'.$arreglo[$i]['CODIGO'].'</td>';
    $nombre_corto = (strlen($arreglo[$i]['NOMBRE']) > 15) ? substr($arreglo[$i]['NOMBRE'], 0, 15).'...' : $arreglo[$i]['NOMBRE'];
    $html .= '<td style="font-size:11px; padding:3px;" title="'.$arreglo[$i]['NOMBRE'].'"><strong>'.$nombre_corto.'</strong></td>';
    $html .= '<td class="text-center" style="font-size:14px; padding:3px;">';
    $html .= '<a href="javascript: devuelve_entidad('.$arreglo[$i]['CODIGO'].')"><i title="Modificar" class="icon-pencil fg-brown"></i></a>';
    $html .= '</td>';
    $html .= '</tr>';
    }
$html .= '</tbody></table>';
$html .= '<div style="text-align:right; font-size:11px; color:#666; padding:5px;">Total: '.$numero.' registros</div>';
```

---

## 9. CSS estándar de consolas

```css
body.metro {
    background-color: #edededff !important;
    background-image: none !important;
    }
.fila_entidad {
    font-size: 12px;
    }
.fila_entidad:hover {
    border: 0.5px solid rgba(220, 53, 69, 0.2) !important;
    box-shadow: 0 0 8px rgba(220, 53, 69, 0.2);
    background-color: rgba(255, 240, 240, 0.9) !important;
    transition: all 0.3s ease-in-out;
    cursor: pointer;
    }
textarea, input[type="text"] {
    text-transform: uppercase;
    }
::-webkit-scrollbar { display: none; }
* { scrollbar-width: none; }
#id_espera {
    display:    none;
    position:   fixed;
    z-index:    1000;
    top:        0;
    left:       0;
    height:     100%;
    width:      100%;
    background: rgba( 255, 255, 255, .8 ) url('http://i.stack.imgur.com/FhHRx.gif') 50% 50% no-repeat;
    }
.myTitleClass .ui-dialog-titlebar { background: #88010e; color: #FFFFFF; }
.ui-button.cancelButton { background: #88010e; color: #FFFFFF; }
```

---

## 10. messageBox y dialog jQuery UI

Siempre incluir en el HTML:
```html
<div id="dialog" title="Alerta"></div>
<div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>
```

Inicialización en `$(document).ready`:
```javascript
$(document).ready(function()
    {
    $("#id_espera").hide();
    $("#dialog").dialog(
        {
        modal: true,
        buttons: [{text: "Aceptar", class: 'cancelButton', click: function() {$(this).dialog("close");}}],
        autoOpen: false, dialogClass: 'myTitleClass'
        });
    actualiza_listado();
    });
```

Función `messageBox`:
```javascript
function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }
```

---

## 11. Trazabilidad

Toda grabación debe registrar trazabilidad con `registra_transaccion()`. Antes de grabar, comparar valores anteriores vs nuevos y construir `$campos_modificados` con el formato:
```
CAMPO: [valor_anterior] -> [valor_nuevo], CAMPO2: [valor_anterior] -> [valor_nuevo]
```

```php
$tipo_accion = ($codigo == 0) ? "ING" : "MOD";
registra_transaccion(
    $codigo_tipo_evento, $codigo_usuario, 0, 0, 0, 0,
    $tipo_accion." ENTIDAD: ".$nombre,
    $campos_modificados, "",
    $codigo_final, 0, 0, 0, 0,
    $campoc1, $campoc2, 0, $tipo_accion, $nombre, "", "");
```

---

## 12. Función `boton_nuevo()`

Siempre existe para limpiar el formulario. El código va a `"0"` y los numéricos a `"0.00"`:
```javascript
function boton_nuevo()
    {
    $("#id_codigo_entidad").val("0");
    $("#id_nombre_entidad").val("");
    $("#id_campoc1_entidad").val("0.00");
    $("#id_nombre_entidad").focus();
    }
```

---

## 13. Ordenamiento de columnas

Variables globales al inicio del script:
```javascript
var global_ordenamiento = 'NOMBREENTIDAD';
var global_direccion = 'ASC';
```

Función estándar:
```javascript
function ordenar_por(campo)
    {
    if(global_ordenamiento == campo)
        {
        if(global_direccion == 'ASC')
            global_direccion = 'DESC';
        else
            global_direccion = 'ASC';
        }
    else
        {
        global_ordenamiento = campo;
        global_direccion = 'ASC';
        }
    actualiza_listado();
    }
```

---

## 14. Filtrado local (sin AJAX)

```javascript
function filtrar_entidades()
    {
    var texto = $("#id_filtro_entidad").val().toUpperCase();
    $("#id_listado_entidades table tbody tr").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }
```

HTML del input de filtro:
```html
<input type="text" id="id_filtro_entidad" style="width: 250px; font-size: 12px;"
    placeholder="Buscar..." onkeyup="filtrar_entidades();" />
```

---

## 15. Paleta de colores del sistema

| Uso | Valor |
|-----|-------|
| Color principal (crimson) | `#88010e` / `#970202ff` |
| Hover fila | `rgba(255, 240, 240, 0.9)` |
| Fondo body | `#edededff` |
| Thead tablas | `background-color: #970202ff; color: white` |

---

## 16. Select2 — SELECTs con buscador

### Includes en el `<head>`
```html
<link href="css/select2.min.css" rel="stylesheet" />
<script src="js/select2.min.js"></script>
```

### Inicialización en `$(document).ready()`
```javascript
$('#id_codigo_aerolinea').select2(
    {
    width: '220px',
    minimumResultsForSearch: 3,
    dropdownAutoWidth: false,
    dropdownCssClass: 'dropdown-alineado-derecha',
    placeholder: "-- SELECCIONE AEROLINEA --"
    });
```

### Setear valor en Select2 desde código
SIEMPRE usar `.trigger('change')` después de `.val()`:
```javascript
$("#id_codigo_aerolinea").val(datos.CODIGOAEROLINEA).trigger('change');
```

---

## 17. Flatpickr — Calendarios desplegables

### Inicialización
```javascript
flatpickr("#id_fecha_entidad",
    {
    dateFormat: "Y-m-d",
    locale: "es",
    defaultDate: "<?php echo $fecha_hoy; ?>",
    allowInput: false
    });
```

### HTML del campo de fecha
```html
<div style="position: relative; width: 220px;">
    <input type="text" id="id_fecha_entidad" class="input_pequeno"
        style="width: 220px; background-color: #fff; cursor: pointer; padding-right: 28px;"
        value="<?php echo $fecha_hoy; ?>" placeholder="Click para seleccionar fecha" />
    <i class="icon-calendar" style="color: #88010e; font-size: 16px; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); pointer-events: none;" title="Seleccionar fecha"></i>
</div>
```

### CSS estándar de Flatpickr (estilo crimson)
```css
.flatpickr-calendar {
    font-size: 11px !important;
    border: 2px solid #88010e !important;
    }
.flatpickr-months,
.flatpickr-months .flatpickr-month,
.flatpickr-weekdays {
    background: #88010e !important;
    }
.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: #88010e !important;
    border-color: #88010e !important;
    color: #ffffff !important;
    font-weight: bold !important;
    }
```

---

## 18. Validaciones servidor + cliente — IDÉNTICAS

Las validaciones SIEMPRE se hacen en ambos lados con los mismos mensajes.

### Cliente (JS)
```javascript
function valida_formulario()
    {
    if($("#id_codigo_aerolinea").val() == "0" || $("#id_codigo_aerolinea").val() == null)
        return "Por favor seleccione una aerolinea";
    if($("#id_nombre_entidad").val().trim() == "")
        return "Por favor ingrese un nombre";
    return "OK";
    }

function grabar_entidad()
    {
    var mensaje = valida_formulario();
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    // ... continuar con el AJAX
    }
```

### Servidor (PHP)
Mismas validaciones, mismos mensajes — defensa contra peticiones manipuladas:
```php
function grabar_entidad($codigo, $nombre, $codigo_aerolinea)
    {
    if($codigo_aerolinea == 0 || $codigo_aerolinea == "")
        return "Por favor seleccione una aerolinea";
    if(trim($nombre) == "")
        return "Por favor ingrese un nombre";
    // ... continuar con INSERT/UPDATE
    }
```

### Restricción de caracteres en inputs
```html
<input type="text" id="id_nombre_vuelo" class="input_pequeno" maxlength="32"
    style="text-transform: uppercase;"
    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9\.\-]/g, '');" />
```

Validar también en servidor con regex:
```php
$nombre = strtoupper(trim($nombre));
if(!preg_match('/^[A-Z0-9\.\-]+$/', $nombre))
    return "El nombre solo puede contener letras, numeros, puntos y guiones";
```
