<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<title>Divasoft - Búsqueda de productos por proveedor</title>
</head>
<script language="javascript">
function actualiza_proveedores()
    {
    var filtro_productos = document.getElementById("txtFiltroProductos").value;
    var filtro_paises = document.getElementById("txtFiltroPaises").value;
    var url = "funciones_ajax.php?funcion=actualiza_proveedores&parametro1="+filtro_productos+"&parametro2="+filtro_paises;
    $.get(url, function (data, status)
        {
        $(".herman2").html(data);
        });     
        
    }
function carga_filtros()
    {
    var url = "funciones_ajax.php?funcion=carga_productos_filtros";
    $.get(url, function (data, status)
        {
        $(".herman1").html(data);
        });  
    }
function check_filtro_producto(objeto,codigo)
    {
    var filtro = document.getElementById("txtFiltroProductos").value;
    var cadena = '<'+codigo+'>';
    if(objeto.checked == true)
        if(filtro.search(cadena)<0)
            filtro += cadena;
    if(objeto.checked == false)
        if(filtro.search(cadena)>=0)
            {
            var longitud_filtro = filtro.length;
            var longitud_cadena = cadena.length;
            var posicion_cadena = filtro.search(cadena);
            filtro = filtro.substring(0,posicion_cadena) + filtro.substring(posicion_cadena+longitud_cadena,longitud_filtro);
            }
    document.getElementById("txtFiltroProductos").value = filtro;
    actualiza_proveedores();
    }
function check_filtro_pais(objeto,codigo)
    {
    var filtro = document.getElementById("txtFiltroPaises").value;
    var cadena = '<'+codigo+'>';
    if(objeto.checked == true)
        if(filtro.search(cadena)<0)
            filtro += cadena;
    if(objeto.checked == false)
        if(filtro.search(cadena)>=0)
            {
            var longitud_filtro = filtro.length;
            var longitud_cadena = cadena.length;
            var posicion_cadena = filtro.search(cadena);
            filtro = filtro.substring(0,posicion_cadena) + filtro.substring(posicion_cadena+longitud_cadena,longitud_filtro);
            }
    document.getElementById("txtFiltroPaises").value = filtro;
    actualiza_proveedores();
    }
</script>
<body class="metro" onload="carga_filtros()">
<header class="bg-dark" data-load="barra_navegacion.php"></header>
<div class="herman1"></div>
<div class="herman2">
    <div class="herman" id="IdProductosProveedor" style="position: absolute; width: 350px; margin-top:10px; margin-left:920px;">
        <legend>Proveedores:</legend>
    </div>
</div>
</div><i class="icon-clipboard-2" style="position: absolute; background: darkRed; color: white; padding: 10px; border-radius: 50%; margin-top:35px; margin-left:930px;"></i>
<input style="position: absolute;" type="hidden" name="txtFiltroProductos" value="" id="txtFiltroProductos"></input>
<input style="position: absolute;" type="hidden" name="txtFiltroPaises" value="<1>" id="txtFiltroPaises"></input>
</body>
</html>