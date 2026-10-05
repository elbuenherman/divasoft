// ============================================================================
//  js_comun_dsft.js  -  JS compartido por las consolas (estilo v3).
// 
//  Vive aca lo que usan VARIAS pantallas: el aviso breve y el selector de guias
//  (asignar y transferir), para no duplicarlo en cada consola.
//
//  Lo que depende de cada pantalla NO se asume: despues de asignar o transferir
//  se llama a sel_guia_refrescar(), que delega en sel_guia_refrescar_consola()
//  si la consola la definio. Cada consola decide que redibujar.
//
//  Requiere, ya cargados por la consola: jQuery, jQuery UI, Flatpickr, Select2,
//  messageBox() y la variable global_codigo_usuario.
// ============================================================================

// Enganche de refresco. El archivo comun no sabe que hay en pantalla; cada
// consola define sel_guia_refrescar_consola(info) con lo suyo.
//   info: { accion, nivel, codigo_ff, consolidado, destino }
function sel_guia_refrescar(info) {
    if (typeof sel_guia_refrescar_consola === "function")
        sel_guia_refrescar_consola(info);
}

// ===== AVISO BREVE =====
// Cartel chico arriba al centro, justo debajo de la barra fija (45px de alto,
// z-index 90 en css_v4.php), que aparece y se disuelve solo. No hay que
// cerrarlo, no bloquea nada (pointer-events:none) y no es modal. Si llegan
// varios clics seguidos, el nuevo reemplaza al anterior en vez de apilarse.
// tipo: "verde" para confirmaciones; cualquier otro valor = gris oscuro.
// Aviso chico que sale JUNTO al elemento clicado y se disuelve solo. Imita el
// aspecto del messageBox del sistema (barra crimson + cuerpo blanco) pero NO es
// un dialog jQuery UI: uno real seria modal y robaria el foco. Las medidas
// salen de .ui-dialog / .myTitleClass en css/dienersoft_comun.css.
// No hay que cerrarlo, no bloquea (pointer-events:none) y no es modal. Si
// llegan varios clics seguidos, el nuevo reemplaza al anterior.
//   elemento: el <a> donde se hizo clic. Sin el, cae al respaldo arriba al centro.
function aviso_breve(texto, elemento) {
    // Nunca dos a la vez, ni dos elementos con el mismo id.
    $("#id_aviso_breve").stop(true, true).remove();

    // Nace transparente y fuera de pantalla: asi se puede medir (un display:none
    // no se deja medir) sin que llegue a verse en la esquina.
    // El padding derecho de la barra es 12px y no 36px como en el dialog real,
    // porque aca no hay boton X que dejar libre.
    var caja = '<div id="id_aviso_breve" style="' +
        'position:absolute; top:0; left:-9999px; opacity:0;' +
        ' z-index:95; pointer-events:none;' +
        ' background:#fff; border:1px solid #aaa; border-radius:4px;' +
        ' box-shadow:0 4px 12px rgba(0,0,0,0.3); overflow:hidden;' +
        ' min-width:220px; font-family:inherit;">' +
        '<div style="background:#88010e; color:#fff; font-weight:bold;' +
        ' font-size:13px; padding:8px 12px; box-sizing:border-box;">Alerta</div>' +
        '<div style="background:#fff; color:#000; font-size:14px;' +
        ' padding:16px 12px; text-align:left; white-space:nowrap;' +
        ' box-sizing:border-box;">' + texto + '</div>' +
        '</div>';

    $("body").append(caja);
    var aviso = $("#id_aviso_breve");

    // Respaldo: sin elemento de referencia, arriba al centro y fijo a la ventana.
    if (!elemento || $(elemento).length == 0) {
        aviso.css({ "position": "fixed", "top": "53px", "left": "50%", "margin-left": -(aviso.outerWidth() / 2) + "px" });
        aviso.animate({ "opacity": 1 }, 150, function() {
            $(this).delay(800).fadeOut(1000, function() { $(this).remove(); });
        });
        return;
    }

    var posicion = $(elemento).offset();
    var ancho_el = $(elemento).outerWidth();
    var alto_el = $(elemento).outerHeight();
    var ancho_av = aviso.outerWidth();
    var alto_av = aviso.outerHeight();
    var centro_x = posicion.left + (ancho_el / 2);

    // Por defecto encima del icono. Si ahi lo taparia la barra fija (45px de
    // alto, anclada a la ventana), se pasa abajo.
    var piso = $(window).scrollTop() + 45 + 4;
    var pos_y = posicion.top - alto_av - 8;
    if (pos_y < piso)
        pos_y = posicion.top + alto_el + 8;
    // Ni arriba ni abajo alcanza: pasa si el icono quedo bajo la barra por un
    // scroll con el dialogo abierto. Se deja apenas debajo de la barra.
    if (pos_y < piso)
        pos_y = piso;

    // Centrado sobre el icono, pero sin salirse por los costados de la ventana.
    var pos_x = centro_x - (ancho_av / 2);
    var minimo = $(window).scrollLeft() + 8;
    var maximo = $(window).scrollLeft() + $(window).width() - ancho_av - 8;
    if (pos_x > maximo)
        pos_x = maximo;
    if (pos_x < minimo)
        pos_x = minimo;

    // Entra con un leve empujon hacia arriba (5px), se deja leer 0,8 s y se
    // disuelve en 1 s antes de salir del DOM.
    aviso.css({ "left": pos_x + "px", "top": (pos_y + 5) + "px" });
    aviso.animate({ "top": pos_y + "px", "opacity": 1 }, 150, function() {
        $(this).delay(800).fadeOut(1000, function() { $(this).remove(); });
    });
}

// ===== SELECTOR DE GUIAS (iconos #): asignar y transferir =====
// Un solo dialogo para los tres niveles. El destino se guarda en estas globales
// ANTES de abrirlo, porque las filas las dibuja el servidor y no lo conocen.
// Nombres con prefijo sel_ para que no choquen con los del dialogo GUIAS
// (dialog_gestion_guias) ni con nada previo.
var sel_nivel = "caja"; // "caja" | "factura" | "consolidado"
var sel_codigo_ff = 0;
var sel_numero_caja = 0;
var sel_consolidado = 0;
var sel_origen = null; // elemento clicado, para el aviso_breve
var sel_flat_rango = null;

function sel_guia_abrir_caja(codigo_ff, numero_caja, codigo_consolidado, codigo_guia_actual, elemento) {
    sel_nivel = "caja";
    sel_codigo_ff = codigo_ff;
    sel_numero_caja = numero_caja;
    sel_consolidado = codigo_consolidado;
    sel_origen = elemento;
    sel_guia_cargar(codigo_guia_actual, "Guía de la caja " + numero_caja);
}

function sel_guia_abrir_factura(codigo_ff, codigo_consolidado, elemento) {
    sel_nivel = "factura";
    sel_codigo_ff = codigo_ff;
    sel_numero_caja = 0;
    sel_consolidado = codigo_consolidado;
    sel_origen = elemento;
    sel_guia_cargar(0, "Guía de la factura " + codigo_ff);
}

function sel_guia_abrir_consolidado(codigo_consolidado, elemento) {
    sel_nivel = "consolidado";
    sel_codigo_ff = 0;
    sel_numero_caja = 0;
    sel_consolidado = codigo_consolidado;
    sel_origen = elemento;
    sel_guia_cargar(0, "Guía del consolidado " + codigo_consolidado);
}

function sel_guia_cargar(codigo_guia_actual, titulo) {
    var url = "funciones_ajax.php?funcion=render_selector_guias_dsft" +
        "&parametro1=" + sel_nivel +
        "&parametro2=" + sel_codigo_ff +
        "&parametro3=" + sel_numero_caja +
        "&parametro4=" + sel_consolidado +
        "&parametro5=" + codigo_guia_actual;
    $.get(url, function(data) {
        $("#id_dialog_selector_guias").html(data);
        $("#id_dialog_selector_guias").dialog({
            modal: true,
            width: 560,
            title: titulo,
            dialogClass: 'myTitleClass',
            buttons: [{
                text: "CERRAR",
                class: 'cancelButton',
                click: function() { $(this).dialog("close"); }
            }]
        });
        sel_guia_armar_filtros();
    });
}

// Flatpickr y Select2 de la seccion de transferir. dropdownParent apunta al
// dialogo: si no, el desplegable del Select2 queda detras del modal.
function sel_guia_armar_filtros() {
    if ($("#id_sel_rango_transferir").length == 0)
        return;
    sel_flat_rango = flatpickr("#id_sel_rango_transferir", {
        mode: "range",
        dateFormat: "Y-m-d",
        locale: "es",
        allowInput: false,
        onClose: function() { sel_guia_recargar_transferir(); }
    });
    $("#id_sel_marcacion_transferir").select2({
        width: '170px',
        dropdownParent: $("#id_dialog_selector_guias").closest(".ui-dialog"),
        minimumResultsForSearch: 3
    });
    $("#id_sel_marcacion_transferir").on('change', function() {
        sel_guia_recargar_transferir();
    });
}

// Recarga SOLO la lista de consolidados de la seccion de transferir.
function sel_guia_recargar_transferir() {
    var texto = $("#id_sel_rango_transferir").val();
    var fechas = (texto || "").match(/\d{4}-\d{2}-\d{2}/g);
    var desde = (fechas && fechas.length >= 1) ? fechas[0] : "";
    var hasta = (fechas && fechas.length >= 2) ? fechas[1] : desde;
    var marcacion = $("#id_sel_marcacion_transferir").val();
    if (!marcacion)
        marcacion = 0;

    var url = "funciones_ajax.php?funcion=render_transferir_consolidados_dsft" +
        "&parametro1=" + sel_nivel +
        "&parametro2=" + sel_codigo_ff +
        "&parametro3=" + sel_consolidado +
        "&parametro4=" + desde +
        "&parametro5=" + hasta +
        "&parametro6=" + marcacion;
    $.get(url, function(data) {
        $("#id_sel_lista_transferir").html(data);
    });
}

// ----- A) ASIGNAR una guia de ESTE consolidado. codigo_guia 0 = quitar. -----
function sel_guia_asignar(codigo_guia, numero_guia, texto_fecha) {
    $("#id_dialog_selector_guias").dialog("close");

    // El destino se congela aca: si se leyeran las globales al confirmar, un
    // clic en otro icono mientras viaja el AJAX las cambiaria.
    var d = {
        nivel: sel_nivel,
        codigo_ff: sel_codigo_ff,
        numero_caja: sel_numero_caja,
        consolidado: sel_consolidado,
        origen: sel_origen
    };

    if (d.nivel == "caja") {
        sel_guia_guardar_caja(d, codigo_guia, numero_guia);
        return;
    }

    var codigo = (d.nivel == "factura") ? d.codigo_ff : d.consolidado;
    var url = "funciones_ajax.php?funcion=render_confirma_guia_dsft" +
        "&parametro1=" + d.nivel +
        "&parametro2=" + codigo +
        "&parametro3=" + codigo_guia;
    $.get(url, function(texto) {
        $("#id_dialog_confirma_factura").html(texto);
        $("#id_dialog_confirma_factura").dialog({
            modal: true,
            width: 430,
            dialogClass: 'myTitleClass',
            buttons: [{
                    text: "SI",
                    class: 'cancelButton',
                    click: function() {
                        $(this).dialog("close");
                        sel_guia_guardar_masivo(d, codigo_guia, numero_guia);
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

function sel_guia_guardar_caja(d, codigo_guia, numero_guia) {
    var url = "funciones_ajax.php?funcion=asignar_guia_caja_dsft" +
        "&parametro1=" + d.codigo_ff +
        "&parametro2=" + d.numero_caja +
        "&parametro3=" + codigo_guia +
        "&parametro4=" + global_codigo_usuario;
    $.get(url, function(data) {
        if (data != "OK") {
            messageBox(data);
            return;
        }
        if (codigo_guia > 0)
            aviso_breve("Caja " + d.numero_caja + " asignada a la guía " + numero_guia, d.origen);
        else
            aviso_breve("Caja " + d.numero_caja + " sin guía", d.origen);
        sel_guia_refrescar({
            accion: "asignar",
            nivel: d.nivel,
            codigo_ff: d.codigo_ff,
            consolidado: d.consolidado
        });
    });
}

function sel_guia_guardar_masivo(d, codigo_guia, numero_guia) {
    if (d.nivel != "factura" && d.nivel != "consolidado")
        return;

    var url = "";
    if (d.nivel == "factura") {
        url = "funciones_ajax.php?funcion=asignar_guia_factura_dsft" +
            "&parametro1=" + d.codigo_ff +
            "&parametro2=" + codigo_guia +
            "&parametro3=" + global_codigo_usuario;
    } else {
        url = "funciones_ajax.php?funcion=asignar_guia_consolidado_cajas_dsft" +
            "&parametro1=" + d.consolidado +
            "&parametro2=" + codigo_guia +
            "&parametro3=" + global_codigo_usuario;
    }

    $("#id_espera").show();
    $.get(url, function(data) {
        $("#id_espera").hide();
        if (data != "OK") {
            messageBox(data);
            return;
        }
        if (d.nivel == "factura") {
            aviso_breve((codigo_guia > 0) ? "Factura asignada a la guía " + numero_guia :
                "Guía quitada de la factura", d.origen);
            sel_guia_refrescar({
                accion: "asignar",
                nivel: d.nivel,
                codigo_ff: d.codigo_ff,
                consolidado: d.consolidado
            });
            return;
        }
        aviso_breve((codigo_guia > 0) ? "Consolidado asignado a la guía " + numero_guia :
            "Guía quitada de todas las cajas", d.origen);
        sel_guia_refrescar({
            accion: "asignar",
            nivel: d.nivel,
            codigo_ff: d.codigo_ff,
            consolidado: d.consolidado
        });
    });
}

// ----- B) TRANSFERIR a otro consolidado -----
// Clic en el encabezado de un consolidado: hay que elegir que pasa con las guias.
function sel_guia_transferir_a(codigo_destino) {
    var d = {
        nivel: sel_nivel,
        codigo_ff: sel_codigo_ff,
        consolidado: sel_consolidado,
        origen: sel_origen
    };
    $("#id_dialog_selector_guias").dialog("close");

    var url = "funciones_ajax.php?funcion=render_confirma_transferencia_dsft" +
        "&parametro1=" + d.nivel +
        "&parametro2=" + d.codigo_ff +
        "&parametro3=" + d.consolidado +
        "&parametro4=" + codigo_destino +
        "&parametro5=0";
    $.get(url, function(texto) {
        $("#id_dialog_confirma_factura").html(texto);
        $("#id_dialog_confirma_factura").dialog({
            modal: true,
            width: 470,
            dialogClass: 'myTitleClass',
            buttons: [{
                    text: "CON LAS MISMAS GUÍAS",
                    class: 'cancelButton',
                    click: function() {
                        $(this).dialog("close");
                        sel_guia_ejecutar_transferencia(d, codigo_destino, "mismas", 0);
                    }
                },
                {
                    text: "SIN GUÍA",
                    class: 'cancelButton',
                    click: function() {
                        $(this).dialog("close");
                        sel_guia_ejecutar_transferencia(d, codigo_destino, "sin_guia", 0);
                    }
                },
                {
                    text: "CANCELAR",
                    click: function() { $(this).dialog("close"); }
                }
            ]
        });
    });
}

// Clic en una guia de otro consolidado: transferir y asignar esa guia.
function sel_guia_transferir_con_guia(codigo_destino, codigo_guia, numero_guia, texto_fecha) {
    var d = {
        nivel: sel_nivel,
        codigo_ff: sel_codigo_ff,
        consolidado: sel_consolidado,
        origen: sel_origen
    };
    $("#id_dialog_selector_guias").dialog("close");

    var url = "funciones_ajax.php?funcion=render_confirma_transferencia_dsft" +
        "&parametro1=" + d.nivel +
        "&parametro2=" + d.codigo_ff +
        "&parametro3=" + d.consolidado +
        "&parametro4=" + codigo_destino +
        "&parametro5=" + codigo_guia;
    $.get(url, function(texto) {
        $("#id_dialog_confirma_factura").html(texto);
        $("#id_dialog_confirma_factura").dialog({
            modal: true,
            width: 470,
            dialogClass: 'myTitleClass',
            buttons: [{
                    text: "SI",
                    class: 'cancelButton',
                    click: function() {
                        $(this).dialog("close");
                        sel_guia_ejecutar_transferencia(d, codigo_destino, "guia", codigo_guia);
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

function sel_guia_ejecutar_transferencia(d, codigo_destino, modo, codigo_guia) {
    var url = "";
    if (d.nivel == "factura") {
        url = "funciones_ajax.php?funcion=transferir_factura_dsft" +
            "&parametro1=" + d.codigo_ff +
            "&parametro2=" + codigo_destino +
            "&parametro3=" + modo +
            "&parametro4=" + codigo_guia +
            "&parametro5=" + global_codigo_usuario;
    } else {
        url = "funciones_ajax.php?funcion=transferir_consolidado_dsft" +
            "&parametro1=" + d.consolidado +
            "&parametro2=" + codigo_destino +
            "&parametro3=" + modo +
            "&parametro4=" + codigo_guia +
            "&parametro5=" + global_codigo_usuario;
    }

    $("#id_espera").show();
    $.get(url, function(data) {
        $("#id_espera").hide();
        if (data.substring(0, 2) != "OK") {
            messageBox(data);
            return;
        }
        var partes = data.split("|");
        var facturas = (partes.length > 1) ? parseInt(partes[1]) : 0;
        if (isNaN(facturas))
            facturas = 0;
        aviso_breve((facturas == 1) ? "Factura transferida al consolidado " + codigo_destino :
            facturas + " facturas transferidas al consolidado " + codigo_destino,
            d.origen);
        sel_guia_refrescar({
            accion: "transferir",
            nivel: d.nivel,
            codigo_ff: d.codigo_ff,
            consolidado: d.consolidado,
            destino: codigo_destino
        });
    });
}