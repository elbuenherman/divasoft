<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);

$arreglo_registros = select_usuarios(NULL);
$total_registros = count($arreglo_registros);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$ultimo_indice = 7;
$tamano_texto = 14;
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
        function check_permisos(objeto,codigo_permiso)
            {
            var codigo_usuario = window.document.frmPrincipal.txtCodigoUsuario.value;   
            var status  = 0;
            if(objeto.checked == true) status = 1;
            var url = "funciones_ajax.php?funcion=check_permisos&parametro1=" + codigo_permiso +"&parametro2=" + codigo_usuario +"&parametro3=" + status; 
            $.get(url, function (data, status){;});
            }
         function copia_permisos(codigo_perfil)
            {
            var codigo_usuario = window.document.frmPrincipal.txtCodigoUsuario.value;
            if(codigo_usuario==0) 
                {
                alert("Seleccione un usuario primero");
                return 0;
                }
            var r = confirm("Está seguro que desea copiar los permisos al usuario seleccionado?");
            if (r == false) return 0;
            var url = "funciones_ajax.php?funcion=copia_permisos&parametro1=" + codigo_perfil+"&parametro2=" + codigo_usuario;   
            $.get(url, function (data, status) {;});
            var url1 = "funciones_ajax.php?funcion=carga_permisos_usuarios&parametro1=" + codigo_usuario;   
            $.get(url1, function (data1, status1)
                {
                $(".german").html(data1);
                });            
            }         
        function pone_numero_en_codigo(codigo)
            {
            window.document.frmPrincipal.txtCodigoUsuario.value = codigo;               
            }
        function ensera_formulario()
            {
            window.document.frmPrincipal.txtNombreUsuario.value = "";             
            pone_numero_en_codigo(0);                
            }
        function devuelve_registro(codigo,descripcion)
            {
            ensera_formulario();
            pone_numero_en_codigo(codigo);
            window.document.frmPrincipal.txtNombreUsuario.value = descripcion;
            var url = "funciones_ajax.php?funcion=carga_permisos_usuarios&parametro1=" + codigo;   
            $.get(url, function (data, status)
                {
                $(".german").html(data);
                });
            }
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
                        $codigo_usuario = $arreglo_registros[$j]['codigo_usuario'];
                        $nombre_apellido_usuario = $arreglo_registros[$j]['apellido_usuario']." ".$arreglo_registros[$j]['nombre_usuario'];
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $codigo_usuario; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_apellido_usuario; ?></td>
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_usuario.",'".$nombre_apellido_usuario."'"; ?>)"><i title="Modificar" class="icon-arrow-right-3 fg-red"></i></a>
                            <a href="javascript: copia_permisos(<?php echo $codigo_usuario; ?>)"><i title="Cargar Perfil" class="icon-forward fg-violet"></i></a>
                            </td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>

<!--            <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px; height: <?php echo $campos[$ultimo_indice]+80;?>px; ">-->
                <div class="herman" style="position:absolute; width: 660px; margin-top: 25px; margin-left:585px;">
                <legend style="position: absolute; width: 570px;">Usuario / Permisos</legend>
                <form name="frmPrincipal" target="_self">
                    <fieldset >
    <!-- Linea 1 -->                
                        <div class="input-control text" data-role="input-control" style="margin-top: <?php echo $titulo[1];?>px;">
                            <input name="txtCodigoUsuario" style="width: 100px" type="text" data-popover="popover" data-popover-position="right" data-popover-text="Este campo se llena automáticamente, Haga click en una de las opciones" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="0" readonly="readonly">
                            <input name="txtNombreUsuario" style="width: 453px" type="text" data-popover="popover" data-popover-position="left" data-popover-text="Este campo se llena automáticamente, Haga click en una de las opciones" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" value="" readonly="readonly">
                        </div>                        
    
                    </fieldset>
                </form>        
            </div>
        <div class="german"><div class="herman" style="position:absolute; width: 660px; margin-top: 188px; margin-left:585px;">
                Seleccione un usuario .... 
            </div></div>
    </body>
</html>
<?php
mysqli_close($link);
?>