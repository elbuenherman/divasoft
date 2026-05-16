<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_usuarios = select_usuarios(NULL);
$total_usuarios = count($arreglo_usuarios)
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<title>Divasoft - Consola de Usuarios</title>
</head>
<script language="javascript">
function pone_numero_en_codigo(numero)
	{
	window.document.frmUsuario.txtCodigoUsuario.value = numero;
	document.location.href = "#inicio";
	}
function boton_nuevo(numero)
	{
	pone_numero_en_codigo(numero);
	window.document.frmUsuario.txtApellidoUsuario.value ="";
	window.document.frmUsuario.txtNombreUsuario.value = "";
	window.document.frmUsuario.txtUsernameUsuario.value = "";
	window.document.frmUsuario.txtClaveUsuario.value = "";
	window.document.frmUsuario.txtFechaNacimientoUsuario.value = "";
	window.document.frmUsuario.txtApellidoUsuario.focus();
	}

function devuelve_usuario(codigo)
	{
	var url = "funciones_ajax.php?funcion=devuelve_usuario&parametro1="+codigo;
	var xmlDoc=loadXMLDoc(url);
	var x=xmlDoc.getElementsByTagName("apellido_usuario");
//	for (i=0;i<x.length;i++)
  	window.document.frmUsuario.txtApellidoUsuario.value = x[0].childNodes[0].nodeValue;	
	x=xmlDoc.getElementsByTagName("nombre_usuario");
	window.document.frmUsuario.txtNombreUsuario.value = x[0].childNodes[0].nodeValue;
	x=xmlDoc.getElementsByTagName("username_usuario");
	window.document.frmUsuario.txtUsernameUsuario.value = x[0].childNodes[0].nodeValue;
	x=xmlDoc.getElementsByTagName("clave_usuario");
	window.document.frmUsuario.txtClaveUsuario.value = x[0].childNodes[0].nodeValue;
	x=xmlDoc.getElementsByTagName("fecha_nacimiento_usuario");
	hola= x[0].childNodes[0].nodeValue;
	window.document.frmUsuario.txtFechaNacimientoUsuario.value = x[0].childNodes[0].nodeValue;
	pone_numero_en_codigo(codigo);
	window.document.frmUsuario.txtApellidoUsuario.focus();
	}
function ingresar_usuario()
	{
	var txtNombreUsuario = window.document.frmUsuario.txtNombreUsuario.value;
	var txtCodigoUsuario = window.document.frmUsuario.txtCodigoUsuario.value;
  	var txtApellidoUsuario = window.document.frmUsuario.txtApellidoUsuario.value;	
	var txtUsernameUsuario = window.document.frmUsuario.txtUsernameUsuario.value;
	var txtClaveUsuario = window.document.frmUsuario.txtClaveUsuario.value;
	var txtFechaNacimientoUsuario = window.document.frmUsuario.txtFechaNacimientoUsuario.value;

	txtNombreUsuario = txtNombreUsuario.toUpperCase();
	txtApellidoUsuario = txtApellidoUsuario.toUpperCase();
	
	window.document.frmUsuario.txtNombreUsuario.value = txtNombreUsuario;
	window.document.frmUsuario.txtApellidoUsuario.value = txtApellidoUsuario; 
	if(txtNombreUsuario.length<4||txtApellidoUsuario.length<4||txtUsernameUsuario.length<4||txtClaveUsuario.length<4||txtFechaNacimientoUsuario.length<4)
		{
		window.alert("Todos los campos son obligatorios y deben tener mas de 4 caracteres");
		}
	else
		{
		var url = "funciones_ajax.php?funcion=inserta_usuario&parametro1="+txtCodigoUsuario+"&parametro2="+txtNombreUsuario+"&parametro3="+txtApellidoUsuario+"&parametro4="+txtUsernameUsuario+"&parametro5="+txtClaveUsuario+"&parametro6="+txtFechaNacimientoUsuario;
		$.get(url, function(data, status)
			{
			var existe_usuario = 0;
			existe_usuario = data; 
			if(existe_usuario=='0')
				{
				window.alert("El nombre del usuario ya existe");	
				}
			else
				window.document.frmUsuario.submit();
			});	
		}
	}
function elimina_usuario(codigo)
	{
	var r = confirm("Está seguro que desea eliminar el usuario seleccionado");
	if (r == true) 
		{	
		var url = "funciones_ajax.php?funcion=elimina_usuario&parametro1="+codigo;
		$.get(url, function(data, status)
			{ 
			if(data==0)
				{
				window.alert("No se puede eliminar el usuario\nEl usuario está siendo utilizado en otra entidad");	
				}
			if(data==1)
				{
				window.document.frmUsuario.submit();	
				}
			});	
		}
	}
</script>
<body class="metro">
    <a name="inicio"></a>
    <header class="bg-dark" data-load="barra_navegacion.php"></header>
<div class="herman" style="width: 650px; margin-top: 25px; margin-left:20px; position:absolute;">
    <legend>Listado de usuarios</legend>
    <table class="table hovered">
    	<thead>
        	<tr>
            	<th class="text-left">Código</th>
                <th class="text-left">Apellido</th>
                <th class="text-left">Nombre</th>
                <th class="text-left">Username</th>
                <th class="text-center">Opciones</th>
            </tr>
         </thead>
         <tbody>
<?php
for($j=1;$j<=$total_usuarios;$j++)
	{
	$codigo_usuario = $arreglo_usuarios[$j]['codigo_usuario'];
	$nombre_usuario = $arreglo_usuarios[$j]['nombre_usuario'];
	$apellido_usuario = $arreglo_usuarios[$j]['apellido_usuario'];
	$username_usuario = $arreglo_usuarios[$j]['username_usuario'];
?>
         <tr>
         	<td><?php echo $codigo_usuario;?></td>
            <td class="right"><?php echo $apellido_usuario;?></td>
            <td class="right"><?php echo $nombre_usuario;?></td>
            <td class="right"><?php echo $username_usuario;?></td>
            <td class="text-center"><a href="javascript: devuelve_usuario(<?php echo $codigo_usuario; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_usuario(<?php echo $codigo_usuario; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
<?php
	}
?>
         </tr>
         </tbody>
		 <tfoot></tfoot>
	</table>
</div>
<div class="herman" style="width: 450px; margin-top: 25px; margin-left:685px; position:absolute;">
    <legend>Ingreso/Modificación de usuarios</legend>
<form name="frmUsuario" target="_self">
	<fieldset>
    	<label>Código del usuario</label>
        <div class="input-control text" data-role="input-control">
        	<input style="width: 100px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly" name="txtCodigoUsuario">
        </div>
        <label>Apellido del usuario</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtApellidoUsuario" type="text" placeholder="Escriba el apellido del usuario" autofocus>
            <button class="btn-clear" tabindex="1"></button>
        </div>
        <label>Nombre del usuario</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtNombreUsuario" type="text" placeholder="Escriba el nombre del usuario">
            <button class="btn-clear" tabindex="2"></button>
        </div>
        <label>Username</label>
        <div class="input-control text" data-role="input-control">
            <input name="txtUsernameUsuario" type="text" placeholder="Escriba el username del usuario">
            <button class="btn-clear" tabindex="3"></button>
        </div>
        <label>Clave</label>
        <div class="input-control password" data-role="input-control">
            <input name="txtClaveUsuario" type="password" placeholder="Escriba la clave del usuario" >
            <button class="btn-reveal" tabindex="4"></button>
        </div>
        <label>Fecha de nacimiento</label>
        <div class="input-control text" data-role="datepicker" data-format="yyyy-mm-dd" data-position="top">
            <input name="txtFechaNacimientoUsuario" type="text" placeholder="aaaa-mm-dd">
            <button class="btn-date" tabindex="5"></button>
        </div>
		<div>
        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position:absolute; margin-left: 200px">Nuevo</a>
        <a href="javascript: ingresar_usuario();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange">Ingresar</a>
        </div>
    </fieldset>
</form>
</div>
</body>
</html>
<?php
mysqli_close($link);
?>