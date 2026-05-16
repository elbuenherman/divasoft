<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);


$arreglo_registros = select_ciudades(NULL);
$total_registros = count($arreglo_registros)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<title>Divasoft - Consola de Ciudades</title>
</head>
<script language="javascript">
function pone_numero_en_codigo(numero)
	{
	window.document.frmPrincipal.txtCodigoCiudad.value = numero;
	document.location.href = "#inicio";
	}
function boton_nuevo(numero)
	{
	pone_numero_en_codigo(numero);
	window.document.frmPrincipal.txtCodigoCiudad.value ="0";
	window.document.frmPrincipal.txtNombreCiudad.value = "";
	window.document.frmPrincipal.txtCodigoPais.selectedIndex = 2
	window.document.frmPrincipal.txtNombreCiudad.focus();
	}

function devuelve_registro(codigo)
	{		
	var url = "funciones_ajax.php?funcion=devuelve_ciudad&parametro1="+codigo;
	var xmlDoc=loadXMLDoc(url);
	var x=xmlDoc.getElementsByTagName("nombre_ciudad");
//	for (i=0;i<x.length;i++)
  	window.document.frmPrincipal.txtNombreCiudad.value = x[0].childNodes[0].nodeValue;	
	x=xmlDoc.getElementsByTagName("codigo_pais");
	var codigo_pais = x[0].childNodes[0].nodeValue;
	var objeto = window.document.frmPrincipal.txtCodigoPais;
	pone_opcion_en_select_x_codigo(codigo_pais,objeto);
	pone_numero_en_codigo(codigo);
	window.document.frmPrincipal.txtNombreCiudad.focus();
	}
function ingresar_registro()
	{
	var Objeto = window.document.frmPrincipal.txtCodigoPais;
	var txtCodigoCiudad = window.document.frmPrincipal.txtCodigoCiudad.value;
  	var txtNombreCiudad = window.document.frmPrincipal.txtNombreCiudad.value;	
	var indice = Objeto.selectedIndex;
	var txtCodigoPais = Objeto.options[indice].value;
	txtNombreCiudad = txtNombreCiudad.toUpperCase();
	
	if(txtNombreCiudad.length<4)
		{
		window.alert("El nombre de la ciudad debe tener mas de 4 caracteres");
		}
	else
		{
		var url = "funciones_ajax.php?funcion=inserta_ciudad&parametro1="+txtCodigoCiudad+"&parametro2="+txtNombreCiudad+"&parametro3="+txtCodigoPais;
		$.get(url, function(data, status)
			{
			var existe_registro = 0;
			existe_registro = data; 
			if(existe_registro=='0')
				{
				window.alert("El nombre de la ciudad ya existe");	
				}
			else
				window.document.frmPrincipal.submit();
			});	
		}
	}
function elimina_registro(codigo)
	{
	var r = confirm("Está seguro que desea eliminar la ciudad seleccionada");
	if (r == true) 
		{	
		var url = "funciones_ajax.php?funcion=elimina_ciudad&parametro1="+codigo;
		$.get(url, function(data, status)
			{ 
			if(data==0)
				{
				window.alert("No se puede eliminar la ciudad\nEl usuario está siendo utilizado en otra entidad");	
				}
			if(data==1)
				{
				window.document.frmPrincipal.submit();	
				}
			});	
		}
	}
</script>
<body class="metro">
    <a name="inicio"></a>
    <header class="bg-dark" data-load="barra_navegacion.php"></header>
<div class="herman" style="width: 650px; margin-top: 25px; margin-left:20px; position:absolute;">
    <legend><i class="icon-direction fg-crimson"></i> Listado de ciudades</legend>
    <table class="table hovered">
    	<thead>
        	<tr>
            	<th class="text-left">Código</th>
                <th class="text-left">Nombre</th>
                <th class="text-left">País</th>
                <th class="text-center">Opciones</th>
            </tr>
         </thead>
         <tbody>
<?php
for($j=1;$j<=$total_registros;$j++)
	{
	$codigo_ciudad = $arreglo_registros[$j]['codigo_ciudad'];
	$nombre_ciudad = $arreglo_registros[$j]['nombre_ciudad'];
	$codigo_pais = $arreglo_registros[$j]['codigo_pais'];
	$nombre_pais = $arreglo_registros[$j]['nombre_pais'];
?>
         <tr>
         	<td><?php echo $codigo_ciudad;?></td>
            <td class="right"><?php echo $nombre_ciudad;?></td>
            <td class="right"><?php echo $nombre_pais;?></td>
            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_ciudad; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_registro(<?php echo $codigo_ciudad; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
<?php
	}
?>
         </tr>
         </tbody>
		 <tfoot></tfoot>
	</table>
</div>
<div class="herman" style="width: 450px; margin-top: 25px; margin-left:685px; position:absolute;">
    <legend><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de ciudades</legend>
<form name="frmPrincipal" target="_self">
	<fieldset>
    	<label>Código de la ciudad</label>
        <div class="input-control text" data-role="input-control">
        	<input style="width: 100px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly" name="txtCodigoCiudad">
        </div>
        <label>Nombre de la ciudad</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtNombreCiudad" type="text" placeholder="Escriba el nombre de la ciudad" autofocus>
            <button class="btn-clear" tabindex="1"></button>
        </div>
        <label>País</label>
		<div class="input-control select">
   		 	<select name="txtCodigoPais">
<?php
$arreglo_paises = select_paises(NULL);
$total_paises = count($arreglo_paises);
for($j=1;$j<=$total_paises;$j++)
	{
	$codigo_pais = $arreglo_paises[$j]['codigo_pais'];
	$nombre_pais = $arreglo_paises[$j]['nombre_pais'];

?>
    		    <option value="<?php echo $codigo_pais;?>"><?php echo $nombre_pais;?></option>
<?php
	}
?>
    		</select>
        </div>
		<div>
        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position:absolute; margin-left: 200px">Nuevo</a>
        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange">Ingresar</a>
        </div>
    </fieldset>
</form>
</div>
</body>
</html>
<?php
mysqli_close($link);
?>