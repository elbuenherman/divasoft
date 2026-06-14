<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
 
// CHEQUEO PERMISOS.
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'], $permiso);
$usuario_web = $_SESSION['s_codigo'];

$fecha_hoy      = date("Y-m-d");
$fecha_hora_hoy = date("Y-m-d H:i:s");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Tipos de Producto</title>
<?php include("css_v4.php"); ?>
<script language="javascript" src="controles_especiales.js"></script>
<script type="text/javascript" src="js/jquery.mask.min.js"></script>
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

/* ===== Grid de tipos de producto ===== */
.grid_tipos {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_tipos thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_tipos thead th {
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
.grid_tipos tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_tipos tbody .grupo_tipo:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_tipos tbody .td_centro {
    text-align: center;
    }
.grid_tipos tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_tipos tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
.grid_tipos tbody .grupo_tipo {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_tipos tbody .grupo_tipo:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_tipos tbody .grupo_tipo_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_tipos tbody .grupo_tipo_seleccionado td:first-child {
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
</style>
<script language="javascript">
var global_codigo_seleccionado = 0;
var global_ordenamiento = "NOMBRE";
var global_direccion = "ASC";
var global_codigo_usuario = <?php echo (int)$_SESSION['s_codigo']; ?>;
var global_codigo_a_eliminar = 0;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }

// ===== Filtro local por texto =====
function filtrar_listado_local()
    {
    var texto = $("#id_busqueda_listado").val().toUpperCase();
    $("#id_listado_tipos table tbody .grupo_tipo").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente el tipo seleccionado =====
function marca_tipo_seleccionado()
    {
    $("#id_listado_tipos .grupo_tipo").removeClass("grupo_tipo_seleccionado");
    if(global_codigo_seleccionado > 0)
        $("#id_grupo_tipo_"+global_codigo_seleccionado).addClass("grupo_tipo_seleccionado");
    }

// ===== Trae el tipo de producto y llena el formulario =====
function devuelve_tipo_producto(codigo)
    {
    global_codigo_seleccionado = codigo;
    marca_tipo_seleccionado();
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_tipo_producto_dsft&parametro1=" + codigo;
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
        $("#id_codigo_tipo").val(datos.CODIGO || "");
        $("#id_nombre_tipo").val(datos.NOMBRE || "");
        $("#id_ingles_tipo").val(datos.NOMBREINGLES || "");
        $("#id_ruso_tipo").val(datos.NOMBRERUSO || "");
        $("#id_obs_tipo").val(datos.OBSERVACIONES || "");
        if(parseInt(datos.ESTADO) == 1)
            $("#id_estado_tipo").prop('checked', true);
        else
            $("#id_estado_tipo").prop('checked', false);
        $("#id_nombre_tipo").focus();
        });
    }

// ===== Validacion de formulario (identica al servidor) =====
function valida_formulario()
    {
    if($("#id_nombre_tipo").val().trim() == "")
        return "Por favor ingrese el NOMBRE del tipo de producto";
    return "OK";
    }

// ===== Grabar (INSERT si codigo == 0, UPDATE si > 0) =====
function grabar_tipo_producto()
    {
    var mensaje = valida_formulario();
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    var estado_val = $("#id_estado_tipo").is(":checked") ? "1" : "0";
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=grabar_tipo_producto_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + encodeURIComponent($("#id_nombre_tipo").val())
        + "&parametro3=" + encodeURIComponent($("#id_ingles_tipo").val())
        + "&parametro4=" + encodeURIComponent($("#id_ruso_tipo").val())
        + "&parametro5=" + encodeURIComponent($("#id_obs_tipo").val())
        + "&parametro6=" + estado_val
        + "&parametro7=" + global_codigo_usuario;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.substring(0, 2) == "OK")
            {
            messageBox("Tipo de producto grabado correctamente");
            boton_nuevo();
            actualiza_listado();
            }
        else
            {
            messageBox("Error al grabar: " + data);
            }
        });
    }

// ===== Eliminacion logica (ESTADO = -1) con confirmacion jQuery UI =====
function elimina_tipo_producto(codigo)
    {
    global_codigo_a_eliminar = codigo;
    $("#id_dialog_confirma").dialog("open");
    }

function ejecuta_eliminacion()
    {
    var codigo = global_codigo_a_eliminar;
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=elimina_tipo_producto_dsft&parametro1=" + codigo + "&parametro2=" + global_codigo_usuario;
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
    var url = "funciones_ajax.php?funcion=lista_tipo_producto_dsft&parametro1=" + global_ordenamiento + "&parametro2=" + global_direccion;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_tipos").html(data);
        marca_tipo_seleccionado();
        // Reaplicar el filtro de texto si el usuario tenia algo escrito.
        filtrar_listado_local();
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_tipo").val("");
    $("#id_nombre_tipo").val("");
    $("#id_ingles_tipo").val("");
    $("#id_ruso_tipo").val("");
    $("#id_obs_tipo").val("");
    $("#id_estado_tipo").prop('checked', true);
    $("#id_listado_tipos .grupo_tipo").removeClass("grupo_tipo_seleccionado");
    $("#id_nombre_tipo").focus();
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

    $("#id_dialog_confirma").dialog(
        {
        modal: true,
        width: 420,
        autoOpen: false,
        dialogClass: 'myTitleClass',
        buttons: [
            {text: "Eliminar", class: 'cancelButton', click: function() {$(this).dialog("close"); ejecuta_eliminacion();}},
            {text: "Cancelar", click: function() {$(this).dialog("close");}}
            ]
        });

    boton_nuevo();
    actualiza_listado();
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: listado a la izquierda (60%) + formulario a la derecha (40%) -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">

        <!-- ===== COLUMNA IZQUIERDA: LISTADO ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-tag fg-darkRed"></i> LISTADO DE TIPOS DE PRODUCTO</strong></center></span>

            <!-- Buscador -->
            <div style="padding: 5px 8px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; overflow: hidden;">
                <div style="float: left;">
                    <button type="button" onclick="actualiza_listado();" style="background-color: #ffffff; color: #000000; border: 1px solid #c0c0c0; padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: bold; vertical-align: middle;">ACTUALIZAR</button>
                </div>
                <div style="float: right;">
                    <i class="icon-search" style="color: #88010e; vertical-align: middle;"></i>
                    <input type="text" id="id_busqueda_listado" class="input_pequeno" autocomplete="off" style="width: 200px; display: inline-block; vertical-align: middle;" placeholder="Buscar..." onkeyup="filtrar_listado_local();" />
                </div>
            </div>

            <!-- Listado -->
            <div id="id_listado_tipos" style="flex: 1; overflow-y: auto; padding: 0;">
                <!-- Se llena via AJAX -->
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: FORMULARIO ===== -->
        <div id="id_formulario_tipo" class="aida" style="width: 440px; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-tag fg-darkRed"></i> DATOS DEL TIPO DE PRODUCTO</strong></center></span>

            <div style="padding: 12px;">
                <table style="width: 100%; font-size: 13px;">
                    <colgroup>
                        <col style="width: 35%;">
                        <col style="width: 65%;">
                    </colgroup>
                    <!-- CODIGO (readonly) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CODIGO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_codigo_tipo" class="input_readonly" autocomplete="off" readonly />
                        </td>
                    </tr>
                    <!-- NOMBRE -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">NOMBRE:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_nombre_tipo" class="input_pequeno" autocomplete="off" maxlength="100" />
                        </td>
                    </tr>
                    <!-- NOMBRE INGLES -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">INGLES:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_ingles_tipo" class="input_pequeno" autocomplete="off" maxlength="100" />
                        </td>
                    </tr>
                    <!-- NOMBRE RUSO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">RUSO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_ruso_tipo" class="input_pequeno" autocomplete="off" maxlength="100" style="text-transform: none;" />
                        </td>
                    </tr>
                    <!-- OBSERVACIONES -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">OBSERVAC:</td>
                        <td style="padding-bottom: 8px;">
                            <textarea id="id_obs_tipo" autocomplete="off" maxlength="255" rows="2"
                                style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"></textarea>
                        </td>
                    </tr>
                    <!-- ESTADO (checkbox) -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">ACTIVO:</td>
                        <td style="padding-bottom: 8px;">
                            <div class="input-control checkbox default-style" data-role="input-control" style="display: inline-block;">
                                <label><input type="checkbox" id="id_estado_tipo" autocomplete="off" checked /><span class="check"></span></label>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Botones GRABAR / NUEVO -->
                <div style="text-align: right; margin-top: 10px;">
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_tipo_producto();">GRABAR</button>
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">NUEVO</button>
                </div>
            </div>
        </div>

    </div>

    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_dialog_confirma" title="Confirmar eliminacion">Esta seguro de eliminar este tipo de producto?</div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body>
</html>
