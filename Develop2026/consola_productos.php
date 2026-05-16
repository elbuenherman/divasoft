<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_producto(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830",13=>"900",14=>"970",15=>"1040"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860",13=>"930",14=>"1000",15=>"1070"];
$margen_inferior = $campos[14]+85;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <?php
        include("css.php");
        ?>
        <title>Divasoft - Consola de Categorías de Producto</title>
    </head>
    <script language="javascript">
//AJAX
        $(document).ready(function(){
            var fileExtension = "";
            $(':file').change(function()
                {
                var file = $("#imagen")[0].files[0];
                var fileName = file.name;
                fileExtension = fileName.substring(fileName.lastIndexOf('.') + 1);
                var fileSize = file.size;
                var fileType = file.type;
                var formData = new FormData();
                formData.append("imagen",file);   
                $.ajax({
                    url: 'upload.php',type: 'POST',data: formData, cache: false, contentType: false, processData: false,
                    beforeSend: function(){;},
                    success: function(data)
                        {
                        if(isImage(fileExtension))
                            {
                            document.getElementById("idFotoProductoTx").src = " ";
                            document.getElementById("idFotoProducto").src = 'files/'+data;           
                            }
                        else
                            {
                            window.alert("Extensión errónea");
                            borra_campo_imagen();    
                            }
                        },
                    //si ha ocurrido un error
                    error: function(){;}
                    });
                });
            })
// FIN AJAX
        function borra_campo_imagen()
            {
            var codigo_producto = window.document.frmPrincipal.txtCodigoProducto.value;    
            var codigo_categoria = window.document.frmPrincipal.txtCodigoCategoria.value; 
            var nombre_producto = window.document.frmPrincipal.txtNombreProducto.value; 
            var nombre_ingles_producto = window.document.frmPrincipal.txtNombreInglesProducto.value; 
            var nombre_ruso_producto = window.document.frmPrincipal.txtTonalidadRusoProducto.value;
            var tonalidad_producto = window.document.frmPrincipal.txtTonalidadProducto.value;
            var tonalidad_ingles_producto = window.document.frmPrincipal.txtTonalidadInglesProducto.value;
            var tonalidad_ruso_producto = window.document.frmPrincipal.txtTonalidadRusoProducto.value;
            var petalos_producto = window.document.frmPrincipal.txtPetalosProducto.value;
            var tallos_producto = window.document.frmPrincipal.txtTallosProducto.value;
            var boton_producto = window.document.frmPrincipal.txtBotonProducto.value;
            var duracion_producto = window.document.frmPrincipal.txtDuracionProducto.value;
            var bs_producto = 0; if(document.getElementById("txtBsProducto").checked) bs_producto=1;
            var descripcion_producto = window.document.frmPrincipal.txtDescripcionProducto.value;
            var categoria_producto = window.document.frmPrincipal.txtCategoriaProducto.value;
            var dflt = 0; if(document.getElementById("txtDflt").checked) dflt = 1;
            window.document.frmPrincipal.reset();         
            window.document.frmPrincipal.txtCodigoProducto.value = codigo_producto;    
            window.document.frmPrincipal.txtCodigoCategoria.value = codigo_categoria;
            window.document.frmPrincipal.txtNombreProducto.value = nombre_producto;
            window.document.frmPrincipal.txtNombreInglesProducto.value = nombre_ingles_producto;
            window.document.frmPrincipal.txtTonalidadRusoProducto.value = nombre_ruso_producto;
            window.document.frmPrincipal.txtTonalidadProducto.value = tonalidad_producto;
            window.document.frmPrincipal.txtTonalidadInglesProducto.value = tonalidad_ingles_producto;
            window.document.frmPrincipal.txtTonalidadRusoProducto.value = tonalidad_ruso_producto;
            window.document.frmPrincipal.txtPetalosProducto.value = petalos_producto;
            window.document.frmPrincipal.txtTallosProducto.value = tallos_producto;
            window.document.frmPrincipal.txtBotonProducto.value = boton_producto;
            window.document.frmPrincipal.txtDuracionProducto.value = duracion_producto;
            if(bs_producto==1) document.getElementById("txtBsProducto").checked = true ;
            window.document.frmPrincipal.txtDescripcionProducto.value = descripcion_producto;
            window.document.frmPrincipal.txtCategoriaProducto.value = categoria_producto;
            if(dflt==1) document.getElementById("txtDflt").checked = true;   
            }
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
            document.getElementById("idFotoProducto").src = "";   
            document.getElementById("idFotoProductoTx").src = "";
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
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_ingles_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreInglesProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_ruso_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreRusoProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("tonalidad_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTonalidadProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("tonalidad_ingles_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTonalidadInglesProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("tonalidad_ruso_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTonalidadRusoProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("petalos_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtPetalosProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("tallos_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTallosProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("boton_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtBotonProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("duracion_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDuracionProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("bs_producto")[0].childNodes[0])=== "object")) if(x.nodeValue==1) document.getElementById("txtBsProducto").checked = true;
            if((typeof(x = xmlDoc.getElementsByTagName("descripcion_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtDescripcionProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("categoria_producto")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCategoriaProducto.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("dflt")[0].childNodes[0])=== "object")) if(x.nodeValue==1) document.getElementById("txtDflt").checked = true;         
            if((typeof(x = xmlDoc.getElementsByTagName("codigo_categoria_producto_facturacion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtCodigoCatgeoriaGrupoFacturacion.value = x.nodeValue;
            var url1 = "imagen_producto.php?CODIGO="+codigo;
            document.getElementById("idFotoProducto").src = url1;
            var url2 = "imagen_producto_tx.php?CODIGO="+codigo; 
            document.getElementById("idFotoProductoTx").src = url2;                  
            document.frmPrincipal.txtCodigoCategoria.focus();
            }
        function ingresar_registro()
            {
            var codigo_producto = window.document.frmPrincipal.txtCodigoProducto.value;    
            var codigo_categoria = window.document.frmPrincipal.txtCodigoCategoria.value; 
            var nombre_producto = window.document.frmPrincipal.txtNombreProducto.value; 
            var nombre_ingles_producto = window.document.frmPrincipal.txtNombreInglesProducto.value; 
            var nombre_ruso_producto = window.document.frmPrincipal.txtTonalidadRusoProducto.value;
            var tonalidad_producto = window.document.frmPrincipal.txtTonalidadProducto.value;
            var tonalidad_ingles_producto = window.document.frmPrincipal.txtTonalidadInglesProducto.value;
            var tonalidad_ruso_producto = window.document.frmPrincipal.txtTonalidadRusoProducto.value;
            var petalos_producto = window.document.frmPrincipal.txtPetalosProducto.value;
            var tallos_producto = window.document.frmPrincipal.txtTallosProducto.value;
            var boton_producto = window.document.frmPrincipal.txtBotonProducto.value;
            var duracion_producto = window.document.frmPrincipal.txtDuracionProducto.value;
            var bs_producto = 0; if(document.getElementById("txtBsProducto").checked) bs_producto=1;
            var descripcion_producto = window.document.frmPrincipal.txtDescripcionProducto.value;
            var categoria_producto = window.document.frmPrincipal.txtCategoriaProducto.value;
            var dflt = 0; if(document.getElementById("txtDflt").checked) dflt = 1;
            var txtFotoProducto = document.getElementById("idFotoProducto").src;
            var txtCodigoCatgeoriaGrupoFacturacion = window.document.frmPrincipal.txtCodigoCatgeoriaGrupoFacturacion.value; 
//            var r = confirm("Está seguro que desea ingresar el producto");
//            if (r != true)
//                return 0;
//||nombre_ingles_producto.length < 1||nombre_ruso_producto.length < 1||tonalidad_producto.length < 1||tonalidad_ingles_producto.length < 1||tonalidad_ruso_producto.length < 1||petalos_producto.length < 1||tallos_producto.length < 1||boton_producto.length < 1||duracion_producto.length < 1||descripcion_producto.length < 1            
            if (nombre_producto.length < 1)
                window.alert("El nombre del producto es obligatorio");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_producto&parametro1=" + codigo_producto + "&parametro2=" + codigo_categoria + "&parametro3=" + nombre_producto + "&parametro4=" + nombre_ingles_producto + "&parametro5=" + nombre_ruso_producto + "&parametro6=" + tonalidad_producto + "&parametro7=" + tonalidad_ingles_producto + "&parametro8=" + tonalidad_ruso_producto + "&parametro9=" + petalos_producto + "&parametro10=" + tallos_producto + "&parametro11=" + boton_producto + "&parametro12=" + duracion_producto + "&parametro13=" + bs_producto + "&parametro14=" + descripcion_producto + "&parametro15=" + categoria_producto + "&parametro16=" + dflt + "&parametro17=" + txtFotoProducto + "&parametro18="  + txtCodigoCatgeoriaGrupoFacturacion;       
                $.get(url, function (data, status)
                    {
                    var existe_registro = 0;
                    existe_registro = data;
                    if (existe_registro == '0')
                        window.alert("El nombre de la categoria ya existe");
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
            <legend><i class="icon-cart fg-crimson"></i>  Listado de categorías de producto</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left">COD</th>
                        <th class="text-left">Nombre</th>
                        <th class="text-left">Icono</th>
                        <th class="text-center">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $codigo_categoria_anterior = 0;
                    $tamano_texto = 14;
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
                                <td class="right"><img src='imagen.php?CODIGO=<?php echo $codigo_categoria; ?>' width='40' height='30'/></td>
                                <td class="text-center"></td>
                            </tr>
                            <?php                            
                            }
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_producto; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_producto; ?></td>
                            <td class="right"><img src='imagen_producto_short.php?CODIGO=<?php echo $codigo_producto; ?>' width='40' height='30'/></td>
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
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i>  Ingreso/Modificación de Productos</legend>
            <form name="frmPrincipal" target="_self" enctype="multipart/form-data" action="upload.php" method="POST">
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
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px; margin-left:260px;">Tonalidad</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[2];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtNombreProducto" tabindex="3" type="text" placeholder="Escriba el nombre del producto" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[2];?>px; margin-left:260px;">
                        <input name="txtTonalidadProducto" tabindex="6" type="text" placeholder="Escriba la tonalidad del producto" >
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 3 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[3];?>px;">Nombre producto (RUSO)</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[3];?>px; margin-left:260px;">Tonalidad (RUSO)</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[3];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtNombreRusoProducto" tabindex="4" type="text" placeholder="Escriba el nombre del producto (RUSO)" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[3];?>px; margin-left:260px;">
                        <input name="txtTonalidadRusoProducto" tabindex="7" type="text" placeholder="Escriba la tonalidad del producto (RUSO)" >
                            <button class="btn-clear"></button>
                    </div>   
<!-- Linea 4 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[4];?>px;">Nombre producto (INGLES)</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[4];?>px; margin-left:260px;">Tonalidad (INGLES)</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[4];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtNombreInglesProducto" tabindex="5" type="text" placeholder="Escriba el nombre del producto (INGLES)" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[4];?>px; margin-left:260px;">
                        <input name="txtTonalidadInglesProducto" tabindex="8" type="text" placeholder="Escriba la tonalidad del producto (INGLES)" >
                            <button class="btn-clear"></button>
                    </div> 
<!-- Linea 5 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[5];?>px;">No. Petalos</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[5];?>px; margin-left:260px;">Largos del tallo</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[5];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtPetalosProducto" tabindex="9" type="text" placeholder="Escriba el número de pétalos" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[5];?>px; margin-left:260px;">
                        <input name="txtTallosProducto" tabindex="10" type="text" placeholder="Escriba los largos del tallo" >
                            <button class="btn-clear"></button>
                    </div> 
<!-- Linea 6 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[6];?>px;">Tamaño del botón</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[6];?>px; margin-left:260px;">Duración en florero</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[6];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtBotonProducto" tabindex="11" type="text" placeholder="Escriba el tamaño del botón" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[6];?>px; margin-left:260px;">
                        <input name="txtDuracionProducto" tabindex="12" type="text" placeholder="Escriba la duración en florero" >
                            <button class="btn-clear"></button>
                    </div>  
<!-- Linea 7 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[7];?>px;">Best Seller</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[7];?>px; margin-left:260px;">Descripción del producto</label>  
                    <div class="input-control checkbox margin10" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[7];?>px; margin-left:0px;">
                        <label><input type="checkbox" tabindex="13" name="txtBsProducto" id="txtBsProducto"/><span class="check"></span>Mejor vendido</label>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[7];?>px; margin-left:260px;">
                        <input name="txtDescripcionProducto" tabindex="14" type="text" placeholder="Escriba la descripción" >
                            <button class="btn-clear"></button>
                    </div>                  
 <!-- Linea 8 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[8];?>px;">Categorías</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[8];?>px; margin-left:260px;">Default</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[8];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtCategoriaProducto" tabindex="16" type="text" placeholder="Escriba las categorías" >
                        <button class="btn-clear"></button>
                    </div>
                    <div class="input-control checkbox margin10" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[8];?>px; margin-left:260px;">
                        <label><input type="checkbox" tabindex="17" name="txtDflt" id="txtDflt"/><span class="check"></span>Por defecto</label>
                    </div>                         
<!-- Linea 9 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[9];?>px;">Fotografía</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[9];?>px; margin-left:260px;">Imagen</label>  
                    <div class="input-control file" data-role="input-control" style="position: absolute; width: 230px; margin-top: <?php echo $campos[9];?>px; margin-left:0px;">
                        <input style="width: 530px" name="txtImagenProducto" tabindex="18"  type="file" id="imagen">
                            <button class="btn-file"></button>
                    </div>
                    <div class="clearfix" style="position: absolute; width='280'; margin-top: <?php echo $campos[9];?>px; margin-left:260px; background-color: transparent">
                        <!--<img name="imgFotoProducto" width='280' height='30' src="" class="shadow">-->
                        <input type="image" name="imgFotoProducto" width='280' src="" class="shadow" id="idFotoProducto" value=" " disabled >
                    </div>
<!-- Linea 10 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[10];?>px;">Web</label>                 
                    <div class="clearfix" style="position: absolute; width='240'; margin-top: <?php echo $campos[10];?>px; margin-left:0px;">
                        <input type="image" name="imgFotoProductoTx" width='240' src="" class="shadow" id="idFotoProductoTx" value=" " disabled >
                    </div>             

 <!-- Linea 14 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[14];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[14];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>
                <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $titulo[2];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $titulo[2];?>px; margin-left:470px;" tabindex="21">Ingresar</a>                
            </form>    
            <form name="frmSecundario" target="_self" action="consola_productos.php"></form>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>