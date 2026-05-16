<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_marcaciones(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$ultimo_indice = 4;
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
        <title>Divasoft - Consola de Marcaciones</title>
    </head>
    <script language="javascript">
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoMarcacion.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            window.document.frmPrincipal.txtCodigoMarcacion.value = "0";
            ensera_formulario();
            document.getElementById("txtNombreMarcacion").focus();            
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.txtNombreMarcacion.value = "";               
            pone_numero_en_codigo(0);                
            }
        function devuelve_registro(codigo)
            {
            ensera_formulario();
            var url = "funciones_ajax.php?funcion=devuelve_marcacion&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_marcacion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreMarcacion.value = x.nodeValue;    
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_cliente_marcacion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCodigoCliente.value = x.nodeValue;
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombreMarcacion.focus();
            }
        function ingresar_registro()
            {
            var txtNombreMarcacion = window.document.frmPrincipal.txtNombreMarcacion.value;
            var txtCodigoMarcacion = window.document.frmPrincipal.txtCodigoMarcacion.value;  
            var txtCodigoCliente = window.document.frmPrincipal.txtCodigoCliente.value;
            if (txtNombreMarcacion.length < 4)
                window.alert("La marcación debe tener mas de 4 caracteres");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_marcacion&parametro1=" + txtCodigoMarcacion + "&parametro2=" + txtNombreMarcacion+ "&parametro3="+txtCodigoCliente;
                $.get(url, function (data, status)
                    {
                    var existe_registro = 0;
                    existe_registro = data;
                    if (existe_registro == '0')
                        {
                        window.alert("El nombre de la marcación ya existe");
                        }
                    else
                        window.document.frmPrincipal.submit();
                    });
                }
            }
        function elimina_registro(codigo)
        {
            var r = confirm("Está seguro que desea eliminar la marcación selcccionada");
            if (r == true)
            {
                var url = "funciones_ajax.php?funcion=elimina_marcacion&parametro1=" + codigo;
                $.get(url, function (data, status)
                {
                    if (data == 0)
                    {
                        window.alert("No se puede eliminar la marcación\nLa misma está siendo utilizada en otra entidad");
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
            <legend><i class="icon-clubs fg-crimson"></i> Listado de Marcaciones</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Marcación</th>
                        <th class="text-left">Cliente</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_marcacion = $arreglo_registros[$j]['codigo_marcacion'];
                        $nombre_marcacion = $arreglo_registros[$j]['nombre_marcacion'];
                        $nombre_cliente = $arreglo_registros[$j]['nombre_cliente']." - ".$arreglo_registros[$j]['nombre_pais']." - ".$arreglo_registros[$j]['nombre_ciudad'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_marcacion; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_marcacion; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_cliente; ?></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_marcacion; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> 
                            <a href="javascript: elimina_registro(<?php echo $codigo_marcacion; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a>
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
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de Marcaciones</legend>
            <form name="frmPrincipal" target="_self">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>                  
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input name="txtCodigoMarcacion" style="width: 240px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                    </div>
<!-- Linea 2 -->
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[2];?>px;">Nombre de la marcación</label>
                    <div class="input-control text" data-role="input-control text" style="position: absolute; width: 550px; margin-top: <?php echo $campos[2];?>px;">
                        <input name="txtNombreMarcacion" id="txtNombreMarcacion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el nombre de la marcación">
                            <button class="btn-clear"></button>
                    </div>    
 <!-- Linea 3 --> 
                    <label style="position: absolute; width: 550px; margin-top: <?php echo $titulo[3];?>px;">Cliente:</label>
                    <div class="input-control select" style="position: absolute; width: 140px;">
   		 	<select name="txtCodigoCliente" tabindex="1" style="position: absolute; width: 550px; margin-top: <?php echo $campos[3];?>px;">
                        <?php
                        $arreglo_registros = select_clientes(NULL);;
                        $total_clientes = count($arreglo_registros);
                        for($j=1;$j<=$total_clientes;$j++)
                                {
                                $codigo_cliente = $arreglo_registros[$j]['codigo_cliente'];
                                $nombre_cliente = $arreglo_registros[$j]['nombre_cliente']." (".$arreglo_registros[$j]['nombre_pais']." - ".$arreglo_registros[$j]['nombre_ciudad'].")";
                        ?>
                            <option value="<?php echo $codigo_cliente;?>"><?php echo $nombre_cliente;?></option>
                        <?php
                                }
                        ?>
                        </select>
                    </div> 
 <!-- Linea 4 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[4];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[4];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>
            </form>       
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>