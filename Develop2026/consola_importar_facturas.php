<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);
$usuario_web = $_SESSION['s_codigo'];

$fecha_hora_hoy = date("Y-m-d H:i:s");
?>
<!DOCTYPE html>
<html> 
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0">
<title><?php echo $titulo_hoja;?> - Importar Facturas</title>
<?php include("css_v4.php"); ?>
<script language="javascript" src="controles_especiales.js"></script>
<style>
body.metro {
    background-color: #edededff !important;
    background-image: none !important;
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
.ui-button.cancelButton          { background: #88010e; color: #FFFFFF; }

.fg-darkRed { color: #88010e; }
.fg-teal    { color: #155a60; }
.fg-brown   { color: #8a5048; }
.fg-gray    { color: #888888; }

/* ===== Zona de seleccion del PDF ===== */
.zona_archivo_pdf {
    border: 2px dashed #88010e;
    border-radius: 6px;
    background-color: #fffbe6;
    padding: 30px 20px;
    text-align: center;
    transition: background-color 0.2s, border-color 0.2s;
    }
.zona_archivo_pdf.zona_con_archivo {
    background-color: #f0fff0;
    border-color: #2d8f2d;
    border-style: solid;
    }
.zona_archivo_pdf .icono_pdf {
    font-size: 48px;
    color: #88010e;
    }
.zona_archivo_pdf.zona_con_archivo .icono_pdf {
    color: #2d8f2d;
    }
.zona_archivo_pdf .texto_principal {
    font-size: 14px;
    font-weight: bold;
    color: #333;
    margin-top: 8px;
    }
.zona_archivo_pdf .texto_secundario {
    font-size: 11px;
    color: #777;
    margin-top: 4px;
    }
.zona_archivo_pdf .nombre_archivo {
    font-size: 13px;
    color: #2d8f2d;
    font-weight: bold;
    margin-top: 8px;
    word-break: break-all;
    }
.zona_archivo_pdf .tamano_archivo {
    font-size: 11px;
    color: #555;
    margin-top: 2px;
    }

/* Input file oculto - solo se accede via boton */
#id_archivo_pdf {
    display: none;
    }

.boton_accion {
    padding: 8px 20px;
    font-size: 13px;
    font-weight: bold;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    transition: background-color 0.15s;
    }
.boton_accion:disabled {
    background-color: #cccccc !important;
    color: #888888 !important;
    cursor: not-allowed;
    }
.boton_procesar {
    background-color: #88010e;
    color: white;
    }
.boton_procesar:hover:not(:disabled) {
    background-color: #a8010e;
    }
.boton_limpiar {
    background-color: #888888;
    color: white;
    }
.boton_limpiar:hover {
    background-color: #6a6a6a;
    }

/* ===== Zona de resultado ===== */
.zona_resultado {
    background-color: #ffffff;
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 12px;
    color: #333;
    min-height: 200px;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-y: auto;
    max-height: 500px;
    }
.zona_resultado_placeholder {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 60px 20px;
    font-family: inherit;
    }
</style>
<script language="javascript">
// ===== Variables globales =====
var global_archivo_seleccionado = null;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    }

// ===== Click en la zona - dispara el input file oculto =====
function abre_selector_archivo()
    {
    $("#id_archivo_pdf").click();
    }

// ===== Se ejecuta cuando el usuario selecciona un archivo =====
function archivo_seleccionado(input)
    {
    if(input.files.length == 0)
        return;
    var archivo = input.files[0];
    // Validacion: solo PDF
    var nombre_lower = archivo.name.toLowerCase();
    if(nombre_lower.substring(nombre_lower.length - 4) != ".pdf")
        {
        messageBox("El archivo debe ser un PDF (.pdf)");
        $("#id_archivo_pdf").val("");
        return;
        }
    // Validacion: tamano maximo 32MB (limite de la API de Anthropic)
    var tamano_mb = archivo.size / (1024 * 1024);
    if(tamano_mb > 32)
        {
        messageBox("El archivo es demasiado grande ("+tamano_mb.toFixed(2)+" MB). El maximo permitido es 32 MB.");
        $("#id_archivo_pdf").val("");
        return;
        }
    // Archivo valido - guardar y actualizar UI
    global_archivo_seleccionado = archivo;
    var tamano_kb = (archivo.size / 1024).toFixed(1);
    $(".zona_archivo_pdf").addClass("zona_con_archivo");
    $("#id_icono_zona").removeClass("icon-file-pdf").addClass("icon-checkmark");
    $("#id_texto_principal_zona").html("ARCHIVO LISTO PARA PROCESAR");
    $("#id_texto_secundario_zona").hide();
    $("#id_nombre_archivo").html(archivo.name).show();
    $("#id_tamano_archivo").html(tamano_kb+" KB").show();
    $("#id_boton_procesar").prop('disabled', false);
    }

// ===== Procesar la factura (placeholder - aqui ira el AJAX al API en la siguiente fase) =====
function procesa_factura()
    {
    if(global_archivo_seleccionado == null)
        {
        messageBox("Por favor seleccione un archivo PDF primero");
        return;
        }
    $("#id_espera").show();
    // TODO siguiente fase: enviar el archivo via FormData a funciones_ajax.php
    //                     que a su vez llamara a llamar_api_claude() y devolvera el JSON
    // Por ahora solo mostramos placeholder simulando la espera
    setTimeout(function()
        {
        $("#id_espera").hide();
        var texto_placeholder = "[ FASE GUI - SIN PROCESAMIENTO REAL TODAVIA ]\n\n";
        texto_placeholder += "Archivo recibido: "+global_archivo_seleccionado.name+"\n";
        texto_placeholder += "Tamano: "+(global_archivo_seleccionado.size/1024).toFixed(1)+" KB\n";
        texto_placeholder += "Tipo: "+global_archivo_seleccionado.type+"\n\n";
        texto_placeholder += "En la siguiente fase aqui apareceran los datos extraidos\n";
        texto_placeholder += "por la API de Claude (Anthropic) en formato JSON.";
        $("#id_zona_resultado").removeClass("zona_resultado_placeholder").html(texto_placeholder);
        }, 800);
    }

// ===== boton_nuevo: limpia la seleccion y la zona de resultado =====
function boton_nuevo()
    {
    global_archivo_seleccionado = null;
    $("#id_archivo_pdf").val("");
    $(".zona_archivo_pdf").removeClass("zona_con_archivo");
    $("#id_icono_zona").removeClass("icon-checkmark").addClass("icon-file-pdf");
    $("#id_texto_principal_zona").html("HAGA CLICK PARA SELECCIONAR UNA FACTURA PDF");
    $("#id_texto_secundario_zona").show();
    $("#id_nombre_archivo").html("").hide();
    $("#id_tamano_archivo").html("").hide();
    $("#id_boton_procesar").prop('disabled', true);
    $("#id_zona_resultado").addClass("zona_resultado_placeholder").html("Aqui apareceran los datos extraidos de la factura una vez procesada.");
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
    // Inicializa en estado limpio
    boton_nuevo();
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

    <!-- LAYOUT PRINCIPAL: una columna centrada -->
    <div style="display: flex; flex-direction: column; align-items: center; margin-top: 15px; gap: 10px;">

        <!-- PANEL 1: SELECCION Y CARGA DEL PDF -->
        <div class="aida" style="width: 720px;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-upload fg-darkRed"></i> IMPORTAR FACTURA PDF</strong></center></span>

            <div style="padding: 20px;">
                <!-- Input file oculto -->
                <input type="file" id="id_archivo_pdf" accept="application/pdf,.pdf" onchange="archivo_seleccionado(this);" />

                <!-- Zona visual clickeable -->
                <div class="zona_archivo_pdf" onclick="abre_selector_archivo();" style="cursor: pointer;">
                    <i id="id_icono_zona" class="icon-file-pdf icono_pdf"></i>
                    <div id="id_texto_principal_zona" class="texto_principal">HAGA CLICK PARA SELECCIONAR UNA FACTURA PDF</div>
                    <div id="id_texto_secundario_zona" class="texto_secundario">Solo archivos PDF (max. 32 MB)</div>
                    <div id="id_nombre_archivo" class="nombre_archivo" style="display:none;"></div>
                    <div id="id_tamano_archivo" class="tamano_archivo" style="display:none;"></div>
                </div>

                <!-- Botones de accion -->
                <div style="margin-top: 15px; text-align: center;">
                    <button type="button" id="id_boton_procesar" class="boton_accion boton_procesar" onclick="procesa_factura();" disabled>
                        <i class="icon-cog"></i> PROCESAR FACTURA
                    </button>
                    <button type="button" class="boton_accion boton_limpiar" onclick="boton_nuevo();" style="margin-left: 8px;">
                        <i class="icon-cancel"></i> LIMPIAR
                    </button>
                </div>
            </div>
        </div>

        <!-- PANEL 2: RESULTADO DE LA EXTRACCION -->
        <div class="aida" style="width: 720px;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-clipboard fg-darkRed"></i> DATOS EXTRAIDOS</strong></center></span>

            <div style="padding: 15px;">
                <div id="id_zona_resultado" class="zona_resultado zona_resultado_placeholder">
                    Aqui apareceran los datos extraidos de la factura una vez procesada.
                </div>
            </div>
        </div>

    </div>

    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

</body>
</html>
<?php
mysqli_close($link);
?>