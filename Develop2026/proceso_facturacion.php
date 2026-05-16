<?php
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
// CHEQUEO PERMISOS
verifica_permisos_url($url_actual,$_SESSION['s_codigo']);
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);
        
//Generación de posiciones para campos y titulos
$titulo = [1=>"60",2=>"130",3=>"200",4=>"270",5=>"340",6=>"410",7=>"480",8=>"550",9=>"620",10=>"690",11=>"760",12=>"830"];
$campos = [1=>"90",2=>"160",3=>"230",4=>"300",5=>"370",6=>"440",7=>"510",8=>"580",9=>"650",10=>"720",11=>"790",12=>"860"];
$lineas = [1=>"60",2=>"93",3=>"126",4=>"159",5=>"192",6=>"225",7=>"258",8=>"291",9=>"324",10=>"357",11=>"390",12=>"423",13=>"456",14=>"489",15=>"522",16=>"555",17=>"588"];
$ultimo_indice = 11;
$ultimo_indice_2 = 16;
$tamano_texto = 11;
$padding_tabla_aida = 0;
$estilo_tabla_aida = "padding: ".$padding_tabla_aida."px; font-size:".$tamano_texto."px;";
$estilo_tabla_factura = "padding: ".$padding_tabla_aida."px; font-size:".($tamano_texto+2)."px;";
$estilo_tabla_total = "padding: ".$padding_tabla_aida."px; font-size:".($tamano_texto+2)."px;";
$onload = "";
$tabindex = 10;
global $url_sitio;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <?php
        include("css.php");
        ?>
        <title>Divasoft - Consolidación</title>
        <meta http-equiv=”refresh” content=”10; URL=http://www.blogodisea.com” />
    </head>
    <script language="javascript" src="js_controles_especiales.js"></script>
    <script language="javascript">

    </script>
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>     
        
        <div class="ingreso_facturas" style="position:absolute;">
            <div class="facturas">
                <div class="aida" style="width: 795px; margin-top: 25px; margin-left:10px; height: 30px;">
                    <div class="ribbed-crimson" style="height: 2px;"></div> 
                    <i class="icon-attachment fg-crimson" style="font-size: 25px"></i> FACTURAS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                    <div class="input-control text" data-role="input-control text" style = "width: 125px; font-size:<?php echo $tamano_texto; ?>px;" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="NUMERO FACTURA" onkeypress="return ce_solo_numeros(event,this);" onblur="(isNaN(parseFloat(this.value))) ? this.value = 0 : this.value = parseFloat(this.value);"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
                    <div class="input-control text" style = "width: 100px; font-size:<?php echo $tamano_texto; ?>px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" type="text" placeholder="FECHA" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px">
                        <button class="btn-date fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
                    <div class="input-control text" data-role="input-control text" style = "width: 125px; font-size:<?php echo $tamano_texto; ?>px;" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="FINCA"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
                    <a href="javascript: alert('hola');" class="icon-plus button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange"> Ingresar factura</a>
                </div>
            </div>
        </div>      
        <div class="ingreso_detalles" style="position: absolute;">
            <div class="detalles"  style="position: absolute;">
                <div class="aida" style="position: absolute; width: 795px; margin-top: 100px; margin-left:10px; height: 90px;">
                    <div class="ribbed-crimson" style="position: absolute; height: 2px; width: 760px" ></div> 
<!--DETALLES  -->   <?php $xt=0; $yt=0; $at=0; $x=30; $y=7; $a=45;?>                     
                    <i class="icon-clipboard-2 fg-crimson" style="position: absolute; margin-left:<?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; font-size: 25px"></i> <div style="position: absolute; margin-left:<?php echo $x;?>px; margin-top: <?php echo $y;?>px;"> DETALLES</div> 
<!--NO. FACTUR-->   <?php $xt=0; $yt=0; $at=0; $x=30; $y=40; $a=45;?> 
                    <div class="input-control text" data-role="input-control text" style="position: absolute; margin-left:<?php echo $x;?>px; margin-top: <?php echo $y;?>px; width: 125px; " onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "position: absolute; font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="NUMERO FACTURA" onkeypress="return ce_solo_numeros(event,this);" onblur="(isNaN(parseFloat(this.value))) ? this.value = 0 : this.value = parseFloat(this.value);">
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
<!--NO. FULLES-->   <?php $xt=5+165; $yt=40-20; $at=45; $x=0+165; $y=60-20; $a=45;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;"># FB</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="Fulles"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
<!--  VARIED  -->   <?php $xt=54+165; $yt=40-20; $at=45; $x=44+165; $y=60-20; $a=130;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;">Variedad</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="Variedad"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>                       
<!--  TAMAÑO  -->   <?php $xt=180+165; $yt=40-20; $at=45; $x=173+165; $y=60-20; $a=50;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;">Tam</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="Tamaño"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>                    
<!--  TALLOS  -->   <?php $xt=224+165; $yt=40-20; $at=45; $x=221+165; $y=60-20; $a=55;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;">Tallos</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="Tallos"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>   
<!--  $ TALLO -->   <?php $xt=269+165; $yt=40-20; $at=45; $x=269+165; $y=60-20; $a=55;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;">$ Tallo</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="$ Tallo"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>  
<!--  $ TOTAL -->   <?php $xt=325+165; $yt=40-20; $at=45; $x=314+165; $y=60-20; $a=75;?>  
                    <div style = "position: absolute; margin-left: <?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; width: <?php echo $at;?>px; font-size:<?php echo $tamano_texto+3; ?>px;">$ Total</div>                     
                    <div class="input-control text" data-role="input-control text" style = "position: absolute; margin-left: <?php echo $x;?>px;  margin-top: <?php echo $y;?>px; width: <?php echo $a;?>px;">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="$ Total"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>                      
<!--BOTON-->        <?php $xt=0+165; $yt=0; $at=0; $x=390+165; $y=60-20; $a=0;?>             
                    <a href="javascript: alert('hola');" style="position: absolute; margin-left:<?php echo $x;?>px; margin-top: <?php echo $y;?>px;" class="icon-plus button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange"> Ingresar Detalle</a>
                </div>
            </div>
        </div>
<!--DIV PARA CONSLIDADO-->
        <div class="ingreso_detalles" style="position: absolute;">
            <div class="detalles"  style="position: absolute;">
                <div class="aida" style="position: absolute; width: 795px; margin-top: 195px; margin-left:10px;">
                    <div class="ribbed-magenta" style="position: absolute; height: 2px; width: 760px" ></div> 
<!--CONSOLIDDO-->   <?php $xt=0; $yt=5; $at=0; $x=30; $y=7; $a=750;?>                     
                    <i class="icon-database fg-magenta" style="position: absolute; margin-left:<?php echo $xt;?>px; margin-top: <?php echo $yt;?>px; font-size: 25px"></i> <div style="position: absolute; margin-left:<?php echo $x;?>px; margin-top: <?php echo $y;?>px;"> CONSOLIDADO 
                        <a href="javascript: alert('hola');" style=" margin-left:455px; margin-top: -4px; width: 155px;" class="icon-star-4 button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange"> Generar Factura</a>
</div> 
<!--TABLA FACT-->   <?php $xt=0; $yt=30; $at=0; $x=30; $y=7; $a=45;?>                         
                    <table  style="padding:0px; margin-top: <?php echo $yt;?>px; ">
                        <tbody>
                            <tr>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="30">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="600"><i title="EDITAR PEDIDO" class="icon-forrst fg-crimson"></i><strong>FINCA:</strong> ROSA PRIMA / ROSA PRIMA
                                    </td>
                                <td class="text-right" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="200">
                                    <a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a></td>                          
                            </tr>
                            <tr>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="30"></td>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="600"><i title="EDITAR PEDIDO" class="icon-attachment fg-crimson"></i><strong>FACTURA:</strong> 000 - 128831 &nbsp;&nbsp;<i title="EDITAR PEDIDO" class="icon-calendar fg-crimson"></i><strong>FECHA:</strong> 2015-08-17 &nbsp;&nbsp;
                                <td class="text-right" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="200"></td>                          
                            </tr>                            
                        </tbody>
                        <tfoot></tfoot>
                        </table> 
    <!--TABLA DETA-->   <table class="table hovered" style="padding:0px;">
                        <thead>
                            <tr>
                                <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="100px"> </th> 
                                <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px"><strong>FB</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="200px"><strong>VARIEDAD</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="40px"><strong>L</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="20px"><strong>T</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="30px"><strong>CxT</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="35px"><strong>CxTxC</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="20px"><strong></strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="100px"><strong>Total</strong></th>
                                <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>"></th> 
                            </tr> 
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="100px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">10.5</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="200px">CREAM DE LA CREAM</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">90</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="20px">650</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="30px">0.40</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="35px">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; margin-top: -1px; margin-left: -17px; width: 35px; height: 21px;">
                                         <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="font-size: 11px; color: crimson; display: block; font-weight: bold;"  >   
                                    </div>                                
                                </td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="20px">
                                    <a style="position: absolute; margin-top: 3px; margin-left: 2px;" href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-plus-2 fg-red"></i></a>                                  
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="100px"><strong>$260.00</strong></td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">
                                    <a href="proceso_consolidados.php"><i title="CONSOLIDAR" class="icon-tab fg-darkCyan"></i></a>
                                    &nbsp;<a href="javascript: funcion2('1')"><i title="FACTURAR" class="icon-box-remove fg-crimson"></i></a>
                                    &nbsp;&nbsp;&nbsp;
                                    &nbsp;&nbsp;<a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a>
                                </td>      
                            </tr>                             
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="100px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">10.5</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="200px">CREAM DE LA CREAM</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">90</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="20px">650</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="30px">0.40</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="35px">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; margin-top: -1px; margin-left: -17px; width: 35px; height: 21px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="font-size: 11px; color: crimson; display: block; font-weight: bold;"  >   
                                    </div>                                
                                </td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="20px">
                                    <a style="position: absolute; margin-top: 3px; margin-left: 2px;" href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-plus-2 fg-red"></i></a>                                  
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="100px"><strong>$260.00</strong></td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">
                                    <a href="proceso_consolidados.php"><i title="CONSOLIDAR" class="icon-tab fg-darkCyan"></i></a>
                                    &nbsp;<a href="javascript: funcion2('1')"><i title="FACTURAR" class="icon-box-remove fg-crimson"></i></a>
                                    &nbsp;&nbsp;&nbsp;
                                    &nbsp;&nbsp;<a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a>
                                </td>      
                            </tr>    
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="100px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">10.5</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="200px">CREAM DE LA CREAM</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px">90</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="20px">650</td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="30px">0.40</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="35px">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; margin-top: -1px; margin-left: -17px; width: 35px; height: 21px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="font-size: 11px; color: crimson; display: block; font-weight: bold;"  >   
                                    </div>                                
                                </td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="20px">
                                    <a style="position: absolute; margin-top: 3px; margin-left: 2px;" href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-plus-2 fg-red"></i></a>                                  
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="100px"><strong>$260.00</strong></td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">
                                    <a href="proceso_consolidados.php"><i title="CONSOLIDAR" class="icon-tab fg-darkCyan"></i></a>
                                    &nbsp;<a href="javascript: funcion2('1')"><i title="FACTURAR" class="icon-box-remove fg-crimson"></i></a>
                                    &nbsp;&nbsp;&nbsp;
                                    &nbsp;&nbsp;<a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                    <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a>
                                </td>      
                            </tr>                             
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="100px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="40px"><strong></strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="200px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="40px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="20px"><strong></strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="30px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="35px"></td>                                
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="20px"><strong></strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="100px"></td>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>"></td>      
                            </tr>
                        </tfoot>
                    </table>
    <!--TABLA TOTA-->   <?php $xt=0; $yt=-37; $at=0; $x=30; $y=7; $a=45;?>                         
                    <table  style="padding:0px; margin-top: <?php echo $yt;?>px; ">
                        <tfoot> 
                            <tr>
                                <th class="text-center" style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="100px"> </th> 
                                <th class="text-center" style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="40px"><strong>25</strong></th>
                                <th class="text-left"   style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="200px"><strong></strong></th>
                                <th class="text-right"   style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="40px"><strong></strong></th>
                                <th class="text-right"   style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="40px"><strong>2025</strong></th>
                                <th class="text-right"   style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="40px"><strong></strong></th>
                                <th class="text-right"   style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="100px"><strong>$1635.00</strong></th>
                                <th class="text-center" style="<?php echo $estilo_tabla_total; ?> background-color:darkseagreen ;" width="200px"></th> 
                            </tr> 
                        </tfoot> 
                        </table>
                </div>
            </div>
        </div>

        <div style="position:absolute;">
            <div class="aida" style="position:absolute; width: 430px; margin-top: 25px; margin-left:820px; height: <?php echo $lineas[$ultimo_indice]+85;?>px; ">
                <legend style="position: absolute; width: 400px;"><i class="icon-database fg-crimson"></i> Consolidado</legend>
                <form name="frmPrincipal" target="_self">
                    <input type="hidden" name="txtCodigoPagoS" id="txtCodigoPagoS" value=""></input>
                    <fieldset style="position: absolute">
    <!--Linea 1-->
                        <label style="position: absolute; margin-top: <?php echo $lineas[1];?>px; width: 200px; "><i class="icon-database fg-crimson"></i> Consolidado: </label>     
                        <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $lineas[1];?>px; margin-left:120px;">
                            <input style="width: 240px" name="txtCodigoProducto" READONLY value="0" type="text" data-popover="popover" data-popover-position="left" data-popover-text="El código se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" placeholder="Escriba el código del producto">
                        </div>
    <!--Linea 2--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[2];?>px; width: 200px;"><i class="icon-user-2 fg-crimson"></i> Cliente: </label>                    
                        <?php input_german(240,34,$lineas[2],120,"idCliente",1,"Escriba el cliente","proveedor",$tabindex++);?>
    <!--Linea 3--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[3];?>px; width: 200px;"><i class="icon-clubs fg-crimson"></i> Marcación: </label>                    
                        <?php input_german(240,34,$lineas[3],120,"idMarcacion",1,"Escriba la marcación","proveedor",$tabindex++);?>
     <!--Linea 4--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[4];?>px; width: 200px;"><i class="icon-bus fg-crimson"></i> Truck: </label>                    
                        <?php input_german(240,34,$lineas[4],120,"idTruck",1,"Escriba el Truck","proveedor",$tabindex++);?> 
     <!--Linea 5--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[5];?>px; width: 200px;"><i class="icon-cube fg-crimson"></i> Carguera: </label>                    
                        <?php input_german(240,34,$lineas[5],120,"idCarguera",1,"Escriba la carguera","proveedor",$tabindex++);?> 
     <!--Linea 6--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[6];?>px; width: 200px;"><i class="icon-clock fg-crimson"></i> Fecha Vuelo:</label>                    
                        <div class="input-control text" style="position: absolute; width: 240px; margin-top: <?php echo $lineas[6];?>px; margin-left:120px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom" onChange="">
                            <input name="txtFechaDesde" id="txtFechaDesde" type="text" placeholder="Escriba la fecha del vuelo" style = "">
                            <button class="btn-date fg-crimson" tabindex="1"></button>
                        </div>                 
     <!--Linea 7--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[7];?>px; width: 240px;"><i class="icon-comments-5 fg-crimson"></i> Observación: </label>
                        <div class="input-control textarea">
                            <textarea name="txtObservaciones" id="txtObservaciones" tabindex="<?php echo $tabindex++;?>" style="resize:none; position: absolute; width: 240px; margin-top: <?php echo $lineas[7];?>px; margin-left:120px; height: 10px;"></textarea>
                        </div>  
    <!--Linea 10-->
                        <label style="position: absolute; margin-top: <?php echo $lineas[10];?>px; width: 200px; "><i class="icon-bookmark fg-crimson"></i> AWB: </label>     
                        <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $lineas[10];?>px; margin-left:120px;">
                            <input style="width: 240px" name="txtCodigoProducto" value="0" type="text" data-popover="popover" data-popover-position="left" data-popover-text="El código se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" placeholder="Escriba el código del producto">
                        </div>                    
    <!-- Linea 11 --> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[11];?>px; width: 240px;"><i class="icon-flickr fg-crimson"></i> Status: </label>
                        <div class="input-control radio default-style" data-role="input-control" style="position: absolute; width: 120px; margin-top: <?php echo $lineas[11];?>px; margin-left:120px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS1" name="txtTpoPagoS" value = "1" checked /><span class="check"></span>Abierto<i title="ABIERTO" class="icon-sun-3 fg-yellow"></i></label>
                        </div>
                        <div class="input-control radio  default-style" data-role="input-control" style="position: absolute; width: 150px; margin-top: <?php echo $lineas[11];?>px; margin-left:215px;">
                            <label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS2" name="txtTpoPagoS" value = "2" /><span class="check"></span>Cerrado<i title="CERRADO" class="icon-moon-2 fg-black"></i></label>
                        </div>          
    <!-- Linea 12 --> 
                        <div>
                            <a href="javascript: boton_nuevo();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $lineas[12];?>px; margin-left:240px;" tabindex="<?php echo $tabindex++;?>">Nuevo</a>
                            <a href="javascript: boton_ingresa_pago();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $lineas[12];?>px; margin-left:315px;" tabindex="<?php echo $tabindex++;?>">Ingresar</a>
                        </div>
    <!-- Línea recuadro izquierdo --> 
                        <input type="text" id="lblPago" READONLY style="position: absolute; border:transparent; background-color: transparent; color:brown; font-size:20px; width: 120px; margin-top: <?php echo $lineas[5];?>px;" value="" ></input>
                        <input type="text" id="idPagoEditado" READONLY style="position: absolute; alignment-adjust:central; border:transparent; background-color: transparent; color:brown; font-size:25px; width: 120px; margin-top: <?php echo $lineas[6];?>px;" value=""></input>
                    </fieldset>
                </form>       
            </div>
        </div>

       <div style="position:absolute;">
            <div class="aida" style="position:absolute; width: 430px; margin-top: 510px; margin-left:820px; height: <?php echo $lineas[2]+85;?>px; ">
                <legend style="position: absolute; width: 400px;"><i class="icon-tab fg-darkCyan"></i> Remaracion</legend>
                <form name="frmRemarcacion" target="_self">
                    <fieldset style="position: absolute">
    <!--Linea 1-->
                        <label style="position: absolute; margin-top: <?php echo $lineas[1];?>px; width: 200px; "><i class="icon-clipboard-2 fg-darkCyan"></i> Detalle: </label>
                        <div class="input-control text" data-role="input-control" style="position: absolute; margin-top: <?php echo $lineas[1];?>px; margin-left:120px;">
                            <input style="width: 240px" name="txtCodigoProducto" READONLY value="0" type="text" data-popover="popover" data-popover-position="left" data-popover-text="El código se llena automáticamente" data-popover-background="bg-red" data-popover-color="fg-white" data-popover-mode="focus" placeholder="Escriba el código del producto">
                        </div>
    <!--Linea 2--> 
                        <label style="position: absolute; margin-top: <?php echo $lineas[2];?>px; width: 200px;"><i class="icon-attachment fg-darkCyan"></i> Factura: </label>                    
                        <?php input_german(240,34,$lineas[2],120,"idCliente",1,"Escriba el cliente","proveedor",$tabindex++);?>

    <!-- Linea 12 --> 
                        <div>
                            <a href="javascript: boton_nuevo();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $lineas[4]-20;?>px; margin-left:225px;" tabindex="<?php echo $tabindex++;?>">Cancelar</a>
                            <a href="javascript: boton_ingresa_pago();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="position: absolute; margin-top: <?php echo $lineas[4]-20;?>px; margin-left:315px;" tabindex="<?php echo $tabindex++;?>">Asignar</a>
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