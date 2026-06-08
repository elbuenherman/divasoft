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

// Conectar a la BD para cargar el listado de paises inline en el SELECT del formulario.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Clientes</title>
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

/* ===== Grid de clientes ===== */
.grid_clientes {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_clientes thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_clientes thead th {
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
.grid_clientes tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_clientes tbody .grupo_cliente:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_clientes tbody .td_centro {
    text-align: center;
    }
.grid_clientes tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_clientes tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
.grid_clientes tbody .grupo_cliente {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_clientes tbody .grupo_cliente:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_clientes tbody .grupo_cliente_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_clientes tbody .grupo_cliente_seleccionado td:first-child {
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

/* ===== Tablita read-only de MARCACIONES DEL CLIENTE ===== */
.grid_marcaciones_cliente {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    table-layout: fixed;
    }
.grid_marcaciones_cliente thead {
    background-color: rgb(154, 22, 22);
    color: white;
    }
.grid_marcaciones_cliente thead th {
    font-size: 10px;
    padding: 5px 4px;
    text-align: center;
    border-right: 1px solid white;
    background-color: rgb(154, 22, 22);
    color: white;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_marcaciones_cliente tbody td {
    font-size: 11px;
    padding: 4px 4px;
    border-bottom: 1px solid #e8e8e8;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_marcaciones_cliente tbody tr:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_marcaciones_cliente tbody .td_centro {
    text-align: center;
    }
.grid_marcaciones_cliente tbody .td_vacio {
    text-align: center;
    color: #888;
    padding: 12px !important;
    background-color: #ffffff !important;
    }
.grid_marcaciones_cliente tbody .grupo_marc_link {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_marcaciones_cliente tbody .grupo_marc_link:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }

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
var global_ordenamiento = "NOMBRECLIENTE";
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
    $("#id_listado_clientes table tbody .grupo_cliente").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente el cliente seleccionado =====
function marca_cliente_seleccionado()
    {
    $("#id_listado_clientes .grupo_cliente").removeClass("grupo_cliente_seleccionado");
    if(global_codigo_seleccionado > 0)
        $("#id_grupo_cliente_"+global_codigo_seleccionado).addClass("grupo_cliente_seleccionado");
    }

// ===== Carga la tablita read-only de marcaciones del cliente seleccionado =====
function carga_marcaciones_cliente(codigo)
    {
    var url = "funciones_ajax.php?funcion=lista_marcaciones_por_cliente_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_tbody_marcaciones_cliente").html(data);
        });
    }

// ===== Trae el cliente y llena el formulario =====
function devuelve_cliente(codigo)
    {
    global_codigo_seleccionado = codigo;
    marca_cliente_seleccionado();
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=devuelve_cliente_dsft&parametro1=" + codigo;
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
        $("#id_codigo_cliente").val(datos.CODIGO || "");
        $("#id_nombre_cliente").val(datos.NOMBRECLIENTE || "");
        $("#id_correo_facturas").val(datos.CORREOFACTURAS || "");
        $("#id_correo_estados").val(datos.CORREOESTADOSCUENTA || "");
        $("#id_telefono").val(datos.TELEFONO || "");
        $("#id_direccion").val(datos.DIRECCION || "");
        $("#id_ciudad").val(datos.CIUDAD || "");
        var pais_val = (datos.CODIGOPAIS && parseInt(datos.CODIGOPAIS) > 0) ? datos.CODIGOPAIS.toString() : "0";
        $("#id_codigopais").val(pais_val).trigger('change');
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
        // Segundo AJAX: carga las marcaciones de este cliente en la tablita.
        carga_marcaciones_cliente(codigo);
        $("#id_nombre_cliente").focus();
        });
    }

// ===== Trazabilidad =====
function muestra_trazabilidad_cliente(codigo)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=trazabilidad_cliente_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        messageBox(data);
        });
    }

// ===== Eliminacion logica (ESTADO = -1) =====
function elimina_cliente_dsft(codigo)
    {
    var r = confirm("Esta seguro de eliminar este cliente?");
    if(r == true)
        {
        $("#id_espera").show();
        var url = "funciones_ajax.php?funcion=elimina_cliente_dsft&parametro1=" + codigo + "&parametro2=" + global_codigo_usuario;
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
// NOMBRE, MAIL FACTURACION y PAIS son obligatorios. Valida en ese orden,
// retorna el primer error encontrado.
function valida_formulario()
    {
    if($("#id_nombre_cliente").val().trim() == "")
        return "Por favor ingrese el NOMBRE del cliente";
    if($("#id_correo_facturas").val().trim() == "")
        return "Por favor ingrese el MAIL DE FACTURACION";
    var pais_sel = parseInt($("#id_codigopais").val());
    if(isNaN(pais_sel) || pais_sel <= 0)
        return "Por favor seleccione el PAIS";
    return "OK";
    }

// ===== Grabar (INSERT si codigo == 0, UPDATE si > 0) =====
function grabar_cliente()
    {
    var mensaje = valida_formulario();
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    $("#id_espera").show();
    var estado_val = $('input[name="grp_estado"]:checked').val();
    if(estado_val == null || estado_val == "")
        estado_val = "1";
    var url = "funciones_ajax.php?funcion=graba_cliente_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + encodeURIComponent($("#id_nombre_cliente").val())
        + "&parametro3="
        + "&parametro4=" + encodeURIComponent($("#id_correo_facturas").val())
        + "&parametro5=" + encodeURIComponent($("#id_correo_estados").val())
        + "&parametro6=" + encodeURIComponent($("#id_telefono").val())
        + "&parametro7=" + encodeURIComponent($("#id_direccion").val())
        + "&parametro8=" + encodeURIComponent($("#id_ciudad").val())
        + "&parametro9=" + encodeURIComponent($("#id_observaciones").val())
        + "&parametro10=" + estado_val
        + "&parametro11=" + global_codigo_usuario
        + "&parametro12=" + $("#id_codigopais").val();
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.substring(0, 2) == "OK")
            {
            messageBox("Cliente grabado correctamente");
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
    var url = "funciones_ajax.php?funcion=lista_clientes_dsft&parametro1=" + global_ordenamiento + "&parametro2=" + global_direccion;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_clientes").html(data);
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_cliente").val("");
    $("#id_nombre_cliente").val("");
    $("#id_correo_facturas").val("");
    $("#id_correo_estados").val("");
    $("#id_telefono").val("");
    $("#id_direccion").val("");
    $("#id_ciudad").val("");
    $("#id_codigopais").val("0").trigger('change');
    $("#id_observaciones").val("");
    $("#id_estado_activo").prop('checked', true);
    $("#id_estado_inactivo").prop('checked', false);
    $("#id_tbody_marcaciones_cliente").html('<tr><td colspan="4" class="td_vacio">Seleccione un cliente para ver sus marcaciones</td></tr>');
    $("#id_listado_clientes .grupo_cliente").removeClass("grupo_cliente_seleccionado");
    $("#id_nombre_cliente").focus();
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

    // Select2 en el select de pais (con buscador)
    $('#id_codigopais').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE --"
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

        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE CLIENTES ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-users fg-darkRed"></i> LISTADO DE CLIENTES</strong></center></span>

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
            <div id="id_listado_clientes" style="flex: 1; overflow-y: auto; padding: 0;">
                <!-- Se llena via AJAX -->
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: wrapper vertical (formulario + tablita marcaciones) ===== -->
        <div style="display: flex; flex-direction: column; gap: 8px; width: 440px;">

        <div id="id_formulario_cliente" class="aida" style="width: 100%; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-user fg-darkRed"></i> DATOS DEL CLIENTE</strong></center></span>

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
                            <input type="text" id="id_codigo_cliente" class="input_readonly" readonly />
                        </td>
                    </tr>
                    <!-- NOMBRE -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">NOMBRE:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_nombre_cliente" class="input_pequeno" maxlength="255" />
                        </td>
                    </tr>
                    <!-- CORREO FACTURAS -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">MAIL FACTURACION:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_correo_facturas" class="input_pequeno" maxlength="255" style="text-transform: none;" />
                        </td>
                    </tr>
                    <!-- CORREO ESTADOS CUENTA -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">MAIL ESTADO CUENTA:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_correo_estados" class="input_pequeno" maxlength="255" style="text-transform: none;" />
                        </td>
                    </tr>
                    <!-- TELEFONO -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">TELEFONO:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_telefono" class="input_pequeno" maxlength="128" style="text-transform: none;" />
                        </td>
                    </tr>
                    <!-- DIRECCION -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">DIRECCION:</td>
                        <td style="padding-bottom: 8px;">
                            <textarea id="id_direccion" maxlength="255" rows="3"
                                style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"></textarea>
                        </td>
                    </tr>
                    <!-- CIUDAD -->
                    <tr>
                        <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CIUDAD:</td>
                        <td style="padding-bottom: 8px;">
                            <input type="text" id="id_ciudad" class="input_pequeno" maxlength="100" />
                        </td>
                    </tr>
                    <!-- PAIS -->
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
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_cliente();">GRABAR</button>
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">NUEVO</button>
                </div>
            </div>
        </div>

        <!-- ===== TERCER PANEL: MARCACIONES DEL CLIENTE (solo lectura) ===== -->
        <div id="id_marcaciones_cliente_panel" class="aida" style="width: 100%; height: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-tag fg-darkRed"></i> MARCACIONES DEL CLIENTE</strong></center></span>
            <div style="padding: 8px 12px;">
                <table class="grid_marcaciones_cliente">
                    <thead>
                        <tr>
                            <th style="width: 10%;">COD</th>
                            <th style="width: 45%;">MARCACION</th>
                            <th style="width: 30%;">TRUCK</th>
                            <th style="width: 15%;">EST</th>
                        </tr>
                    </thead>
                    <tbody id="id_tbody_marcaciones_cliente">
                        <tr><td colspan="4" class="td_vacio">Seleccione un cliente para ver sus marcaciones</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        </div><!-- /wrapper vertical panel derecho -->

    </div>

    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body>
</html>
