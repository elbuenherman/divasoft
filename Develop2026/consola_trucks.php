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
<title><?php echo $titulo_hoja;?> - Trucks</title>
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

/* ===== Grid de trucks ===== */
.grid_trucks {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_trucks thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_trucks thead th {
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
.grid_trucks tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_trucks tbody .grupo_truck:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_trucks tbody .td_centro {
    text-align: center;
    }
.grid_trucks tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_trucks tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
.grid_trucks tbody .grupo_truck {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_trucks tbody .grupo_truck:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_trucks tbody .grupo_truck_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_trucks tbody .grupo_truck_seleccionado td:first-child {
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
var global_ordenamiento = "NOMBRETRUCK";
var global_direccion = "ASC";
var global_codigo_usuario = <?php echo (int)$_SESSION['s_codigo']; ?>;

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
    $("#id_listado_trucks table tbody .grupo_truck").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente el truck seleccionado =====
function marca_truck_seleccionado()
    {
    $("#id_listado_trucks .grupo_truck").removeClass("grupo_truck_seleccionado");
    if(global_codigo_seleccionado > 0)
        $("#id_grupo_truck_"+global_codigo_seleccionado).addClass("grupo_truck_seleccionado");
    }

// ===== Trae el truck y llena el formulario =====
function devuelve_truck(codigo)
    {
    global_codigo_seleccionado = codigo;
    marca_truck_seleccionado();
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_truck_dsft&parametro1=" + codigo;
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
        $("#id_codigo_truck").val(datos.CODIGO || "");
        $("#id_nombre_truck").val(datos.NOMBRETRUCK || "");
        $("#id_correo_truck").val(datos.CORREOTRUCK || "");
        $("#id_telefono").val(datos.TELEFONO || "");
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
        $("#id_nombre_truck").focus();
        });
    }

// ===== Trazabilidad =====
function muestra_trazabilidad_truck(codigo)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=trazabilidad_truck_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        messageBox(data);
        });
    }

// ===== Eliminacion logica (ESTADO = -1) =====
function elimina_truck_dsft(codigo)
    {
    var r = confirm("Esta seguro de eliminar este truck?");
    if(r == true)
        {
        $("#id_espera").show();
        var url = "funciones_ajax.php?funcion=elimina_truck_dsft&parametro1=" + codigo + "&parametro2=" + global_codigo_usuario;
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
// NOMBRE es obligatorio.
function valida_formulario()
    {
    if($("#id_nombre_truck").val().trim() == "")
        return "Por favor ingrese el NOMBRE del truck";
    return "OK";
    }

// ===== Grabar (INSERT si codigo == 0, UPDATE si > 0) =====
function grabar_truck()
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
    var url = "funciones_ajax.php?funcion=graba_truck_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + encodeURIComponent($("#id_nombre_truck").val())
        + "&parametro3=" + encodeURIComponent($("#id_correo_truck").val())
        + "&parametro4=" + encodeURIComponent($("#id_telefono").val())
        + "&parametro5=" + encodeURIComponent($("#id_observaciones").val())
        + "&parametro6=" + estado_val
        + "&parametro7=" + global_codigo_usuario;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.substring(0, 2) == "OK")
            {
            messageBox("Truck grabado correctamente");
            boton_nuevo();
            actualiza_listado();
            }
        else
            {
            messageBox("Error al grabar: " + data);
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
    var url = "funciones_ajax.php?funcion=lista_trucks_dsft&parametro1=" + global_ordenamiento + "&parametro2=" + global_direccion;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_trucks").html(data);
        // Reaplicar el filtro de texto si el usuario tenia algo escrito.
        filtrar_listado_local();
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_truck").val("");
    $("#id_nombre_truck").val("");
    $("#id_correo_truck").val("");
    $("#id_telefono").val("");
    $("#id_observaciones").val("");
    $("#id_estado_activo").prop('checked', true);
    $("#id_estado_inactivo").prop('checked', false);
    $("#id_listado_trucks .grupo_truck").removeClass("grupo_truck_seleccionado");
    $("#id_nombre_truck").focus();
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

    boton_nuevo();
    actualiza_listado();
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: listado a la izquierda + formulario a la derecha -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">

        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE TRUCKS ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-truck fg-darkRed"></i> LISTADO DE TRUCKS</strong></center></span>

            <!-- Buscador -->
            <div style="padding: 5px 8px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; overflow: hidden;">
                <div style="float: left;">
                    <button type="button" onclick="actualiza_listado();" style="background-color: #ffffff; color: #000000; border: 1px solid #c0c0c0; padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: bold; vertical-align: middle;">ACTUALIZAR</button>
                </div>
                <div style="float: right;">
                    <i class="icon-search" style="color: #88010e; vertical-align: middle;"></i>
                    <input type="text" id="id_busqueda_listado" class="input_pequeno" style="width: 200px; display: inline-block; vertical-align: middle;" placeholder="Buscar..." onkeyup="filtrar_listado_local();" />
                </div>
            </div>

            <!-- Listado -->
            <div id="id_listado_trucks" style="flex: 1; overflow-y: auto; padding: 0;">
                <!-- Se llena via AJAX -->
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: FORMULARIO ===== -->
        <div id="id_formulario_truck" class="aida" style="width: 440px; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-truck fg-darkRed"></i> DATOS DEL TRUCK</strong></center></span>

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
                            <input type="text" id="id_codigo_truck" class="input_readonly" readonly />
                        </td>
                    </tr>
                    <!-- NOMBRE -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">NOMBRE:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_nombre_truck" class="input_pequeno" maxlength="255" />
                        </td>
                    </tr>
                    <!-- CORREO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CORREO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_correo_truck" class="input_pequeno" maxlength="255" style="text-transform: none;" />
                        </td>
                    </tr>
                    <!-- TELEFONO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">TELEFONO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_telefono" class="input_pequeno" maxlength="128" style="text-transform: none;" />
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
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_truck();">GRABAR</button>
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
