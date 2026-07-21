<?php
/* 
   ============================================================================
   css_v4.php
   ----------------------------------------------------------------------------
   Inclusion de librerias para modulos MIGRADOS a jQuery 4.0.0.
   
   Proyecto: DIVASOFT (PHP 8.x, MySQLi)
   Se mantiene en paralelo con css.php (legacy con jQuery 1.x).
   Cada modulo elige cual incluir:
     - Modulos legacy:    include("css.php");
     - Modulos migrados:  include("css_v4.php");
   
   Estrategia: minimizar cambios en codigo legacy.
     - Se mantiene TODO el CSS de Metro UI (clases, colores, iconos)
     - Se elimina SOLO el JS de Metro UI (load-metro.js)
     - Se reemplaza con dienersoft_navigation.js (dropdown + data-load)
     - Se agrega dienersoft_jquery_legacy_shim.js para que .success/.error/
       .complete sigan funcionando sin tocar el codigo legacy
   ============================================================================
*/
$url = ""; 
?>
    <meta charset="utf-8">
    
    <!--GLOBALES-->
    <link href="images/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
    <?php $titulo_hoja = "DIVASOFT"; ?>

    <!-- ===== CSS de Metro UI: SE MANTIENE sin cambios ===== -->
    <link href="<?php echo $url;?>css/metro-bootstrap.css" rel="stylesheet">
    <link href="<?php echo $url;?>css/metro-bootstrap-responsive.css" rel="stylesheet">
    <link href="<?php echo $url;?>css/iconFont.css" rel="stylesheet">
    <link href="<?php echo $url;?>css/docs.css" rel="stylesheet">
    <link href="<?php echo $url;?>js/prettify/prettify.css" rel="stylesheet">
    <link href="<?php echo $url;?>css/aw_estilo.css" rel="stylesheet">

    <!-- ===== jQuery 4.0.0 (renombrado con prefijo dsft + sufijo v4) ===== -->
    <script src="<?php echo $url;?>js/jquery/dsft-js/dsft-js-jquery-v4.min.js"></script>
    
    <!-- ===== jQuery Migrate 4.0.2 (deteccion de codigo legacy) ===== -->
    <script src="<?php echo $url;?>js/jquery/dsft-js/dsft-js-jquery-v4-migrate.min.js"></script>
    
    <!-- ===== Shim Dienersoft: restaura .success() .error() .complete() ===== -->
    
    <!-- ===== jQuery UI 1.14.2 completo (widget, dialog, datepicker, etc.) ===== -->
    <script src="<?php echo $url;?>js/jquery/dsft-js/dsft-js-jquery-v4-ui.min.js"></script>
    <!-- Se carga DESPUES de jQuery + Migrate, pero ANTES de cualquier otro JS -->
    <script src="<?php echo $url;?>js/dienersoft_jquery_legacy_shim.js"></script>
    
    
    <!-- ===== Plugins jQuery legacy (compatibles con jQuery 4) ===== -->
    <script src="<?php echo $url;?>js/jquery/dsft-js/dsft-js-jquery.mousewheel.js"></script>
    <script src="<?php echo $url;?>js/jquery/dsft-js/dsft-js-jquery.dataTables.js"></script>
    
    <!-- 
        ===== REEMPLAZO DE METRO UI JS =====
        NO se carga load-metro.js (eliminado).
        En su lugar, dienersoft_navigation.js replica el comportamiento de:
          - data-role="dropdown" (menus de la barra de navegacion)
          - data-load (carga AJAX de barra_navegacion.php)
          - .pull-menu (boton hamburguesa responsive)
        
        NOTA: data-role="input-control" NO requiere reemplazo JS, ya que en
        este sistema se usa solo decorativamente (el CSS de Metro UI lo maneja).
        
        NOTA: data-role="datepicker" tampoco se reemplaza globalmente. Cada
        consola migrada inicializa Flatpickr sobre sus campos de fecha.
    -->
    <script src="<?php echo $url;?>js/dienersoft_navigation.js"></script>

    <!-- ===== JavaScript local del sistema (sin cambios) ===== -->
    <!-- <script src="<?php echo $url;?>js/docs.js"></script> -->
    <script src="<?php echo $url;?>js/github.info.js"></script>
    <script language="javascript" src="<?php echo $url;?>ajax/ajax.js"></script>
    <link rel="stylesheet" href="<?php echo $url;?>flatpickr/material_red.css">
    <link rel="stylesheet" href="<?php echo $url;?>css/dienersoft_flatpickr.css">
    <link rel="stylesheet" href="<?php echo $url;?>css/dienersoft_comun.css">
    <script src="<?php echo $url;?>flatpickr/flatpickr.js"></script>
    <script src="<?php echo $url;?>flatpickr/es.js"></script>

    <!-- ===== Barra de navegacion fija arriba (fixed) en todo el sistema ===== -->
    <style>
    /* Con position:sticky la barra se despegaba al bajar mucho (algun ancestro con
       overflow/altura acotada rompe el sticky). Con fixed queda anclada a la
       VENTANA y nunca se despega. La barra mide 45px (Metro: content height 45px),
       asi que compensamos con padding-top en el body para no tapar el contenido.
       z-index 90: por encima de los grids (thead sticky ~5) y del contenido, pero
       por debajo de los dialogs jQuery UI (~100), Select2 (~1051) y Flatpickr, para
       que el overlay/dialog modal siga cubriendo la barra. */
    header.bg-dark {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 90;
        }
    body.metro {
        padding-top: 45px;
        }
    </style>
<?php
?>