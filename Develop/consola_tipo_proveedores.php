<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_tipo_proveedor(NULL);
$total_registros = count($arreglo_registros)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<title>Divasoft - Consola de Tipo Proveedores</title>
</head>
<script language="javascript">
function pone_numero_en_codigo(numero)
	{
	window.document.frmPrincipal.txtCodigoTipoProveedor.value = numero;
	document.location.href = "#inicio";
	}
function boton_nuevo(numero)
	{
	pone_numero_en_codigo(numero);
	window.document.frmPrincipal.txtCodigoTipoProveedor.value ="0";
	window.document.frmPrincipal.txtNombreTipoProveedor.value = "";
	window.document.frmPrincipal.txtNombreTipoProveedor.focus();
	}

function devuelve_registro(codigo)
	{		
	var url = "funciones_ajax.php?funcion=devuelve_tipo_proveedor&parametro1="+codigo;
	var xmlDoc=loadXMLDoc(url);
	var x=xmlDoc.getElementsByTagName("nombre_tipo_proveedor");
//	for (i=0;i<x.length;i++)
  	window.document.frmPrincipal.txtNombreTipoProveedor.value = x[0].childNodes[0].nodeValue;	
	x=xmlDoc.getElementsByTagName("codigo_tipo_proveedor");
	var codigo_pais = x[0].childNodes[0].nodeValue;
	pone_numero_en_codigo(codigo);
	window.document.frmPrincipal.txtNombreTipoProveedor.focus();
	}
function ingresar_registro()
	{
	var txtCodigoTipoProveedor = window.document.frmPrincipal.txtCodigoTipoProveedor.value;
  	var txtNombreTipoProveedor = window.document.frmPrincipal.txtNombreTipoProveedor.value;	
	txtNombreTipoProveedor = txtNombreTipoProveedor.toUpperCase();
	
	if(txtNombreTipoProveedor.length<4)
		{
		window.alert("El nombre del tipo de proveedor debe tener mas de 4 caracteres");
		}
	else
		{
		var url = "funciones_ajax.php?funcion=inserta_tipo_proveedor&parametro1="+txtCodigoTipoProveedor+"&parametro2="+txtNombreTipoProveedor;
		$.get(url, function(data, status)
			{
			var existe_registro = 0;
			existe_registro = data; 
			if(existe_registro=='0')
				{
				window.alert("El nombre del tipo de proveedor ya existe");	
				}
			else
				window.document.frmPrincipal.submit();
			});	
		}
	}
function elimina_registro(codigo)
	{
	var r = confirm("Está seguro que desea eliminar el tipo de proveedor seleccionado");
	if (r == true) 
		{	
		var url = "funciones_ajax.php?funcion=elimina_tipo_proveedor&parametro1="+codigo;
		$.get(url, function(data, status)
			{ 
			if(data==0)
				{
				window.alert("No se puede eliminar el tipo de proveedor\nEl mismo está siendo utilizado en otra entidad");	
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
    <legend><i class="icon-grid-view fg-crimson"></i> Listado de Tipo de Proveedores</legend>
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
for($j=1;$j<=$total_registros;$j++)
	{
	$codigo_tipo_proveedor = $arreglo_registros[$j]['codigo_tipo_proveedor'];
	$nombre_tipo_proveedor = $arreglo_registros[$j]['nombre_tipo_proveedor'];
?>
         <tr>
         	<td><?php echo $codigo_tipo_proveedor;?></td>
            <td class="right"><?php echo $nombre_tipo_proveedor;?></td>
            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_tipo_proveedor; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_registro(<?php echo $codigo_tipo_proveedor; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
<?php
	}
?>
         </tr>
         </tbody>
		 <tfoot></tfoot>
	</table>
</div>
<div class="herman" style="width: 450px; margin-top: 25px; margin-left:685px; position:absolute;">
    <legend><i class="icon-plus fg-crimson"></i> Ingreso / Modificación Tipo de Proveedores</legend>
<form name="frmPrincipal" target="_self">
	<fieldset>
    	<label>Código del Tipo de Proveedor</label>
        <div class="input-control text" data-role="input-control">
        	<input style="width: 100px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly" name="txtCodigoTipoProveedor">
        </div>
        <label>Nombre del Tipo de Proveedor</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtNombreTipoProveedor" type="text" placeholder="Escriba el nombre del Tipo de Proveedor" autofocus>
            <button class="btn-clear" tabindex="1"></button>
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