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
$tamano_texto = 12; 
        
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
        <title>Divasoft - Consola de Proveedores Extendida</title>
    </head>
    <script language="javascript">
        function devuelve_registro(codigo)
            {
            window.document.frmPrincipal.parametro1.value=codigo;
            window.document.frmPrincipal.submit();
            }
    </script>
    <body class="metro">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>
        <div class="herman" style="width: 3000px; margin-top: 25px; margin-left:20px; position:absolute;">
            <legend><i class="icon-forrst fg-crimson"></i> Listado de proveedores</legend>
            <table class="table hovered">
                <thead>
                    <tr>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Est.</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Nombre</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Tipo</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Nombre comercial</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Telf</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Dirección prov.</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">País</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Ciudad</th>   
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Web</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Observaciones</th> 
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Vendedor</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Email V.</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Telef. V.</th>  
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Skype V.</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Banco</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Tipo Cta</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Cuenta</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Beneficiario</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Tipo ID</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">ID Benf.</th>
                        <th class="text-left" style="font-size:<?php echo $tamano_texto; ?>px">Email Pagos</th>
                        <th class="text-center" style="font-size:<?php echo $tamano_texto; ?>px">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    for ($j = 1; $j <= $total_registros; $j++) 
                        {
                        $codigo_proveedor = $arreglo_registros[$j]['codigo_proveedor'];
                        $nombre_proveedor = $arreglo_registros[$j]['nombre_proveedor'];
                        $nombre_comercial_proveedor = $arreglo_registros[$j]['nombre_comercial_proveedor'];
                        $codigo_tipo_proveedor = $arreglo_registros[$j]['codigo_tipo_proveedor']; 
                        $telefono_proveedor = $arreglo_registros[$j]['telefono_proveedor'];   
                        $codigo_banco_proveedor = $arreglo_registros[$j]['codigo_banco_proveedor'];   
                        $tipo_cuenta_banaria_proveedor = $arreglo_registros[$j]['tipo_cuenta_banaria_proveedor'];   
                        $cuenta_bancaria_proveedor = $arreglo_registros[$j]['cuenta_bancaria_proveedor'];
                        $nombre_beneficiario_cuenta_bancaria_proveedor = $arreglo_registros[$j]['nombre_beneficiario_cuenta_bancaria_proveedor'];   
                        $tipo_identificacion_cuenta_bancaria_proveedor = $arreglo_registros[$j]['tipo_identificacion_cuenta_bancaria_proveedor'];   
                        $identificacion_cuenta_bancaria_proveedor = $arreglo_registros[$j]['identificacion_cuenta_bancaria_proveedor'];   
                        $nombre_vendedor_proveedor = $arreglo_registros[$j]['nombre_vendedor_proveedor'];
                        $email_vendedor_proveedor = $arreglo_registros[$j]['email_vendedor_proveedor'];   
                        $telefono_vendedor_proveedor = $arreglo_registros[$j]['telefono_vendedor_proveedor'];   
                        $skype_vendedor_proveedor = $arreglo_registros[$j]['skype_vendedor_proveedor'];   
                        $direccion_proveedor = $arreglo_registros[$j]['direccion_proveedor'];
                        $codigo_ciudad_proveedor = $arreglo_registros[$j]['codigo_ciudad_proveedor'];   
                        $codigo_pais_proveedor = $arreglo_registros[$j]['codigo_pais_proveedor'];   
                        $direccion_web_proveedor = $arreglo_registros[$j]['direccion_web_proveedor'];   
                        $observaciones_proveedor = $arreglo_registros[$j]['observaciones_proveedor'];
                        $email_pagos_proveedor = $arreglo_registros[$j]['email_pagos_proveedor'];
                        $nombre_tipo_cuenta_banaria_proveedor = $arreglo_registros[$j]['nombre_tipo_cuenta_banaria_proveedor'];
                        $nombre_tipo_identificacion_cuenta_bancaria_proveedor = $arreglo_registros[$j]['nombre_tipo_identificacion_cuenta_bancaria_proveedor'];
                        $nombre_banco = $arreglo_registros[$j]['nombre_banco'];
                        $nombre_pais = $arreglo_registros[$j]['nombre_pais'];
                        $nombre_ciudad = $arreglo_registros[$j]['nombre_ciudad'];
                        $nombre_tipo_proveedor = $arreglo_registros[$j]['nombre_tipo_proveedor'];
                        $status = $arreglo_registros[$j]['status'];
                        $color_rating = "fg-red";
                        if($status=='3') $color_rating="fg-green";
                    ?>
                    <tr>
                            <td class="text-center" style="font-size:<?php echo $tamano_texto; ?>px"><?php //echo $codigo_proveedor; ?>
                                <a href="javascript: devuelve_registro(<?php echo $codigo_proveedor; ?>)"><div class="rating small <?php echo $color_rating;?>" data-role="rating" data-static="true" data-score="<?php echo $status; ?>" data-stars="3" data-show-score="false"></div></a>              
                            </td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_tipo_proveedor; ?></td>                            
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_comercial_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $telefono_proveedor; ?></td>    
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $direccion_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_pais; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_ciudad; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $direccion_web_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $observaciones_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_vendedor_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $email_vendedor_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $telefono_vendedor_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $skype_vendedor_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_banco; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_tipo_cuenta_banaria_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $cuenta_bancaria_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_beneficiario_cuenta_bancaria_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $nombre_tipo_identificacion_cuenta_bancaria_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $identificacion_cuenta_bancaria_proveedor; ?></td>
                            <td class="right" style="font-size:<?php echo $tamano_texto; ?>px"><?php echo $email_pagos_proveedor; ?></td>                            
                            <td class="text-center"><a href="javascript: devuelve_registro(<?php echo $codigo_proveedor; ?>)"><i title="Modificar" class="icon-pencil fg-brown"></i></a> </td>
                        <?php
                        }
                        ?>
                    </tr>
                </tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <a href="consola_proveedores.php"><button style="position: absolute; margin-top: 27px; margin-left: 22px;" class="image-button danger">Menos información<i class="icon-fullscreen-exit-alt bg-red"></i></button></a>
        <form name='frmPrincipal' action="consola_proveedores.php"><input type="hidden" name="parametro1"></form>
    </body>
</html>
<?php
mysqli_close($link);
?>