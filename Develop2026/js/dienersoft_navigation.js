/* 
   ============================================================================
   dienersoft_navigation.js
   ----------------------------------------------------------------------------
   Reemplazo nativo en jQuery 4 de los widgets de Metro UI utilizados por la 
   barra de navegacion del sistema:
     - data-role="dropdown"  => menus desplegables (click para abrir/cerrar)
     - data-load="archivo"   => carga AJAX de un archivo en el contenedor
     - .pull-menu            => boton hamburguesa para responsive
   
   Adicionalmente, marca el tooltip del icono Home con " | JQV4" para 
   indicar que el modulo actual fue migrado a jQuery 4.0.0 (esta libreria 
   solo se carga en modulos migrados, asi que solo aparece ahi).
   
   USA EVENT DELEGATION en el document para que los handlers sobrevivan a 
   cualquier re-render del DOM (AJAX, .html(), etc.). Asi nunca se pierden.
   
   No depende de Metro UI. Funciona con jQuery 4.0.0 + Migrate 4.0.2.
   El HTML existente NO se modifica. Esta libreria detecta automaticamente
   los atributos y los activa.
   
   IMPORTANTE: Este archivo se carga DESPUES de jQuery y Migrate.
   ============================================================================
*/

(function($) {
    "use strict";

    /*
       ------------------------------------------------------------------------
       MODULO 1: DATA-LOAD
       Reemplaza el data-load de Metro UI para cargar archivos via AJAX.
       Uso en HTML: <header data-load="barra_navegacion.php"></header>
       ------------------------------------------------------------------------
    */
    function inicializa_data_load() {
        $("[data-load]").each(function() {
            var contenedor = $(this);
            var archivo = contenedor.attr("data-load");
            if (archivo && archivo.length > 0) {
                contenedor.load(archivo, function(respuesta, estado, xhr) {
                    if (estado === "success") {
                        // Marcamos el tooltip del Home como modulo migrado JQV4
                        marca_tooltip_jqv4_en_contenedor(contenedor);
                        // Aseguramos que los menus dropdown empiecen cerrados
                        contenedor.find('[data-role="dropdown"]').css("display", "none");
                    }
                    if (estado === "error") {
                        console.error("dienersoft_navigation: error al cargar " + archivo + " - " + xhr.status + " " + xhr.statusText);
                    }
                });
            }
        });
    }

    /*
       ------------------------------------------------------------------------
       MARCA JQV4 EN EL TOOLTIP DE HOME
       Como esta libreria SOLO se carga via css_v4.php (modulos migrados),
       agregamos " | JQV4" al tooltip del icono Home para indicarlo.
       ------------------------------------------------------------------------
    */
    function marca_tooltip_jqv4_en_contenedor(contenedor) {
        var enlace_home = contenedor.find('a.element[href="home.php"]').first();
        if (enlace_home.length > 0) {
            var tooltip_actual = enlace_home.attr("title");
            if (tooltip_actual && tooltip_actual.indexOf("JQV4") === -1) {
                enlace_home.attr("title", tooltip_actual + " | JQV4");
            }
        }
    }

    /*
       ------------------------------------------------------------------------
       MODULO 2: DROPDOWNS - EVENT DELEGATION
       
       En vez de enganchar el click a CADA .dropdown-toggle (que se pierde si 
       el HTML se reemplaza), usamos delegation en el document.
       
       Beneficio: el handler funciona para CUALQUIER .dropdown-toggle que 
       exista o que aparezca en el futuro, sin tener que re-inicializar.
       ------------------------------------------------------------------------
    */
    function activa_dropdowns_con_delegacion() {
        // Quitamos handlers viejos por si acaso (idempotente)
        $(document).off("click.dft_dropdown_toggle");

        // Engancha al document, filtrado por .dropdown-toggle
        $(document).on("click.dft_dropdown_toggle", "a.dropdown-toggle", function(e) {
            // Buscamos el menu dropdown asociado a este toggle
            var elemento_toggle = $(this);
            var menu_dropdown = elemento_toggle.next('[data-role="dropdown"]');

            // Si no esta como hermano siguiente, buscamos como hijo del padre
            if (menu_dropdown.length === 0) {
                menu_dropdown = elemento_toggle.parent().children('[data-role="dropdown"]').first();
            }

            // Si no hay menu dropdown asociado, dejamos que el navegador maneje el click
            if (menu_dropdown.length === 0) return;

            e.preventDefault();
            e.stopPropagation();

            var esta_abierto = menu_dropdown.is(":visible");

            // Cerramos todos los dropdowns que NO sean ancestros de este
            cierra_todos_los_dropdowns_excepto(menu_dropdown);

            // Toggle del menu
            if (esta_abierto)
                menu_dropdown.hide();
            else
                menu_dropdown.show();
        });
    }

    function cierra_todos_los_dropdowns_excepto(menu_que_se_mantiene) {
        var ancestros_del_menu = menu_que_se_mantiene.parents('[data-role="dropdown"]');

        $('[data-role="dropdown"]').each(function() {
            var menu_actual = $(this);
            // Si el menu actual no es ancestro del que se mantiene, lo cerramos
            if (menu_actual.is(menu_que_se_mantiene) === false && ancestros_del_menu.is(menu_actual) === false)
                menu_actual.hide();
        });
    }

    function cierra_todos_los_dropdowns() {
        $('[data-role="dropdown"]').hide();
    }

    /*
       ------------------------------------------------------------------------
       MODULO 3: PULL-MENU - EVENT DELEGATION
       Boton hamburguesa para responsive.
       ------------------------------------------------------------------------
    */
    function activa_pull_menu_con_delegacion() {
        $(document).off("click.dft_pullmenu");

        $(document).on("click.dft_pullmenu", ".pull-menu", function(e) {
            var boton_hamburguesa = $(this);
            var menu_principal = boton_hamburguesa.next(".element-menu");

            if (menu_principal.length === 0) {
                menu_principal = boton_hamburguesa.siblings(".element-menu").first();
            }

            if (menu_principal.length === 0) return;

            e.preventDefault();
            e.stopPropagation();
            menu_principal.toggle();
        });
    }

    /*
       ------------------------------------------------------------------------
       EVENTO GLOBAL: cerrar dropdowns al hacer click fuera
       ------------------------------------------------------------------------
    */
    function activa_cierre_por_click_fuera() {
        $(document).off("click.dft_navigation_close");
        $(document).on("click.dft_navigation_close", function(e) {
            var click_dentro_de_dropdown = $(e.target).closest('[data-role="dropdown"], .dropdown-toggle').length > 0;
            if (click_dentro_de_dropdown === false)
                cierra_todos_los_dropdowns();
        });
    }

    /*
       ------------------------------------------------------------------------
       INICIALIZACION GENERAL
       Se ejecuta cuando el DOM esta listo.
       ------------------------------------------------------------------------
    */
    $(function() {
        // 1. Activamos los handlers delegados (sobreviven a re-renders)
        activa_dropdowns_con_delegacion();
        activa_pull_menu_con_delegacion();
        activa_cierre_por_click_fuera();

        // 2. Procesamos los data-load (carga la barra de navegacion)
        inicializa_data_load();
    });

    /*
       ------------------------------------------------------------------------
       API PUBLICA
       Por compatibilidad con cualquier codigo que pudiera llamar recargar().
       Con event delegation ya NO es necesario, pero la dejamos disponible.
       ------------------------------------------------------------------------
    */
    window.dienersoft_navigation = {
        recargar: function(contenedor) {
            // Con event delegation, los handlers ya estan activos.
            // Solo aseguramos que los dropdowns esten cerrados al inicio.
            if (!contenedor) contenedor = $("body");
            contenedor.find('[data-role="dropdown"]').css("display", "none");
        },
        cerrar_dropdowns: cierra_todos_los_dropdowns
    };

})(jQuery);