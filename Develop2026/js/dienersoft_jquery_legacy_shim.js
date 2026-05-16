/* 
   ============================================================================
   dienersoft_jquery_legacy_shim.js
   ----------------------------------------------------------------------------
   Restaura APIs de jQuery / jQuery UI deprecadas o removidas en versiones 
   modernas, para mantener compatibilidad con codigo legacy del sistema 
   sin tener que tocar 91+ archivos.
   
   Lo que restaura:
   
   1. .success() .error() .complete() en jqXHR
      Eliminados en jQuery 3.0.
   
   2. dialogClass: 'xxx' en jQuery UI dialog
      Deprecado en jQuery UI 1.12, removido en 1.13+.
      Lo redirigimos al sistema nuevo de classes: { "ui-dialog": "xxx" }.
   
   3. Botones de dialog que usan el patron viejo:
         open: function() { $(this).addClass('cancelButton'); }
      En jQuery UI 1.14 ese $(this) ya no apunta al boton.
      Aplicamos la clase automaticamente al renderizar los botones.
   
   IMPORTANTE: Este archivo se carga DESPUES de jQuery 4 + Migrate + jQuery UI.
   ============================================================================
*/

(function($) {
    "use strict";

    /* ========================================================================
       SECCION 1: SHIM PARA .success() .error() .complete()
       ======================================================================== */

    function agrega_metodos_legacy_a_jqxhr(jqxhr) {
        if (!jqxhr) return jqxhr;

        if (typeof jqxhr.success !== "function") {
            jqxhr.success = function(callback) {
                return jqxhr.done(callback);
            };
        }

        if (typeof jqxhr.error !== "function") {
            jqxhr.error = function(callback) {
                return jqxhr.fail(callback);
            };
        }

        if (typeof jqxhr.complete !== "function") {
            jqxhr.complete = function(callback) {
                return jqxhr.always(callback);
            };
        }

        return jqxhr;
    }

    var ajax_original = $.ajax;
    var get_original = $.get;
    var post_original = $.post;
    var get_json_original = $.getJSON;

    $.ajax = function() {
        var resultado = ajax_original.apply(this, arguments);
        return agrega_metodos_legacy_a_jqxhr(resultado);
    };

    $.get = function() {
        var resultado = get_original.apply(this, arguments);
        return agrega_metodos_legacy_a_jqxhr(resultado);
    };

    $.post = function() {
        var resultado = post_original.apply(this, arguments);
        return agrega_metodos_legacy_a_jqxhr(resultado);
    };

    $.getJSON = function() {
        var resultado = get_json_original.apply(this, arguments);
        return agrega_metodos_legacy_a_jqxhr(resultado);
    };

    if ($.Deferred) {
        var deferred_original = $.Deferred;
        $.Deferred = function(funcion_inicializacion) {
            var resultado = deferred_original.apply(this, arguments);
            agrega_metodos_legacy_a_jqxhr(resultado);
            return resultado;
        };
        $.extend($.Deferred, deferred_original);
    }

    /* ========================================================================
       SECCION 2 + 3: SHIM PARA jQuery UI dialog
       
       Hace 2 cosas en una sola interceptacion:
       
       A) Si tiene dialogClass: 'xxx', lo traduce a classes: { "ui-dialog": "xxx" }
          (la API nueva de jQuery UI 1.12+).
       
       B) Si los botones usan el patron viejo open: function() { addClass(...) },
          extrae la clase deseada y la aplica directamente al boton via la 
          propiedad 'class' que SI funciona en jQuery UI 1.14.
       ======================================================================== */

    if ($.fn && $.fn.dialog) {
        var dialog_original = $.fn.dialog;

        $.fn.dialog = function(opciones_o_metodo) {
            // Solo procesamos cuando se pasa un objeto de opciones
            // (no cuando se llama metodo: $().dialog("open"), $().dialog("close"), etc.)
            if (typeof opciones_o_metodo === "object" && opciones_o_metodo !== null) {

                // ---- A) Traducir dialogClass -> classes ----
                if (opciones_o_metodo.dialogClass) {
                    if (!opciones_o_metodo.classes)
                        opciones_o_metodo.classes = {};

                    if (opciones_o_metodo.classes["ui-dialog"])
                        opciones_o_metodo.classes["ui-dialog"] += " " + opciones_o_metodo.dialogClass;
                    else
                        opciones_o_metodo.classes["ui-dialog"] = opciones_o_metodo.dialogClass;

                    delete opciones_o_metodo.dialogClass;
                }

                // ---- B) Auto-fix de botones con open: function() { addClass(...) } ----
                if (opciones_o_metodo.buttons && Array.isArray(opciones_o_metodo.buttons)) {
                    for (var indice_boton = 0; indice_boton < opciones_o_metodo.buttons.length; indice_boton++) {
                        var boton = opciones_o_metodo.buttons[indice_boton];

                        // Si el boton tiene una funcion open con addClass, extraemos la clase
                        if (boton && typeof boton === "object" && typeof boton.open === "function") {
                            var codigo_funcion_open = boton.open.toString();
                            // Buscamos addClass('algo') o addClass("algo")
                            var match = codigo_funcion_open.match(/addClass\s*\(\s*['"]([^'"]+)['"]\s*\)/);
                            if (match && match[1]) {
                                var clase_extraida = match[1];

                                // Si ya hay class, concatenamos. Si no, asignamos.
                                if (boton["class"])
                                    boton["class"] += " " + clase_extraida;
                                else
                                    boton["class"] = clase_extraida;

                                // Eliminamos el open viejo para que jQuery UI no se queje
                                delete boton.open;
                            }
                        }

                        // Tambien limpiamos addClass dentro del click si solo tiene eso
                        // (es decoracion redundante que ya no necesitamos)
                        if (boton && typeof boton === "object" && typeof boton.click === "function") {
                            // No tocamos el click, solo lo dejamos como esta.
                            // El click puede tener logica importante ademas del addClass.
                        }
                    }
                }
            }

            return dialog_original.apply(this, arguments);
        };

        // Conservamos las propiedades del metodo original
        $.extend($.fn.dialog, dialog_original);
    }

})(jQuery);