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
.grid_correos tbody .fila_adjunto td {
    background-color: #f4f4f4;
    font-size: 10px;
    color: #555;
    padding: 3px 6px;
    border-bottom: 1px dotted #ddd !important;
    }
.grid_correos tbody .fila_adjunto td:first-child {
    box-shadow: inset 3px 0 0 #cfa3a3;
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
.ui-tooltip {  
    background: #fff;
    color: #333;
    border: 1px solid #88010e;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    max-width: 400px; 
    white-space: pre-line;
    }  
.ui-helper-hidden-accessible,
div[id^="ui-tooltip"] {
    visibility: hidden !important;
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
    }
</style>
<script language="javascript">
var global_codigo_correo_seleccionado = 0;
var global_ordenamiento = "FECHAHORA";
var global_direccion = "DESC";
var global_intervalo_progreso = null;

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
    $("#id_listado_correos .grupo_correo").each(function()
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

// ===== Ver cuerpo del correo en modal (iframe a ver_cuerpo.php) =====
function ver_cuerpo_correo(codigo_correo, asunto)
    {
    var titulo = "Cuerpo del correo";
    if(asunto && asunto.length > 0)
        {
        titulo = titulo + " - " + asunto;
        }
    $("#id_dialog_cuerpo").dialog("option", "title", titulo);
    $("#id_dialog_cuerpo").html('<iframe src="ver_cuerpo.php?codigo=' + codigo_correo + '" style="width:100%; height:100%; border:none;"></iframe>');
    $("#id_dialog_cuerpo").dialog("open");
    }

// ===== Ver adjunto PDF en modal (iframe a ver_adjunto.php) =====
function ver_adjunto_pdf(codigo_adjunto, nombre_archivo)
    {
    var titulo = "Adjunto";
    if(nombre_archivo && nombre_archivo.length > 0)
        {
        titulo = titulo + ": " + nombre_archivo;
        }
    $("#id_dialog_pdf").dialog("option", "title", titulo);
    $("#id_dialog_pdf").html('<iframe src="ver_adjunto.php?codigo=' + codigo_adjunto + '" style="width:100%; height:100%; border:none;"></iframe>');
    $("#id_dialog_pdf").dialog("open");
    }

// ===== Placeholder: grabar (siguiente fase) =====
function grabar_correo()
    {
    messageBox("Grabado - pendiente implementar (maqueta)");
    }

// ===== Extraer correos desde Gmail por rango de fechas =====
function extraer_correos()
    {
    var rango = $("#id_rango_fechas").val();
    var fechas = rango.match(/\d{4}-\d{2}-\d{2}/g);
    if(fechas == null || fechas.length < 2)
        {
        messageBox("Por favor seleccione un rango de fechas (desde y hasta)");
        return;
        }
    var fecha_desde = fechas[0];
    var fecha_hasta = fechas[1];
    $("#id_espera").show();

    // Mostrar progreso e iniciar polling al endpoint progreso_extraccion.
    $("#id_progreso_extraccion").show().html("Iniciando extraccion...");
    if(global_intervalo_progreso != null)
        clearInterval(global_intervalo_progreso);
    global_intervalo_progreso = setInterval(function()
        {
        $.get("funciones_ajax.php?funcion=progreso_extraccion", function(data)
            {
            try
                {
                var p = JSON.parse(data);
                if(p.estado == "en_curso")
                    {
                    var html = "Procesando dia: " + (p.dia_actual || "...") + "<br>";
                    html += "Correos: " + p.procesados + " procesados<br>";
                    html += "Guardados: " + p.guardados + " | Saltados: " + p.saltados;
                    if(p.total_dias)
                        html += "<br>(Total dias: " + p.total_dias + ")";
                    $("#id_progreso_extraccion").html(html);
                    }
                }
            catch(e) {}
            });
        }, 2000);

    var url = "funciones_ajax.php?funcion=extraer_correos_facturas&parametro1="+fecha_desde+"&parametro2="+fecha_hasta;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        clearInterval(global_intervalo_progreso);
        global_intervalo_progreso = null;
        $("#id_progreso_extraccion").hide();
        $("#id_espera").hide();
        messageBox(data);
        actualiza_listado();
        });
    obj_ajax.fail(function(jqXHR, textStatus, errorThrown)
        {
        clearInterval(global_intervalo_progreso);
        global_intervalo_progreso = null;
        $("#id_progreso_extraccion").hide();
        $("#id_espera").hide();
        var msg = "Error en la extraccion:\n";
        msg += "Estado: " + textStatus + "\n";
        msg += "HTTP: " + jqXHR.status + "\n";
        if(errorThrown)
            msg += "Detalle: " + errorThrown;
        messageBox(msg);
        });
    }

// ===== Procesar una factura adjunta con IA (Claude/GLM via CLI) =====
function procesar_factura(codigo_adjunto, nombre_archivo)
    {
    $("#id_dialog_confirma_factura").html(
        "<p>Procesar factura con IA:</p>" +
        "<p><strong>" + nombre_archivo + "</strong></p>" +
        "<p>Codigo adjunto: " + codigo_adjunto + "</p>"
        );
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 420,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "Procesar",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    ejecutar_procesamiento_factura(codigo_adjunto, nombre_archivo);
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
    }

// ===== Ejecuta el procesamiento de la factura via AJAX + polling de progreso =====
function ejecutar_procesamiento_factura(codigo_adjunto, nombre_archivo)
    {
    var hora_inicio_proc = new Date();
    var hora_inicio_str = hora_inicio_proc.getHours().toString().padStart(2,'0') + ":"
                        + hora_inicio_proc.getMinutes().toString().padStart(2,'0') + ":"
                        + hora_inicio_proc.getSeconds().toString().padStart(2,'0');
    $("#id_progreso_extraccion").show().html(
        "Iniciando procesamiento de " + nombre_archivo + "...<br>"
        + "<span style='font-size:12px; color:#555;'>Inicio: " + hora_inicio_str + "</span>"
        );
    $("#id_espera").show();

    // Polling: el script CLI corre en background. El endpoint progreso_factura
    // detecta cuando termina leyendo tmp_factura_output_<codigo>.txt.
    if(global_intervalo_progreso != null)
        clearInterval(global_intervalo_progreso);
    global_intervalo_progreso = setInterval(function()
        {
        $.get("funciones_ajax.php?funcion=progreso_factura&parametro1=" + codigo_adjunto, function(data)
            {
            try
                {
                var p = JSON.parse(data);
                if(p.estado == "en_curso")
                    {
                    var ahora2 = new Date();
                    var hora2 = ahora2.getHours().toString().padStart(2,'0') + ":"
                              + ahora2.getMinutes().toString().padStart(2,'0') + ":"
                              + ahora2.getSeconds().toString().padStart(2,'0');
                    var segs = Math.round((ahora2 - hora_inicio_proc) / 1000);
                    $("#id_progreso_extraccion").html(
                        (p.mensaje || "Procesando...") + "<br>"
                        + "<span style='font-size:12px; color:#555;'>Inicio: "
                        + hora_inicio_str + " | Transcurrido: " + segs
                        + "s | Actualizado: " + hora2 + "</span>"
                        );
                    }
                else if(p.estado == "finalizado")
                    {
                    clearInterval(global_intervalo_progreso);
                    global_intervalo_progreso = null;
                    $("#id_progreso_extraccion").hide();
                    $("#id_espera").hide();
                    var msg = p.mensaje || "Procesamiento completado";
                    if(p.codigo_factura > 0)
                        msg += '<br><a href="ver_factura_finca.php?codigo='
                             + p.codigo_factura
                             + '" target="_blank" style="color:#88010e; font-weight:bold;">Ver factura procesada</a>';
                    messageBox(msg);
                    actualiza_listado();
                    }
                else if(p.estado == "error")
                    {
                    clearInterval(global_intervalo_progreso);
                    global_intervalo_progreso = null;
                    $("#id_progreso_extraccion").hide();
                    $("#id_espera").hide();
                    messageBox("Error: " + (p.mensaje || "Error desconocido"));
                    actualiza_listado();
                    }
                }
            catch(e) {}
            });
        }, 2000);

    // El AJAX solo dispara el proceso en background y retorna casi instantaneo.
    // El resultado real lo detecta el polling cuando aparece "=== FIN ===".
    var url = "funciones_ajax.php?funcion=procesar_factura_web&parametro1=" + codigo_adjunto;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        // El proceso ya esta corriendo en background. No hacer nada aqui;
        // el polling se encarga de detectar el fin y mostrar el resultado.
        });
    obj_ajax.fail(function(jqXHR, textStatus, errorThrown)
        {
        clearInterval(global_intervalo_progreso);
        global_intervalo_progreso = null;
        $("#id_progreso_extraccion").hide();
        $("#id_espera").hide();
        messageBox("Error al iniciar el proceso:\nEstado: " + textStatus + "\nHTTP: " + jqXHR.status);
        });
    }

// ===== Ordenar el listado por una columna (alterna ASC/DESC) =====
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

// ===== Actualizar listado: trae los correos reales por AJAX, con filtro por rango si esta seteado =====
function actualiza_listado(callback_post)
    {
    $("#id_espera").show();
    var fecha_desde = "";
    var fecha_hasta = "";
    var rango = $("#id_rango_filtro").val();
    if(rango)
        {
        var fechas = rango.match(/\d{4}-\d{2}-\d{2}/g);
        if(fechas && fechas.length >= 2)
            {
            fecha_desde = fechas[0];
            fecha_hasta = fechas[1];
            }
        }
    var url = "funciones_ajax.php?funcion=lista_correos_facturas"
        +"&parametro1="+global_ordenamiento
        +"&parametro2="+global_direccion
        +"&parametro3="+fecha_desde
        +"&parametro4="+fecha_hasta;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        $("#id_listado_correos").html(data);
        // Reaplicar el filtro de texto si el usuario tenia algo escrito,
        // asi un actualiza_listado() disparado tras procesar/extraer no
        // tira el estado del buscador.
        filtrar_listado_local();
        if(typeof callback_post === "function")
            callback_post();
        });
    obj_ajax.fail(function(jqXHR, textStatus, errorThrown)
        {
        $("#id_espera").hide();
        var msg = "Error al actualizar listado:\n";
        msg += "Estado: " + textStatus + "\n";
        msg += "HTTP: " + jqXHR.status + "\n";
        if(errorThrown)
            msg += "Detalle: " + errorThrown;
        messageBox(msg);
        });
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
    $("#id_dialog_cuerpo").dialog(
        {
        autoOpen: false, modal: true, width: 700, height: 800, resizable: true,
        dialogClass: 'myTitleClass',
        buttons: [{text: "Cerrar", class: 'cancelButton', click: function() {$(this).dialog("close");}}]
        });
    $("#id_dialog_pdf").dialog(
        {
        autoOpen: false, modal: true, width: 600, height: 800, resizable: true,
        dialogClass: 'myTitleClass',
        buttons: [{text: "Cerrar", class: 'cancelButton', click: function() {$(this).dialog("close");}}]
        });

    // Tooltip de jQuery UI para las celdas DE / PARA (delegado, sirve para el listado cargado por AJAX)
    $(document).tooltip({
        items: "[data-tooltip]",
        content: function() {
            var raw = $(this).attr("data-tooltip");
            if(!raw) return "";
            var t = document.createElement("textarea");
            t.innerHTML = raw;
            var decoded = t.value;
            return decoded.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            },
        track: true,
        hide: { effect: "fadeOut", duration: 100 },
        show: { effect: "fadeIn", duration: 150 }
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
        locale: "es",
        onChange: function(selectedDates, dateStr, instance)
            {
            if(selectedDates.length == 2)
                {
                actualiza_listado();
                }
            },
        onClose: function(selectedDates, dateStr, instance)
            {
            if(selectedDates.length == 0)
                {
                actualiza_listado();
                }
            }
        });

    // Rango de fechas ABAJO (extraccion desde Gmail) - Flatpickr en modo range
    flatpickr("#id_rango_fechas",
        {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "es"
        });

    boton_nuevo();
    actualiza_listado();
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
                    <button type="button" onclick="actualiza_listado();" style="background-color: #ffffff; color: #000000; border: 1px solid #c0c0c0; padding: 4px 12px; cursor: pointer; font-size: 12px; font-weight: bold; vertical-align: middle;">ACTUALIZAR</button>
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
                </table>
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
    <div id="id_dialog_cuerpo" title=""></div>
    <div id="id_dialog_pdf" title=""></div>
    <div id="id_dialog_confirma_factura" title="Confirmar"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>
    <div id="id_progreso_extraccion" style="position:fixed; z-index:1001; top:50%; left:50%; transform:translate(-50%,-50%); background:rgba(255,255,255,0.95); padding:20px 30px; border-radius:8px; border:2px solid #88010e; font-size:14px; font-weight:bold; color:#88010e; display:none; text-align:center; min-width:280px;"></div>

</body>
</html> 
 