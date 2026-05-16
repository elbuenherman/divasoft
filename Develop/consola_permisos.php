<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_permisos(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$ultimo_indice = 7;
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
        <title>Divasoft - Consola de Permisos</title>
    </head>
    <script language="javascript">
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoPermiso.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            window.document.frmPrincipal.txtCodigoPermiso.value = "0";
            ensera_formulario();
            document.getElementById("txtDetallePermiso").focus();            
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.txtDetallePermiso.value = "";      
            window.document.frmPrincipal.txtValorVerdaderoPermiso.value = "";
            window.document.frmPrincipal.txtValorFalsoPermiso.value = "";
            window.document.frmPrincipal.txtValorSinEstablecerPermiso.value = "";          
            pone_numero_en_codigo(0);                
            }
        function devuelve_registro(codigo)
            {
            ensera_formulario();
            var url = "funciones_ajax.php?funcion=devuelve_permiso&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("descripcion_permiso")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDetallePermiso.value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("valor_verdadero_permiso")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtValorVerdaderoPermiso.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("valor_falso_permiso")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtValorFalsoPermiso.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("valor_sin_establecer_permiso")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtValorSinEstablecerPermiso.value = x.nodeValue;            
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombreProveedor.focus();
            }
        function ingresar_registro()
            {
            var txtDetallePermiso = window.document.frmPrincipal.txtDetallePermiso.value
            var txtValorVerdaderoPermiso = window.document.frmPrincipal.txtValorVerdaderoPermiso.value
            var txtValorFalsoPermiso = window.document.frmPrincipal.txtValorFalsoPermiso.value
            var txtValorSinEstablecerPermiso = window.document.frmPrincipal.txtValorSinEstablecerPermiso.value
            var txtCodigoPermiso = window.document.frmPrincipal.txtCodigoPermiso.value;  
            if (txtDetallePermiso.length < 4)
                window.alert("El detalle del permiso debe tener mas de 4 caracteres");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_permiso&parametro1=" + txtCodigoPermiso + "&parametro2=" + txtValorVerdaderoPermiso + "&parametro3=" + txtValorFalsoPermiso + "&parametro4=" + txtValorSinEstablecerPermiso + "&parametro5=" + txtDetallePermiso;
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
//        function elimina_registro(codigo)
//        {
//            var r = confirm("Está seguro que desea eliminar el proveedor seleccionado");
//            if (r == true)
//            {
//                var url = "funciones_ajax.php?funcion=elimina_proveedor&parametro1=" + codigo;
//                $.get(url, function (data, status)
//                {
//                    if (data == 0)
//                    {
//                        window.alert("No se puede eliminar el proveedor\nEl mismo está siendo utilizado en otra entidad");
//                    }
//                    if (data == 1)
//                    {
//                        window.document.frmPrincipal.submit();
//                    }
//                });
//            }
//        }
    </script>
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div class="herman" style="width: 550px; margin-top: 25px; margin-left:20px; position:absolute;">
            <legend>Listado de Permisos</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Permiso</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_permiso = $arreglo_registros[$j]['codigo_permiso'];
                        $descripcion_permiso = $arreglo_registros[$j]['descripcion_permiso'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_permiso; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $descripcion_permiso; ?></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_permiso; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> 
                            <!--<a href="javascript: elimina_registro(<?php echo $codigo_permiso; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a>-->
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
            <legend style="position: absolute; width: 570px;">Ingreso/Modificación de Permisos</legend>
            <form name="frmPrincipal" target="_self">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>                  
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input name="txtCodigoPermiso" style="width: 240px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                    </div>
<!-- Linea 2 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px;">Detalle del permiso</label>                  
                    <div class="input-control textarea">
                        <textarea name="txtDetallePermiso" id="txtDetallePermiso" tabindex="<?php echo $tabindex++;?>" style="resize:none; position: absolute; width: 550px; margin-top: <?php echo $campos[2];?>px; margin-left:0px;"></textarea>
                    </div>
<!-- Linea 4 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[4];?>px;">Valor verdadero</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[4];?>px;">
                        <input name="txtValorVerdaderoPermiso" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el valor verdadero">
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 5 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[5];?>px;">Valor falso</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[5];?>px;">
                        <input name="txtValorFalsoPermiso" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el valor falso">
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 6 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[6];?>px;">Valor sin establecer</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[6];?>px;">
                        <input name="txtValorSinEstablecerPermiso" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el valor cuando no se establece el permiso">
                            <button class="btn-clear"></button>
                    </div>                   
 <!-- Linea 7 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[7];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[7];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>
            </form>       
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>