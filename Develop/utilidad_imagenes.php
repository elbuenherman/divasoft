<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_suscripciones(NULL);
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
        <title>Divasoft - Utilidad Imágenes</title>
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
                        if(isImageResize(fileExtension))
                            {
                                document.getElementById("idTexto").value = 'files/'+data; 
                            document.getElementById("idFotoProducto").src = " ";
                            document.getElementById("idFotoProducto").src = 'imagen_reduce.php?archivo=files/'+data;      
                             
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
function isImageResize(extension)
    {
    switch(extension.toLowerCase()) 
        {
        case 'jpg': case 'gif': case 'png': case 'jpeg':
            return true;
        break;
        default:
            return false;
        break;
        }
    }
    </script>
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div style="position:absolute;" style="width: 860px;">
            <form name="frmPrincipal" target="_self" enctype="multipart/form-data" action="upload.php" method="POST">
                <div class="aida" style="width: 860px; margin-top: 25px; margin-left:10px;">
                    <div class="ribbed-crimson" style="height: 2px;">.</div>
                    <legend><i class="icon-instagram fg-crimson"></i> Reducir imagen </legend>
                    <div class="input-control file" data-role="input-control" style="width: 350px;">
                        <input style="width: 350px" name="txtImagenProducto" tabindex="18"  type="file" id="imagen">
                        <button class="btn-file fg-crimson"></button>
                    </div>
                    <div class="clearfix" style="width:'300px';">
                        <!--<img name="imgFotoProducto" width='280' height='30' src="" class="shadow">-->
                        <input type="image" name="idFotoProducto" id='idFotoProducto' src="" width="300" class="shadow"  value=" " >
                        <input type="hidden" name="idTexto" id='idTexto' src="" width="300" class="shadow" value=" " >
                    
                    </div>                    
                    <div><br>
                        <a href="javascript: subir_archivo();" class="place-left button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="">ENVIAR EMAIL</a>
                    </div>   
                </div>
            </form>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>