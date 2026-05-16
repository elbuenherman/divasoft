<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_producto_quick(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830",13=>"900",14=>"970",15=>"1040"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860",13=>"930",14=>"1000",15=>"1070"];
$margen_inferior = $campos[3]+85;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <?php
        include("css.php");
        ?>
        <title>Divasoft - Consola de Productos</title>
    </head>
    <script language="javascript">
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoProducto.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            ensera_formulario();
            window.document.frmPrincipal.txtCodigoProducto.value = "0";
            document.frmPrincipal.txtCodigoCategoria.focus();
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.reset();
            }
        function devuelve_registro(codigo)
            {
            ensera_formulario();
            window.document.frmPrincipal.txtCodigoProducto.value = codigo;
            var url = "funciones_ajax.php?funcion=devuelve_producto&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCodigoProducto.value = x.nodeValue;      
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_categoria")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCodigoCategoria.value = x.nodeValue;           
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreProducto.value = x.nodeValue;       
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_categoria_producto_facturacion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCodigoCatgeoriaGrupoFacturacion.value = x.nodeValue;
            document.frmPrincipal.txtCodigoCategoria.focus();
            }
        function ingresar_registro()
            {
            var codigo_producto = window.document.frmPrincipal.txtCodigoProducto.value;    
            var codigo_categoria = window.document.frmPrincipal.txtCodigoCategoria.value; 
            var nombre_producto = window.document.frmPrincipal.txtNombreProducto.value; 
            var nombre_ingles_producto = '';
            var nombre_ruso_producto = '';
            var tonalidad_producto = '';
            var tonalidad_ingles_producto = '';
            var tonalidad_ruso_producto = '';
            var petalos_producto = '';
            var tallos_producto = '';
            var boton_producto = '';
            var duracion_producto = '';
            var bs_producto =  '';
            var descripcion_producto = '';
            var categoria_producto = '';
            var dflt = '';
            var txtFotoProducto = '';
            var txtCodigoCatgeoriaGrupoFacturacion = window.document.frmPrincipal.txtCodigoCatgeoriaGrupoFacturacion.value; 
            if (nombre_producto.length < 1)
                window.alert("El nombre del producto es obligatorio");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_producto_quick&parametro1=" + codigo_producto + "&parametro2=" + codigo_categoria + "&parametro3=" + nombre_producto + "&parametro4=" + nombre_ingles_producto + "&parametro5=" + nombre_ruso_producto + "&parametro6=" + tonalidad_producto + "&parametro7=" + tonalidad_ingles_producto + "&parametro8=" + tonalidad_ruso_producto + "&parametro9=" + petalos_producto + "&parametro10=" + tallos_producto + "&parametro11=" + boton_producto + "&parametro12=" + duracion_producto + "&parametro13=" + bs_producto + "&parametro14=" + descripcion_producto + "&parametro15=" + categoria_producto + "&parametro16=" + dflt + "&parametro17=" + txtFotoProducto + "&parametro18="  + txtCodigoCatgeoriaGrupoFacturacion;       
                $.get(url, function (data, status)
                    {
                    var existe_registro = 0;
                    existe_registro = data;
                    if (existe_registro == '0')
                        window.alert("El nombre del producto ya existe");
                    else
                        window.document.frmSecundario.submit();
                    });
                }
            }
        function elimina_registro(codigo)
            {
            var r = confirm("Está seguro que desea eliminar el registro seleccionado");
            if (r == true)
                {
                var url = "funciones_ajax.php?funcion=elimina_producto&parametro1=" + codigo;
                $.get(url, function (data, status)
                    {
                    if (data == 0)
                        window.alert("No se puede eliminar el registro\nEl mismo está siendo utilizado en otra entidad");
                    if (data == 1)
                        window.document.frmSecundario.submit();
                    });
                }
            }
    </script>
    <body class="metro">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div class="herman" style="width: 550px; margin-top: 25px; margin-left:20px; position:absolute;">
            <legend><i class="icon-cart fg-crimson"></i>  Listado de productos</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Nombre</th>
                        <th class="text-left">Img</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $codigo_categoria_anterior = 0;
                    $tamano_texto = 11;
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_producto = $arreglo_registros[$j]['codigo_producto'];
                        $nombre_producto = $arreglo_registros[$j]['nombre_producto'];
                        $codigo_categoria = $arreglo_registros[$j]['codigo_categoria'];
                        $nombre_categoria = $arreglo_registros[$j]['nombre_categoria'];
                        if($codigo_categoria_anterior!=$codigo_categoria)
                            {
                            $codigo_categoria_anterior = $codigo_categoria;
                            ?>
                            <tr style="background-color: darkred;">
                                <td class="text-center" style="color: ivory ;font-size:<?php echo $tamano_texto+2;?>px">--</td>
                                <td class="right" style=";color: ivory; font-size:<?php echo $tamano_texto+2; ?>px"><b><?php echo $nombre_categoria; ?></b></td>
                                <td class="right"></td>
                                <td class="text-center"></td>
                            </tr>
                            <?php                            
                            }
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_producto; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_producto; ?></td>
                            <td class="right"><img src='imagen_producto_short.php?CODIGO=<?php echo $codigo_producto; ?>' width='32' height='24'/></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_producto; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_registro(<?php echo $codigo_producto; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px; height:<?php echo $margen_inferior;?>px">
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de Productos</legend>
            <form name="frmPrincipal" target="_self">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>
                    <label style="position: absolute; width: 100px; margin-top: <?php echo $titulo[1];?>px; margin-left:260px;">Grupo Web</label>  
                    <label style="position: absolute; width: 120px; margin-top: <?php echo $titulo[1];?>px; margin-left:410px;">Grupo Facturas</label>                    
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input style="width: 240px" name="txtCodigoProducto" READONLY value="0" type="text" data-popover="popover" data-popover-position="right" data-popover-text="El código se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" placeholder="Escriba el código del producto">
                    </div>
                    <div class="input-control select" style="position: absolute; width: 140px;">
   		 	<select name="txtCodigoCategoria" tabindex="1" style="position: absolute; width: 140px; margin-top: <?php echo $campos[1];?>px; margin-left:260px;">
                        <?php
                        $arreglo_categoria = select_categoria_producto(NULL);
                        $total_categorias = count($arreglo_categoria);
                        for($j=1;$j<=$total_categorias;$j++)
                                {
                                $codigo_categoria = $arreglo_categoria[$j]['codigo_categoria'];
                                $nombre_categoria = $arreglo_categoria[$j]['nombre_categoria'];
                        ?>
                            <option value="<?php echo $codigo_categoria;?>"><?php echo $nombre_categoria;?></option>
                        <?php
                                }
                        ?>
                        </select>
                    </div>
                    <div class="input-control select">
                        <select name="txtCodigoCatgeoriaGrupoFacturacion" tabindex="2" style="position: absolute; width: 140px; margin-top: <?php echo $campos[1];?>px; margin-left:410px;">
                        <?php
                        $arreglo_grupo_facturacion = select_categorias_producto_facturacion(NULL);
                        $total_grupo_facturacion = count($arreglo_grupo_facturacion);
                        for($j=1;$j<=$total_grupo_facturacion;$j++)
                                {
                                $codigo_categoria_producto_facturacion = $arreglo_grupo_facturacion[$j]['codigo_categoria_producto_facturacion'];
                                $descripcion_categoria_producto_facturacion = $arreglo_grupo_facturacion[$j]['descripcion_categoria_producto_facturacion'];

                        ?>
                        <option value="<?php echo $codigo_categoria_producto_facturacion;?>"><?php echo $descripcion_categoria_producto_facturacion;?></option>
                        <?php
                                }
                        ?>
                        </select>
                    </div>  
<!-- Linea 2 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px;">Nombre producto</label>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 550px; margin-top: <?php echo $campos[2];?>px; margin-left:0px;">
                        <input style="width: 550px" name="txtNombreProducto" tabindex="3" type="text" placeholder="Escriba el nombre del producto" >
                            <button class="btn-clear"></button>
                    </div>  
 <!-- Linea 3 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[3];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[3];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>          
            </form>    
            <form name="frmSecundario" target="_self" action="consola_productos_quick.php"></form>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>