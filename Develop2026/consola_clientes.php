<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_clientes(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$ultimo_indice = 8;
$tamano_texto = 11;
$onload = "";
if (isset($_REQUEST['parametro1'])) 
    {
    $parametro1 = $_REQUEST['parametro1'];
    $onload = "javascript: devuelve_registro(".$parametro1.");";
    }
$tabindex = 10;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <?php
        include("css.php");
        ?>
        <title>Divasoft - Consola de Clientes</title>
    </head>
    <script language="javascript">
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoCliente.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            window.document.frmPrincipal.txtCodigoCliente.value = "0";
            ensera_formulario();
            document.getElementById("txtNombreCliente").focus();            
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.txtNombreCliente.value = "";
            window.document.frmPrincipal.txtEmailFacturasCliente.value = "";
            window.document.frmPrincipal.txtEmailEstadoCuentaCliente.value = "";
            window.document.frmPrincipal.txtTelefonoCliente.value = "";
            window.document.frmPrincipal.txtDireccionCliente.value = "";
            window.document.frmPrincipal.txtObservacionesCliente.value = "";
            pone_opcion_en_select_x_codigo(23, window.document.frmPrincipal.txtCodigoPaisCliente);
            pone_opcion_en_select_x_codigo(17, window.document.frmPrincipal.txtCodigoCiudadCliente);
            pone_numero_en_codigo(0);                
            }
        function devuelve_registro(codigo)
            {
            ensera_formulario();
            var url = "funciones_ajax.php?funcion=devuelve_cliente&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreCliente.value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("email_facturas_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtEmailFacturasCliente.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("email_estado_cuenta_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtEmailEstadoCuentaCliente.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("telefono_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTelefonoCliente.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("direccion_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDireccionCliente.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_ciudad_cliente")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoCiudadCliente);
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_pais_cliente")[0].childNodes[0])=== "object")) pone_opcion_en_select_x_codigo(x.nodeValue, window.document.frmPrincipal.txtCodigoPaisCliente);
            if((typeof(x = xmlDoc.getElementsByTagName("observaciones_cliente")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtObservacionesCliente.value = x.nodeValue;
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombreCliente.focus();
            }
        function ingresar_registro()
            {
            var txtNombreCliente = window.document.frmPrincipal.txtNombreCliente.value; txtNombreCliente = txtNombreCliente.toUpperCase();
            var txtCodigoCliente = window.document.frmPrincipal.txtCodigoCliente.value;
            var txtEmailFacturasCliente = window.document.frmPrincipal.txtEmailFacturasCliente.value;
            var txtEmailEstadoCuentaCliente = window.document.frmPrincipal.txtEmailEstadoCuentaCliente.value;
            var txtTelefonoCliente = window.document.frmPrincipal.txtTelefonoCliente.value;
            var txtDireccionCliente = window.document.frmPrincipal.txtDireccionCliente.value;
            var txtCodigoPaisCliente = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoPaisCliente);
            var txtCodigoCiudadCliente = devuelve_codigo_select(window.document.frmPrincipal.txtCodigoCiudadCliente);
            var txtObservacionesCliente = window.document.frmPrincipal.txtObservacionesCliente.value;           
            if (txtNombreCliente.length < 4)
                window.alert("El nombre del cliente debe tener mas de 4 caracteres");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_cliente&parametro1=" + txtCodigoCliente + "&parametro2=" + txtNombreCliente + "&parametro3=" + txtEmailFacturasCliente + "&parametro4=" + txtEmailEstadoCuentaCliente + "&parametro5=" + txtTelefonoCliente + "&parametro6=" + txtDireccionCliente + "&parametro7=" + txtCodigoPaisCliente + "&parametro8=" + txtCodigoCiudadCliente + "&parametro9=" + txtObservacionesCliente;
                $.get(url, function (data, status)
                    {
                    var existe_registro = 0;
                    existe_registro = data;
                    if (existe_registro == '0')
                        {
                        window.alert("El nombre del cliente ya existe");
                        }
                    else
                        window.document.frmPrincipal.submit();
                    });
                }
            }
        function elimina_registro(codigo)
        {
            var r = confirm("Está seguro que desea eliminar el cliente selcccionado");
            if (r == true)
            {
                var url = "funciones_ajax.php?funcion=elimina_cliente&parametro1=" + codigo;
                $.get(url, function (data, status)
                {
                    if (data == 0)
                    {
                        window.alert("No se puede eliminar el cliente\nEl mismo está siendo utilizado en otra entidad");
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
            <legend><i class="icon-user-2 fg-crimson"></i>Listado de Clientes</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Cliente</th>
                        <th class="text-left">País/Ciudad</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_cliente = $arreglo_registros[$j]['codigo_cliente'];
                        $nombre_cliente = $arreglo_registros[$j]['nombre_cliente'];
                        $pais_ciudad_cliente = $arreglo_registros[$j]['nombre_pais']." - ".$arreglo_registros[$j]['nombre_ciudad'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_cliente; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_cliente; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $pais_ciudad_cliente; ?></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_cliente; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> 
                            <a href="javascript: elimina_registro(<?php echo $codigo_cliente; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a>
                            </td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px; height: <?php echo $campos[$ultimo_indice]+80;?>px; ">
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de Clientes</legend>
            <form name="frmPrincipal" target="_self">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>                  
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input name="txtCodigoCliente" style="width: 240px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                    </div>
<!-- Linea 2 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[2];?>px;">Nombre del Cliente</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[2];?>px;">
                        <input name="txtNombreCliente" id="txtNombreCliente" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el nombre del cliente">
                        <button class="btn-clear"></button>
                    </div>  
<!-- Linea 3 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[3];?>px;">Email para facturas</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[3];?>px;">
                        <input name="txtEmailFacturasCliente" id="txtEmailFacturasCliente" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el email para facturas">
                        <button class="btn-clear"></button>
                    </div>  
<!-- Linea 4 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[4];?>px;">Email para estados de cuenta</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[4];?>px;">
                        <input name="txtEmailEstadoCuentaCliente" id="txtEmailEstadoCuentaCliente" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el email para estados de cuenta">
                        <button class="btn-clear"></button>
                    </div> 
<!-- Linea 5 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[5];?>px;">Teléfono</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[5];?>px;">
                        <input name="txtTelefonoCliente" id="txtTelefonoCliente" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el teléfono del cliente">
                        <button class="btn-clear"></button>
                    </div>                       
<!-- Linea 6 -->
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[6];?>px;">Dirección</label>
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[6];?>px; margin-left:260px;">País</label>
                    <label style="position: absolute; width: 270px; margin-top: <?php echo $titulo[6];?>px; margin-left:410px;">Ciudad</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $campos[6];?>px;">
                        <input name="txtDireccionCliente" tabindex="5" type="text" placeholder="Escriba la dirección para el cliente">
                        <button class="btn-clear"></button>
                    </div>
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[6];?>px; margin-left:260px;" class="input-control select">
                        <select name="txtCodigoPaisCliente" tabindex="6" style="width: 140px;">
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
                    <div style="position: absolute; width: 200px; margin-top: <?php echo $campos[6];?>px; margin-left:410px;" class="input-control select">
                        <select name="txtCodigoCiudadCliente" tabindex="7" style="width: 140px;">
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
<!-- Linea 7 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[7];?>px;">Observaciones</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[7];?>px;">
                        <input name="txtObservacionesCliente" tabindex="5" type="text" placeholder="Escriba observaciones para el cliente">
                        <button class="btn-clear"></button>
                    </div>                    
<!-- Linea 8 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[8];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[8];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>

                </fieldset>
            </form>       
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>