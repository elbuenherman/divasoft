<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_proveedor(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$ultimo_indice = 12;
$barra_separacion = ["X"=>"585","Y"=>$campos[$ultimo_indice]+130];
$flores_proveedor = ["X"=>$barra_separacion["X"],"Y"=>$barra_separacion["Y"]+55,"H"=>500];
$tamano_texto = 11;
$onload = "";
if (isset($_REQUEST['parametro1'])) 
    {
    $parametro1 = $_REQUEST['parametro1'];
    $onload = "javascript: devuelve_registro(".$parametro1.");";
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <?php
        include("css.php");
        ?>
        <title>Divasoft - Consola de Proveedores</title>
    </head>
    <script language="javascript">
        function check_productos(objeto,codigo_producto)
            {
            var txtCodigoProveedor = window.document.frmPrincipal.txtCodigoProveedor.value;   
            var status  = 0;
            if(objeto.checked == true) status = 1;
            var url = "funciones_ajax.php?funcion=check_productos&parametro1=" + txtCodigoProveedor +"&parametro2=" + codigo_producto +"&parametro3=" + status;
            $.get(url, function (data, status){;});
            }
        function carga_productos()
            {
            var txtCodigoProveedor = window.document.frmPrincipal.txtCodigoProveedor.value;
            if(txtCodigoProveedor=="0")
                alert("Debe seleccionar un proveedor primero");
            else
                {
                var url = "funciones_ajax.php?funcion=carga_productos&parametro1=" + txtCodigoProveedor +"&parametro2=" + <?php echo $tamano_texto;?>+"&parametro3=" + <?php echo $flores_proveedor["Y"];?>+"&parametro4=" + <?php echo $flores_proveedor["X"];?>+"&parametro5=" + <?php echo $flores_proveedor["H"];?>;
                $.get(url, function (data, status)
                        {
                        $(".herman1").html(data);
                        });
                }
            }
        function borra_productos()
            {
            var data = "<div class='herman1'><div class='herman' id='IdProductosProveedor' style='position: absolute; width: 660px; margin-top: <?php echo $flores_proveedor["Y"];?>px; margin-left:<?php echo $flores_proveedor["X"];?>px;' height: <?php echo $flores_proveedor["H"];?>px;'><legend>Productos de la finca / Ciudades del Truck</legend></div></div>";
            $(".herman1").html(data);
            }
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoProveedor.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            borra_productos();
            pone_numero_en_codigo(numero);
            window.document.frmPrincipal.txtCodigoProveedor.value = "0";
            ensera_formulario();
            window.document.frmPrincipal.txtNombreProveedor.focus();
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.txtNombreProveedor.value = "";      
            pone_opcion_en_select_x_codigo(-1, window.document.frmPrincipal.txtCodigoTipoProveedor);           
            window.document.frmPrincipal.txtNombreComercialProveedor.value = "";     
            window.document.frmPrincipal.txtTelefonoProveedor.value = "";
            pone_opcion_en_select_x_codigo(7, window.document.frmPrincipal.txtCodigoBancoProveedor);
            pone_opcion_en_select_x_codigo(3, window.document.frmPrincipal.txtTipoCuentaBancariaProveedor);
            window.document.frmPrincipal.txtCuentaBancariaProveedor.value = "";
            window.document.frmPrincipal.txtNombreBeneficiarioProveedor.value = "";
            pone_opcion_en_select_x_codigo(3, window.document.frmPrincipal.txtTipoIdentificacionCuentaBancariaProveedor);
            window.document.frmPrincipal.txtIdentificacionCuentaBancariaProveedor.value = "";
            window.document.frmPrincipal.txtNombreVendedorProveedor.value = "";        
            window.document.frmPrincipal.txtEmailVendedorProveedor.value = "";
            window.document.frmPrincipal.txtTelefonoVendedorProveedor.value = "";
            window.document.frmPrincipal.txtSkypeVendedorProveedor.value = "";
            window.document.frmPrincipal.txtDireccionProveedor.value = "";
            pone_opcion_en_select_x_codigo(23, window.document.frmPrincipal.txtCodigoPaisProveedor);
            pone_opcion_en_select_x_codigo(17, window.document.frmPrincipal.txtCodigoCiudadProveedor);
            window.document.frmPrincipal.txtDireccionWebProveedor.value = "";
            window.document.frmPrincipal.txtObservacionesProveedor.value = "";
            window.document.frmPrincipal.txtEmailPagosProveedor.value = "";
            pone_numero_en_codigo(0);                

            }
        function devuelve_registro(codigo)
        {
            ensera_formulario();
            borra_productos();
            var url = "funciones_ajax.php?funcion=devuelve_proveedor&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreProveedor.value = x.nodeValue;      
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_tipo_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoTipoProveedor);           
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_comercial_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreComercialProveedor.value = x.nodeValue;       
            if((typeof(x = xmlDoc.getElementsByTagName("telefono_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTelefonoProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_banco_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoBancoProveedor);
            if((typeof(x = xmlDoc.getElementsByTagName("tipo_cuenta_banaria_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtTipoCuentaBancariaProveedor);
            if((typeof(x = xmlDoc.getElementsByTagName("cuenta_bancaria_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCuentaBancariaProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_beneficiario_cuenta_bancaria_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreBeneficiarioProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("tipo_identificacion_cuenta_bancaria_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtTipoIdentificacionCuentaBancariaProveedor);
            if((typeof(x = xmlDoc.getElementsByTagName("identificacion_cuenta_bancaria_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtIdentificacionCuentaBancariaProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_vendedor_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreVendedorProveedor.value = x.nodeValue;        
            if((typeof(x = xmlDoc.getElementsByTagName("email_vendedor_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtEmailVendedorProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("telefono_vendedor_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTelefonoVendedorProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("skype_vendedor_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtSkypeVendedorProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("direccion_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDireccionProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_pais_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoPaisProveedor);
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_ciudad_proveedor")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoCiudadProveedor);
            if((typeof(x = xmlDoc.getElementsByTagName("direccion_web_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDireccionWebProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("observaciones_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtObservacionesProveedor.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("email_pagos_proveedor")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtEmailPagosProveedor.value = x.nodeValue;
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombreProveedor.focus();
        }
        function ingresar_registro()
            {
            borra_productos();
            var txtNombreProveedor = window.document.frmPrincipal.txtNombreProveedor.value; txtNombreProveedor = escape(txtNombreProveedor.toUpperCase());
            var txtCodigoTipoProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoTipoProveedor);           
            var txtNombreComercialProveedor = window.document.frmPrincipal.txtNombreComercialProveedor.value; txtNombreComercialProveedor = escape(txtNombreComercialProveedor.toUpperCase());
            var txtTelefonoProveedor = window.document.frmPrincipal.txtTelefonoProveedor.value;
            var txtCodigoBancoProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoBancoProveedor);
            var txtTipoCuentaBancariaProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtTipoCuentaBancariaProveedor);
            var txtCuentaBancariaProveedor = window.document.frmPrincipal.txtCuentaBancariaProveedor.value;
            var txtNombreBeneficiarioProveedor = window.document.frmPrincipal.txtNombreBeneficiarioProveedor.value; txtNombreBeneficiarioProveedor.toUpperCase();
            var txtTipoIdentificacionCuentaBancariaProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtTipoIdentificacionCuentaBancariaProveedor);
            var txtIdentificacionCuentaBancariaProveedor = window.document.frmPrincipal.txtIdentificacionCuentaBancariaProveedor.value; txtIdentificacionCuentaBancariaProveedor.toUpperCase();
            var txtNombreVendedorProveedor = window.document.frmPrincipal.txtNombreVendedorProveedor.value; txtNombreVendedorProveedor.toUpperCase();
            var txtEmailVendedorProveedor = window.document.frmPrincipal.txtEmailVendedorProveedor.value;
            var txtTelefonoVendedorProveedor = window.document.frmPrincipal.txtTelefonoVendedorProveedor.value;
            var txtSkypeVendedorProveedor = window.document.frmPrincipal.txtSkypeVendedorProveedor.value;
            var txtDireccionProveedor = window.document.frmPrincipal.txtDireccionProveedor.value;
            var txtCodigoPaisProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoPaisProveedor);
            var txtCodigoCiudadProveedor = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoCiudadProveedor);
            var txtDireccionWebProveedor = escape(window.document.frmPrincipal.txtDireccionWebProveedor.value);
            var txtObservacionesProveedor = window.document.frmPrincipal.txtObservacionesProveedor.value;   
            var txtCodigoProveedor = window.document.frmPrincipal.txtCodigoProveedor.value;  
            var txtEmailPagosProveedor = window.document.frmPrincipal.txtEmailPagosProveedor.value; 

            if (txtNombreProveedor.length < 4)
                window.alert("El nombre del proveedor debe tener mas de 4 caracteres");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_proveedor&parametro1=" + txtNombreProveedor + "&parametro2=" + txtCodigoTipoProveedor + "&parametro3=" + txtNombreComercialProveedor + "&parametro4=" + txtTelefonoProveedor + "&parametro5=" + txtCodigoBancoProveedor + "&parametro6=" + txtTipoCuentaBancariaProveedor + "&parametro7=" + txtCuentaBancariaProveedor + "&parametro8=" + txtNombreBeneficiarioProveedor + "&parametro9=" + txtTipoIdentificacionCuentaBancariaProveedor + "&parametro10=" + txtIdentificacionCuentaBancariaProveedor + "&parametro11=" + txtNombreVendedorProveedor + "&parametro12=" + txtEmailVendedorProveedor + "&parametro13=" + txtTelefonoVendedorProveedor + "&parametro14=" + txtSkypeVendedorProveedor + "&parametro15=" + txtDireccionProveedor + "&parametro16=" + txtCodigoPaisProveedor + "&parametro17=" + txtCodigoCiudadProveedor + "&parametro18=" + txtDireccionWebProveedor + "&parametro19=" + txtObservacionesProveedor +"&parametro20="+txtCodigoProveedor + "&parametro21="+txtEmailPagosProveedor;
                $.get(url, function (data, status)
                    {
                    var existe_registro = 0;
                    existe_registro = data;
                    if (existe_registro == '0')
                        {
                        window.alert("El nombre del proveedor ya existe");
                        }
                    else
                        window.document.frmPrincipal.submit();
                    });
                }
            }
        function elimina_registro(codigo)
        {
            borra_productos();
            var r = confirm("Está seguro que desea eliminar el proveedor seleccionado");
            if (r == true)
            {
                var url = "funciones_ajax.php?funcion=elimina_proveedor&parametro1=" + codigo;
                $.get(url, function (data, status)
                {
                    if (data == 0)
                    {
                        window.alert("No se puede eliminar el proveedor\nEl mismo está siendo utilizado en otra entidad");
                    }
                    if (data == 1)
                    {
                        window.document.frmPrincipal.submit();
                    }
                });
            }
        }
    </script>
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div class="herman" style="width: 550px; margin-top: 25px; margin-left:20px; position:absolute;">
            <legend><i class="icon-forrst fg-crimson"></i> Listado de proveedores</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Nombre</th>
                        <th class="text-left">Nombre comercial</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_proveedor = $arreglo_registros[$j]['codigo_proveedor'];
                        $nombre_proveedor = $arreglo_registros[$j]['nombre_proveedor'];
                        $nombre_comercial_proveedor = $arreglo_registros[$j]['nombre_comercial_proveedor'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_comercial_proveedor; ?></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_proveedor; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_registro(<?php echo $codigo_proveedor; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px; height: <?php echo $campos[$ultimo_indice]+80;?>px; ">
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de Proveedores</legend>
            <form name="frmPrincipal" target="_self">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[1];?>px; margin-left:260px;">Nombre del proveedor</label>                    
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input style="width: 240px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly" name="txtCodigoProveedor">
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[1];?>px; margin-left:260px;">
                        <input name="txtNombreProveedor" tabindex="1" type="text" placeholder="Escriba el nombre del proveedor"autofocus>
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 2 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px;">Tipo proveedor</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px; margin-left:260px;">Nombre comercial del proveedor</label>                    
                    <div class="input-control select" style="position: absolute; width: 240px; margin-top: <?php echo $campos[2];?>px;">
                        <select name="txtCodigoTipoProveedor" tabindex="2">
                            <?php
                            $arreglo_tipo_proveedor = select_tipo_proveedor(NULL);
                            $total_tipo_proveedor = count($arreglo_tipo_proveedor);
                            for ($j = 1; $j <= $total_tipo_proveedor; $j++) 
                            {
                                $codigo_tipo_proveedor = $arreglo_tipo_proveedor[$j]['codigo_tipo_proveedor'];
                                $nombre_tipo_proveedor = $arreglo_tipo_proveedor[$j]['nombre_tipo_proveedor'];
                                ?>
                                <option value="<?php echo $codigo_tipo_proveedor; ?>"><?php echo $nombre_tipo_proveedor; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[2];?>px; margin-left:260px;">
                        <input name="txtNombreComercialProveedor" tabindex="3" type="text" placeholder="Escriba el nombre comercial del proveedor" >
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 3 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[3];?>px;">Teléfonos del proveedor</label>
                    <div  style="position: absolute; width: 550px; margin-top: <?php echo $campos[3];?>px;" class="input-control text" data-role="input-control">
                        <input name="txtTelefonoProveedor" tabindex="4" type="text" placeholder="Escriba los teléfonos del proveedor">
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 4 -->
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[4];?>px;">Dirección</label>
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[4];?>px; margin-left:260px;">País</label>
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[4];?>px; margin-left:410px;">Ciudad</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[4];?>px;">
                        <input name="txtDireccionProveedor" tabindex="5" type="text" placeholder="Escriba la dirección del proveedor">
                            <button class="btn-clear"></button>
                    </div>
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[4];?>px; margin-left:260px;" class="input-control select">
                        <select name="txtCodigoPaisProveedor" tabindex="6" style="width: 140px;">
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
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[4];?>px; margin-left:410px;" class="input-control select">
                        <select name="txtCodigoCiudadProveedor" tabindex="7" style="width: 140px;">
                        <?php
                        $arreglo_ciudades = select_ciudades(NULL);
                        $total_ciudades = count($arreglo_ciudades);
                        for($j=1;$j<=$total_ciudades;$j++)
                                {
                                $codigo_ciudad = $arreglo_ciudades[$j]['codigo_ciudad'];
                                $nombre_ciudad = $arreglo_ciudades[$j]['nombre_ciudad'];

                        ?>
                        <option value="<?php echo $codigo_ciudad;?>"><?php echo $nombre_ciudad;?></option>
                        <?php
                                }
                        ?>
                        </select>
                    </div>
<!-- Linea 5 -->
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[5];?>px;">Dirección WEB</label>
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[5];?>px; margin-left:260px;">Observaciones</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[5];?>px;">
                        <input name="txtDireccionWebProveedor" tabindex="8" type="text" placeholder="Escriba la dirección web">
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control textarea">
                        <textarea name="txtObservacionesProveedor" tabindex="9" style="resize:none; position: absolute; width: 290px; margin-top: <?php echo $campos[5];?>px; margin-left:260px; height: 100px;"></textarea>
                    </div>
<!-- Linea 7 -->
                    <label style="color: #669900 !important; font-family: 'Segoe UI Semibold_', 'Open Sans Bold', Verdana, Arial, Helvetica, sans-serif; font-weight: bold; font-size: 1.6rem; line-height: 1.6rem; -webkit-transform: rotate(-90deg); z-transform: rotate(-90deg); -ms-transform: rotate(-90deg); -o-transform: rotate(-90deg); transform: rotate(-90deg); position: absolute; width: 270px; margin-top: <?php echo $titulo[7]-20;?>px; margin-left:-165px;">CONTACTO</label>
                    <label style="color: #669900; position: absolute; width: 270px; margin-top: <?php echo $titulo[7];?>px;">Nombre vendedor</label>
                    <label style="color: #669900; position: absolute; width: 270px; margin-top: <?php echo $titulo[7];?>px; margin-left:260px;">Email vendedor</label>
                    <div class="input-control text success-state" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[7];?>px;">
                        <input name="txtNombreVendedorProveedor" tabindex="10" type="text" placeholder="Escriba el email del vendedor">
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text success-state" data-role="input-control text" style="position: absolute; width: 290px; margin-top: <?php echo $campos[7];?>px; margin-left:260px;">
                        <input name="txtEmailVendedorProveedor" tabindex="11" type="text" placeholder="Escriba la dirección web">
                            <button class="btn-clear"></button>
                    </div>                    
<!-- Linea 8 -->
                    <label style="color: #669900; position: absolute; width: 270px; margin-top: <?php echo $titulo[8];?>px;">Teléfono vendedor</label>
                    <label style="color: #669900; position: absolute; width: 270px; margin-top: <?php echo $titulo[8];?>px; margin-left:260px;">Skype vendedor</label>
                    <div class="input-control text success-state" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[8];?>px;">
                        <input name="txtTelefonoVendedorProveedor" tabindex="12" type="text" placeholder="Escriba el teléfono de vendedor">
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text success-state" data-role="input-control text" style="position: absolute; width: 290px; margin-top: <?php echo $campos[8];?>px; margin-left:260px;">
                        <input name="txtSkypeVendedorProveedor" tabindex="13" type="text" placeholder="Escriba el Skype del vendedor">
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 9 -->
                    <label style="color: #0066cc !important; font-family: 'Segoe UI Semibold_', 'Open Sans Bold', Verdana, Arial, Helvetica, sans-serif; font-weight: bold; font-size: 1.6rem; line-height: 1.6rem; -webkit-transform: rotate(-90deg); z-transform: rotate(-90deg); -ms-transform: rotate(-90deg); -o-transform: rotate(-90deg); transform: rotate(-90deg); position: absolute; width: 270px; margin-top: <?php echo $titulo[9]-30;?>px; margin-left:-165px;">BANCO</label>
                    <label style="color: #0066cc; position: absolute; width: 270px; margin-top: <?php echo $titulo[9];?>px;">Banco</label>
                    <label style="color: #0066cc; position: absolute; width: 250px; margin-top: <?php echo $titulo[9];?>px; margin-left:260px;">Tipo de cuenta</label>
                    <label style="color: #0066cc; position: absolute; width: 250px; margin-top: <?php echo $titulo[9];?>px; margin-left:410px;">No. Cuenta</label>
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[9];?>px;" class="input-control select info-state">
                        <select name="txtCodigoBancoProveedor" tabindex="14" style=" width: 240px;">
                            <?php
                            $arreglo_bancos = select_bancos(NULL);
                            $total_bancos = count($arreglo_bancos);
                            for ($j = 1; $j <= $total_bancos; $j++) 
                            {
                                $codigo_banco = $arreglo_bancos[$j]['codigo_banco'];
                                $nombre_banco = $arreglo_bancos[$j]['nombre_banco'];
                                ?>
                                <option value="<?php echo $codigo_banco; ?>"><?php echo $nombre_banco; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>                    
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[9];?>px; margin-left:260px;" class="input-control select info-state">
                        <select name="txtTipoCuentaBancariaProveedor" tabindex="15" style="width: 140px;">
                            <option value="1">CTA AHORROS</option>
                            <option value="2">CTA CORRIENTE</option>
                            <option value="3">Sin definir</option>
                        </select>
                    </div> 
                    <div class="input-control text info-state" data-role="input-control" style="position: absolute; width: 140px; margin-top: <?php echo $campos[9];?>px; margin-left:410px;">
                        <input name="txtCuentaBancariaProveedor" tabindex="16" type="text" placeholder="Escriba el No. de cuenta">
                            <button class="btn-clear"></button>
                    </div>
 <!-- Linea 10 -->
                    <label style="color: #0066cc; position: absolute; width: 270px; margin-top: <?php echo $titulo[10];?>px;">Beneficiario</label>
                    <label style="color: #0066cc; position: absolute; width: 270px; margin-top: <?php echo $titulo[10];?>px; margin-left:260px;">Tipo ID</label>
                    <label style="color: #0066cc; position: absolute; width: 270px; margin-top: <?php echo $titulo[10];?>px; margin-left:410px;">ID del Beneficiario</label>
                    <div class="input-control text info-state" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[10];?>px;">
                        <input name="txtNombreBeneficiarioProveedor" tabindex="17" type="text" placeholder="Escriba el nombre del beneficiario">
                            <button class="btn-clear"></button>
                    </div>
                    <div style="position: absolute; width: 240px; margin-top: <?php echo $campos[10];?>px; margin-left:260px;" class="input-control select info-state">
                        <select name="txtTipoIdentificacionCuentaBancariaProveedor" tabindex="18" style="width: 140px;">
                            <option value="1">RUC</option>
                            <option value="2">Cédula</option>
                            <option value="3">Otro</option>
                        </select>
                    </div>    
                    <div class="input-control text info-state" data-role="input-control" style="position: absolute; width: 140px; margin-top: <?php echo $campos[10];?>px; margin-left:410px;">
                        <input name="txtIdentificacionCuentaBancariaProveedor" tabindex="19" type="text" placeholder="Escriba la ID del beneficiario">
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 11 -->
                    <label style="color: #0066cc; position: absolute; width: 270px; margin-top: <?php echo $titulo[11];?>px;">Email para pagos</label>
                    <div class="input-control text info-state" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[11];?>px;">
                        <input name="txtEmailPagosProveedor" tabindex="20" type="text" placeholder="Escriba el email para pagos">
                            <button class="btn-clear"></button>
                    </div>
 <!-- Linea 12 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[12];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[12];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>
            </form>       
        </div>
        <a href="consola_proveedores_extendida.php"><button style="position: absolute; margin-top: 27px; margin-left: 22px;" class="image-button danger">Mas información<i class="icon-fullscreen-alt bg-red"></i></button></a>
        <div class="margin5 padding10 ribbed-darkRed" style="position: absolute; width: 660px; margin-top: <?php echo $barra_separacion["Y"];?>px; margin-left:<?php echo $barra_separacion["X"];?>px;"></div>
        <a href="javascript: carga_productos();"><button style="position: absolute; margin-top: 1021px; margin-left: <?php echo $barra_separacion["X"];?>px;" class="image-button danger">Cargar Productos<i class="icon-download bg-red"></i></button></a>
        <a href="consola_busqueda_productos.php"><button style="position: absolute; margin-top: 1021px; margin-left: <?php echo $barra_separacion["X"]+172;?>px;" class="image-button danger">Búsq. Productos<i class="icon-search bg-red"></i></button></a>
        <a href="javascript: carga_ciudades();"><button style="position: absolute; margin-top: 1021px; margin-left: <?php echo $barra_separacion["X"]+337;?>px;" class="image-button danger">Cargar Ciudades<i class="icon-download bg-red"></i></button></a>
        <a href="consola_busqueda_ciudades.php"><button style="position: absolute; margin-top: 1021px; margin-left: <?php echo $barra_separacion["X"]+503;?>px;" class="image-button danger">Búsq. Ciudades<i class="icon-search bg-red"></i></button></a>        
        <div class="herman1">
            <div class="herman" id="IdProductosProveedor" style="position: absolute; width: 660px; margin-top: <?php echo $flores_proveedor["Y"];?>px; margin-left:<?php echo $flores_proveedor["X"];?>px;" height: <?php echo $flores_proveedor["H"];?>px; ">
                <legend>Productos de la finca / Ciudades del Truck</legend>       
            </div>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>