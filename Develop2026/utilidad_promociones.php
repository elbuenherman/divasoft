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
        <title>Divasoft - Utilidad promociones</title>
    </head>
    <script language="javascript">
        function pone_numero_en_codigo(numero)
            {
            window.document.frmPrincipal.txtCodigoPromocion.value = numero;
            document.location.href = "#inicio";
            }
        function boton_nuevo(numero)
            {
            window.document.frmPrincipal.txtCodigoPromocion.value = "0";
            ensera_formulario();
            document.getElementById("txtNombreNombrePromocion").focus();            
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.reset();               
            pone_numero_en_codigo(0);                
            }
        function devuelve_registro(codigo)
            {
            ensera_formulario();
            var url = "funciones_ajax.php?funcion=devuelve_suscripcion&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_promocion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtNombrePromocion.value = x.nodeValue;    
            if((typeof(x = xmlDoc.getElementsByTagName("telefono_promocion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtTelefonoPromocion.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("email_promocion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtEmailPromocion.value = x.nodeValue;    
            if((typeof(x = xmlDoc.getElementsByTagName("lugar_promocion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtLugarPromocion.value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("fecha_promocion")[0].childNodes[0])=== "object")) window.document.frmPrincipal.txtFechaPromocion.value = x.nodeValue;     
            
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombrePromocion.focus();
            }
        function ingresar_registro()
            {
            var txtCodigoPromocion = window.document.frmPrincipal.txtCodigoPromocion.value;
            var txtNombrePromocion = window.document.frmPrincipal.txtNombrePromocion.value;    
            var txtTelefonoPromocion = window.document.frmPrincipal.txtTelefonoPromocion.value;
            var txtEmailPromocion = window.document.frmPrincipal.txtEmailPromocion.value;    
            var txtLugarPromocion = window.document.frmPrincipal.txtLugarPromocion.value;
            var txtFechaPromocion = window.document.frmPrincipal.txtFechaPromocion.value;  
            if (txtNombrePromocion.length < 4||txtEmailPromocion.length < 4)
                window.alert("La suscripcion y el email debe tener mas de 4 caracteres");
            else
                {
                var url = "funciones_ajax.php?funcion=inserta_suscripcion&parametro1=" + txtCodigoPromocion + "&parametro2=" + txtNombrePromocion + "&parametro3=" + txtTelefonoPromocion + "&parametro4=" + txtEmailPromocion + "&parametro5=" + txtLugarPromocion + "&parametro6=0";
                var obj_ajax = $.get(url, function (data, status){});
                obj_ajax.success(function()
                    {
                    window.document.frmPrincipal.submit();
                    });                    
                }
            }
        function elimina_registro(codigo)
            {   
            var r = confirm("Está seguro que desea eliminar el registro selcccionado");
            if (r == true)
                {
                var url = "funciones_ajax.php?funcion=elimina_suscripcion&parametro1=" + codigo;
                var obj_ajax = $.get(url, function (data, status) {;});
                obj_ajax.success(function()
                    {
                    window.document.frmPrincipal.submit();
                    });
                }      
            }
        function check_email(codigo,valor)
            {  
  //          devuelve_registro(codigo);
            if(valor==1) valor = 0; else valor = 1;
            window.document.frmPrincipal.txtCodigoPromocion.value = 1;
            window.document.frmPrincipal.txtFechaPromocion.value = 1;
            var url = "funciones_ajax.php?funcion=check_email&parametro1=" + codigo + "&parametro2=" + valor;
            var obj_ajax1 = $.get(url, function (data, status){});
            obj_ajax1.success(function()
                {
                window.document.frmPrincipal.submit();
                });
            }
function omitir_acentos(text) 
    {
    var acentos = "ÃÀÁÄÂÈÉËÊÌÍÏÎÒÓÖÔÙÚÜÛãàáäâèéëêìíïîòóöôùúüûÑñÇç";
    var original = "AAAAAEEEEIIIIOOOOUUUUaaaaaeeeeiiiioooouuuuNnCc";
    for (var i=0; i<acentos.length; i++)
        text = text.replace(acentos.charAt(i), original.charAt(i));
    return text;
    }

    function enviar_email()
        {
        var texto = document.getElementById("txtCuerpoEmail").value;    
        if(texto.length<20)
            {
                alert("El texto de promociones debe tener mas de 20 caracteres");
                return;
            }
        var mensaje = "Está seguro que desea enviar el email de promociones";
        var r = confirm(mensaje);
        if (r == true)
            {
            texto = omitir_acentos(texto);
            texto = escape(texto);           
            var url_mail = "phpmailer/divamail/mail_promociones.php?texto="+texto;
         //   alert(url_mail);
            window.open(url_mail, "Popup", "location=0,status=0,scrollbars=0, resizable=0, directories=0, toolbar=0, titlebar=1, width=400, height=400");               
            }
        }
    </script>
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div style="position:absolute;" style="width: 860px;">
            <div class="aida" style="width: 860px; margin-top: 25px; margin-left:10px;">
                <div class="ribbed-crimson" style="height: 2px;">.</div>
                <legend><i class="icon-rainbow fg-crimson"></i> Subscripción de promociones </legend>
                <table class="table hovered" width="100" style="width: 100%">
                    <thead>
                        <tr>
                            <th class="text-left"></th>
                            <th class="text-left">Nombre</th>
                            <th class="text-left">Teléfono</th>
                            <th class="text-left">Email</th>
                            <th class="text-left">Lugar</th>
                            <th class="text-left">Fecha</th>                       
                            <th class="text-center">Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        for ($j = 1; $j <= $total_registros; $j++) 
                            {
                            $codigo_promocion = $arreglo_registros[$j]['codigo_promocion'];
                            $nombre_promocion = $arreglo_registros[$j]['nombre_promocion'];
                            $telefono_promocion = $arreglo_registros[$j]['telefono_promocion'];
                            $email_promocion = $arreglo_registros[$j]['email_promocion'];
                            $lugar_promocion = $arreglo_registros[$j]['lugar_promocion'];
                            $fecha_promocion = $arreglo_registros[$j]['fecha_promocion'];
                            $envio_promocion = $arreglo_registros[$j]['envio_promocion'];                       
                            if($envio_promocion==1) $icono_check_promocion = "icon-checkmark fg-lime";
                            else $icono_check_promocion = "icon-cancel-2 fg-pink";
                            

                        ?>
                        <tr>
                                <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><i title="Check Email" class="<?php echo $icono_check_promocion;?>"></i><?php echo $codigo_promocion; ?></td>
                                <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_promocion; ?></td>
                                <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $telefono_promocion; ?></td>
                                <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $email_promocion; ?></td>
                                <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $lugar_promocion; ?></td>
                                <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $fecha_promocion; ?></td>
                                <td class="text-center">
                                    <a href="javascript: check_email(<?php echo $codigo_promocion; ?>,<?php echo $envio_promocion; ?>)"><i title="Check Email" class="icon-mail fg-green"></i></a>
                                    <a href="javascript: devuelve_registro(<?php echo $codigo_promocion; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> 
                                    <a href="javascript: elimina_registro(<?php echo $codigo_promocion; ?>)"><i title="Eliminar" class="icon-remove fg-red"></i></a>
                                </td>
                            <?php
                            }
                            ?>
                        </tr>
                    </tbody>
                    <tfoot></tfoot>
                </table>
                <div class="ribbed-crimson" style="height: 2px;"></div>
                    <div class="input-control textarea">
                        <legend> TEXTO PROMOCIONES </legend>
                        <textarea name="txtCuerpoEmail" id="txtCuerpoEmail" tabindex="9" style="resize:none; width: 800px;" rows="12" >


- ВСЕ ЦЕНЫ ВКЛЮЧАЮТ НАШУ КОМИССИЮ
- ПРЕДЛОЖЕНИЕ ДЕЙСТВИТЕЛЬНО ДО ОКОНЧАНИЯ СТОКА
                        </textarea>
                    </div>
                    <div>
                        <a href="javascript: enviar_email();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="">ENVIAR EMAIL</a>
                    </div>                
            </div>
        </div>
        <div style="position:absolute;">
            <div class="aida" style="position:absolute; width: 380px; margin-top: 25px; margin-left:885px; height: <?php echo $campos[$ultimo_indice]+48;?>px; ">
                <legend style="position: absolute; width: 355px;"><i class="icon-plus fg-crimson"></i> Ingreso / Modificación</legend>
                <form name="frmPrincipal" target="_self" method="post">
                    <fieldset  style="position: absolute">
    <!-- Linea 1 -->
                        <label style="position: absolute; margin-top: <?php echo $titulo[1];?>px;">Código</label>                  
                        <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $campos[1];?>px;">
                            <input name="txtCodigoPromocion" id="txtCodigoPromocion" style="width: 240px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                        </div>
    <!-- Linea 2 -->
                        <label style="position: absolute; width: 300px; margin-top: <?php echo $titulo[2];?>px;">Nombre</label>
                        <div class="input-control text" data-role="input-control text" style="position: absolute; width: 335px; margin-top: <?php echo $campos[2];?>px;">
                            <input name="txtNombrePromocion" id="txtNombreNombrePromocion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el nombre del subscriptor">
                                <button class="btn-clear"></button>
                        </div>    
    <!-- Linea 3 -->
                        <label style="position: absolute; width: 300px; margin-top: <?php echo $titulo[3];?>px;">Teléfono</label>
                        <div class="input-control text" data-role="input-control text" style="position: absolute; width: 335px; margin-top: <?php echo $campos[3];?>px;">
                            <input name="txtTelefonoPromocion" id="txtTelefonoPromocion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el teléfono del subscriptor">
                                <button class="btn-clear"></button>
                        </div> 
    <!-- Linea 4 -->
                        <label style="position: absolute; width: 300px; margin-top: <?php echo $titulo[4];?>px;">Email</label>
                        <div class="input-control text" data-role="input-control text" style="position: absolute; width: 335px; margin-top: <?php echo $campos[4];?>px;">
                            <input name="txtEmailPromocion" id="txtNombreMatxtEmailPromocionrcacion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el email del subscriptor">
                                <button class="btn-clear"></button>
                        </div> 
    <!-- Linea 5 -->
                        <label style="position: absolute; width: 300px; margin-top: <?php echo $titulo[5];?>px;">Lugar</label>
                        <div class="input-control text" data-role="input-control text" style="position: absolute; width: 335px; margin-top: <?php echo $campos[5];?>px;">
                            <input name="txtLugarPromocion" id="txtLugarPromocion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Escriba el lugar del subscriptor">
                                <button class="btn-clear"></button>
                        </div> 
    <!-- Linea 6 -->
                        <label style="position: absolute; width: 300px; margin-top: <?php echo $titulo[6];?>px;">Fecha</label>
                        <div class="input-control text" data-role="input-control text" style="position: absolute; width: 335px; margin-top: <?php echo $campos[6];?>px;">
                            <input name="txtFechaPromocion" id="txtFechaPromocion" tabindex="<?php echo $tabindex++;?>" type="text" placeholder="Campo de lectura" readonly>
                                <button class="btn-clear"></button>
                        </div>                         
     <!-- Linea 7 -->                    
                        <div>
                            <a href="javascript: boton_nuevo(0);" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[7];?>px; margin-left:185px;" tabindex="20">Nuevo</a>
                            <a href="javascript: ingresar_registro();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $campos[7];?>px; margin-left:265px;" tabindex="21">Ingresar</a>
                        </div>
                    </fieldset>
                </form>  
            </div>
        </div>
    </body>
</html>
<?php
mysqli_close($link);
?>