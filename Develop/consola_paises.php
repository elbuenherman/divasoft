<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_paises = select_paises(NULL);
$total_paises = count($arreglo_paises)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<title>Divasoft - Consola de Países</title>
</head>
<script language="javascript">
function pone_numero_en_codigo(numero)
	{
	window.document.frmPais.txtCodigoPais.value = numero;
	document.location.href = "#inicio";
	}
function boton_nuevo(numero)
	{
	pone_numero_en_codigo(numero);
	window.document.frmPais.txtNombrePais.value ="";
	window.document.frmPais.txtNombrePais.focus();
	}
function devuelve_pais(codigo)
	{
	var url = "funciones_ajax.php?funcion=devuelve_pais&parametro1="+codigo;
	$.get(url, function(data, status){ window.document.frmPais.txtNombrePais.value = data;});	
	pone_numero_en_codigo(codigo);
	window.document.frmPais.txtNombrePais.focus();
	}
function ingresar_pais()
	{
	var txtNombrePais = window.document.frmPais.txtNombrePais.value;
	var txtCodigoPais = window.document.frmPais.txtCodigoPais.value;
	txtNombrePais = txtNombrePais.toUpperCase();
	window.document.frmPais.txtNombrePais.value = txtNombrePais;
	if(txtNombrePais.length<4)
		{
		window.alert("No se ingresa el registro por ser una palabra muy corta");
		}
	else
		{
		var url = "funciones_ajax.php?funcion=inserta_pais&parametro1="+txtNombrePais+"&parametro2="+txtCodigoPais;
		$.get(url, function(data, status)
			{
			var existe_pais = 0;
			existe_pais = data; 
			if(existe_pais=='0')
				{
				window.alert("El nombre del país ya existe");	
				}
			else
				{
				window.document.frmPais.submit();
				}
			});	
		}
	}
function elimina_pais(codigo)
	{
	var r = confirm("Está seguro que desea eliminar el país seleccionado");
	if (r == true) 
		{	
		var url = "funciones_ajax.php?funcion=elimina_pais&parametro1="+codigo;
		$.get(url, function(data, status)
			{ 
			if(data==0)
				{
				window.alert("No se puede eliminar el país\nEl país está siendo utilizado en otra entidad");	
				}
			if(data==1)
				{
				window.document.frmPais.submit();	
				}
			});	
		}
	}
</script>
<body class="metro">
    <a name="inicio"></a>
    <header class="bg-dark" data-load="barra_navegacion.php"></header>
    <div class="notice marker-on-right bg-darkRed fg-white" style="width: 150px; position: absolute; margin-top: 25px; margin-left:10px;">
    	<div class="">Puede ingresar, eliminar y modificar registros. No borre países si están siendo utilizados en otras entidades</div>
    </div>
<div class="herman" style="width: 450px; margin-top: 25px; margin-left:225px; position:absolute;">
    <legend><i class="icon-earth fg-crimson"></i> Listado de países</legend>
    <table class="table hovered">
    	<thead>
        	<tr>
            	<th class="text-left">Código</th>
                <th class="text-left">Nombre</th>
                <th class="text-center">Opciones</th>
            </tr>
         </thead>
         <tbody>
<?php
for($j=1;$j<=$total_paises;$j++)
	{
	$codigo_pais = $arreglo_paises[$j]['codigo_pais'];
	$nombre_pais = $arreglo_paises[$j]['nombre_pais'];
?>
         <tr>
         	<td><?php echo $codigo_pais;?></td>
            <td class="right"><?php echo $nombre_pais;?></td>
            <td class="text-center"><a href="javascript: devuelve_pais(<?php echo $codigo_pais; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_pais(<?php echo $codigo_pais; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
<?php
	}
?>
         </tr>
         </tbody>
		 <tfoot></tfoot>
	</table>
</div>

<div class="herman" style="width: 450px; margin-top: 25px; margin-left:685px; position:absolute;">
    <legend><i class="icon-plus fg-crimson"></i>  Ingreso/Modificación de países</legend>
<form name="frmPais" target="_self">
	<fieldset>
    	<label>Código del país</label>
        <div class="input-control text" data-role="input-control">
        	<input style="width: 100px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly" name="txtCodigoPais">
        </div>
        <label>Nombre del país</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtNombrePais" type="text" placeholder="Escriba el nombre del país" autofocus>
            <button class="btn-clear" tabindex="1"></button>
        </div>
		<div>
        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position:absolute; margin-left: 200px">Nuevo</a>
        <a href="javascript: ingresar_pais();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange">Ingresar</a>
        </div>
    </fieldset>
</form>
</div>
</body>
</html>
<?php
mysqli_close($link);
?>