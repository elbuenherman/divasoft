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

// Conectar a la BD para cargar los listados de cliente y truck inline en los Select2.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Marcaciones</title>
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

/* ===== Grid de marcaciones ===== */
.grid_marcaciones {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_marcaciones thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_marcaciones thead th {
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
.grid_marcaciones tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_marcaciones tbody .grupo_marcacion:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_marcaciones tbody .td_centro {
    text-align: center;
    }
.grid_marcaciones tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_marcaciones tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
.grid_marcaciones tbody .grupo_marcacion {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_marcaciones tbody .grupo_marcacion:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_marcaciones tbody .grupo_marcacion_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_marcaciones tbody .grupo_marcacion_seleccionado td:first-child {
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
</style>
<script language="javascript">
var global_codigo_seleccionado = 0;
var global_ordenamiento = "NOMBREMARCACION";
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
    $("#id_listado_marcaciones table tbody .grupo_marcacion").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente la marcacion seleccionada =====
function marca_marcacion_seleccionada()
    {
    $("#id_listado_marcaciones .grupo_marcacion").removeClass("grupo_marcacion_seleccionado");
    if(global_codigo_seleccionado > 0)
        $("#id_grupo_marcacion_"+global_codigo_seleccionado).addClass("grupo_marcacion_seleccionado");
    }

// ===== Trae la marcacion y llena el formulario =====
function devuelve_marcacion(codigo)
    {
    global_codigo_seleccionado = codigo;
    marca_marcacion_seleccionada();
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_marcacion_dsft&parametro1=" + codigo;
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
        $("#id_codigo_marcacion").val(datos.CODIGO || "");
        $("#id_nombre_marcacion").val(datos.NOMBREMARCACION || "");
        var cliente_val = (datos.CODIGOCLIENTE && parseInt(datos.CODIGOCLIENTE) > 0) ? datos.CODIGOCLIENTE.toString() : "0";
        $("#id_codigocliente").val(cliente_val).trigger('change');
        var truck_val = (datos.CODIGOTRUCK && parseInt(datos.CODIGOTRUCK) > 0) ? datos.CODIGOTRUCK.toString() : "0";
        $("#id_codigotruck").val(truck_val).trigger('change');
        $("#id_observaciones").val(datos.OBSERVACIONES || "");
        $("#id_porcentaje_comision").val(datos.PORCENTAJECOMISION || "");
        $("#id_rango_invoice").val(datos.RANGOINVOICE || "");
        $("#id_datos_bancarios").val(datos.DATOSBANCARIOS || "");
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
        $("#id_nombre_marcacion").focus();
        });
    }

// ===== Trazabilidad =====
function muestra_trazabilidad_marcacion(codigo)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=trazabilidad_marcacion_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        messageBox(data);
        });
    }

// ===== Eliminacion logica (ESTADO = -1) =====
function elimina_marcacion_dsft(codigo)
    {
    var r = confirm("Esta seguro de eliminar esta marcacion?");
    if(r == true)
        {
        $("#id_espera").show();
        var url = "funciones_ajax.php?funcion=elimina_marcacion_dsft&parametro1=" + codigo + "&parametro2=" + global_codigo_usuario;
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
// MARCACION y CLIENTE son obligatorios. Valida en ese orden, retorna el primer
// error encontrado.
function valida_formulario()
    {
    if($("#id_nombre_marcacion").val().trim() == "")
        return "Por favor ingrese la MARCACION";
    var cliente_sel = parseInt($("#id_codigocliente").val());
    if(isNaN(cliente_sel) || cliente_sel <= 0)
        return "Por favor seleccione el CLIENTE";
    return "OK";
    }

// ===== Grabar (INSERT si codigo == 0, UPDATE si > 0) =====
function grabar_marcacion()
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
    var url = "funciones_ajax.php?funcion=graba_marcacion_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + encodeURIComponent($("#id_nombre_marcacion").val())
        + "&parametro3=" + $("#id_codigocliente").val()
        + "&parametro4=" + $("#id_codigotruck").val()
        + "&parametro5=" + encodeURIComponent($("#id_observaciones").val())
        + "&parametro6=" + estado_val
        + "&parametro7=" + global_codigo_usuario
        + "&parametro8=" + encodeURIComponent($("#id_porcentaje_comision").val())
        + "&parametro9=" + encodeURIComponent($("#id_rango_invoice").val())
        + "&parametro10=" + encodeURIComponent($("#id_datos_bancarios").val());
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.substring(0, 2) == "OK")
            {
            messageBox("Marcacion grabada correctamente");
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
    var url = "funciones_ajax.php?funcion=lista_marcaciones_dsft&parametro1=" + global_ordenamiento + "&parametro2=" + global_direccion;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_marcaciones").html(data);
        // Reaplicar el filtro de texto si el usuario tenia algo escrito.
        filtrar_listado_local();
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_marcacion").val("");
    $("#id_nombre_marcacion").val("");
    $("#id_codigocliente").val("0").trigger('change');
    $("#id_codigotruck").val("0").trigger('change');
    $("#id_observaciones").val("");
    $("#id_porcentaje_comision").val("");
    $("#id_rango_invoice").val("");
    $("#id_datos_bancarios").val("");
    $("#id_estado_activo").prop('checked', true);
    $("#id_estado_inactivo").prop('checked', false);
    $("#id_listado_marcaciones .grupo_marcacion").removeClass("grupo_marcacion_seleccionado");
    $("#id_nombre_marcacion").focus();
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

    // Select2 en el select de cliente (con buscador)
    $('#id_codigocliente').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE --"
        });

    // Select2 en el select de truck (con buscador, opcional)
    $('#id_codigotruck').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE --"
        });

    boton_nuevo();
    actualiza_listado();

    // Si la URL trae ?codigo=N, cargar esa marcacion automaticamente.
    // Usado por consola_clientes_dsft.php para abrir una marcacion en pestana nueva.
    var codigo_url = new URLSearchParams(window.location.search).get("codigo");
    if(codigo_url && parseInt(codigo_url) > 0)
        devuelve_marcacion(parseInt(codigo_url));
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: listado a la izquierda + formulario a la derecha -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">

        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE MARCACIONES ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-tag fg-darkRed"></i> LISTADO DE MARCACIONES</strong></center></span>

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
            <div id="id_listado_marcaciones" style="flex: 1; overflow-y: auto; padding: 0;">
                <!-- Se llena via AJAX -->
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: FORMULARIO ===== -->
        <div id="id_formulario_marcacion" class="aida" style="width: 440px; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-tag fg-darkRed"></i> DATOS DE LA MARCACION</strong></center></span>

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
                            <input type="text" id="id_codigo_marcacion" class="input_readonly" readonly />
                        </td>
                    </tr>
                    <!-- MARCACION -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">MARCACION:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_nombre_marcacion" class="input_pequeno" maxlength="255" />
                        </td>
                    </tr>
                    <!-- CLIENTE -->
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
                    <!-- TRUCK -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">TRUCK:</td>
                        <td style="padding-bottom: 8px;">
                            <select id="id_codigotruck" style="width: 100%;">
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
                    <!-- OBSERVACIONES -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">OBSERVAC:</td>
                        <td style="padding-bottom: 8px;">
                            <textarea id="id_observaciones" maxlength="500" rows="4"
                                style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"></textarea>
                        </td>
                    </tr>
                    <!-- % COMISION -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">% COMISION:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_porcentaje_comision" class="input_pequeno" maxlength="5"
                                style="width: 80px; text-align: right;"
                                placeholder="0.00"
                                oninput="this.value = this.value.replace(/[^0-9.]/g, '');" />
                        </td>
                    </tr>
                    <!-- RANGO INVOICE -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">RANGO INV:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_rango_invoice" class="input_pequeno" maxlength="10"
                                style="width: 120px; text-align: right;"
                                placeholder="100000"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '');" />
                        </td>
                    </tr>
                    <!-- DATOS BANCARIOS -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">DATOS BANCO:</td>
                        <td style="padding-bottom: 8px;">
                            <textarea id="id_datos_bancarios" maxlength="2000" rows="5"
                                style="width: 100%; text-transform: uppercase; font-size: 11px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: vertical; font-family: monospace;"></textarea>
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
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_marcacion();">GRABAR</button>
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
