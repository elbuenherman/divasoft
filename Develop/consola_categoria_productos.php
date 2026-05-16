<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_categoria_producto(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
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
                            {document.getElementById("idFotoCategoria").src = 'files/'+data;}
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
            var txtCodigoCategoria = window.document.frmPrincipal.txtCodigoCategoria.value;
            var txtNombreCategoria = window.document.frmPrincipal.txtNombreCategoria.value;                
            var txtNombreRusoCategoria = window.document.frmPrincipal.txtNombreRusoCategoria.value;     
            var txtNombreInglesCategoria = window.document.frmPrincipal.txtNombreInglesCategoria.value;
            var txtColorCategoria = window.document.frmPrincipal.txtColorCategoria.value;  
            var imgFotoCategoria = window.document.frmPrincipal.imgFotoCategoria.src;
            window.document.frmPrincipal.reset();
            window.document.frmPrincipal.txtCodigoCategoria.value = txtCodigoCategoria;
            window.document.frmPrincipal.txtNombreCategoria.value = txtNombreCategoria;                
            window.document.frmPrincipal.txtNombreRusoCategoria.value = txtNombreRusoCategoria;     
            window.document.frmPrincipal.txtNombreInglesCategoria.value = txtNombreInglesCategoria;
            window.document.frmPrincipal.txtColorCategoria.value = txtColorCategoria;
            window.document.frmPrincipal.imgFotoCategoria.src=imgFotoCategoria;    
            }
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoCategoria.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            window.document.frmPrincipal.txtCodigoCategoria.value = "0";
            ensera_formulario();
            window.document.frmPrincipal.txtNombreCategoria.focus();
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.reset();
            document.getElementById("idFotoCategoria").src = "";
            pone_numero_en_codigo(0);                
            }

        function devuelve_registro(codigo)
            {
            ensera_formulario();
            window.document.frmPrincipal.txtCodigoCategoria.value = codigo;
            var url = "funciones_ajax.php?funcion=devuelve_categoria&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_categoria")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreCategoria.value = x.nodeValue;      
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_ruso_categoria")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreRusoCategoria.value = x.nodeValue;           
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_ingles_categoria")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombreInglesCategoria.value = x.nodeValue;       
            if((typeof(x = xmlDoc.getElementsByTagName("color_categoria")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtColorCategoria.value = x.nodeValue;
            var url1 = "imagen.php?CODIGO="+codigo;
            document.getElementById("idFotoCategoria").src = url1
            window.document.frmPrincipal.txtNombreCategoria.focus();
            }
            
        function ingresar_registro()
            {
            var txtCodigoCategoria = window.document.frmPrincipal.txtCodigoCategoria.value;    
            var txtNombreCategoria = window.document.frmPrincipal.txtNombreCategoria.value; txtNombreCategoria = txtNombreCategoria.toUpperCase();
            var txtNombreInglesCategoria = window.document.frmPrincipal.txtNombreInglesCategoria.value; txtNombreInglesCategoria = txtNombreInglesCategoria.toUpperCase();
            var txtNombreRusoCategoria = window.document.frmPrincipal.txtNombreRusoCategoria.value; txtNombreRusoCategoria = txtNombreRusoCategoria.toUpperCase();
            var txtColorCategoria = window.document.frmPrincipal.txtColorCategoria.value;
            txtColorCategoria = txtColorCategoria.substring(1,7);
            var txtFotoCategoria = document.getElementById("idFotoCategoria").src;
            if (txtNombreCategoria.length < 4||txtNombreRusoCategoria.length < 4||txtNombreInglesCategoria.length < 4)
                window.alert("El nombre de la categoría debe tener mas de 4 caracteres para todos los idiomas");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_categoria&parametro1=" + txtCodigoCategoria + "&parametro2=" + txtNombreCategoria + "&parametro3=" + txtColorCategoria + "&parametro4=" + txtFotoCategoria + "&parametro5=" + txtNombreRusoCategoria + "&parametro6=" + txtNombreInglesCategoria;              
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
            var r = confirm("Está seguro que desea eliminar la categoría seleccionado");
            if (r == true)
                {
                var url = "funciones_ajax.php?funcion=elimina_categoria&parametro1=" + codigo;
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
            <legend><i class="icon-drawer fg-crimson"></i> Listado de categorías de producto</legend>
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
                    $tamano_texto = 12;
                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_categoria = $arreglo_registros[$j]['codigo_categoria'];
                        $nombre_categoria = $arreglo_registros[$j]['nombre_categoria'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_categoria; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_categoria; ?></td>
                            <td class="right"><img src='imagen.php?CODIGO=<?php echo $codigo_categoria; ?>' width='40' height='30'/></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_categoria; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> <a href="javascript: elimina_registro(<?php echo $codigo_categoria; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a></td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px; height: 400px; ">
            <legend style="position: absolute; width: 570px;"><i class="icon-plus fg-crimson"></i> Ingreso/Modificación de Categorías</legend>
            <form name="frmPrincipal" target="_self" enctype="multipart/form-data" action="upload.php" method="POST">
                <fieldset  style="position: absolute">
<!-- Linea 1 -->
                    <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[1];?>px; margin-left:260px;">Nombre categoría producto</label>                    
                    <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                        <input style="width: 240px"  name="txtCodigoCategoria" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[1];?>px; margin-left:260px;">
                        <input name="txtNombreCategoria" tabindex="1" type="text" placeholder="Escriba el nombre de la categoría de productos"autofocus>
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 2 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px;">Nombre en ruso</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[2];?>px; margin-left:260px;">Nombre en inglés</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[2];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtNombreRusoCategoria" tabindex="3" type="text" placeholder="Escriba el nombre en ruso" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[2];?>px; margin-left:260px;">
                        <input name="txtNombreInglesCategoria" tabindex="3" type="text" placeholder="Escriba el nombre en inglés" >
                            <button class="btn-clear"></button>
                    </div>
<!-- Linea 3 -->
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[3];?>px;">Color</label>
                    <label style="position: absolute; width: 500px; margin-top: <?php echo $titulo[3];?>px; margin-left:260px;">Fotografía</label>  
                    <div class="input-control text" data-role="input-control" style="position: absolute; width: 290px; margin-top: <?php echo $campos[3];?>px; margin-left:0px;">
                        <input style="width: 240px" name="txtColorCategoria" tabindex="3" type="color" placeholder="Escriba el código del color" >
                            <button class="btn-clear"></button>
                    </div>
                    <div class="input-control file" data-role="input-control" style="position: absolute; width: 230px; margin-top: <?php echo $campos[3];?>px; margin-left:260px;">
                        <input style="width: 530px" name="txtFotoCategoria" type="file" id="imagen">
                            <button class="btn-file"></button>
                    </div>
                    <div class="showImage" style="position: absolute; width='40'; height='30'; margin-top: <?php echo $campos[3];?>px; margin-left:507px;" >
                        <!--<img name="imgFotoCategoria" width='40' height='30' src="" class="shadow" id="idFotoCategoria">-->
                        <input type="image" name="imgFotoCategoria" width='40' height='30' src="" class="shadow" id="idFotoCategoria" value=" " disabled >
                    </div>

 <!-- Linea 4 -->                    
                    <div>
                        <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[4];?>px; margin-left:395px;" tabindex="20">Nuevo</a>
                        <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[4];?>px; margin-left:470px;" tabindex="21">Ingresar</a>
                    </div>
                </fieldset>
            </form>    
            <form name="frmSecundario" target="_self" action="consola_categoria_productos.php"></form>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>