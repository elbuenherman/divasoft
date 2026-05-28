<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'], $permiso);
$usuario_web = $_SESSION['s_codigo'];

// ===== SOLO GUI / MAQUETA - sin consultas de negocio, sin AJAX funcional todavia =====
// Los catalogos de FINCA y CONSOLIDADO, el listado real y el grabado se haran en la fase de funcionalidad.
$fecha_hoy      = date("Y-m-d");
$fecha_hora_hoy = date("Y-m-d H:i:s");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Correos / Facturas</title>
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

/* ===== Grid de correos (cada correo = 2 filas: datos + asunto) ===== */
.grid_correos {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: fixed;
    }
.grid_correos thead {
    background-color: rgb(154, 22, 22);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
    }
.grid_correos thead th {
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
.grid_correos tbody td {
    font-size: 11px;
    padding: 5px 5px;
    border: none !important;
    border-bottom: 1px solid #e8e8e8 !important;
    background-color: #ffffff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }
.grid_correos tbody .grupo_correo:nth-child(even) td {
    background-color: #f9f9f9;
    }
.grid_correos tbody .td_centro {
    text-align: center;
    }
.grid_correos tbody .td_opc {
    text-align: center;
    white-space: nowrap;
    }
.grid_correos tbody .td_opc a {
    margin: 0 3px;
    text-decoration: none;
    font-size: 14px;
    }
/* Segunda fila de cada correo: el asunto ocupando todo el ancho */
.grid_correos tbody .fila_asunto td {
    font-size: 11px;
    font-style: italic;
    color: #555;
    padding: 4px 8px 8px 22px;
    border-bottom: 1px solid #d8d8d8 !important;
    white-space: normal;
    overflow: hidden;
    text-overflow: ellipsis;
    }
.grid_correos tbody .fila_asunto td .etiqueta_asunto {
    font-style: normal;
    font-weight: bold;
    color: #88010e;
    margin-right: 6px;
    }
.grid_correos tbody .grupo_correo {
    cursor: pointer;
    transition: background-color 0.15s ease;
    }
.grid_correos tbody .grupo_correo:hover td {
    background-color: rgba(255, 240, 240, 0.95) !important;
    }
.grid_correos tbody .grupo_correo_seleccionado td {
    background-color: #ffe8e8 !important;
    color: #88010e !important;
    }
.grid_correos tbody .grupo_correo_seleccionado td:first-child {
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
/* Inputs de solo lectura (detalle del correo) */
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

/* ===== Opciones del DIV REPORTES/OPCIONES ===== */
.link_opcion {
    display: block;
    padding: 5px 6px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    cursor: pointer;
    transition: background-color 0.15s;
    }
.link_opcion:hover {
    background-color: rgba(255, 240, 240, 0.7);
    color: #88010e;
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
var global_codigo_correo_seleccionado = 0;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }

// ===== Filtro local por texto (maqueta: recorre los grupos de correo del grid) =====
function filtrar_listado_local()
    {
    var texto = $("#id_busqueda_listado").val().toUpperCase();
    $("#id_listado_correos table tbody .grupo_correo").each(function()
        {
        var fila = $(this).text().toUpperCase();
        if(fila.indexOf(texto) > -1)
            $(this).show();
        else
            $(this).hide();
        });
    }

// ===== Marca visualmente el correo seleccionado =====
function marca_correo_seleccionado()
    {
    $("#id_listado_correos .grupo_correo").removeClass("grupo_correo_seleccionado");
    if(global_codigo_correo_seleccionado > 0)
        $("#id_grupo_correo_"+global_codigo_correo_seleccionado).addClass("grupo_correo_seleccionado");
    }

// ===== Placeholder: cargar el detalle del correo en el formulario (siguiente fase) =====
function devuelve_correo(codigo)
    {
    global_codigo_correo_seleccionado = codigo;
    marca_correo_seleccionado();
    // TODO: AJAX a devuelve_correo para llenar el formulario
    }

// ===== Placeholder: trazabilidad del correo (siguiente fase) =====
function muestra_trazabilidad_correo(codigo)
    {
    messageBox("Trazabilidad del correo " + codigo + " - pendiente implementar");
    }

// ===== Placeholder: ver adjunto PDF/Excel completo (siguiente fase) =====
function ver_adjunto_correo(codigo)
    {
    messageBox("Visor de adjunto del correo " + codigo + " - pendiente implementar");
    }

// ===== Placeholder: ver cuerpo del correo completo (siguiente fase) =====
function ver_cuerpo_correo(codigo)
    {
    messageBox("Cuerpo del correo " + codigo + " - pendiente implementar");
    }

// ===== Placeholder: grabar (siguiente fase) =====
function grabar_correo()
    {
    messageBox("Grabado - pendiente implementar (maqueta)");
    }

// ===== Placeholder: extraer correos por rango de fechas (siguiente fase) =====
function extraer_correos()
    {
    messageBox("Extraccion de correos - pendiente implementar (maqueta)");
    }

// ===== boton_nuevo: limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_correo_seleccionado = 0;
    $("#id_codigo_finca").val("0").trigger('change');
    $("#id_codigo_consolidado").val("0").trigger('change');
    $("#id_estado_correo").val("1").trigger('change');
    $("#id_observaciones_correo").val("");
    $("#id_messageid_correo").val("");
    $("#id_threadid_correo").val("");
    $("#id_cc_correo").val("");
    $("#id_bcc_correo").val("");
    $("#id_fecha_procesado_correo").val("");
    $("#id_listado_correos .grupo_correo").removeClass("grupo_correo_seleccionado");
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

    // Select2 en los selects editables del formulario
    $('#id_codigo_finca').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE --"
        });
    $('#id_codigo_consolidado').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE --"
        });
    $('#id_estado_correo').select2(
        {
        width: '100%',
        minimumResultsForSearch: Infinity
        });

    // Rango de fechas ARRIBA (filtro del listado) - Flatpickr en modo range
    flatpickr("#id_rango_filtro",
        {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "es"
        });

    // Rango de fechas ABAJO (extraccion desde Gmail) - Flatpickr en modo range
    flatpickr("#id_rango_fechas",
        {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "es"
        });

    boton_nuevo();
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: listado a la izquierda + (datos / reportes) a la derecha -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">

        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE CORREOS ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 850px; overflow: hidden; display: flex; flex-direction: column;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-mail fg-darkRed"></i> LISTADO DE CORREOS DE FACTURAS</strong></center></span>

            <!-- Buscador -->
            <div style="padding: 5px 8px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; overflow: hidden;">
                <div style="float: left;">
                    <button type="button" onclick="extraer_correos();" style="background-color: #ffffff; color: #000000; border: 1px solid #c0c0c0; padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: bold; vertical-align: middle;">ACTUALIZAR</button>
                </div>
                <div style="float: right;">
                    <i class="icon-calendar" style="color: #88010e; vertical-align: middle;" title="Filtrar por rango de fechas"></i>
                    <input type="text" id="id_rango_filtro" style="width: 170px; font-size: 13px; display: inline-block; vertical-align: middle; margin-right: 10px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; height: 30px; box-sizing: border-box;" placeholder="Filtrar por rango" />
                    <i class="icon-search" style="color: #88010e; vertical-align: middle;"></i>
                    <input type="text" id="id_busqueda_listado" class="input_pequeno" style="width: 200px; display: inline-block; vertical-align: middle;" placeholder="Buscar..." onkeyup="filtrar_listado_local();" />
                </div>
            </div>

            <!-- Listado de correos (maqueta con datos de ejemplo; en la siguiente fase se llena via AJAX) -->
            <div id="id_listado_correos" style="flex: 1; overflow-y: auto; padding: 0;">
                <table class="grid_correos">
                    <thead>
                        <tr>
                            <th style="width: 5%;">COD</th>
                            <th style="width: 12%;">FINCA</th>
                            <th style="width: 8%;">CONS</th>
                            <th style="width: 6%;">ID</th>
                            <th style="width: 14%;">FH REC</th>
                            <th style="width: 18%;">DE</th>
                            <th style="width: 15%;">PARA</th>
                            <th style="width: 9%;">EST</th>
                            <th style="width: 13%;">OPC</th>
                        </tr>
                    </thead>
                        <!-- ===== CORREO 1 ===== -->
                        <tbody id="id_grupo_correo_1" class="grupo_correo" onclick="devuelve_correo(1);">
                            <tr>
                                <td class="td_centro"><strong>1</strong></td>
                                <td>&mdash;</td>
                                <td class="td_centro">&mdash;</td>
                                <td class="td_centro">18f3a1</td>
                                <td class="td_centro">2026-05-27 08:14</td>
                                <td>facturacion@proveedor1.com</td>
                                <td>compras@divaflor.com</td>
                                <td class="td_centro">PENDIENTE</td>
                                <td class="td_opc">
                                    <a href="javascript: devuelve_correo(1);" title="Editar"><i class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: muestra_trazabilidad_correo(1);" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>
                                    <a href="javascript: ver_adjunto_correo(1);" title="Ver adjunto PDF/Excel"><i class="icon-clipboard-2 fg-darkRed"></i></a>
                                    <a href="javascript: ver_cuerpo_correo(1);" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>
                                </td>
                            </tr>
                            <tr class="fila_asunto">
                                <td colspan="6"><span class="etiqueta_asunto">SUBJ:</span>Factura electronica No. 001-002-000012345</td>
                                <td colspan="3"><span class="etiqueta_asunto">OBS:</span>&mdash;</td>
                            </tr>
                        </tbody>

                        <!-- ===== CORREO 2 ===== -->
                        <tbody id="id_grupo_correo_2" class="grupo_correo" onclick="devuelve_correo(2);">
                            <tr>
                                <td class="td_centro"><strong>2</strong></td>
                                <td>FINCA NORTE</td>
                                <td class="td_centro">C-0420</td>
                                <td class="td_centro">18f3b2</td>
                                <td class="td_centro">2026-05-27 09:02</td>
                                <td>ventas@proveedor2.com</td>
                                <td>compras@divaflor.com</td>
                                <td class="td_centro">PROCESADO</td>
                                <td class="td_opc">
                                    <a href="javascript: devuelve_correo(2);" title="Editar"><i class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: muestra_trazabilidad_correo(2);" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>
                                    <a href="javascript: ver_adjunto_correo(2);" title="Ver adjunto PDF/Excel"><i class="icon-clipboard-2 fg-darkRed"></i></a>
                                    <a href="javascript: ver_cuerpo_correo(2);" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>
                                </td>
                            </tr>
                            <tr class="fila_asunto">
                                <td colspan="6"><span class="etiqueta_asunto">SUBJ:</span>Invoice attached - Order 99812 - Roses Premium</td>
                                <td colspan="3"><span class="etiqueta_asunto">OBS:</span>OK</td>
                            </tr>
                        </tbody>

                        <!-- ===== CORREO 3 ===== -->
                        <tbody id="id_grupo_correo_3" class="grupo_correo" onclick="devuelve_correo(3);">
                            <tr>
                                <td class="td_centro"><strong>3</strong></td>
                                <td>&mdash;</td>
                                <td class="td_centro">&mdash;</td>
                                <td class="td_centro">18f3c3</td>
                                <td class="td_centro">2026-05-27 11:47</td>
                                <td>cobranzas@proveedor3.com</td>
                                <td>compras@divaflor.com</td>
                                <td class="td_centro">IGNORADO</td>
                                <td class="td_opc">
                                    <a href="javascript: devuelve_correo(3);" title="Editar"><i class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: muestra_trazabilidad_correo(3);" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>
                                    <a href="javascript: ver_adjunto_correo(3);" title="Ver adjunto PDF/Excel"><i class="icon-clipboard-2 fg-darkRed"></i></a>
                                    <a href="javascript: ver_cuerpo_correo(3);" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>
                                </td>
                            </tr>
                            <tr class="fila_asunto">
                                <td colspan="6"><span class="etiqueta_asunto">SUBJ:</span>Recordatorio de pago - Factura 0078</td>
                                <td colspan="3"><span class="etiqueta_asunto">OBS:</span>&mdash;</td>
                            </tr>
                        </tbody>

                        <!-- ===== CORREO 4 ===== -->
                        <tbody id="id_grupo_correo_4" class="grupo_correo" onclick="devuelve_correo(4);">
                            <tr>
                                <td class="td_centro"><strong>4</strong></td>
                                <td>FINCA SUR</td>
                                <td class="td_centro">C-0421</td>
                                <td class="td_centro">18f3d4</td>
                                <td class="td_centro">2026-05-27 14:20</td>
                                <td>facturas@proveedor4.com</td>
                                <td>compras@divaflor.com</td>
                                <td class="td_centro">ERROR</td>
                                <td class="td_opc">
                                    <a href="javascript: devuelve_correo(4);" title="Editar"><i class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: muestra_trazabilidad_correo(4);" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>
                                    <a href="javascript: ver_adjunto_correo(4);" title="Ver adjunto PDF/Excel"><i class="icon-clipboard-2 fg-darkRed"></i></a>
                                    <a href="javascript: ver_cuerpo_correo(4);" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>
                                </td>
                            </tr>
                            <tr class="fila_asunto">
                                <td colspan="6"><span class="etiqueta_asunto">SUBJ:</span>Factura y guia de remision - despacho 5521</td>
                                <td colspan="3"><span class="etiqueta_asunto">OBS:</span>REV</td>
                            </tr>
                        </tbody>
                </table>
                <div style="text-align:right; font-size:11px; color:#666; padding:5px;">Total: 4 correos (datos de ejemplo)</div>
            </div>
        </div>

        <!-- ===== COLUMNA DERECHA: DATOS (arriba) + REPORTES/OPCIONES (abajo) ===== -->
        <div style="display: flex; flex-direction: column; width: 360px; height: 850px; gap: 8px;">

            <!-- ZONA 2: DATOS DEL CORREO -->
            <div id="id_formulario_correo" class="aida" style="width: 360px;">
                <div class="ribbed-crimson" style="height: 2px;"></div>
                <span><center><strong><i class="icon-box fg-darkRed"></i> DATOS DEL CORREO</strong></center></span>

                <div style="padding: 12px;">
                    <table style="width: 100%; font-size: 13px;">
                        <colgroup>
                            <col style="width: 35%;">
                            <col style="width: 65%;">
                        </colgroup>
                        <!-- FINCA (editable) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">FINCA:</td>
                            <td style="padding-bottom: 8px;">
                                <select id="id_codigo_finca" style="width: 100%;">
                                    <option value="0">-- SELECCIONE --</option>
                                </select>
                            </td>
                        </tr>
                        <!-- CONSOLIDADO (editable) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CONSOLIDADO:</td>
                            <td style="padding-bottom: 8px;">
                                <select id="id_codigo_consolidado" style="width: 100%;">
                                    <option value="0">-- SELECCIONE --</option>
                                </select>
                            </td>
                        </tr>
                        <!-- ESTADO (editable) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">ESTADO:</td>
                            <td style="padding-bottom: 8px;">
                                <select id="id_estado_correo" style="width: 100%;">
                                    <option value="1">PENDIENTE</option>
                                    <option value="2">PROCESADO</option>
                                    <option value="3">IGNORADO</option>
                                    <option value="4">ERROR</option>
                                </select>
                            </td>
                        </tr>
                        <!-- OBSERVACIONES (editable, ~4 lineas) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; vertical-align: top; white-space: nowrap;">OBSERVAC.:</td>
                            <td style="padding-bottom: 8px;">
                                <textarea id="id_observaciones_correo" maxlength="255" rows="4"
                                    style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"
                                    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9\s\.\,\-\/\(\)\#]/g, '');"></textarea>
                            </td>
                        </tr>

                        <!-- ===== Detalle del correo (SOLO LECTURA) ===== -->
                        <tr>
                            <td colspan="2" style="padding: 6px 0 4px 0;">
                                <div style="border-top: 1px dashed #ccc; padding-top: 6px; font-size: 11px; color: #999;">DETALLE DEL CORREO (solo lectura)</div>
                            </td>
                        </tr>
                        <!-- MESSAGEID -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">MESSAGEID:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_messageid_correo" class="input_readonly" readonly />
                            </td>
                        </tr>
                        <!-- THREADID -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">THREADID:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_threadid_correo" class="input_readonly" readonly />
                            </td>
                        </tr>
                        <!-- CC -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">CC:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_cc_correo" class="input_readonly" readonly />
                            </td>
                        </tr>
                        <!-- BCC -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">BCC:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_bcc_correo" class="input_readonly" readonly />
                            </td>
                        </tr>
                        <!-- FH PRO (FECHAPROCESADO) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">FH PRO:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_fecha_procesado_correo" class="input_readonly" readonly />
                            </td>
                        </tr>
                    </table>

                    <!-- Botones GRABAR / NUEVO -->
                    <div style="text-align: right; margin-top: 10px;">
                        <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_correo();">GRABAR</button>
                        <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">NUEVO</button>
                    </div>
                </div>
            </div>

            <!-- ZONA 3: REPORTES / OPCIONES -->
            <div id="id_reportes_opciones" class="aida" style="width: 360px; flex: 1; display: flex; flex-direction: column;">
                <div class="ribbed-crimson" style="height: 2px;"></div>
                <span><center><strong><i class="icon-cog fg-darkRed"></i> REPORTES / OPCIONES</strong></center></span>

                <div style="padding: 12px;">
                    <table style="width: 100%; font-size: 13px;">
                        <colgroup>
                            <col style="width: 30%;">
                            <col style="width: 70%;">
                        </colgroup> 
                        <!-- RANGO DE FECHAS (un solo campo) -->
                        <tr>
                            <td style="text-align: right; padding-right: 8px; padding-bottom: 8px; white-space: nowrap;">RANGO:</td>
                            <td style="padding-bottom: 8px;">
                                <input type="text" id="id_rango_fechas" name="rango_fechas" style="width: 95%; font-size: 14px;" placeholder="Seleccione rango" />
                            </td>
                        </tr>
                    </table>

                    <!-- Boton EXTRAER -->
                    <div style="text-align: right; margin-top: 10px;">
                        <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="extraer_correos();">EXTRAER</button>
                    </div>
                </div>
            </div>

        </div>

    </div>
 
    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body> 
</html>
