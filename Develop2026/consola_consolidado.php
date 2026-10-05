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
// Las guias ya NO se cargan aca: cada consolidado tiene sus propias guias y la
// gestion vive en el dialogo GUIAS (render_guias_consolidado_dsft), que las pide
// por AJAX. Las sugerencias para escribir las da numeros_guia_recientes_dsft.
// FINCAS (proveedor con codigo_tipo_proveedor = 1) para el Select2 del
// dialog "Añadir factura". Se vuelca en una variable JS al inicio del
// script para no requerir AJAX al abrir el dialog.
$sql_fincas_js      = "SELECT codigo_proveedor AS CODIGO, nombre_proveedor AS NOMBRE
    FROM proveedor
    WHERE codigo_tipo_proveedor = 1
    ORDER BY nombre_proveedor";
$res_fincas_js      = mysqli_query($link, $sql_fincas_js);
$opciones_fincas_js = '<option value="0">-- SELECCIONE FINCA --</option>';
if($res_fincas_js)
    {
    $total_fincas_js = mysqli_num_rows($res_fincas_js);
    for($fi=1; $fi<=$total_fincas_js; $fi++)
        {
        $prov                = mysqli_fetch_assoc($res_fincas_js);
        $opciones_fincas_js .= '<option value="'.(int)$prov["CODIGO"].'">'
            .htmlspecialchars((string)$prov["NOMBRE"], ENT_QUOTES, "UTF-8")
            .'</option>';
        }
    }

// Opciones de tipo de producto (para el select inline del doble-click en PROD).
// Orden por CAMPOE1 (orden de seccion) y luego NOMBRE.
$sql_tipos_js      = "SELECT CODIGO, NOMBRE
    FROM tipo_producto
    WHERE ESTADO = 1
    ORDER BY CAMPOE1, NOMBRE";
$res_tipos_js      = mysqli_query($link, $sql_tipos_js);
$opciones_tipos_js = "";
if($res_tipos_js)
    {
    $total_tipos_js = mysqli_num_rows($res_tipos_js);
    for($ti=1; $ti<=$total_tipos_js; $ti++)
        {
        $tipo               = mysqli_fetch_assoc($res_tipos_js);
        $opciones_tipos_js .= '<option value="'.(int)$tipo["CODIGO"].'">'
            .htmlspecialchars((string)$tipo["NOMBRE"], ENT_QUOTES, "UTF-8")
            .'</option>';
        }
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
<!-- JS compartido con otras consolas: aviso breve y selector de guias. -->
<script src="js_comun_dsft.js?v=20261004"></script>
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

/* Celdas editables del grid de detalle de factura. */
.celda_editable
    {
    cursor: pointer;
    }
.celda_editable:hover
    {
    background-color: rgba(136,1,14,0.08) !important;
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
/* ===== MODO SELECCION (pasar lineas a otra medida) ===== */
/* Mientras el modo esta activo, el grid no deja seleccionar texto: si no, un
   Shift+clic para marcar un rango pintaria media tabla de azul. */
.grid_factura_detalle.modo_seleccion
    {
    user-select: none;
    -webkit-user-select: none;
    cursor: pointer;
    }
.grid_factura_detalle.modo_seleccion tr[data-codigo]:hover td
    {
    background-color: #fffaf0 !important;
    }
.grid_factura_detalle tr.fila_seleccionada td
    {
    background-color: #fff3cd !important;
    border-top: 1px solid #d4a017 !important;
    border-bottom: 1px solid #d4a017 !important;
    }
/* Cabeceras de cm clicables solo cuando hay lineas marcadas. */
.grid_factura_detalle th.th_cm_sel.th_cm_activa
    {
    cursor: pointer;
    text-decoration: underline;
    }
.grid_factura_detalle th.th_cm_sel.th_cm_activa:hover
    {
    background-color: #d4a017 !important;
    color: #fff !important;
    }
</style>
<script language="javascript">
var global_codigo_seleccionado = 0;
var global_ordenamiento = "FECHACONSOLIDADO";
var global_direccion = "DESC";
var global_codigo_usuario = <?php echo (int)$_SESSION['s_codigo']; ?>;
var global_opciones_fincas = '<?php echo $opciones_fincas_js; ?>';
var global_opciones_tipos_producto = '<?php echo $opciones_tipos_js; ?>';
var flatpickr_fechaconsolidado = null;
var flatpickr_rango_filtro = null;

function messageBox(texto)
    {
    $("#id_espera").hide();
    $("#dialog").html(texto);
    $("#dialog").dialog("open");
    // Si ya habia otro dialogo abierto (GUIAS, MEDIDAS, entrega...), el aviso
    // nace con un z-index menor y queda DETRAS. moveToTop lo sube por encima
    // del que este activo. Se arregla aca, en la raiz, para todo el sistema.
    $("#dialog").dialog("moveToTop");
    }

// jQuery UI calcula la posicion de un dialogo UNA sola vez, al abrirlo. Si justo
// despues cambia el alto de la pagina (se vacia el detalle, se reemplaza el
// listado), esa posicion queda vieja y el dialogo puede terminar fuera de la
// pantalla: se ve la capa modal gris, no responde nada y el usuario no llega al
// boton Aceptar. Por eso, cada vez que termina una recarga de contenido se
// recentra lo que este abierto.
// La clase ui-dialog-content la pone jQuery UI solo en dialogos ya inicializados,
// asi que el selector no puede alcanzar un elemento sin dialog().
function recentrar_dialogos_abiertos()
    {
    $(".ui-dialog-content:visible").each(function()
        {
        $(this).dialog("option", "position", { my: "center", at: "center", of: window });
        });
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
        if(flatpickr_fechaconsolidado)
            {
            if(datos.FECHACONSOLIDADO)
                flatpickr_fechaconsolidado.setDate(datos.FECHACONSOLIDADO);
            else
                flatpickr_fechaconsolidado.clear();
            }
        var pais_val = (datos.CODIGOPAIS && parseInt(datos.CODIGOPAIS) > 0) ? datos.CODIGOPAIS.toString() : "0";
        $("#id_codigopais").val(pais_val).trigger('change');
        var ag_val = (datos.CODIGOAGENCIA && parseInt(datos.CODIGOAGENCIA) > 0) ? datos.CODIGOAGENCIA.toString() : "0";
        $("#id_codigoagencia").val(ag_val).trigger('change');
        $("#id_observaciones").val(datos.OBSERVACIONES || "");
        // ESTADO: checkbox checked = activo (1), unchecked = inactivo (0).
        $("#id_estado_consolidado").prop("checked", parseInt(datos.ESTADO) == 1);

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

        // Resumen corto de guias; la gestion esta en el boton GUIAS.
        cargar_resumen_guias(parseInt(datos.CODIGO));
        cargar_detalle_consolidado(parseInt(datos.CODIGO));

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
// FECHA y MARCACION son obligatorios. GUIA ya NO es campo del formulario
// (se maneja por la seccion GUIAS separada).
function valida_formulario()
    {
    if($("#id_fechaconsolidado").val().trim() == "")
        return "Por favor ingrese la FECHA del consolidado";
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
    var estado_val = $("#id_estado_consolidado").is(":checked") ? "1" : "0";
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=graba_consolidado_dsft"
        + "&parametro1="  + global_codigo_seleccionado
        + "&parametro2="  + encodeURIComponent($("#id_fechaconsolidado").val())
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
            // El aviso va DESPUES de limpiar y recargar: si se abriera antes,
            // boton_nuevo() vacia el detalle y la recarga reemplaza el listado,
            // la pagina cambia de alto y el dialogo queda fuera de la pantalla.
            boton_nuevo();
            actualiza_listado(function()
                {
                messageBox("Consolidado grabado correctamente");
                });
            }
        else
            {
            messageBox("Error al grabar: " + data);
            }
        });
    }

// ===== GUIAS DEL CONSOLIDADO (gestion: alta, edicion y baja) =====
// Cada guia pertenece a UN solo consolidado. Toda la gestion vive en el dialogo
// que abre el boton GUIAS; en el formulario queda solo un resumen.
// OJO con los nombres: dialog_gestion_guias() es ESTE dialogo, y
// dialog_guias_consolidado(codigo, elemento) es el del icono # del listado, que
// ASIGNA una guia a todas las cajas. Son dos cosas distintas y antes se
// llamaban igual: ganaba la declaracion mas tardia, asi que el boton terminaba
// llamando a la del icono sin argumentos y el codigo llegaba undefined.
var flatpickr_guia_nueva   = null;
var flatpickr_guia_filtro  = null;

function dialog_gestion_guias()
    {
    if(global_codigo_seleccionado <= 0)
        {
        messageBox("Primero grabe el consolidado antes de agregar guías.");
        return;
        }
    cargar_guias_dialogo();
    $("#id_dialog_guias_consolidado").dialog(
        {
        modal: true,
        width: 520,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "CERRAR",
                class: 'cancelButton',
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// Trae el contenido del dialogo y engancha los dos Flatpickr, que viven en el
// HTML que acaba de llegar por AJAX.
function cargar_guias_dialogo()
    {
    var url = "funciones_ajax.php?funcion=render_guias_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado;
    $.get(url, function(data)
        {
        $("#id_dialog_guias_consolidado").html(data);

        flatpickr_guia_nueva = flatpickr("#id_guia_fecha_nueva",
            {
            dateFormat: "Y-m-d",
            locale: "es",
            allowInput: false
            });
        // Rango desde/hasta, igual que el filtro del listado.
        flatpickr_guia_filtro = flatpickr("#id_guia_fecha_filtro",
            {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "es",
            allowInput: false,
            onChange: function(selectedDates)
                {
                if(selectedDates.length == 0 || selectedDates.length == 2)
                    filtrar_guias_por_fecha();
                },
            onClose: function(selectedDates)
                {
                filtrar_guias_por_fecha();
                }
            });
        });
    }

// Filtro por RANGO de fechas, en el cliente: compara contra el data-fecha de
// cada fila. Sin rango se ve todo, incluidas las guias sin fecha; con un rango
// activo las sin fecha se ocultan, porque no se puede saber si entran.
// El campo trae "2026-09-01 a 2026-09-30" (o un solo dia mientras se elige).
function filtrar_guias_por_fecha()
    {
    var texto  = $("#id_guia_fecha_filtro").val();
    var fechas = (texto || "").match(/\d{4}-\d{2}-\d{2}/g);
    var desde  = "";
    var hasta  = "";
    if(fechas && fechas.length >= 2)
        {
        desde = fechas[0];
        hasta = fechas[1];
        }
    else if(fechas && fechas.length == 1)
        {
        // Un solo dia elegido: se filtra por ese dia.
        desde = fechas[0];
        hasta = fechas[0];
        }

    $("#id_tabla_guias .fila_guia").each(function()
        {
        var fila  = $(this);
        var fecha = fila.attr("data-fecha");
        if(desde == "")
            {
            fila.show();
            return;
            }
        // Las fechas en Y-m-d se comparan bien como texto.
        if(fecha != "" && fecha >= desde && fecha <= hasta)
            fila.show();
        else
            fila.hide();
        });
    }

function limpiar_filtro_guias()
    {
    if(flatpickr_guia_filtro)
        flatpickr_guia_filtro.clear();
    $("#id_guia_fecha_filtro").val("");
    filtrar_guias_por_fecha();
    }

// Validacion identica a la del servidor.
function valida_numero_guia(numero)
    {
    numero = (numero || "").toString().trim();
    if(numero == "")
        return "Valor de guía vacío";
    if(!/^[0-9\-]{1,15}$/.test(numero))
        return "La guía solo admite números y guiones (máximo 15 caracteres)";
    return "OK";
    }

function agregar_guia_dialogo()
    {
    var numero  = $("#id_guia_numero_nueva").val();
    var fecha   = $("#id_guia_fecha_nueva").val();
    var mensaje = valida_numero_guia(numero);
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    var url = "funciones_ajax.php?funcion=agregar_guia_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + encodeURIComponent(numero)
        + "&parametro3=" + global_codigo_usuario
        + "&parametro4=" + encodeURIComponent(fecha);
    $.get(url, function(data)
        {
        if(data == "OK")
            refrescar_por_cambio_de_guias();
        else
            messageBox(data);
        });
    }

// Edicion en un mini dialogo con numero + fecha.
function editar_guia_dialogo(codigo_guia, numero, fecha)
    {
    $("#id_dialog_confirma_factura").html(
        '<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">'
        + '<input type="text" id="id_guia_numero_edit" maxlength="15" value="' + numero + '"'
        + ' style="width:150px; font-size:12px;" />'
        + '<input type="text" id="id_guia_fecha_edit" placeholder="Sin fecha" readonly value="' + fecha + '"'
        + ' style="width:120px; font-size:12px; background:#fff; cursor:pointer;" />'
        + '<a onclick="$(\'#id_guia_fecha_edit\').val(\'\');" style="cursor:pointer; color:#88010e;"'
        + ' title="Dejar sin fecha"><i class="icon-remove"></i></a>'
        + '</div>');
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 400,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "GUARDAR",
                class: 'cancelButton',
                click: function()
                    {
                    var numero_nuevo = $("#id_guia_numero_edit").val();
                    var fecha_nueva  = $("#id_guia_fecha_edit").val();
                    var mensaje      = valida_numero_guia(numero_nuevo);
                    if(mensaje != "OK")
                        {
                        messageBox(mensaje);
                        return;
                        }
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=editar_guia_dsft"
                        + "&parametro1=" + codigo_guia
                        + "&parametro2=" + global_codigo_seleccionado
                        + "&parametro3=" + encodeURIComponent(numero_nuevo)
                        + "&parametro4=" + encodeURIComponent(fecha_nueva)
                        + "&parametro5=" + global_codigo_usuario;
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            refrescar_por_cambio_de_guias();
                        else
                            messageBox(data);
                        });
                    }
                },
                {
                text: "CANCELAR",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    flatpickr("#id_guia_fecha_edit",
        {
        dateFormat: "Y-m-d",
        locale: "es",
        allowInput: false
        });
    }

function quitar_guia_dialogo(codigo_guia, numero)
    {
    var url = "funciones_ajax.php?funcion=quitar_guia_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + codigo_guia;
    $.get(url, function(data)
        {
        if(data == "OK")
            refrescar_por_cambio_de_guias();
        else
            messageBox(data);
        });
    }

// Tras agregar, editar o quitar: el dialogo, el resumen del formulario, el
// detalle (titles e iconos de guia) y el listado (su columna de guias).
function refrescar_por_cambio_de_guias()
    {
    cargar_guias_dialogo();
    cargar_resumen_guias(global_codigo_seleccionado);
    cargar_detalle_consolidado(global_codigo_seleccionado);
    actualiza_listado();
    }

// Resumen corto de guias para el formulario.
function cargar_resumen_guias(codigo_consolidado)
    {
    if(codigo_consolidado <= 0)
        {
        $("#id_resumen_guias_consolidado").html("&mdash;");
        return;
        }
    var url = "funciones_ajax.php?funcion=render_resumen_guias_dsft"
        + "&parametro1=" + codigo_consolidado;
    $.get(url, function(data)
        {
        $("#id_resumen_guias_consolidado").html(data);
        });
    }

// Que redibujar en ESTA consola despues de asignar o transferir. Lo llama
// sel_guia_refrescar() del archivo comun, que no sabe que hay en pantalla.
function sel_guia_refrescar_consola(info)
    {
    if(info.accion == "transferir")
        {
        // La factura (o todas) salen de este consolidado: se redibuja el
        // detalle del ORIGEN y el listado, que cambia conteos y colores.
        cargar_detalle_consolidado(info.consolidado);
        actualiza_listado();
        return;
        }
    // Asignar: alcanza con el grid de la factura, salvo en el nivel
    // consolidado, donde cambian todas sus tarjetas.
    if(info.nivel == "consolidado")
        {
        if(global_codigo_seleccionado == info.consolidado)
            cargar_detalle_consolidado(info.consolidado);
        }
    else
        {
        recargar_grid_factura_por_codigo(info.codigo_ff);
        }
    actualiza_listado();
    }

// ===== ENTREGA A LA CARGUERA (por caja) =====
// La marca es por CAJA y es solo informativa: no bloquea la edicion de las
// lineas. El contador "Entregadas X/Y" vive en la cabecera de la tarjeta, o
// sea FUERA del grid, asi que se refresca aparte.

// Lee "Entregadas X/Y" del contador de la tarjeta. El servidor es el que
// manda: si este numero quedara viejo, solo afecta el texto del dialogo.
function numeros_entregas(codigo_ff)
    {
    var texto  = $("#id_entregadas_" + codigo_ff).text();
    var partes = texto.replace(/[^0-9\/]/g, "").split("/");
    var x = parseInt(partes[0]);
    var y = parseInt(partes[1]);
    return {
        entregadas: isNaN(x) ? 0 : x,
        total:      isNaN(y) ? 0 : y
        };
    }

function actualizar_contador_entregas(codigo_ff)
    {
    var url = "funciones_ajax.php?funcion=render_contador_entregas_dsft"
        + "&parametro1=" + codigo_ff;
    $.get(url, function(data)
        {
        $("#id_entregadas_" + codigo_ff).html(data);
        });
    }

// Clic en el icono de una caja: alterna sin dialogo (es una sola caja y se
// deshace con otro clic).
function alternar_entrega_caja(codigo_ff, numero_caja, elemento)
    {
    // Estado dibujado ahora, solo para elegir el texto del aviso. El que decide
    // el estado real es el servidor.
    var entregada_antes = $("#id_ship_" + codigo_ff + "_" + numero_caja).attr("data-entregada");
    var queda_entregada = (entregada_antes != "1");

    var url = "funciones_ajax.php?funcion=alternar_entrega_caja_dsft"
        + "&parametro1=" + codigo_ff
        + "&parametro2=" + numero_caja
        + "&parametro3=" + global_codigo_usuario;
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            if(queda_entregada)
                aviso_breve("Caja " + numero_caja + " entregada a la carguera", elemento);
            else
                aviso_breve("Caja " + numero_caja + " marcada como no entregada", elemento);
            recargar_grid_factura_por_codigo(codigo_ff);
            }
        else
            {
            messageBox(data);
            }
        });
    }

function confirmar_entrega_factura(codigo_ff, elemento)
    {
    // Guardar el boton ANTES de abrir el dialogo: cuando llega el OK el foco ya
    // no esta en el, pero el globo tiene que salir igual junto a el.
    var boton_origen = elemento;
    var conteo       = numeros_entregas(codigo_ff);
    if(conteo.total <= 0)
        {
        messageBox("Esta factura no tiene cajas para confirmar");
        return;
        }
    $("#id_dialog_confirma_factura").html(
        "<p>&iquest;Confirmar la entrega de las <strong>" + conteo.total
        + "</strong> cajas de esta factura?</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 400,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "SI",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=confirmar_entrega_factura_dsft"
                        + "&parametro1=" + codigo_ff
                        + "&parametro2=" + global_codigo_usuario;
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            {
                            aviso_breve("Factura confirmada: " + conteo.total + " cajas entregadas", boton_origen);
                            recargar_grid_factura_por_codigo(codigo_ff);
                            }
                        else
                            {
                            messageBox(data);
                            }
                        });
                    }
                },
                {
                text: "NO",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

function desconfirmar_entrega_factura(codigo_ff, elemento)
    {
    var boton_origen = elemento;
    var conteo       = numeros_entregas(codigo_ff);
    if(conteo.entregadas <= 0)
        {
        messageBox("Esta factura no tiene cajas confirmadas");
        return;
        }
    $("#id_dialog_confirma_factura").html(
        "<p>&iquest;Desconfirmar las <strong>" + conteo.entregadas
        + "</strong> cajas ya marcadas como entregadas?</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 400,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "SI",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=desconfirmar_entrega_factura_dsft"
                        + "&parametro1=" + codigo_ff
                        + "&parametro2=" + global_codigo_usuario;
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            {
                            aviso_breve("Factura desconfirmada", boton_origen);
                            recargar_grid_factura_por_codigo(codigo_ff);
                            }
                        else
                            {
                            messageBox(data);
                            }
                        });
                    }
                },
                {
                text: "NO",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// ===== BORRAR UNA CAJA COMPLETA =====
// El icono vive en la primera linea de la caja, junto al camion y a la guia.
// Los conteos y el estado de entrega/guia vienen en los data- del propio icono,
// que los puso el servidor al dibujar el grid: no hace falta otra consulta para
// armar el texto de la confirmacion.
function borrar_caja_detalle(codigo_ff, numero_caja, elemento)
    {
    var icono  = $(elemento);
    var lineas = parseInt(icono.attr("data-lineas"));
    var tallos = parseInt(icono.attr("data-tallos"));
    var entregada = (icono.attr("data-entregada") == "1");
    var guia      = icono.attr("data-guia");
    if(isNaN(lineas)) lineas = 0;
    if(isNaN(tallos)) tallos = 0;

    // El verbo concuerda con la cantidad: "Se eliminara 1 linea" / "Se eliminaran 4 lineas".
    var texto = "<p>&iquest;Borrar la caja <strong>" + numero_caja + "</strong> completa?"
        + " Se eliminar&aacute;" + ((lineas == 1) ? "" : "n")
        + " <strong>" + lineas + "</strong> l&iacute;nea" + ((lineas == 1) ? "" : "s")
        + " (" + tallos + " tallo" + ((tallos == 1) ? "" : "s") + ").</p>";

    // Si la caja ya estaba entregada o tenia guia, se avisa en el mismo texto.
    var avisos = [];
    if(entregada)
        avisos.push("est&aacute; marcada como entregada");
    if(guia && guia != "")
        avisos.push("tiene asignada la gu&iacute;a " + guia);
    if(avisos.length > 0)
        texto += "<p style='color:#88010e;'><strong>Atenci&oacute;n:</strong> esta caja "
              + avisos.join(" y ") + ".</p>";

    $("#id_dialog_confirma_factura").html(texto);
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 420,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "SI",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=borrar_caja_detalle_dsft"
                        + "&parametro1=" + codigo_ff
                        + "&parametro2=" + numero_caja
                        + "&parametro3=" + global_codigo_usuario;
                    $.get(url, function(data)
                        {
                        if(data.substring(0, 2) != "OK")
                            {
                            messageBox(data);
                            return;
                            }
                        aviso_breve("Caja " + numero_caja + " eliminada", elemento);
                        // Refresca grid, contador de entregas e icono de guia.
                        recargar_grid_factura_por_codigo(codigo_ff);
                        // Y el listado, porque el color del icono de guia del
                        // consolidado depende de todas sus cajas.
                        actualiza_listado();
                        });
                    }
                },
                {
                text: "NO",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// ===== MODO SELECCION: pasar varias lineas a otra medida =====
// Para corregir rapido las medidas que la IA leyo mal. Solo UNA factura puede
// estar en modo seleccion a la vez. Cambia solo el LARGO: precio, tallos y caja
// quedan iguales.
var global_seleccion_ff     = 0;    // factura en modo seleccion (0 = ninguna)
var global_seleccion_lineas = [];   // CODIGO de detalle marcados, en orden
var global_seleccion_ultima = 0;    // ultima marcada, para el Shift+clic

// true si el elemento pertenece al grid de la factura que esta en modo
// seleccion. Lo usan los handlers de edicion para no abrir el editor.
function en_modo_seleccion(elemento)
    {
    if(global_seleccion_ff <= 0)
        return false;
    return $(elemento).closest("#id_grid_factura_" + global_seleccion_ff).length > 0;
    }

function grid_seleccion()
    {
    return $("#id_grid_factura_" + global_seleccion_ff + " .grid_factura_detalle");
    }

// Apaga el modo en la factura que lo tenga y borra su seleccion.
function limpiar_modo_seleccion()
    {
    if(global_seleccion_ff > 0)
        {
        grid_seleccion().removeClass("modo_seleccion");
        grid_seleccion().find("tr").removeClass("fila_seleccionada");
        grid_seleccion().find("th.th_cm_sel").removeClass("th_cm_activa");
        $("#id_chk_seleccion_" + global_seleccion_ff).prop("checked", false);
        $("#id_ayuda_seleccion_" + global_seleccion_ff).html("");
        }
    global_seleccion_ff     = 0;
    global_seleccion_lineas = [];
    global_seleccion_ultima = 0;
    }

function toggle_modo_seleccion(codigo_ff, elemento)
    {
    var encender = $(elemento).is(":checked");

    // Solo una factura a la vez: al encender en otra, se apaga la anterior.
    if(global_seleccion_ff > 0 && global_seleccion_ff != codigo_ff)
        limpiar_modo_seleccion();

    if(!encender)
        {
        limpiar_modo_seleccion();
        return;
        }

    global_seleccion_ff     = codigo_ff;
    global_seleccion_lineas = [];
    global_seleccion_ultima = 0;
    $("#id_chk_seleccion_" + codigo_ff).prop("checked", true);
    grid_seleccion().addClass("modo_seleccion");
    actualizar_ayuda_seleccion();
    }

function actualizar_ayuda_seleccion()
    {
    if(global_seleccion_ff <= 0)
        return;
    var cuantas = global_seleccion_lineas.length;
    var texto   = (cuantas == 0)
                ? "Toque las l&iacute;neas y luego la medida destino"
                : cuantas + ((cuantas == 1) ? " seleccionada" : " seleccionadas");
    $("#id_ayuda_seleccion_" + global_seleccion_ff).html(texto);

    // Las cabeceras de cm solo se vuelven clicables con algo marcado.
    if(cuantas > 0)
        grid_seleccion().find("th.th_cm_sel").addClass("th_cm_activa");
    else
        grid_seleccion().find("th.th_cm_sel").removeClass("th_cm_activa");
    }

// Marca o desmarca una linea y repinta la fila.
function marcar_linea_seleccion(codigo_linea, fila, forzar_marcada)
    {
    var pos = global_seleccion_lineas.indexOf(codigo_linea);
    var marcar = (typeof forzar_marcada === "boolean") ? forzar_marcada : (pos < 0);

    if(marcar && pos < 0)
        {
        global_seleccion_lineas.push(codigo_linea);
        fila.addClass("fila_seleccionada");
        }
    else if(!marcar && pos >= 0)
        {
        global_seleccion_lineas.splice(pos, 1);
        fila.removeClass("fila_seleccionada");
        }
    }

// Clic sobre una linea del grid de la factura en modo seleccion.
$(document).on("click", ".grid_factura_detalle.modo_seleccion tr[data-codigo]", function(e)
    {
    // Los iconos y controles de la linea siguen funcionando normal.
    if($(e.target).closest("a, button, input, select, textarea").length > 0)
        return;

    var fila         = $(this);
    var codigo_linea = parseInt(fila.data("codigo"));
    if(isNaN(codigo_linea) || codigo_linea <= 0)
        return;

    // Shift+clic: marca el rango desde la ultima marcada.
    if(e.shiftKey && global_seleccion_ultima > 0)
        {
        var filas = grid_seleccion().find("tr[data-codigo]");
        var desde = filas.index(grid_seleccion().find("tr[data-codigo='" + global_seleccion_ultima + "']"));
        var hasta = filas.index(fila);
        if(desde >= 0 && hasta >= 0)
            {
            var ini = (desde < hasta) ? desde : hasta;
            var fin = (desde < hasta) ? hasta : desde;
            for(var k = ini; k <= fin; k++)
                {
                var f = filas.eq(k);
                marcar_linea_seleccion(parseInt(f.data("codigo")), f, true);
                }
            actualizar_ayuda_seleccion();
            return;
            }
        }

    marcar_linea_seleccion(codigo_linea, fila);
    global_seleccion_ultima = codigo_linea;
    actualizar_ayuda_seleccion();
    });

// Clic en la cabecera de una medida: pasa las lineas marcadas a esa medida.
$(document).on("click", ".grid_factura_detalle th.th_cm_sel.th_cm_activa", function()
    {
    var th        = $(this);
    var medida    = parseInt(th.data("cm"));
    var codigo_ff = parseInt(th.data("ff"));
    if(isNaN(medida) || medida <= 0 || codigo_ff != global_seleccion_ff)
        return;
    if(global_seleccion_lineas.length == 0)
        return;

    var cuantas = global_seleccion_lineas.length;
    var lineas  = global_seleccion_lineas.join(",");
    var origen  = this;

    $("#id_dialog_confirma_factura").html(
        "<p>&iquest;Pasar <strong>" + cuantas + "</strong> l&iacute;nea"
        + ((cuantas == 1) ? "" : "s") + " a <strong>" + medida + " cm</strong>?</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 380,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "SI",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=mover_lineas_medida_dsft"
                        + "&parametro1=" + codigo_ff
                        + "&parametro2=" + lineas
                        + "&parametro3=" + medida
                        + "&parametro4=" + global_codigo_usuario;
                    $.get(url, function(data)
                        {
                        if(data.substring(0, 2) != "OK")
                            {
                            messageBox(data);
                            return;
                            }
                        aviso_breve(cuantas + ((cuantas == 1) ? " linea pasada a " : " lineas pasadas a ")
                            + medida + " cm", origen);
                        limpiar_modo_seleccion();
                        recargar_grid_factura_por_codigo(codigo_ff);
                        });
                    }
                },
                {
                text: "NO",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    });

// Esc borra la seleccion y apaga el modo.
$(document).on("keydown", function(e)
    {
    if(e.which == 27 && global_seleccion_ff > 0)
        limpiar_modo_seleccion();
    });

// El icono de guia de la cabecera vive fuera del grid, igual que el contador de
// entregas, asi que se refresca aparte.
function actualizar_icono_guia_factura(codigo_ff)
    {
    var url = "funciones_ajax.php?funcion=render_icono_guia_factura_dsft"
        + "&parametro1=" + codigo_ff;
    $.get(url, function(data)
        {
        $("#id_guia_factura_" + codigo_ff).html(data);
        });
    }

// ===== MEDIDAS (columnas cm) del consolidado =====
// Las columnas de cm del grid y de los Excel salen de consolidado.MEDIDASCM.
// Este dialog las edita; despues de cada cambio hay que redibujar TODAS las
// tarjetas de facturas porque comparten las mismas columnas.
function dialog_medidas_consolidado()
    {
    if(global_codigo_seleccionado <= 0)
        {
        messageBox("Por favor seleccione un consolidado");
        return;
        }
    cargar_medidas_consolidado();
    $("#id_dialog_medidas").dialog(
        {
        modal: true,
        width: 340,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "CERRAR",
                class: 'cancelButton',
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// Trae el contenido del dialog (lista de medidas + campo para agregar).
function cargar_medidas_consolidado()
    {
    var url = "funciones_ajax.php?funcion=render_medidas_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado;
    $.get(url, function(data)
        {
        $("#id_dialog_medidas").html(data);
        });
    }

// Validacion identica a la del servidor.
function valida_medida(medida)
    {
    if(medida == "" || isNaN(medida))
        return "Por favor ingrese una medida mayor a cero";
    var valor = parseInt(medida);
    if(isNaN(valor) || valor <= 0)
        return "Por favor ingrese una medida mayor a cero";
    if(valor > 500)
        return "La medida no puede ser mayor a 500 cm";
    return "OK";
    }

function agregar_medida_consolidado()
    {
    var medida  = $("#id_medida_nueva").val();
    var mensaje = valida_medida(medida);
    if(mensaje != "OK")
        {
        messageBox(mensaje);
        return;
        }
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=agregar_medida_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + parseInt(medida)
        + "&parametro3=" + global_codigo_usuario;
    $.get(url, function(data)
        {
        $("#id_espera").hide();
        if(data == "OK")
            refrescar_por_cambio_de_medidas();
        else
            messageBox(data);
        });
    }

function quitar_medida_consolidado(medida)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=quitar_medida_consolidado_dsft"
        + "&parametro1=" + global_codigo_seleccionado
        + "&parametro2=" + medida
        + "&parametro3=" + global_codigo_usuario;
    $.get(url, function(data)
        {
        $("#id_espera").hide();
        if(data == "OK")
            refrescar_por_cambio_de_medidas();
        else
            messageBox(data);
        });
    }

// Tras agregar o quitar: recargar la lista del dialog Y el detalle completo,
// porque todas las tarjetas comparten las mismas columnas de cm.
function refrescar_por_cambio_de_medidas()
    {
    cargar_medidas_consolidado();
    cargar_detalle_consolidado(global_codigo_seleccionado);
    }

// ===== Detalle del consolidado (facturas asignadas) =====
// Carga el HTML del detalle (grids de cada factura + areas PDF) en el div
// inferior id_detalle_consolidado. Se invoca al seleccionar un consolidado.
function cargar_detalle_consolidado(codigo_consolidado)
    {
    if(codigo_consolidado <= 0)
        {
        $("#id_detalle_consolidado").hide().html("");
        return;
        }
    $("#id_detalle_consolidado").show().html(
        "<center><i class='icon-clock'></i> Cargando facturas...</center>");
    var url = "funciones_ajax.php?funcion=detalle_consolidado_dsft"
        + "&parametro1=" + codigo_consolidado;
    $.get(url, function(data)
        {
        $("#id_detalle_consolidado").html(data);
        // El detalle acaba de cambiar de alto: reubicar lo que este abierto.
        recentrar_dialogos_abiertos();
        // Inicializar Select2 en los selects de finca de cada tarjeta.
        $("[id^='id_select_finca_']").select2(
            {
            width: '220px',
            placeholder: '-- CONFIRMAR FINCA --'
            });
        // Inicializar Select2 en los selects de tipo de producto de cada tarjeta.
        $("[id^='id_select_tipo_']").select2(
            {
            width: '150px',
            placeholder: '-- TIPO --'
            });
        });
    }

// Persiste la finca elegida en factura_finca.CODIGOFINCA.
function confirmar_finca(codigo_ff)
    {
    var codigo_finca = $("#id_select_finca_" + codigo_ff).val();
    if(!codigo_finca || codigo_finca == "0")
        {
        messageBox("Seleccione una finca primero");
        return;
        }
    var url = "funciones_ajax.php?funcion=confirmar_finca_factura_dsft"
        + "&parametro1=" + codigo_ff
        + "&parametro2=" + codigo_finca;
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            messageBox("Finca confirmada");
            // Cambiar el boton a "Cambiar" verde sin recargar el grid.
            var btn = $("#id_btn_finca_" + codigo_ff);
            btn.text("Cambiar");
            btn.css("background", "#2e7d32");
            }
        else
            {
            messageBox("Error: " + data);
            }
        });
    }

// Persiste el tipo de producto elegido en factura_finca.CODIGOTIPOPRODUCTO.
function confirmar_tipo_producto(codigo_ff)
    {
    var codigo_tipo = $("#id_select_tipo_" + codigo_ff).val();
    if(!codigo_tipo || codigo_tipo == "0")
        {
        messageBox("Seleccione un tipo de producto");
        return;
        }
    var url = "funciones_ajax.php?funcion=confirmar_tipo_producto_dsft"
        + "&parametro1=" + codigo_ff
        + "&parametro2=" + codigo_tipo;
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            messageBox("Tipo de producto confirmado");
            // Cambiar el boton a "Cambiar" verde.
            var btn = $("#id_btn_tipo_" + codigo_ff);
            btn.text("Cambiar");
            btn.css("background", "#2e7d32");
            // Recargar el grid: el detalle ahora muestra el nuevo PROD en cada linea.
            var url2 = "funciones_ajax.php?funcion=render_grid_factura_dsft"
                + "&parametro1=" + codigo_ff;
            $.get(url2, function(data2)
                {
                $("#id_grid_factura_" + codigo_ff).html(data2);
                });
            } 
        else
            {
            messageBox("Error: " + data);
            }
        });
    }

// Mini-menu flotante (Excel / PDF) anclado al icono puzzle del listado.
function toggle_menu_formato(el, codigo)
    {
    var menu = $("#id_menu_formato");
    // Si ya esta visible para este codigo, cerrarlo.
    if(menu.is(":visible") && menu.data("codigo") == codigo)
        {
        menu.hide();
        return;
        }
    // Posicionar debajo del icono.
    var offset = $(el).offset();
    menu.css(
        {
        top: offset.top + 20,
        left: offset.left - 10,
        position: "absolute"
        });
    menu.data("codigo", codigo);
    // Asignar acciones a cada opcion.
    $("#id_menu_excel").attr("onclick",
        "descargar_consolidado(" + codigo + ", 'xlsx'); $('#id_menu_formato').hide();");
    $("#id_menu_pdf").attr("onclick",
        "descargar_consolidado(" + codigo + ", 'pdf'); $('#id_menu_formato').hide();");
    menu.show();
    }

// Cerrar el menu si se hace click fuera de el o del icono puzzle.
$(document).on("click", function(e)
    {
    if(!$(e.target).closest("#id_menu_formato, .icon-puzzle").length)
        $("#id_menu_formato").hide();
    });

// Abre el endpoint de descarga en pestana nueva con el formato elegido.
function descargar_consolidado(codigo_consolidado, formato)
    {
    window.open("funciones_ajax.php?funcion=generar_consolidado_dsft"
        + "&parametro1=" + codigo_consolidado
        + "&parametro2=" + formato, "_blank");
    }

// ===== FACTURA CLIENTE (INVOICE) =====
// Si NO existe invoice: crea directamente y abre. Si YA existen: dialog jQuery UI
// crimson con la mini-lista de todos los invoices + boton CREAR NUEVO.
function abrir_factura_cliente(codigo_consolidado)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=verifica_factura_cliente_dsft&parametro1=" + codigo_consolidado;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        var datos = JSON.parse(data);
        if(datos.EXISTE == "SI")
            {
            // Construir mini-lista de invoices.
            var html = '<table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:12px;">';
            html += '<tr style="background:#970202; color:#fff;">';
            html += '<th style="padding:4px 8px; text-align:center;">INVOICE</th>';
            html += '<th style="padding:4px 8px; text-align:center;">FECHA</th>';
            html += '<th style="padding:4px 8px; text-align:center;">OPC</th>';
            html += '</tr>';
            var invoices = datos.INVOICES;
            for(var key in invoices)
                {
                var inv = invoices[key];
                var num_display = (inv.NUMEROINVOICE != "") ? inv.NUMEROINVOICE : inv.CODIGO;
                // Usuario creador junto al codigo/numero (ej: "9 - HDIENER"). Vacio si no hay.
                var usuario_display = (inv.USERNAME && inv.USERNAME != "") ? (" - " + inv.USERNAME) : "";
                // Fecha (solo la parte de fecha) + hora HH:MM:SS tomada de FECHAREGISTRO (datetime).
                var fecha_raw = (inv.FECHAINVOICE != "" && inv.FECHAINVOICE != null) ? inv.FECHAINVOICE : inv.FECHAREGISTRO;
                var solo_fecha = (fecha_raw && fecha_raw.indexOf(" ") > -1) ? fecha_raw.split(" ")[0] : fecha_raw;
                var hora = "";
                if(inv.FECHAREGISTRO && inv.FECHAREGISTRO.indexOf(" ") > -1)
                    {
                    var hms = inv.FECHAREGISTRO.split(" ")[1].split(":");
                    if(hms.length >= 3)
                        hora = hms[0] + ":" + hms[1] + ":" + hms[2];
                    else if(hms.length == 2)
                        hora = hms[0] + ":" + hms[1];
                    }
                var fecha_display = solo_fecha + (hora != "" ? " " + hora : "");
                var bg = (parseInt(key) % 2 == 0) ? "#f9f9f9" : "#fff";
                html += '<tr style="background:' + bg + ';">';
                html += '<td style="padding:4px 8px; border-bottom:1px solid #eee; text-align:center;"><strong>' + num_display + usuario_display + '</strong></td>';
                html += '<td style="padding:4px 8px; border-bottom:1px solid #eee; text-align:center;">' + fecha_display + '</td>';
                html += '<td style="padding:4px 8px; border-bottom:1px solid #eee; text-align:center;">';
                html += '<a onclick="$(\'#id_dialog_invoice_cliente\').dialog(\'close\'); window.open(\'consola_factura_cliente.php?codigo=' + inv.CODIGO + '\', \'_blank\');" style="cursor:pointer; color:#2196F3; font-size:14px;" title="Abrir"><i class="icon-arrow-right-3"></i></a>';
                html += '<a onclick="confirmar_borrar_invoice(' + inv.CODIGO + ', ' + codigo_consolidado + ');" style="cursor:pointer; color:#c62828; font-size:14px; margin-left:8px;" title="Borrar"><i class="icon-remove"></i></a>';
                html += '</td>'; 
                html += '</tr>';
                }
            html += '</table>';

            $("#id_dialog_invoice_cliente").html(html);
            $("#id_dialog_invoice_cliente").dialog(
                {
                modal: true,
                width: 450,
                title: "Invoices del Consolidado",
                dialogClass: 'myTitleClass',
                buttons:
                    [
                    {
                    text: "CREAR NUEVO",
                    class: 'cancelButton',
                    click: function()
                        {
                        $(this).dialog("close");
                        crear_invoice_nuevo(codigo_consolidado);
                        }
                    },
                    {
                    text: "CANCELAR",
                    class: 'cancelButton',
                    click: function()
                        {
                        $(this).dialog("close");
                        }
                    }
                    ]
                });
            }
        else
            {
            // No existe — crear directamente sin preguntar.
            crear_invoice_nuevo(codigo_consolidado);
            }
        });
    }

function crear_invoice_nuevo(codigo_consolidado)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=crear_factura_cliente_nueva_dsft&parametro1=" + codigo_consolidado
        + "&parametro2=" + global_codigo_usuario;
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
        var codigo_nuevo = parseInt(resp);
        if(isNaN(codigo_nuevo) || codigo_nuevo <= 0)
            {
            messageBox("Error al crear el invoice. Respuesta del servidor: [" + resp + "]");
            return;
            }
        window.open("consola_factura_cliente.php?codigo=" + codigo_nuevo, "_blank");
        });
    }

// Confirmacion (dialog jQuery UI del sistema) para borrado FISICO de un invoice.
function confirmar_borrar_invoice(codigo, codigo_consolidado)
    {
    $("#id_dialog_confirma_factura").html("<p>&iquest;Desea borrar esta factura?</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 350,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "SI",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    borrar_invoice_fisico(codigo, codigo_consolidado);
                    }
                },
                {
                text: "NO",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    }
                }
            ]
        });
    }

// Borrado FISICO del invoice + recarga de la mini-lista. Si tras borrar no
// quedan invoices, NO reabrir el dialog (abrir_factura_cliente auto-crearia uno).
function borrar_invoice_fisico(codigo, codigo_consolidado)
    {
    $("#id_espera").show();
    var url = "funciones_ajax.php?funcion=eliminar_factura_cliente_fisico_dsft&parametro1=" + codigo;
    var obj_ajax = $.get(url, function(data, status){;});
    obj_ajax.success(function(data, status)
        {
        $("#id_espera").hide();
        if(data.trim() != "OK")
            {
            messageBox("Error al borrar: " + data);
            return;
            }
        $("#id_dialog_invoice_cliente").dialog("close");
        // Verificar si quedan invoices para decidir si reabrir la mini-lista.
        var url_v = "funciones_ajax.php?funcion=verifica_factura_cliente_dsft&parametro1=" + codigo_consolidado;
        $.get(url_v, function(data_v)
            {
            var datos_v = JSON.parse(data_v);
            if(datos_v.EXISTE == "SI")
                abrir_factura_cliente(codigo_consolidado);
            else
                messageBox("No quedan facturas para este consolidado.");
            });
        });
    }

// ===== AÑADIR FACTURA A UN CONSOLIDADO =====
// Dialog con dos opciones: "Crear a mano" (forma rapida) y "Subir archivo"
// (proximamente). Se invoca desde el icono "+" verde de la columna OPC.
// ===== DIALOG CREAR FACTURA MANUAL =====
// Solo finca + numero de factura. Boton habilitado cuando ambos estan
// completos. Llama a crear_factura_manual() que persiste la cabecera.
function dialog_crear_factura_manual(codigo_consolidado)
    {
    var html = '<div style="text-align:center; padding:10px;">'
        + '<p>Crear factura manual en consolidado <strong>#'
        + codigo_consolidado + '</strong></p>'
        + '<hr style="margin:10px 0;">'
        + '<div style="margin-bottom:15px; text-align:left;">'
        + '<label style="font-size:12px; display:block; margin-bottom:4px;">Finca / Proveedor:</label>'
        + '<select id="id_nueva_finca_select" style="width:90%;">'
        + global_opciones_fincas
        + '</select>'
        + '</div>'
        + '<div style="margin-bottom:15px; text-align:left;">'
        + '<label style="font-size:12px; display:block; margin-bottom:4px;">Numero de factura:</label>'
        + '<input type="text" id="id_nuevo_nfac" onkeyup="validar_dialog_crear_manual();" style="width:90%; text-transform:uppercase; font-size:12px; padding:4px;" placeholder="Ej: F001234" autocomplete="off">'
        + '</div>'
        + '<hr style="margin:10px 0;">'
        + '<button type="button" id="id_btn_crear_manual" onclick="crear_factura_manual('
        + codigo_consolidado + ');"'
        + ' class="button bg-darkRed bg-hover-red fg-white"'
        + ' disabled style="margin-right:10px; opacity:0.5; cursor:not-allowed;">'
        + '<i class="icon-pencil"></i> Crear</button>'
        + '<button type="button" onclick="$(\'#id_dialog_confirma_factura\').dialog(\'close\');"'
        + ' class="button bg-gray bg-hover-darkGray fg-white">'
        + 'Cancelar</button>'
        + '</div>';
    $("#id_dialog_confirma_factura").html(html);
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 420,
        dialogClass: 'myTitleClass',
        buttons: []
        });
    setTimeout(function()
        {
        $("#id_nueva_finca_select").select2(
            {
            width: '90%',
            placeholder: '-- SELECCIONE FINCA --',
            dropdownParent: $("#id_dialog_confirma_factura")
            });
        $("#id_nueva_finca_select").on("change", function()
            {
            validar_dialog_crear_manual();
            });
        }, 100);
    }

// Habilita/deshabilita el boton "Crear" segun finca + nfac.
function validar_dialog_crear_manual()
    {
    var finca_ok = false;
    var nfac_ok  = false;

    var val_finca = $("#id_nueva_finca_select").val();
    if(val_finca && val_finca != "0")
        finca_ok = true;

    var val_nfac = $("#id_nuevo_nfac").val();
    if(val_nfac && val_nfac.trim() != "")
        nfac_ok = true;

    if(finca_ok && nfac_ok)
        $("#id_btn_crear_manual").prop("disabled", false).css({"opacity":"1", "cursor":"pointer"});
    else
        $("#id_btn_crear_manual").prop("disabled", true).css({"opacity":"0.5", "cursor":"not-allowed"});
    }
  
// Crea una factura "a mano" en la BD y, si el consolidado destino es el
// seleccionado actualmente, recarga el detalle para verla.
function crear_factura_manual(codigo_consolidado)
    {
    var codigo_finca = $("#id_nueva_finca_select").val();
    var nombre_finca = $("#id_nueva_finca_select option:selected").text().trim();
    var nfac         = $("#id_nuevo_nfac").val().trim().toUpperCase();
    $("#id_dialog_confirma_factura").dialog("close");

    var url = "funciones_ajax.php?funcion=crear_factura_manual_dsft"
        + "&parametro1=" + codigo_consolidado
        + "&parametro2=" + codigo_finca
        + "&parametro3=" + encodeURIComponent(nombre_finca)
        + "&parametro4=" + encodeURIComponent(nfac);
    $.get(url, function(data)
        {
        var partes = data.split("|");
        if(partes[0] == "OK")
            {
            messageBox("Factura creada. Codigo: " + partes[1]);
            if(global_codigo_seleccionado == codigo_consolidado)
                cargar_detalle_consolidado(codigo_consolidado);
            }
        else
            {
            messageBox("Error: " + data);
            }
        });
    }

// ===== DIALOG SUBIR ARCHIVO (sin finca: la IA la extrae) =====
function dialog_subir_archivo(codigo_consolidado)
    {
    var html = '<div style="text-align:center; padding:10px;">'
        + '<p>Subir archivo al consolidado <strong>#'
        + codigo_consolidado + '</strong></p>'
        + '<p style="font-size:11px; color:#666;">La IA extraera '
        + 'automaticamente los datos de la factura.</p>'
        + '<hr style="margin:10px 0;">'
        + '<div id="id_zona_drop" style="border:2px dashed #ccc; border-radius:6px; padding:20px; text-align:center; color:#888; font-size:12px; cursor:pointer; margin-bottom:8px; transition:all 0.2s;">'
        + '<i class="icon-upload" style="font-size:24px; display:block; margin-bottom:6px;"></i>'
        + 'Arrastre un archivo aqui<br>o haga click para seleccionar'
        + '<input type="file" id="id_archivo_factura" accept=".pdf,.xlsx,.xls" style="display:none;">'
        + '<div id="id_nombre_archivo_sel" style="margin-top:8px; color:#88010e; font-weight:bold; display:none;"></div>'
        + '</div>'
        + '<button type="button" id="id_btn_subir_procesar"'
        + ' onclick="subir_archivo_factura(' + codigo_consolidado + ');"'
        + ' class="button bg-darkRed bg-hover-red fg-white"'
        + ' disabled style="opacity:0.5; cursor:not-allowed;">'
        + '<i class="icon-upload"></i> Subir y procesar con IA</button>'
        + ' <button type="button" onclick="$(\'#id_dialog_confirma_factura\').dialog(\'close\');"'
        + ' class="button bg-gray bg-hover-darkGray fg-white">'
        + 'Cancelar</button>'
        + '</div>';
    $("#id_dialog_confirma_factura").html(html);
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 420,
        dialogClass: 'myTitleClass',
        buttons: []
        });

    // Handlers drag-and-drop. off().on() para idempotencia si el dialog
    // se abre y cierra varias veces.
    setTimeout(function()
        {
        $(document).off("click", "#id_zona_drop");
        $(document).on("click", "#id_zona_drop", function(e)
            {
            if(e.target.tagName != "INPUT")
                $("#id_archivo_factura").click();
            });

        $(document).off("dragover", "#id_zona_drop");
        $(document).on("dragover", "#id_zona_drop", function(e)
            {
            e.preventDefault();
            $(this).css({"border-color":"#88010e", "background":"#fff5f5"});
            });

        $(document).off("dragleave", "#id_zona_drop");
        $(document).on("dragleave", "#id_zona_drop", function(e)
            {
            e.preventDefault();
            $(this).css({"border-color":"#ccc", "background":"transparent"});
            });

        $(document).off("drop", "#id_zona_drop");
        $(document).on("drop", "#id_zona_drop", function(e)
            {
            e.preventDefault();
            $(this).css({"border-color":"#ccc", "background":"transparent"});
            var files = e.originalEvent.dataTransfer.files;
            if(files.length > 0)
                {
                var archivo = files[0];
                var ext = archivo.name.split(".").pop().toLowerCase();
                if(ext != "pdf" && ext != "xlsx" && ext != "xls")
                    {
                    messageBox("Solo archivos PDF o Excel");
                    return;
                    }
                var dt = new DataTransfer();
                dt.items.add(archivo);
                document.getElementById("id_archivo_factura").files = dt.files;
                $("#id_nombre_archivo_sel").text(archivo.name).show();
                $("#id_btn_subir_procesar").prop("disabled", false)
                    .css({"opacity":"1", "cursor":"pointer"});
                }
            });

        $(document).off("change", "#id_archivo_factura");
        $(document).on("change", "#id_archivo_factura", function()
            {
            var archivo = this.files[0];
            if(archivo)
                {
                var ext = archivo.name.split(".").pop().toLowerCase();
                if(ext != "pdf" && ext != "xlsx" && ext != "xls")
                    {
                    messageBox("Solo archivos PDF o Excel");
                    this.value = "";
                    $("#id_nombre_archivo_sel").hide();
                    return;
                    }
                $("#id_nombre_archivo_sel").text(archivo.name).show();
                $("#id_btn_subir_procesar").prop("disabled", false)
                    .css({"opacity":"1", "cursor":"pointer"});
                }
            });
        }, 100);
    }

// ===== PROGRESO FLOTANTE MODAL =====
// Overlay translucido + caja crimson centrada. Bloquea la interaccion
// durante upload + procesamiento con IA. Mismo patron que la consola
// de correos.
function mostrar_progreso_flotante(html)
    {
    $("#id_flotante_contenido").html(html);
    $("#id_overlay_procesando").show();
    $("#id_flotante_procesando").show();
    }

function ocultar_progreso_flotante()
    {
    $("#id_overlay_procesando").hide();
    $("#id_flotante_procesando").hide();
    }

// ===== SUBIR ARCHIVO PDF/EXCEL Y PROCESAR CON IA =====
// 1) Cierra el dialog.
// 2) Muestra el progreso flotante modal.
// 3) POST multipart con codigo_consolidado + archivo.
// 4) Lanza procesar_factura_web (CLI background).
// 5) Polling a progreso_factura cada 3s, actualizando el contador.
// 6) Al "finalizado": asignar_consolidado_post_ia_dsft + recarga detalle.
function subir_archivo_factura(codigo_consolidado)
    {
    var archivo = document.getElementById("id_archivo_factura").files[0];
    if(!archivo)
        return;

    var ext = archivo.name.split(".").pop().toLowerCase();
    if(ext != "pdf" && ext != "xlsx" && ext != "xls")
        return;

    $("#id_dialog_confirma_factura").dialog("close");

    var formData = new FormData();
    formData.append("funcion", "subir_archivo_factura_dsft");
    formData.append("archivo", archivo);
    formData.append("codigo_consolidado", codigo_consolidado);

    mostrar_progreso_flotante(
        '<strong style="color:#88010e;">'
        + '<i class="icon-upload"></i> Subiendo '
        + archivo.name + '...</strong>');

    $.ajax(
        {
        url: "funciones_ajax.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(data)
            {
            var partes = data.split("|");
            if(partes[0] == "OK")
                {
                var codigo_adj = parseInt(partes[1]);

                var hora_inicio = new Date();
                var hora_str    = hora_inicio.toTimeString().substr(0, 8);
                mostrar_progreso_flotante(
                    '<strong style="color:#88010e;">'
                    + '<i class="icon-clock"></i> Procesando con IA...</strong><br>'
                    + '<span style="font-size:12px; color:#555;">'
                    + archivo.name + '</span><br>'
                    + '<span id="id_tiempo_progreso" style="font-size:12px; color:#555;">'
                    + 'Inicio: ' + hora_str + ' | Transcurrido: 0s</span>');

                // Disparar procesamiento background.
                $.get("funciones_ajax.php?funcion=procesar_factura_web"
                    + "&parametro1=" + codigo_adj, function() {});

                // Polling de progreso cada 3s.
                var intervalo = setInterval(function()
                    {
                    var ahora = new Date();
                    var segs  = Math.round((ahora - hora_inicio) / 1000);
                    // Refrescar contador inmediatamente.
                    $("#id_tiempo_progreso").text(
                        'Inicio: ' + hora_str + ' | Transcurrido: ' + segs + 's');
                    $.get("funciones_ajax.php?funcion=progreso_factura"
                        + "&parametro1=" + codigo_adj, function(data3)
                        {
                        try
                            {
                            var p = JSON.parse(data3);
                            if(p.estado == "finalizado")
                                {
                                clearInterval(intervalo);
                                // Asignar consolidado al factura_finca creado por la IA.
                                $.get("funciones_ajax.php"
                                    + "?funcion=asignar_consolidado_post_ia_dsft"
                                    + "&parametro1=" + codigo_adj
                                    + "&parametro2=" + codigo_consolidado,
                                    function(data4)
                                    {
                                    ocultar_progreso_flotante();
                                    messageBox("Factura " + archivo.name
                                        + " procesada (" + segs + "s).");
                                    cargar_detalle_consolidado(codigo_consolidado);
                                    });
                                }
                            else if(p.estado == "error")
                                {
                                clearInterval(intervalo);
                                ocultar_progreso_flotante();
                                messageBox("Error: " + (p.mensaje || "desconocido"));
                                }
                            }
                        catch(e) {}
                        });
                    }, 3000);
                }
            else
                {
                ocultar_progreso_flotante();
                messageBox("Error: " + data);
                }
            },
        error: function()
            {
            ocultar_progreso_flotante();
            messageBox("Error de conexion al subir el archivo");
            }
        });
    }

// Desasocia una factura del consolidado actual (UPDATE CODIGOCONSOLIDADO = NULL).
// La factura no se elimina, solo se quita del consolidado.
function quitar_factura_consolidado(codigo_ff, descripcion)
    {
    $("#id_dialog_confirma_factura").html(
        "<p>Quitar factura <strong>" + descripcion
        + "</strong> de este consolidado?</p>"
        + "<p>La factura no se elimina, solo se desasocia.</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 400,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "Quitar",
                class: 'cancelButton',
                click: function()
                    { 
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=quitar_factura_consolidado_dsft"
                        + "&parametro1=" + codigo_ff;
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            cargar_detalle_consolidado(global_codigo_seleccionado);
                        else
                            messageBox("Error: " + data);
                        });
                    }
                },
                {
                text: "Cancelar",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// Minimiza/expande el contenido (metadata + grid + PDF) de una tarjeta de
// factura. La linea de totales queda siempre visible (esta fuera del div).
function toggle_tarjeta_factura(codigo_ff)
    {
    var contenido = $("#id_contenido_factura_" + codigo_ff);
    var icono     = $("#id_toggle_icon_" + codigo_ff);
    if(contenido.is(":visible"))
        {
        contenido.slideUp(200);
        icono.removeClass("icon-arrow-up").addClass("icon-arrow-down");
        }
    else
        {
        contenido.slideDown(200);
        icono.removeClass("icon-arrow-down").addClass("icon-arrow-up");
        }
    }

// Pinta el PDF en el area derecha de la tarjeta de factura.
function ver_pdf_consolidado(codigo_adjunto, nombre_archivo, codigo_ff)
    {
    var area = $("#id_pdf_area_" + codigo_ff);
    area.html('<iframe src="ver_adjunto.php?codigo=' + codigo_adjunto
        + '" style="width:100%; height:500px; border:none;"></iframe>');
    }

// ===== EDICION CELDA POR CELDA DEL GRID DE DETALLE =====
// Delegacion: el HTML del grid se carga por AJAX (id_detalle_consolidado),
// por eso usamos $(document).on() en vez de bind directo.

// Persiste un cambio: PHP valida el campo contra una lista blanca
// (VARIEDAD/LARGO/TALLOSTOTAL/PRECIOUNITARIO).
function guardar_celda_detalle(codigo, campo, valor, callback)
    {
    var url = "funciones_ajax.php?funcion=actualizar_celda_detalle_dsft"
        + "&parametro1=" + codigo
        + "&parametro2=" + encodeURIComponent(campo)
        + "&parametro3=" + encodeURIComponent(valor);
    $.get(url, function(data)
        {
        if(data != "OK")
            messageBox("Error al guardar: " + data);
        if(typeof callback === "function")
            callback();
        });
    }

// Recalculo del PRECIOTOTAL lo hace el servidor en el mismo UPDATE.
// Aqui solo recargamos el grid de esa factura para mostrar el nuevo total.
function recalcular_total_linea(codigo, tr)
    {
    recargar_grid_factura(tr);
    }

// Recarga SOLO el grid de la factura afectada (no todas las tarjetas).
// elemento puede ser un tr, td o cualquier hijo del grid: buscamos hacia
// arriba el ancestro con id="id_grid_factura_*" y extraemos el CODIGO_FF.
function recargar_grid_factura(elemento) 
    {
    var wrapper = $(elemento).closest("[id^='id_grid_factura_']");
    if(wrapper.length == 0)
        return;
    var codigo_ff = wrapper.attr("id").replace("id_grid_factura_", "");
    if(!codigo_ff)
        return;
    recargar_grid_factura_por_codigo(codigo_ff);
    }

// Helper: recargar el grid + los totales (que estan fuera del colapsable)
// de una factura por codigo_ff.
function recargar_grid_factura_por_codigo(codigo_ff)
    {
    var url = "funciones_ajax.php?funcion=render_grid_factura_dsft"
        + "&parametro1=" + codigo_ff;
    $.get(url, function(data)
        {
        $("#id_grid_factura_" + codigo_ff).html(data);
        // Refrescar tambien los totales (estan fuera del grid).
        var url_tot = "funciones_ajax.php?funcion=render_totales_factura_dsft"
            + "&parametro1=" + codigo_ff;
        $.get(url_tot, function(data_tot)
            {
            $("#id_totales_factura_" + codigo_ff).html(data_tot);
            });
        actualizar_contador_entregas(codigo_ff);
        actualizar_icono_guia_factura(codigo_ff);
        });
    }

function eliminar_linea_detalle(codigo_detalle, codigo_ff)
    {
    $("#id_dialog_confirma_factura").html(
        "<p>Eliminar esta linea del detalle?</p>");
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 350,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "Eliminar",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=eliminar_linea_detalle_dsft"
                        + "&parametro1=" + codigo_detalle;
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            {
                            // Si era la ultima linea de la caja, se borro tambien
                            // su registro (y con el su entrega y su guia), asi que
                            // hay que refrescar el contador y el icono de guia.
                            recargar_grid_factura_por_codigo(codigo_ff);
                            }
                        else
                            messageBox(data);
                        }); 
                    }
                },
                {
                text: "Cancelar",
                click: function() { $(this).dialog("close"); }
                } 
            ]
        });
    }

// Agrega una linea adicional a una caja existente (mismo NUMEROCAJA y
// TIPOCAJA). Se invoca desde el icono "+" verde en la primera linea de
// cada caja.
function agregar_linea_a_caja(codigo_ff, numero_caja, tipo_caja)
    {
    var url = "funciones_ajax.php?funcion=agregar_linea_a_caja_dsft"
        + "&parametro1=" + codigo_ff
        + "&parametro2=" + numero_caja
        + "&parametro3=" + encodeURIComponent(tipo_caja);
    $.get(url, function(data)
        {
        if(data == "OK")
            {
            var url2 = "funciones_ajax.php?funcion=render_grid_factura_dsft"
                + "&parametro1=" + codigo_ff;
            $.get(url2, function(data2)
                {
                $("#id_grid_factura_" + codigo_ff).html(data2);
                });
            }
        else
            messageBox(data);
        }); 
    }

// Agrega una caja NUEVA: abre un dialog para que la usuaria elija el
// tipo (HB/FB/QB/OB), y luego crea la primera linea de esa caja.
function agregar_caja_detalle(codigo_ff)
    {
    var html_dialog = '<p>Tipo de caja:</p>'
        + '<select id="id_select_tipo_caja_nueva" style="width:100%;">'
        + '<option value="HB">HB (Half Box = 0.5)</option>'
        + '<option value="FB">FB (Full Box = 1)</option>'
        + '<option value="QB">QB (Quarter Box = 0.25)</option>'
        + '<option value="OB">OB (Octave Box = 0.125)</option>'
        + '</select>';
    $("#id_dialog_confirma_factura").html(html_dialog);
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 350,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "Crear",
                class: 'cancelButton',
                click: function()
                    {
                    var tipo = $("#id_select_tipo_caja_nueva").val();
                    $(this).dialog("close");
                    var url = "funciones_ajax.php?funcion=agregar_caja_detalle_dsft"
                        + "&parametro1=" + codigo_ff
                        + "&parametro2=" + encodeURIComponent(tipo);
                    $.get(url, function(data)
                        {
                        if(data == "OK")
                            {
                            var url2 = "funciones_ajax.php?funcion=render_grid_factura_dsft"
                                + "&parametro1=" + codigo_ff;
                            $.get(url2, function(data2)
                                {
                                $("#id_grid_factura_" + codigo_ff).html(data2);
                                });
                            }
                        else
                            messageBox(data);
                        });
                    }
                },
                {
                text: "Cancelar",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }

// ===== RESTAURAR FACTURA AL ORIGINAL DE LA IA =====
// Reusa el JSON guardado en factura_finca.RESPUESTACLAUDE (no llama de
// nuevo a Claude): elimina el detalle actual y lo recrea desde el JSON.
// Es instantaneo, no requiere polling.
// Antes de regenerar: si la factura tiene cajas confirmadas, avisar, porque
// al rehacer las lineas se pueden perder esas confirmaciones.
function regenerar_factura(codigo_ff, codigo_adj, nombre_adj)
    {
    // El texto lo arma el servidor: cuenta las cajas entregadas Y las que tienen
    // guia asignada. Respuesta vacia = no hay nada que advertir.
    var url = "funciones_ajax.php?funcion=render_aviso_regenerar_dsft"
        + "&parametro1=" + codigo_ff;
    $.get(url, function(aviso)
        {
        if($.trim(aviso) == "")
            {
            dialog_restaurar_factura(codigo_ff, codigo_adj, nombre_adj);
            return;
            }
        $("#id_dialog_confirma_factura").html(aviso);
        $("#id_dialog_confirma_factura").dialog(
            {
            modal: true,
            width: 420,
            dialogClass: 'myTitleClass',
            buttons:
                [
                    {
                    text: "SI",
                    class: 'cancelButton',
                    click: function()
                        {
                        $(this).dialog("close");
                        dialog_restaurar_factura(codigo_ff, codigo_adj, nombre_adj);
                        }
                    },
                    {
                    text: "NO",
                    click: function() { $(this).dialog("close"); }
                    }
                ]
            });
        });
    }

function dialog_restaurar_factura(codigo_ff, codigo_adj, nombre_adj)
    {
    $("#id_dialog_confirma_factura").html(
        "<p>Restaurar factura <strong>" + nombre_adj + "</strong> al original de la IA?</p>"
        + "<p style='color:#88010e;'>Esto eliminara las modificaciones manuales y restaurara "
        + "los datos exactos que extrajo la IA originalmente.</p>"
        + "<p>Las ediciones manuales se perderan.</p>"
        ); 
    $("#id_dialog_confirma_factura").dialog(
        {
        modal: true,
        width: 450,
        dialogClass: 'myTitleClass',
        buttons:
            [
                {
                text: "Restaurar",
                class: 'cancelButton',
                click: function()
                    {
                    $(this).dialog("close");
                    ejecutar_regeneracion(codigo_ff, codigo_adj, nombre_adj);
                    }
                },
                {
                text: "Cancelar",
                click: function() { $(this).dialog("close"); }
                }
            ]
        });
    }
 
function ejecutar_regeneracion(codigo_ff, codigo_adj, nombre_adj)
    {
    var url = "funciones_ajax.php?funcion=regenerar_detalle_factura_dsft"
        + "&parametro1=" + codigo_ff;
    $.get(url, function(data)
        {
        var partes = data.split("|");
        if(partes[0] == "OK")
            {
            messageBox("Factura restaurada al original de la IA ("
                + partes[1] + " lineas).");
            // Recargar el grid de esta factura. Por recargar_grid_factura_por_codigo
            // y no a mano, para que se refresquen tambien el contador de entregas
            // y el icono de guia, que viven FUERA del grid y acaban de cambiar.
            recargar_grid_factura_por_codigo(codigo_ff);
            // Scroll al grid restaurado.
            $("html, body").animate(
                {
                scrollTop: $("#id_grid_factura_" + codigo_ff).offset().top - 50
                }, 300);
            }
        else
            {
            messageBox("Error: " + data);
            }
        });
    }

// Doble-click en celda PROD: abre un Select2 inline con los tipos de producto.
// Al elegir, cambia el PRODUCTO de TODAS las lineas de esa caja.
$(document).on("dblclick", ".celda_prod", function()
    {
    // En modo seleccion el clic marca la linea: no se abre el editor.
    if(en_modo_seleccion(this))
        return;
    var td = $(this);
    if(td.find("select").length > 0)
        return; // ya editando

    var select_html = '<select class="select_prod_inline" style="width:100%; font-size:10px;">'
        + '<option value="">-- TIPO --</option>'
        + global_opciones_tipos_producto
        + '</select>';
    td.html(select_html);
    td.find(".select_prod_inline").select2(
        {
        width: '100%',
        dropdownAutoWidth: true
        }).select2("open");
    });

// Al elegir un tipo en el select inline: guardar y recargar el grid.
$(document).on("change", ".select_prod_inline", function()
    {
    var td          = $(this).closest(".celda_prod");
    var codigo_ff   = td.data("ff");
    var numero_caja = td.data("caja");
    var codigo_tipo = $(this).val();

    if(!codigo_tipo || codigo_tipo == "")
        {
        recargar_grid_factura_por_codigo(codigo_ff);
        return;
        }

    var url = "funciones_ajax.php?funcion=cambiar_producto_caja_dsft"
        + "&parametro1=" + codigo_ff
        + "&parametro2=" + numero_caja
        + "&parametro3=" + codigo_tipo;
    $.get(url, function(data)
        {
        if(data == "OK")
            recargar_grid_factura_por_codigo(codigo_ff);
        else
            messageBox("Error: " + data);
        });
    });

// Click en celda editable -> reemplazar texto por input. Las celdas cm
// se manejan con dblclick (no con click simple), asi que retornamos.
$(document).on("click", ".celda_editable", function()
    {
    if(en_modo_seleccion(this))
        return;
    var td = $(this);
    if(td.data("field") == "CM")
        return; // CM se maneja con dblclick
    if(td.find("input, select").length > 0)
        return; // ya editando

    var field        = td.data("field");
    var valor_actual = td.text().trim();

    if(field == "PRECIOUNITARIO")
        {
        var input = '<input type="number" step="0.01" value="'+valor_actual+'" data-orig="'+valor_actual+'" style="width:60px; font-size:11px; text-align:right;">';
        td.html(input);
        td.find("input").focus().select();
        }
    else if(field == "VARIEDAD")
        {
        var input = '<input type="text" value="'+valor_actual+'" style="width:100%; font-size:11px;">';
        td.html(input);
        td.find("input").focus().select();
        }
    });

// Doble-click en celda cm: edita el numero. Si la fila ya tiene un
// valor en otra columna cm, lo mueve a la columna del dblclick.
$(document).on("dblclick", ".celda_cm", function()
    {
    if(en_modo_seleccion(this))
        return;
    var td = $(this);
    if(td.find("input").length > 0)
        return; // ya editando

    var tr         = td.closest("tr");
    var cm_destino = td.data("cm");

    // Buscar si esta fila tiene un valor en alguna celda cm.
    var celda_con_valor = null;
    var tallos_actuales = "";
    tr.find(".celda_cm").each(function()
        {
        var txt = $(this).text().trim();
        if(txt != "" && !isNaN(txt))
            {
            celda_con_valor = $(this);
            tallos_actuales = txt;
            }
        });

    if(celda_con_valor)
        {
        var cm_origen = celda_con_valor.data("cm");
        if(cm_origen == cm_destino)
            {
            // Mismo cm: solo editar el numero in-place.
            var input = '<input type="number" value="' + tallos_actuales
                + '" style="width:45px; font-size:11px; text-align:center;">';
            td.html(input);
            td.find("input").focus().select();
            return;
            }
        // Mover el valor: limpiar origen, poner input en destino con el valor.
        celda_con_valor.text("");
        var input = '<input type="number" value="' + tallos_actuales
            + '" style="width:45px; font-size:11px; text-align:center;">';
        td.html(input);
        td.find("input").focus().select();
        }
    else
        {
        // Fila sin datos cm: input vacio para tipear el numero.
        var input = '<input type="number" value=""'
            + ' style="width:45px; font-size:11px; text-align:center;"'
            + ' placeholder="0">';
        td.html(input);
        td.find("input").focus();
        }
    });

// Blur en celda cm: guardar LARGO + TALLOSTOTAL (o limpiar si vacio/0).
$(document).on("blur", ".celda_cm input", function()
    {
    var td           = $(this).closest(".celda_cm");
    var tr           = td.closest("tr");
    var codigo       = tr.data("codigo");
    var cm_destino   = td.data("cm");
    var nuevo_tallos = $(this).val();

    // Validacion: no aceptar negativos. Recargar el grid restaura el estado real
    // (incluida la celda origen si hubo un "mover" desde otra columna cm).
    if(nuevo_tallos != "" && nuevo_tallos != "0")
        {
        var valor = parseInt(nuevo_tallos);
        if(isNaN(valor) || valor < 0)
            {
            messageBox("El valor no puede ser negativo");
            recargar_grid_factura(tr);
            return;
            }
        }

    if(nuevo_tallos == "" || nuevo_tallos == "0")
        {
        // Vacio o cero: limpiar LARGO y TALLOSTOTAL.
        guardar_celda_detalle(codigo, "LARGO", "", function()
            {
            guardar_celda_detalle(codigo, "TALLOSTOTAL", "", function()
                {
                recargar_grid_factura(tr);
                });
            });
        return;
        }

    guardar_celda_detalle(codigo, "LARGO", cm_destino, function()
        {
        guardar_celda_detalle(codigo, "TALLOSTOTAL", nuevo_tallos, function()
            {
            recargar_grid_factura(tr);
            });
        });
    });

// Blur en VARIEDAD/PRECIOUNITARIO -> guardar. El handler tiene un
// selector mas especifico que excluye CM (.celda_cm se maneja arriba).
$(document).on("blur", ".celda_editable input, .celda_editable select", function()
    {
    var td = $(this).closest(".celda_editable");
    if(td.hasClass("celda_cm"))
        return; // CM tiene su propio handler

    var tr     = td.closest("tr");
    var codigo = tr.data("codigo");
    var field  = td.data("field");

    if(field == "PRECIOUNITARIO")
        {
        // Validacion: no aceptar negativos (mismo mensaje que el servidor).
        var valor_original = $(this).data("orig");
        var valor = parseFloat($(this).val());
        if(isNaN(valor) || valor < 0)
            {
            messageBox("El precio no puede ser negativo");
            td.text(valor_original);
            return;
            }
        }

    // Guardar y recargar el grid completo de la factura (para que dos personas
    // editando el mismo consolidado vean los cambios de la otra al recargar).
    var nuevo_valor = $(this).val();
    guardar_celda_detalle(codigo, field, nuevo_valor, function()
        {
        if(field == "PRECIOUNITARIO")
            recalcular_total_linea(codigo, tr);
        else
            recargar_grid_factura(tr);
        });
    });

// Enter = guardar (trigger blur). Escape = cancelar (recarga el grid).
// Aplica a inputs de cualquier celda editable, incluyendo .celda_cm.
$(document).on("keydown", ".celda_editable input", function(e)
    {
    if(e.keyCode == 13)
        $(this).blur();
    if(e.keyCode == 27)
        recargar_grid_factura($(this).closest("tr"));
    });

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
function actualiza_listado(callback_post)
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
        // El listado acaba de cambiar de alto: reubicar lo que este abierto.
        recentrar_dialogos_abiertos();
        if(typeof callback_post === "function")
            callback_post();
        });
    }

// ===== Limpia el formulario =====
function boton_nuevo()
    {
    global_codigo_seleccionado = 0;
    $("#id_codigo_consolidado").val("");
    if(flatpickr_fechaconsolidado)
        flatpickr_fechaconsolidado.clear();
    // CLIENTE a 0 sin disparar el handler (para no encadenar un AJAX inutil).
    $("#id_codigocliente").val("0").trigger('change.select2');
    // MARCACION vuelve a solo "-- SELECCIONE --" (sin opciones de ningun cliente).
    $("#id_codigomarcacion").html('<option value="0">-- SELECCIONE --</option>').trigger('change.select2');
    $("#id_codigotruck").val("0").trigger('change.select2');
    $("#id_codigopais").val("0").trigger('change.select2');
    $("#id_codigoagencia").val("0").trigger('change.select2');
    $("#id_observaciones").val("");
    // Nuevo registro = activo por defecto.
    $("#id_estado_consolidado").prop("checked", true);
    $("#id_listado_consolidados .grupo_consolidado").removeClass("grupo_consolidado_seleccionado");
    // Limpiar el resumen de guias del formulario.
    $("#id_resumen_guias_consolidado").html("&mdash;");
    // Ocultar el detalle inferior de facturas.
    $("#id_detalle_consolidado").hide().html("");
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

    // Flatpickr para FECHA del consolidado (formulario). clickOpens controla que solo
    // se abra al clickear el input, no al recibir focus programatico.
    flatpickr_fechaconsolidado = flatpickr("#id_fechaconsolidado",
        {
        dateFormat: "Y-m-d",
        locale: "es",
        clickOpens: true,
        allowInput: false
        });

    // Flatpickr modo range para filtrar el grid por FECHACONSOLIDADO.
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
    // El Select2 de guias se quito junto con el bloque del formulario: ahora el
    // numero se escribe en el dialogo GUIAS, en un input con datalist de
    // sugerencias (numeros_guia_recientes_dsft).

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

    // Si viene ?codigo=N por URL, seleccionar ese consolidado automaticamente.
    // Esperar un momento para que actualiza_listado termine de renderizar.
    var params = new URLSearchParams(window.location.search);
    var codigo_param = params.get("codigo");
    if(codigo_param && parseInt(codigo_param) > 0)
        {
        setTimeout(function()
            {
            devuelve_consolidado(parseInt(codigo_param));
            }, 600);
        }
    });
</script>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>
 
    <!-- LAYOUT PRINCIPAL: listado a la izquierda + formulario a la derecha -->
    <div style="display: flex; flex-direction: row; align-items: flex-start; margin-top:10px; margin-left:0; gap: 8px;">
 
        <!-- ===== COLUMNA IZQUIERDA: LISTADO DE CONSOLIDADOS ===== -->
        <div id="id_panel_listado" class="aida" style="width: 760px; height: 400px; overflow: hidden; display: flex; flex-direction: column;">
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
        <div id="id_formulario_consolidado" class="aida" style="width: 440px; height: 400px !important; overflow-y: auto;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <span><center><strong><i class="icon-clipboard fg-darkRed"></i> DATOS DEL CONSOLIDADO</strong>
                <input type="text" id="id_codigo_consolidado" class="input_readonly" readonly
                    style="height:22px; padding:2px 4px; width:50px; margin-left:10px; font-size:13px; display:inline-block; vertical-align:middle;"
                    autocomplete="off" />
            </center></span>

            <div style="padding: 12px;">
                <!-- Layout 2 columnas con ancho identico. Cada fila tiene 2 campos lado a lado. -->
                <table style="width: 100%; font-size: 13px; border-collapse: separate; border-spacing: 0; table-layout:fixed;">
                    <colgroup>
                        <col style="width: 65px;">
                        <col style="width: 50%;">
                        <col style="width: 65px;">
                        <col style="width: 50%;"> 
                    </colgroup>
                    <!-- Fila 1: FECHA + ACTIVO (checkbox) -->
                    <tr>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">FECHA:</td>
                        <td style="padding-right: 6px; padding-bottom: 5px;">
                            <input type="text" id="id_fechaconsolidado" autocomplete="off" class="input_pequeno" placeholder="aaaa-mm-dd" style="background-color:#fff; cursor:pointer; text-transform: none;" />
                        </td>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">ACTIVO:</td>
                        <td style="padding-bottom: 5px;">
                            <input type="checkbox" id="id_estado_consolidado" checked />
                        </td>
                    </tr>
                    <!-- Fila 2: CLIENTE + MARCA -->
                    <tr>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">CLIENTE:</td>
                        <td style="padding-right: 6px; padding-bottom: 5px;">
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
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">MARCA:</td>
                        <td style="padding-bottom: 5px;">
                            <select id="id_codigomarcacion" style="width: 100%;">
                                <option value="0">-- SELECCIONE --</option>
                            </select>
                        </td>
                    </tr>
                    <!-- Fila 3: TRUCK + AGN -->
                    <tr>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">TRUCK:</td>
                        <td style="padding-right: 6px; padding-bottom: 5px;">
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
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">AGN:</td>
                        <td style="padding-bottom: 5px;">
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
                    <!-- Fila 4: PAIS + OBS -->
                    <tr>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">PAIS:</td>
                        <td style="padding-right: 6px; padding-bottom: 5px;">
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
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; vertical-align: top; white-space: nowrap;">OBS:</td>
                        <td style="padding-bottom: 5px;">
                            <textarea id="id_observaciones" maxlength="500" rows="2"
                                style="width: 100%; text-transform: uppercase; font-size: 12px; padding: 5px 6px; border: 1px solid #c0c0c0; border-radius: 2px; box-sizing: border-box; resize: none; font-family: inherit;"></textarea>
                        </td>
                    </tr>
                    <!-- Fila 5: resumen de guias. La gestion esta en el boton GUIAS. -->
                    <tr>
                        <td style="text-align: right; padding-right: 4px; padding-bottom: 5px; white-space: nowrap;">AWB:</td>
                        <td colspan="3" style="padding-bottom: 5px;">
                            <span id="id_resumen_guias_consolidado" style="font-size:12px; color:#555;">&mdash;</span>
                        </td>
                    </tr>
                </table>

                <!-- Botones MEDIDAS / GRABAR / NUEVO -->
                <div style="text-align: right; margin-top: 10px;">
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="dialog_medidas_consolidado();">MEDIDAS</button>
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="dialog_gestion_guias();" style="margin-left: 5px;">GUÍAS</button>
                    <button type="button" class="button bg-darkRed bg-hover-red fg-white" onclick="grabar_consolidado();" style="margin-left: 5px;">GRABAR</button>
                    <button type="button" class="button bg-gray bg-hover-darkGray fg-white" onclick="boton_nuevo();" style="margin-left: 5px;">NUEVO</button>
                </div>
            </div>
        </div>

    </div>

    <!-- DETALLE INFERIOR: facturas asignadas al consolidado seleccionado. -->
    <div id="id_detalle_consolidado" style="width:100%; margin-top:10px; display:none;">
    </div>

    <!-- DIALOGOS -->
    <div id="dialog" title="Alerta"></div>
    <div id="id_dialog_confirma_factura" title="Confirmar"></div>
    <div id="id_dialog_invoice_cliente" title="Invoice Cliente"></div>
    <div id="id_dialog_medidas" title="Medidas (cm) del consolidado"></div>
    <div id="id_dialog_selector_guias" title="Guías"></div>
    <div id="id_dialog_guias_consolidado" title="Guías del consolidado"></div>
    <div id="id_espera"><strong><i class="icon-clock fg-white"></i></strong></div>

    <!-- Mini-menu flotante de formato (Excel / PDF) del icono puzzle. -->
    <div id="id_menu_formato" style="display:none; position:absolute; background:#fff; border:1px solid #ccc; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.15); padding:4px; z-index:9999; white-space:nowrap;">
        <a id="id_menu_excel" onclick="" style="cursor:pointer; color:#2e7d32; padding:4px 8px; display:inline-block;" title="Excel"><i class="icon-file-excel" style="font-size:16px;"></i></a>
        <a id="id_menu_pdf" onclick="" style="cursor:pointer; color:#88010e; padding:4px 8px; display:inline-block;" title="PDF"><i class="icon-file-pdf" style="font-size:16px;"></i></a>
    </div>

    <!-- Progreso flotante modal: overlay + caja crimson centrada. Bloquea
         la interaccion del usuario durante upload+procesamiento con IA. -->
    <div id="id_overlay_procesando" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9998;"></div>
    <div id="id_flotante_procesando" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; border:2px solid #88010e; border-radius:8px; padding:20px 30px; z-index:9999; min-width:350px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <div id="id_flotante_contenido"></div>
    </div>

</body>
</html>
