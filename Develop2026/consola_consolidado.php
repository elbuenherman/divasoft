<?php
include("variables_globales.php"); 
include("funciones.php");
include("valida_sesion.php");  
// CHEQUEO PERMISOS  
$permiso[] = NULL;       
consulta_permisos($_SESSION['s_codigo'], $permiso);
$usuario_web = $_SESSION['s_codigo'];

$fecha_hoy      = date("Y-m-d");
$fecha_hora_hoy = date("Y-m-d H:i:s");

// Conectar a la BD para cargar los catalogos inline en los Select2.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// MARCACIONES: ya NO se precargan. El Select2 arranca vacio y se llena al
// seleccionar un CLIENTE (AJAX a opciones_marcaciones_por_cliente_dsft).

// CLIENTES.
$sql_clientes = "SELECT CODIGO, NOMBRECLIENTE FROM cliente WHERE ESTADO >= 0 ORDER BY NOMBRECLIENTE";
$resultado_clientes = mysqli_query($link, $sql_clientes);
$numero_clientes    = $resultado_clientes ? mysqli_num_rows($resultado_clientes) : 0;
$arreglo_clientes   = array();
for($i=0; $i<$numero_clientes; $i++)
    {
    $fila = mysqli_fetch_array($resultado_clientes);
    $arreglo_clientes[$i]['CODIGO']        = $fila['CODIGO'];
    $arreglo_clientes[$i]['NOMBRECLIENTE'] = $fila['NOMBRECLIENTE'];
    }

// TRUCKS.
$sql_trucks = "SELECT CODIGO, NOMBRETRUCK FROM truck WHERE ESTADO >= 0 ORDER BY NOMBRETRUCK";
$resultado_trucks = mysqli_query($link, $sql_trucks);
$numero_trucks    = $resultado_trucks ? mysqli_num_rows($resultado_trucks) : 0;
$arreglo_trucks   = array();
for($i=0; $i<$numero_trucks; $i++)
    {
    $fila = mysqli_fetch_array($resultado_trucks);
    $arreglo_trucks[$i]['CODIGO']      = $fila['CODIGO'];
    $arreglo_trucks[$i]['NOMBRETRUCK'] = $fila['NOMBRETRUCK'];
    }

// PAISES (legacy: minusculas con guion bajo).
$sql_paises = "SELECT codigo_pais AS CODIGOPAIS, nombre_pais AS NOMBREPAIS FROM pais ORDER BY nombre_pais";
$resultado_paises = mysqli_query($link, $sql_paises);
$numero_paises    = $resultado_paises ? mysqli_num_rows($resultado_paises) : 0;
$arreglo_paises   = array();
for($i=0; $i<$numero_paises; $i++)
    {
    $fila = mysqli_fetch_array($resultado_paises);
    $arreglo_paises[$i]['CODIGOPAIS'] = $fila['CODIGOPAIS'];
    $arreglo_paises[$i]['NOMBREPAIS'] = $fila['NOMBREPAIS'];
    }

// AGENCIAS = proveedor con codigo_tipo_proveedor = 3 (legacy).
$sql_agencias = "SELECT codigo_proveedor AS CODIGOAGENCIA, nombre_proveedor AS NOMBREAGENCIA
    FROM proveedor WHERE codigo_tipo_proveedor = 3 ORDER BY nombre_proveedor";
$resultado_agencias = mysqli_query($link, $sql_agencias);
$numero_agencias    = $resultado_agencias ? mysqli_num_rows($resultado_agencias) : 0;
$arreglo_agencias   = array();
for($i=0; $i<$numero_agencias; $i++)
    {
    $fila = mysqli_fetch_array($resultado_agencias);
    $arreglo_agencias[$i]['CODIGOAGENCIA'] = $fila['CODIGOAGENCIA'];
    $arreglo_agencias[$i]['NOMBREAGENCIA'] = $fila['NOMBREAGENCIA'];
    }

// GUIAS recientes (ultimos 15 dias) para el Select2 con tags.
// Si la usuaria escribe un NUMEROGUIA nuevo, Select2 lo manda como string;
// el backend crea la guia.
$sql_guias = "SELECT CODIGO, NUMEROGUIA FROM guia
    WHERE ESTADO >= 0 AND FECHAREGISTRO >= DATE_SUB(NOW(), INTERVAL 15 DAY)
    ORDER BY NUMEROGUIA";
$resultado_guias = mysqli_query($link, $sql_guias);
$numero_guias    = $resultado_guias ? mysqli_num_rows($resultado_guias) : 0;
$arreglo_guias   = array();
for($i=0; $i<$numero_guias; $i++)
    {
    $fila = mysqli_fetch_array($resultado_guias);
    $arreglo_guias[$i]['CODIGO']     = $fila['CODIGO'];
    $arreglo_guias[$i]['NUMEROGUIA'] = $fila['NUMEROGUIA'];
    }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Consolidados</title>
<?php include("css_v4.php"); ?>
<script language="javascript" src="controles_especiales.js"></script>
<script type="text/javascript" src="js/jquery.mask.min.js"></script>
<!-- Select2 -->
<link href="css/select2.min.css" rel="stylesheet" />
<script src="js/select2.min.js"></script>
<style>
body.metro {
    background-color: #edededff !important;
    background-image: none !important;
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

/* ===== Grid de consolidados ===== */
.grid_consolidados {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_consolidados thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_consolidados thead th {
    font-size: 11px;
    padding: 8px 5px;
    text-align: center;
    border-right: 1px solid white;
    user-select: none;
    background-color: rgb(154, 22, 22);
    color: white;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_consolidados tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_consolidados tbody .grupo_consolidado:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_consolidados tbody .td_centro {
    text-align: center;
    }
.grid_consolidados tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_consolidados tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
.grid_consolidados tbody .grupo_consolidado {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_consolidados tbody .grupo_consolidado:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_consolidados tbody .grupo_consolidado_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_consolidados tbody .grupo_consolidado_seleccionado td:first-child {
    box-shadow: inset 4px 0 0 #88010e !important;
    }

.input_pequeno {
    width: 100%;
    font-size: 13px;
    padding: 5px 6px;
    border: 1px solid #c0c0c0;
    border-radius: 2px;
    height: 30px;
    box-sizing: border-box;
    }
.input_pequeno:focus {
    outline: none;
    border-color: #88010e;
    box-shadow: 0 0 3px rgba(136, 1, 14, 0.3);
    }
.input_readonly {
    width: 100%;
    font-size: 12px;
    padding: 5px 6px;
    border: 1px solid #e0e0e0;
    border-radius: 2px;
    height: 28px;
    box-sizing: border-box;
    background-color: #f4f4f4;
    color: #555;
    text-transform: none;
    }
.fg-brown { color: #8a5048; }
.fg-darkRed { color: #88010e; }
.fg-teal { color: #155a60; }

/* ===== Select2 ===== */
.select2-selection__rendered {
    font-size: 13px !important;
    line-height: 30px !important;
    }
.select2-container .select2-selection--single {
    height: 32px !important;
    border: 1px solid #c0c0c0 !important;
    border-radius: 2px !important;
    }
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 30px !important;
    }
.select2-results__option {
    font-size: 13px;
    padding: 5px 12px !important;
    }
.select2-container--open .select2-selection--single {
    border-color: #88010e !important;
    }
/* TRUCK auto-llenado segun MARCACION: visualmente readonly. */
.select2_truck_readonly + .select2-container .select2-selection--single {
    background-color: #f4f4f4 !important;
    color: #555 !important;
    }

/* ===== Flatpickr - estilo crimson ===== */
.flatpickr-calendar {
    font-size: 11px !important;
    border: 2px solid #88010e !important;
    }
.flatpickr-months,
.flatpickr-months .flatpickr-month,
.flatpickr-weekdays {
    background: #88010e !important;
    }
.flatpickr-weekday {
    background: #88010e !important;
    color: #ffffff !important;
    font-weight: 500 !important;
    }
.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: #88010e !important;
    border-color: #88010e !important;
    color: #ffffff !important;
    font-weight: bold !important;
    }
.flatpickr-day.today {
    border-color: #88010e !important;
    font-weight: bold !important;
    }
.flatpickr-day:hover:not(.selected) {
    background: #DC143C !important;
    border-color: #88010e !important;
    color: #fff !important;
    }
</style>
<script language="javascript">
var global_codigo_seleccionado = 0;
var global_ordenamiento = "FECHAVUELO";
var global_direccion = "DESC";
var global_codigo_usuario = <?php echo (int)$_SESSION['s_codigo']; ?>;
var flatpickr_fechavuelo = null;
var flatpickr_rango_filtro = null;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }

// ===== Filtro local por texto =====
function filtrar_listado_local_consolidado()
    {
    var texto = $("#id_busqueda_consolidado").val().toUpperCase();
    $("#id_listado_consolidados .grupo_consolidado").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente el consolidado seleccionado =====
function marca_consolidado_seleccionado()
    {
    $("#id_listado_consolidados .grupo_consolidado").removeClass("grupo_consolidado_seleccionado");
    if(global_codigo_seleccionado > 0)
        $("#id_grupo_consolidado_"+global_codigo_seleccionado).addClass("grupo_consolidado_seleccionado");
    }

// ===== Trae el consolidado y llena el formulario =====
function devuelve_consolidado(codigo)
    {
    global_codigo_seleccionado = codigo;
    marca_consolidado_seleccionado();
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_consolidado_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        var datos = JSON.parse(data);
        if(datos.ERROR)
            {
            messageBox(datos.ERROR);
            return;
            }
        $("#id_codigo_consolidado").val(datos.CODIGO || "");
        if(flatpickr_fechavuelo)
            {
            if(datos.FECHAVUELO)
                flatpickr_fechavuelo.setDate(datos.FECHAVUELO);
            else
                flatpickr_fechavuelo.clear();
            }
        var pais_val = (datos.CODIGOPAIS && parseInt(datos.CODIGOPAIS) > 0) ? datos.CODIGOPAIS.toString() : "0";
        $("#id_codigopais").val(pais_val).trigger('change');
        var ag_val = (datos.CODIGOAGENCIA && parseInt(datos.CODIGOAGENCIA) > 0) ? datos.CODIGOAGENCIA.toString() : "0";
        $("#id_codigoagencia").val(ag_val).trigger('change');
        $("#id_observaciones").val(datos.OBSERVACIONES || "");
        if(parseInt(datos.ESTADO) == 0)
            {
            $("#id_estado_activo").prop('checked', false);
            $("#id_estado_inactivo").prop('checked', true);
            }
        else
            {
            $("#id_estado_activo").prop('checked', true);
            $("#id_estado_inactivo").prop('checked', false);
            }

        // CLIENTE primero: setear el select sin disparar el handler (para evitar
        // limpiar MARCACION/TRUCK). Luego pedir las marcaciones de ese cliente,
        // y en su callback setear MARCACION + TRUCK del consolidado.
        var cli_val = (datos.CODIGOCLIENTE && parseInt(datos.CODIGOCLIENTE) > 0) ? datos.CODIGOCLIENTE.toString() : "0";
        $("#id_codigocliente").val(cli_val).trigger('change.select2');
        if(parseInt(cli_val) > 0)
            {
            $.get("funciones_ajax.php?funcion=opciones_marcaciones_por_cliente_dsft&parametro1=" + cli_val, function(opts)
                {
                $("#id_codigomarcacion").html(opts);
                var marc_val = (datos.CODIGOMARCACION && parseInt(datos.CODIGOMARCACION) > 0) ? datos.CODIGOMARCACION.toString() : "0";
                $("#id_codigomarcacion").val(marc_val).trigger('change.select2');
                var truck_val = (datos.CODIGOTRUCK && parseInt(datos.CODIGOTRUCK) > 0) ? datos.CODIGOTRUCK.toString() : "0";
                $("#id_codigotruck").val(truck_val).trigger('change.select2');
                });
            }
        else
            {
            // Sin cliente -> MARCACION queda vacia y TRUCK en 0.
            $("#id_codigomarcacion").html('<option value="0">-- SELECCIONE --</option>').trigger('change.select2');
            $("#id_codigotruck").val("0").trigger('change.select2');
            }

        // Cargar las guias asociadas a este consolidado.
        cargar_guias_consolidado(parseInt(datos.CODIGO));

        // No hacer focus al campo de fecha (abre el Flatpickr).
        });
    }

// ===== Trazabilidad =====
function muestra_trazabilidad_consolidado(codigo)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=trazabilidad_consolidado_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        messageBox(data);
        });
    }

// ===== Eliminacion logica (ESTADO = -1) =====
function elimina_consolidado_dsft(codigo)
    {
    var r = confirm("Esta seguro de eliminar este consolidado?");
    if(r == true)
        {
        $("#id_espera").show();
        var url = "funciones_ajax.php?funcion=elimina_consolidado_dsft&parametro1=" + codigo + "&parametro2=" + global_codigo_usuario;
        var obj_ajax = $.get(url, function(data, status){;});
        obj_ajax.success(function(data, status)
            {
            $("#id_espera").hide();
            if(data.substring(0, 2) == "OK")
                {
                if(codigo == global_codigo_seleccionado)
                    boton_nuevo();
                actualiza_listado();
                }
            else
                {
                messageBox("Error al eliminar: " + data);
                }
            });
        }
    }

// ===== Validacion de formulario =====
// FECHA VUELO y MARCACION son obligatorios. GUIA ya NO es campo del formulario
// (se maneja por la seccion GUIAS separada).
function valida_formulario()
    {
    if($("#id_fechavuelo").val().trim() == "")
        return "Por favor ingrese la FECHA DE VUELO";
    var marc_sel = parseInt($("#id_codigomarcacion").val());
    if(isNaN(marc_sel) || marc_sel <= 0)
        return "Por favor seleccione la MARCACION";
    return "OK";
    }

// ===== Grabar (INSERT si codigo == 0, UPDATE si > 0) =====
function grabar_consolidado()
    {
    var mensaje = valida_formulario();
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    var estado_val = $('input[name="grp_estado"]:checked').val();
    if(estado_val == null || estado_val == "")
        estado_val = "1";
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=graba_consolidado_dsft"
        + "&parametro1="  + global_codigo_seleccionado
        + "&parametro2="  + encodeURIComponent($("#id_fechavuelo").val())
        + "&parametro3="  + $("#id_codigomarcacion").val()
        + "&parametro4="  + $("#id_codigocliente").val()
        + "&parametro5="  + $("#id_codigotruck").val()
        + "&parametro6="  + $("#id_codigopais").val()
        + "&parametro7="  + $("#id_codigoagencia").val()
        + "&parametro8="  + encodeURIComponent($("#id_observaciones").val())
        + "&parametro9="  + estado_val
        + "&parametro10=" + global_codigo_usuario;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.substring(0, 2) == "OK")
            {
            messageBox("Consolidado grabado correctamente");
            boton_nuevo();
            actualiza_listado();
            }
        else
            {
            messageBox("Error al grabar: " + data);
            }
        });
    }

// ===== Guias del consolidado (tabla guiaconsolidado, NxN con guia) =====
// Asocia una guia al consolidado actual. Si la usuaria selecciono una guia
// existente, el value es el CODIGO numerico. Si tipeo una nueva, Select2
// (tags:true) la manda como id == el texto -> el backend la crea.
function agregar_guia_consolidado()
    {
    var codigo_consolidado = global_codigo_seleccionado;
    if(codigo_consolidado <= 0)
        {
        messageBox("Primero grabe el consolidado antes de agregar guias.");
        return;
        }
    var select = $("#id_select_guia");
    var valor  = select.val();
    // allowClear hace que el valor vacio sea null o "" (no "0").
    if(!valor || valor == "")
        {
        messageBox("Seleccione o escriba una guia.");
        return;
        }

    // Si es un tag nuevo (no numerico = no es CODIGO de guia existente),
    // validar: solo numeros y guiones, max 15 chars.
    var es_nuevo = isNaN(parseInt(valor));
    if(es_nuevo)
        {
        if(valor.length > 15)
            {
            messageBox("La guia no puede tener mas de 15 caracteres.");
            return;
            }
        if(!/^[0-9\-]{1,15}$/.test(valor))
            {
            messageBox("La guia solo admite numeros y guiones (max 15 caracteres).");
            return;
            }
        }

    var url = "funciones_ajax.php?funcion=agregar_guia_consolidado_dsft"
        + "&parametro1=" + codigo_consolidado
        + "&parametro2=" + encodeURIComponent(valor)
        + "&parametro3=" + global_codigo_usuario;
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            cargar_guias_consolidado(codigo_consolidado);
            // Si era un tag nuevo (valor no numerico), recargar las options del
            // Select2 para que aparezca en futuras busquedas sin recargar pagina.
            if(isNaN(parseInt(valor)))
                {
                $.get("funciones_ajax.php?funcion=opciones_guias_recientes_dsft", function(data_opts)
                    {
                    // allowClear necesita una primera <option value=""></option>.
                    var default_opt = '<option value=""></option>';
                    $("#id_select_guia").html(default_opt + data_opts).val("").trigger("change.select2");
                    });
                }
            else
                {
                select.val("").trigger("change");
                }
            actualiza_listado();
            }
        else
            {
            messageBox(data);
            }
        });
    }

// Desasocia una guia del consolidado actual (no borra de tabla guia).
function quitar_guia_consolidado(codigo_guia)
    {
    var codigo_consolidado = global_codigo_seleccionado;
    if(codigo_consolidado <= 0)
        return;
    var url = "funciones_ajax.php?funcion=quitar_guia_consolidado_dsft"
        + "&parametro1=" + codigo_consolidado
        + "&parametro2=" + codigo_guia;
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            cargar_guias_consolidado(codigo_consolidado);
            actualiza_listado();
            }
        else
            {
            messageBox(data);
            }
        });
    }

// Trae el HTML de los badges con las guias del consolidado y lo pinta.
function cargar_guias_consolidado(codigo_consolidado)
    {
    if(codigo_consolidado <= 0)
        {
        $("#id_lista_guias_consolidado").html("");
        return;
        }
    var url = "funciones_ajax.php?funcion=lista_guias_consolidado_dsft"
        + "&parametro1=" + codigo_consolidado;
    $.get(url, function(data)
        {
        $("#id_lista_guias_consolidado").html(data);
        });
    }

// ===== Ordenar por columna =====
function ordenar_por(campo)
    {
    if(global_ordenamiento == campo)
        {
        if(global_direccion == "ASC")
            global_direccion = "DESC";
        else
            global_direccion = "ASC";
        }
    else
        {
        global_ordenamiento = campo;
        global_direccion = "ASC";
        }
    actualiza_listado();
    }

// ===== Trae el listado y lo pega en el div =====
function actualiza_listado()
    {
    $("#id_espera").show();

    // Leer rango de fechas del filtro Flatpickr; si esta vacio se envia "".
    var fecha_desde = "";
    var fecha_hasta = "";
    var rango = $("#id_rango_filtro_consolidado").val();
    if(rango)
        {
        var fechas = rango.match(/\d{4}-\d{2}-\d{2}/g);
        if(fechas && fechas.length >= 2)
            {
            fecha_desde = fechas[0];
            fecha_hasta = fechas[1];
            }
        }

    var url = "funciones_ajax.php?funcion=lista_consolidados_dsft"
        + "&parametro1=" + global_ordenamiento
        + "&parametro2=" + global_direccion
        + "&parametro3=" + fecha_desde
        + "&parametro4=" + fecha_hasta;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_consolidados").html(data);
        // Reaplicar el filtro de texto si el usuario tenia algo escrito.
        filtrar_listado_local_consolidado();
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_consolidado").val("");
    if(flatpickr_fechavuelo)
        flatpickr_fechavuelo.clear();
    // CLIENTE a 0 sin disparar el handler (para no encadenar un AJAX inutil).
    $("#id_codigocliente").val("0").trigger('change.select2');
    // MARCACION vuelve a solo "-- SELECCIONE --" (sin opciones de ningun cliente).
    $("#id_codigomarcacion").html('<option value="0">-- SELECCIONE --</option>').trigger('change.select2');
    $("#id_codigotruck").val("0").trigger('change.select2');
    $("#id_codigopais").val("0").trigger('change.select2');
    $("#id_codigoagencia").val("0").trigger('change.select2');
    $("#id_observaciones").val("");
    $("#id_estado_activo").prop('checked', true);
    $("#id_estado_inactivo").prop('checked', false);
    $("#id_listado_consolidados .grupo_consolidado").removeClass("grupo_consolidado_seleccionado");
    // Limpiar la seccion GUIAS (allowClear necesita value = "").
    $("#id_lista_guias_consolidado").html("");
    $("#id_select_guia").val("").trigger('change.select2');
    // No hacer focus al campo de fecha (abre el Flatpickr).
    }

$(document).ready(function()
    {
    $("#id_espera").hide();
    $("#dialog").dialog(
        {
        modal: true,
        width: 500,
        buttons: [{text: "Aceptar", class: 'cancelButton', click: function() {$(this).dialog("close");}}],
        autoOpen: false,
        dialogClass: 'myTitleClass'
        });

    // Flatpickr para FECHA VUELO (formulario). clickOpens controla que solo
    // se abra al clickear el input, no al recibir focus programatico.
    flatpickr_fechavuelo = flatpickr("#id_fechavuelo",
        {
        dateFormat: "Y-m-d",
        locale: "es",
        clickOpens: true,
        allowInput: false
        });

    // Flatpickr modo range para filtrar el grid por FECHAVUELO.
    // Al elegir 2 fechas o al limpiar, redispara actualiza_listado().
    flatpickr_rango_filtro = flatpickr("#id_rango_filtro_consolidado",
        {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "es",
        onChange: function(selectedDates, dateStr, instance)
            {
            if(selectedDates.length == 2)
                actualiza_listado();
            },
        onClose: function(selectedDates, dateStr, instance)
            {
            if(selectedDates.length == 2)
                actualiza_listado();
            else if(selectedDates.length == 0)
                actualiza_listado();
            }
        });

    // Select2 para los 5 catalogos.
    $('#id_codigomarcacion').select2({width: '100%', minimumResultsForSearch: 3, placeholder: "-- SELECCIONE --"});
    $('#id_codigocliente').select2(  {width: '100%', minimumResultsForSearch: 3, placeholder: "-- SELECCIONE --"});
    $('#id_codigotruck').select2(    {width: '100%', minimumResultsForSearch: 3, placeholder: "-- SELECCIONE --"});
    // TRUCK es readonly: el valor se hereda de la MARCACION elegida y el
    // usuario no puede abrir el dropdown. Setear por JS sigue funcionando
    // ($('#id_codigotruck').val(N).trigger('change.select2')).
    $('#id_codigotruck').on('select2:opening', function(e)
        {
        e.preventDefault();
        });
    $('#id_codigopais').select2(     {width: '100%', minimumResultsForSearch: 3, placeholder: "-- SELECCIONE --"});
    $('#id_codigoagencia').select2(  {width: '100%', minimumResultsForSearch: 3, placeholder: "-- SELECCIONE --"});
    // Select2 de guias con tags:true para permitir crear una guia nueva
    // escribiendo el NUMEROGUIA en el cuadro de busqueda.
    // createTag filtra el texto a numeros/guiones y limita a 15 chars antes
    // de aceptar el tag.
    $('#id_select_guia').select2(
        {
        width: '220px',
        tags: true,
        allowClear: true,
        placeholder: '-- Buscar o crear guia --',
        minimumResultsForSearch: 1,
        createTag: function(params)
            {
            var term = params.term.replace(/[^0-9\-]/g, '');
            if(term == '' || term.length > 15)
                return null;
            return { id: term, text: term, newTag: true };
            }
        });

    // Mascara: en el campo de busqueda del Select2, bloquear caracteres
    // distintos de numeros y guiones, y limitar a 15 chars.
    $('#id_select_guia').on('select2:open', function()
        {
        var searchField = document.querySelector('.select2-search__field');
        if(searchField)
            {
            searchField.setAttribute('maxlength', '15');
            searchField.setAttribute('autocomplete', 'off');
            searchField.addEventListener('input', function()
                {
                this.value = this.value.replace(/[^0-9\-]/g, '');
                });
            }
        });

    // Cuando cambia CLIENTE: cargar marcaciones de ese cliente via AJAX,
    // limpiar TRUCK. Si cliente == 0, vaciar marcaciones.
    $('#id_codigocliente').on('change', function()
        {
        var codigo_cliente = parseInt($(this).val());
        $("#id_codigotruck").val("0").trigger('change.select2');
        if(isNaN(codigo_cliente) || codigo_cliente <= 0)
            {
            $("#id_codigomarcacion").html('<option value="0">-- SELECCIONE --</option>').trigger('change.select2');
            return;
            }
        $.get("funciones_ajax.php?funcion=opciones_marcaciones_por_cliente_dsft&parametro1=" + codigo_cliente, function(opts)
            {
            $("#id_codigomarcacion").html(opts).trigger('change.select2');
            });
        });

    // Cuando cambia MARCACION: leer data-truck de la option seleccionada
    // y setear TRUCK automaticamente.
    $('#id_codigomarcacion').on('change', function()
        {
        var truck = $(this).find('option:selected').data('truck');
        if(truck == null || truck == undefined)
            truck = 0;
        $("#id_codigotruck").val(truck.toString()).trigger('change.select2');
        });

    boton_nuevo();
    actualiza_listado();
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: listado a la izquierda + formulario a la derecha -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">

        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE CONSOLIDADOS ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-clipboard fg-darkRed"></i> LISTADO DE CONSOLIDADOS</strong></center></span>

            <!-- Buscador -->
            <div style="padding: 5px 8px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; overflow: hidden;">
                <div style="float: left;">
                    <button type="button" onclick="actualiza_listado();" style="background-color: #ffffff; color: #000000; border: 1px solid #c0c0c0; padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: bold; vertical-align: middle;">ACTUALIZAR</button>
                </div>
                <div style="float: right;">
                    <i class="icon-calendar" style="color:#88010e; vertical-align:middle;" title="Filtrar por rango de fechas"></i>
                    <input type="text" id="id_rango_filtro_consolidado" autocomplete="off"
                        style="width:170px; font-size:13px; display:inline-block; vertical-align:middle; margin-right:10px; padding:5px 6px; border:1px solid #c0c0c0; border-radius:2px; height:30px; box-sizing:border-box;"
                        placeholder="Filtrar por fecha vuelo" readonly>
                    <i class="icon-search" style="color: #88010e; vertical-align: middle;"></i>
                    <input type="text" id="id_busqueda_consolidado" autocomplete="off" class="input_pequeno" style="width: 200px; display: inline-block; vertical-align: middle;" placeholder="Buscar..." onkeyup="filtrar_listado_local_consolidado();" />
                </div>
            </div>

            <!-- Listado -->
            <div id="id_listado_consolidados" style="flex: 1; overflow-y: auto; padding: 0;">
                <!-- Se llena via AJAX -->
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: FORMULARIO ===== -->
        <div id="id_formulario_consolidado" class="aida" style="width: 440px; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-clipboard fg-darkRed"></i> DATOS DEL CONSOLIDADO</strong></center></span>

            <div style="padding: 12px;">
                <table style="width: 100%; font-size: 13px;">
                    <colgroup>
                        <col style="width: 35%;">
                        <col style="width: 65%;">
                    </colgroup>
                    <!-- 1) CODIGO (readonly) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CODIGO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_codigo_consolidado" autocomplete="off" class="input_readonly" readonly />
                        </td>
                    </tr>
                    <!-- 2) FECHA VUELO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">FECHA VUELO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_fechavuelo" autocomplete="off" class="input_pequeno" placeholder="aaaa-mm-dd" style="background-color:#fff; cursor:pointer; text-transform: none;" />
                        </td>
                    </tr>
                    <!-- 3) CLIENTE (GUIA pasa a ser una seccion separada con tabla guiaconsolidado) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CLIENTE:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigocliente" style="width: 100%;">
                                <option value="0">-- SELECCIONE --</option>
                                <?php
                                for($i=0; $i<$numero_clientes; $i++)
                                    {
                                    echo '<option value="'.(int)$arreglo_clientes[$i]['CODIGO'].'">'.htmlspecialchars((string)$arreglo_clientes[$i]['NOMBRECLIENTE'], ENT_QUOTES, 'UTF-8').'</option>';
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <!-- 5) MARCACION (vacio al inicio; se llena via AJAX cuando se elige CLIENTE) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">MARCACION:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigomarcacion" style="width: 100%;">
                                <option value="0">-- SELECCIONE --</option>
                            </select>
                        </td>
                    </tr>
                    <!-- 6) TRUCK (auto-llenado segun MARCACION elegida; visualmente readonly) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">TRUCK:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigotruck" style="width: 100%;" class="select2_truck_readonly">
                                <option value="0">-- SELECCIONE --</option>
                                <?php
                                for($i=0; $i<$numero_trucks; $i++)
                                    {
                                    echo '<option value="'.(int)$arreglo_trucks[$i]['CODIGO'].'">'.htmlspecialchars((string)$arreglo_trucks[$i]['NOMBRETRUCK'], ENT_QUOTES, 'UTF-8').'</option>';
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <!-- 7) AGENCIA -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">AGENCIA:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigoagencia" style="width: 100%;">
                                <option value="0">-- SELECCIONE --</option>
                                <?php
                                for($i=0; $i<$numero_agencias; $i++)
                                    {
                                    echo '<option value="'.(int)$arreglo_agencias[$i]['CODIGOAGENCIA'].'">'.htmlspecialchars((string)$arreglo_agencias[$i]['NOMBREAGENCIA'], ENT_QUOTES, 'UTF-8').'</option>';
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <!-- 8) PAIS -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">PAIS:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigopais" style="width: 100%;">
                                <option value="0">-- SELECCIONE --</option>
                                <?php
                                for($i=0; $i<$numero_paises; $i++)
                                    {
                                    echo '<option value="'.(int)$arreglo_paises[$i]['CODIGOPAIS'].'">'.htmlspecialchars((string)$arreglo_paises[$i]['NOMBREPAIS'], ENT_QUOTES, 'UTF-8').'</option>';
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <!-- OBSERVACIONES -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">OBSERVAC:</td>
                        <td style="padding-bottom: 8px;">
                            <textarea id="id_observaciones" maxlength="500" rows="4"
                                style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"></textarea>
                        </td>
                    </tr>
                    <!-- ===== GUIAS: integrado al formulario como filas de la table ===== -->
                    <tr>
                        <td colspan="2"><hr style="border:none; border-top:1px solid #ccc; margin:10px 0;"></td>
                    </tr>
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">GUIAS:</td>
                        <td style="padding-bottom: 8px; white-space: nowrap;">
                            <select id="id_select_guia" style="width: 220px;">
                                <option value=""></option>
                                <?php
                                for($i=0; $i<$numero_guias; $i++)
                                    {
                                    echo '<option value="'.(int)$arreglo_guias[$i]['CODIGO'].'">'.htmlspecialchars((string)$arreglo_guias[$i]['NUMEROGUIA'], ENT_QUOTES, 'UTF-8').'</option>';
                                    }
                                ?>
                            </select>
                            <a onclick="agregar_guia_consolidado();" title="Agregar guia"
                                style="cursor:pointer; color:#88010e; margin-left:6px; font-size:16px; vertical-align:middle; display:inline-block;">
                                <i class="icon-plus"></i>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="padding-bottom: 8px;">
                            <div id="id_lista_guias_consolidado" style="padding: 4px 0;">
                                <!-- Llenado por cargar_guias_consolidado() cuando se selecciona un consolidado. -->
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="border:none; border-top:1px solid #ccc; margin:10px 0;"></td>
                    </tr>
                    <!-- ESTADO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">ESTADO:</td>
                        <td style="padding-bottom: 8px;">
                            <div class="input-control radio default-style" data-role="input-control" style="display: inline-block; margin-right: 18px;">
                                <label><input type="radio" id="id_estado_activo" name="grp_estado" value="1" checked /><span class="check"></span>ACTIVO</label>
                            </div>
                            <div class="input-control radio default-style" data-role="input-control" style="display: inline-block;">
                                <label><input type="radio" id="id_estado_inactivo" name="grp_estado" value="0" /><span class="check"></span>INACTIVO</label>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Botones GRABAR / NUEVO -->
                <div style="text-align: right; margin-top: 10px;">
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_consolidado();">GRABAR</button>
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">NUEVO</button>
                </div>
            </div>
        </div>

    </div>

    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body>
</html>
