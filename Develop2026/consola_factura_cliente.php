<?php     
include("variables_globales.php");
include("funciones.php");
include("funciones_v2.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS (igual que consola_consolidado.php).
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'], $permiso);
$usuario_web = $_SESSION['s_codigo'];

$fecha_hoy      = date("Y-m-d");
$fecha_hora_hoy = date("Y-m-d H:i:s");

// Conectar a la BD para leer la cabecera del invoice y los catalogos inline.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo_factura_cliente = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 0;

// Cabecera del invoice.
$sql_cab = "SELECT CODIGO AS CODIGO, NUMEROINVOICE AS NUMEROINVOICE,
    FECHAINVOICE AS FECHAINVOICE, FECHAVUELO AS FECHAVUELO,
    NOMBREMARCACION AS NOMBREMARCACION, PAIS AS PAIS, LABEL AS LABEL,
    AWB AS AWB, CODIGOCONSOLIDADO AS CODIGOCONSOLIDADO
    FROM factura_cliente
    WHERE CODIGO = ".$codigo_factura_cliente."
      AND ESTADO >= 0";
$res_cab   = mysqli_query($link, $sql_cab);
$cab       = ($res_cab && mysqli_num_rows($res_cab) > 0) ? mysqli_fetch_assoc($res_cab) : null;

// Opciones de tipo de cobro (para el dialog "Agregar cobro"). Se vuelcan inline
// en el <select> estatico del dialog (el placeholder ya es la primera opcion).
$sql_tipos_cobro          = "SELECT CODIGO, NOMBRE FROM tipo_cobro WHERE ESTADO = 1 ORDER BY NOMBRE";
$res_tipos_cobro          = mysqli_query($link, $sql_tipos_cobro);
$opciones_tipo_cobro_form = "";
if($res_tipos_cobro)
    {
    $total_tipos_cobro = mysqli_num_rows($res_tipos_cobro);
    for($i=1; $i<=$total_tipos_cobro; $i++)
        {
        $tc                        = mysqli_fetch_assoc($res_tipos_cobro);
        $opciones_tipo_cobro_form .= '<option value="'.(int)$tc["CODIGO"].'">'
            .htmlspecialchars((string)$tc["NOMBRE"], ENT_QUOTES, "UTF-8")
            .'</option>';
        }
    }

// Valores de cabecera para el header (o vacios si no se encontro el invoice).
$num_invoice = ($cab !== null) ? htmlspecialchars((string)$cab["NUMEROINVOICE"], ENT_QUOTES, "UTF-8") : "";
$ship_date   = ($cab !== null) ? htmlspecialchars((string)$cab["FECHAVUELO"], ENT_QUOTES, "UTF-8") : "";
$customer    = ($cab !== null) ? htmlspecialchars(strtoupper((string)$cab["NOMBREMARCACION"]), ENT_QUOTES, "UTF-8") : "";
$address     = ($cab !== null) ? htmlspecialchars(strtoupper((string)$cab["PAIS"]), ENT_QUOTES, "UTF-8") : "";
$label       = ($cab !== null) ? htmlspecialchars(strtoupper((string)$cab["LABEL"]), ENT_QUOTES, "UTF-8") : "";
$awb         = ($cab !== null) ? htmlspecialchars((string)$cab["AWB"], ENT_QUOTES, "UTF-8") : "";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Invoice Cliente</title>
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

/* Fix para que Select2 no se corte dentro de dialogs jQuery UI */
.ui-dialog { overflow: visible !important; }
.ui-dialog .ui-dialog-content { overflow: visible !important; }

/* Header del invoice. */
.invoice_wrap {
    max-width: 1150px;
    margin: 15px 0 15px 5px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 18px 22px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
.invoice_header {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    border-bottom: 2px solid #88010e;
    padding-bottom: 10px;
    margin-bottom: 6px;
    }
.invoice_empresa {
    font-size: 12px;
    color: #555;
    line-height: 1.5;
    }
.invoice_empresa .titulo {
    font-weight: bold;
    color: #88010e;
    font-size: 15px;
    }
.invoice_datos {
    font-size: 12px;
    line-height: 1.9;
    }
.invoice_datos .etq {
    font-weight: bold;
    color: #88010e;
    display: inline-block;
    min-width: 120px;
    }
.invoice_datos input[type="text"] {
    font-size: 12px;
    padding: 3px 6px;
    border: 1px solid #c0c0c0;
    border-radius: 2px;
    }
.celda_editable_fc:hover {
    background-color: rgba(136,1,14,0.08) !important;
    }

/* Select2. */
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

/* Flatpickr - estilo crimson. */
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
var global_codigo_factura       = <?php echo (int)$codigo_factura_cliente; ?>;
var global_codigo_usuario       = <?php echo (int)$_SESSION['s_codigo']; ?>;
var flatpickr_shipdate          = null;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }

// ===== Cargar / recargar el detalle completo (grid + cobros + totales) =====
function cargar_detalle()
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=detalle_factura_cliente_dsft"
        + "&parametro1=" + global_codigo_factura;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_detalle_invoice").html(data);
        });
    }

// ===== Edicion inline de celdas del grid =====
$(document).on("click", ".celda_editable_fc", function()
    {
    var td = $(this);
    if(td.find("input").length > 0)
        return; // ya editando

    var valor_actual = td.text().trim();
    var alinear      = (td.hasClass("celda_cm_fc") || td.data("campo") == "PRECIOUNITARIO") ? "right" : "left";
    var input = '<input type="text" value="' + valor_actual
        + '" data-orig="' + valor_actual
        + '" style="width:90%; font-size:11px; text-align:' + alinear + ';">';
    td.html(input);
    td.find("input").focus().select();
    });

$(document).on("blur", ".celda_editable_fc input", function()
    {
    var input  = $(this);
    var td     = input.closest(".celda_editable_fc");
    var codigo = td.data("codigo");
    var campo  = td.data("campo");
    var valor  = input.val().trim();
    var orig   = String(input.data("orig"));

    if(valor == orig)
        {
        cargar_detalle(); // sin cambios: restaurar vista
        return;
        }
    guardar_celda(codigo, campo, valor);
    });

$(document).on("keydown", ".celda_editable_fc input", function(e)
    {
    if(e.which == 13)
        $(this).blur();
    else if(e.which == 27)
        cargar_detalle();
    });

function guardar_celda(codigo, campo, valor)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=actualizar_celda_detalle_factura_cliente_dsft"
        + "&parametro1=" + codigo
        + "&parametro2=" + encodeURIComponent(campo)
        + "&parametro3=" + encodeURIComponent(valor);
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        var resp = data.trim();
        if(resp.indexOf("ERROR:") === 0)
            messageBox(resp);
        cargar_detalle();
        });
    }

// ===== Header editable: INVOICE NUM y SHIP DATE =====
function guardar_cabecera(campo, valor)
    {
    var url = "funciones_ajax.php?funcion=actualizar_cabecera_factura_cliente_dsft"
        + "&parametro1=" + global_codigo_factura
        + "&parametro2=" + encodeURIComponent(campo)
        + "&parametro3=" + encodeURIComponent(valor);
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        var resp = data.trim();
        if(resp.indexOf("ERROR:") === 0)
            messageBox(resp);
        });
    }

// ===== Cobros =====
// El dialog es un div estatico inicializado en document.ready (autoOpen:false).
// Aqui solo se limpian los campos y se abre.
function dialog_agregar_cobro()
    {
    $("#id_select_tipo_cobro").val("0").trigger("change");
    $("#id_monto_cobro").val("0.00");
    $("#id_dialog_agregar_cobro").dialog("open");
    }

function ejecutar_agregar_cobro()
    {
    var tipo  = $("#id_select_tipo_cobro").val();
    var monto = $("#id_monto_cobro").val();
    if(tipo == null || tipo == "" || tipo == "0")
        {
        messageBox("Por favor seleccione un tipo de cobro");
        return;
        }
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=agregar_cobro_factura_cliente_dsft"
        + "&parametro1=" + global_codigo_factura
        + "&parametro2=" + tipo
        + "&parametro3=" + encodeURIComponent(monto);
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        var resp = data.trim();
        if(resp.indexOf("ERROR:") === 0)
            {
            messageBox(resp);
            return;
            }
        $("#id_dialog_agregar_cobro").dialog("close");
        cargar_detalle();
        });
    }

function eliminar_cobro_fc(codigo_cobro)
    {
    $("#id_dialog_confirma").html("¿Eliminar este cobro?");
    $("#id_dialog_confirma").dialog(
        {
        modal: true,
        width: 320,
        title: "Confirmar",
        dialogClass: 'myTitleClass',
        buttons: [
            {
            text: "Eliminar",
            class: 'cancelButton',
            click: function()
                {
                var d = $(this);
                $("#id_espera").show();
                var url = "funciones_ajax.php?funcion=eliminar_cobro_factura_cliente_dsft"
                    + "&parametro1=" + codigo_cobro;
                var obj_ajax = $.get(url, function(data, status){;});
                obj_ajax.success(function(data, status)
                    {
                    $("#id_espera").hide();
                    d.dialog("close");
                    var resp = data.trim();
                    if(resp.indexOf("ERROR:") === 0)
                        {
                        messageBox(resp);
                        return;
                        }
                    cargar_detalle();
                    });
                }
            },
            {
            text: "Cancelar",
            click: function()
                {
                $(this).dialog("close");
                }
            }
            ]
        });
    $("#id_dialog_confirma").dialog("open");
    }

// ===== Descarga Excel / PDF =====
function descargar_invoice(formato)
    {
    if(formato == "pdf")
        window.open("funciones_ajax.php?funcion=generar_pdf_factura_cliente_dsft&parametro1=" + global_codigo_factura, "_blank");
    else
        window.open("funciones_ajax.php?funcion=generar_excel_factura_cliente_dsft&parametro1=" + global_codigo_factura, "_blank");
    }

$(document).ready(function()
    {
    $("#id_espera").hide();
    $("#dialog").dialog(
        {
        modal: true,
        buttons: [{text: "Aceptar", class: 'cancelButton', click: function() {$(this).dialog("close");}}],
        autoOpen: false,
        dialogClass: 'myTitleClass'
        });

    // Dialog de "Agregar cobro" (estatico, se abre desde dialog_agregar_cobro()).
    $("#id_dialog_agregar_cobro").dialog(
        {
        modal: true,
        width: 420,
        autoOpen: false,
        title: "Agregar cobro",
        dialogClass: 'myTitleClass',
        buttons:
            [
            {text: "Agregar", class: 'cancelButton', click: function() { ejecutar_agregar_cobro(); }},
            {text: "Cancelar", class: 'cancelButton', click: function() { $(this).dialog("close"); }}
            ]
        });

    // Select2 del tipo de cobro. dropdownParent evita que el dropdown se corte
    // dentro del dialog.
    $('#id_select_tipo_cobro').select2(
        {
        width: '100%',
        minimumResultsForSearch: 3,
        placeholder: "-- SELECCIONE TIPO COBRO --",
        dropdownParent: $("#id_dialog_agregar_cobro")
        });

    flatpickr_shipdate = flatpickr("#id_fechainvoice",
        {
        dateFormat: "Y-m-d",
        locale: "es",
        allowInput: false,
        onChange: function(selectedDates, dateStr, instance)
            {
            guardar_cabecera("FECHAINVOICE", dateStr);
            }
        });

    <?php if($cab !== null): ?>
    cargar_detalle();
    <?php endif; ?>
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

<?php if($cab === null): ?>
    <div class="invoice_wrap">
        <div style="text-align:center; color:#88010e; padding:40px; font-size:14px;">
            <i class="icon-warning" style="font-size:28px;"></i><br><br>
            No se encontro el invoice solicitado (codigo <?php echo (int)$codigo_factura_cliente; ?>).
        </div>
    </div>
<?php else: ?>
    <div class="invoice_wrap">

        <!-- HEADER DEL INVOICE -->
        <div class="invoice_header">
            <div class="invoice_empresa">
                <span class="titulo">DIVA FLOREX S.A.S.</span><br>
                RUC 1793189840001<br>
                Av. 6 de Diciembre N34-155 Dpt 84, Quito, Ecuador<br>
                Phone: +593999135857, +59326010256
            </div>
            <div style="text-align:right;">
                <a onclick="descargar_invoice('xlsx');" style="cursor:pointer; color:#2e7d32; font-size:20px; margin-right:8px;" title="Descargar Excel"><i class="icon-file-excel"></i></a>
                <a onclick="descargar_invoice('pdf');" style="cursor:pointer; color:#88010e; font-size:20px;" title="Descargar PDF"><i class="icon-file-pdf"></i></a>
            </div>
        </div>

        <div style="display:flex; gap:40px; flex-wrap:wrap; margin-top:6px;">
            <div class="invoice_datos">
                <div><span class="etq">INVOICE NUM:</span>
                    <input type="text" id="id_numeroinvoice" value="<?php echo $num_invoice; ?>"
                        style="width:150px;" maxlength="32"
                        onblur="guardar_cabecera('NUMEROINVOICE', this.value);" />
                </div>
                <div><span class="etq">SHIP DATE:</span>
                    <input type="text" id="id_fechainvoice" value="<?php echo $ship_date; ?>"
                        style="width:150px; background:#fff; cursor:pointer;" placeholder="Click para fecha" />
                </div>
            </div>
            <div class="invoice_datos">
                <div><span class="etq">CUSTOMER NAME:</span> <?php echo $customer; ?></div>
                <div><span class="etq">ADDRESS:</span> <?php echo $address; ?></div>
                <div><span class="etq">LABEL:</span> <?php echo $label; ?></div>
                <div><span class="etq">AWB NUMBER:</span> <?php echo $awb; ?></div>
            </div>
        </div>

        <!-- DETALLE (grid + cobros + totales + datos bancarios) via AJAX -->
        <div id="id_detalle_invoice" style="margin-top:10px;"></div>

    </div>
<?php endif; ?>

    <!-- Dialogs y overlays -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_dialog_confirma" title="Confirmar"></div>

    <!-- Dialog "Agregar cobro" (estatico; el select se llena inline con PHP). -->
    <div id="id_dialog_agregar_cobro" style="display:none;">
        <div style="margin-bottom: 12px;">
            <label style="font-size: 13px; font-weight: bold;">Tipo de cobro:</label>
            <select id="id_select_tipo_cobro" style="width: 100%;">
                <option value="0">-- SELECCIONE TIPO COBRO --</option>
                <?php echo $opciones_tipo_cobro_form; ?>
            </select>
        </div>
        <div>
            <label style="font-size: 13px; font-weight: bold;">Monto (USD):</label>
            <input type="text" id="id_monto_cobro" class="input_pequeno" style="width: 150px; text-align: right;" placeholder="0.00" />
        </div>
    </div>

    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body>
</html>
