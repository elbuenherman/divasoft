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
$ultimo_indice = 8;
$ultimo_indice_2 = 16;
$tamano_texto = 11;
$padding_tabla_aida = 0;
$estilo_tabla_aida = "padding: ".$padding_tabla_aida."px; font-size:".$tamano_texto."px;";
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
        <title>Divasoft - Ingreso de Créditos de Clientes</title>
        <meta http-equiv=”refresh” content=”10; URL=http://www.blogodisea.com” />
    </head>
    <script language="javascript" src="js_controles_especiales.js"></script>
    <script language="javascript">
    function envia_email(codigo)
        {
        var mensaje = "Está seguro que desea enviar el email de confirmación del pago CODIGO:"+codigo;
        var r = confirm(mensaje);
        if (r == true)
            {
            var url = "funciones_ajax.php?funcion=devuelve_pago_proceso&parametro1=" + codigo;   
            var xmlDoc = loadXMLDoc(url);
            if((typeof(x = xmlDoc.getElementsByTagName("valor_procesado")[0].childNodes[0])=== "object")) var valor_procesado = x.nodeValue;
            if(!isNaN(x.nodeValue.length)==true)
                {               
                if((typeof(x = xmlDoc.getElementsByTagName("tipo_pago")[0].childNodes[0])=== "object")) var tipo_pago = x.nodeValue; 
                if(tipo_pago == 1) tipo_pago = "CHEQUE";
                if(tipo_pago == 2) tipo_pago = "TRANSFERENCIA";
                if(tipo_pago == 3) tipo_pago = "OTROS"; 
                if((typeof(x = xmlDoc.getElementsByTagName("nombre_proveedor")[0].childNodes[0])=== "object")) var nombre_proveedor = x.nodeValue;           
                if((typeof(x = xmlDoc.getElementsByTagName("email_pagos_proveedor")[0].childNodes[0])=== "object")) var email_pagos_proveedor = x.nodeValue;
                mensaje = "El pago con los datos:\nCODIGO: "+codigo+"\nPROVEEDOR: "+nombre_proveedor+"\nFORMA DE PAGO: "+tipo_pago+"\nVALOR: $"+valor_procesado+" USD \n\nSe enviará a los siguientes destinatarios: (Confirmar)";
                var destinatarios =  email_pagos_proveedor + "; sales@divaflor.com; herman.diener@divaflor.com; contabilidad@divaflor.com; compras@divaflor.com";
                destinatarios=prompt(mensaje,destinatarios);
                if(destinatarios == null) return;
                
                var url_mail = "phpmailer/divamail/mail.php?codigo="+codigo+"&destinatarios="+destinatarios;
                window.open(url_mail, "Popup", "location=0,status=0,scrollbars=0, resizable=0, directories=0, toolbar=0, titlebar=1, width=400, height=400");               
                }                             
            }
        }
    function carga_pendientes(opcion)
        {
        boton_nuevo();
        var aux = parseInt(1000*(Math.random()));
        var url = "funciones_ajax.php?funcion=carga_pagos_pendientes&aux="+aux+"&parametro1="+opcion;
        var data;
        espera(0);
        $.get(url, function (data, status)
            {
            $(".pendientes").html(data);
            });      
        carga_procesados(2);    
        }
    function carga_procesados(opcion)
        {
        var fecha_inicial = document.getElementById("txtFechaDesde").value;
        var fecha_final = document.getElementById("txtFechaHasta").value;
        var url = "funciones_ajax.php?funcion=carga_pagos_procesados&parametro1="+fecha_inicial+"&parametro2="+fecha_final+"&parametro3="+opcion;
        $.get(url, function (data, status)
            {
            $(".procesados").html(data);
            });
        }
    function elimina_pago(codigo)
        {   
        var r = confirm("Está seguro que desea eliminar el pago selcccionado");
        if (r == true)
            {
            var url = "funciones_ajax.php?funcion=elimina_pago&parametro1=" + codigo;
            var obj_ajax = $.get(url, function (data, status) {;});
            obj_ajax.success(function(){
   boton_nuevo();
            refresca_pendientes_con_filtro();
});
            
            }      
        }
    function devuelve_pago(codigo)
        {
        boton_nuevo();
        var url = "funciones_ajax.php?funcion=devuelve_pago&parametro1=" + codigo;   
        var xmlDoc = loadXMLDoc(url);
        if((typeof(x = xmlDoc.getElementsByTagName("nombre_proveedor")[0].childNodes[0])=== "object")) document.getElementById("idProveedor").value = x.nodeValue;
        if(!isNaN(x.nodeValue.length)==true)
            {        
            if((typeof(x = xmlDoc.getElementsByTagName("valor_solicitado")[0].childNodes[0])=== "object")) document.getElementById("txtValorSolicitado").value = x.nodeValue;        
            if((typeof(x = xmlDoc.getElementsByTagName("observaciones")[0].childNodes[0])=== "object")) document.getElementById("txtObservaciones").value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("tipo_pago")[0].childNodes[0])=== "object")) var tipo_pago = x.nodeValue; 
            if(tipo_pago == 1) document.getElementById("txtTpoPagoS1").checked = true;
            if(tipo_pago == 2) document.getElementById("txtTpoPagoS2").checked = true;
            if(tipo_pago == 3) document.getElementById("txtTpoPagoS3").checked = true; 
            document.getElementById("lblPago").value="Pago No:"
            document.getElementById("idPagoEditado").value=codigo;
            document.getElementById("idProveedor").focus();  
            document.getElementById("txtValorSolicitado").focus();
            document.getElementById("txtValorSolicitado").focus();
            }
        else boton_nuevo();
        }
    function boton_borrar()
        {
        document.getElementById("idBanco").focus();
        document.getElementById("idBanco").value="                           ";
        document.getElementById("idBanco").focus();
        window.document.frmSecundario.reset(); 
        }        
    function devuelve_pago_para_proceso(codigo)
        {
        boton_nuevo();
        var url = "funciones_ajax.php?funcion=devuelve_pago_proceso&parametro1=" + codigo;   
        var xmlDoc = loadXMLDoc(url);
        if((typeof(x = xmlDoc.getElementsByTagName("valor_solicitado")[0].childNodes[0])=== "object")) document.getElementById("txtValorProcesado").value = x.nodeValue;
        if(!isNaN(x.nodeValue.length)==true)
            {               
            if((typeof(x = xmlDoc.getElementsByTagName("observaciones")[0].childNodes[0])=== "object")) document.getElementById("txtObservaciones2").value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("tipo_pago")[0].childNodes[0])=== "object")) var tipo_pago = x.nodeValue; 
            if(tipo_pago == 1) document.getElementById("txtTpoPagoP1").checked = true;
            if(tipo_pago == 2) document.getElementById("txtTpoPagoP2").checked = true;
            if(tipo_pago == 3) document.getElementById("txtTpoPagoP3").checked = true; 
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_proveedor")[0].childNodes[0])=== "object")) document.getElementById("txtNombreProveedor").value = x.nodeValue;           
            if((typeof(x = xmlDoc.getElementsByTagName("datos_transferancia")[0].childNodes[0])=== "object")) document.getElementById("txtDatosTransferencia").value = x.nodeValue;
            document.getElementById("txtCodigoPagoP").value = codigo;
            document.getElementById("idBanco").focus();
            }
        else boton_nuevo();
        window.location = "#procesados";
        document.getElementById("idBanco").focus();    
        }
    function devuelve_pago_procesado_para_edicion(codigo)
        {
        boton_nuevo();
        var url = "funciones_ajax.php?funcion=devuelve_pago_proceso_edicion&parametro1=" + codigo;   
        var xmlDoc = loadXMLDoc(url);
        if((typeof(x = xmlDoc.getElementsByTagName("valor_procesado")[0].childNodes[0])=== "object")) document.getElementById("txtValorProcesado").value = x.nodeValue;
        if(!isNaN(x.nodeValue.length)==true)
            {               
            if((typeof(x = xmlDoc.getElementsByTagName("observaciones")[0].childNodes[0])=== "object")) document.getElementById("txtObservaciones2").value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("tipo_pago")[0].childNodes[0])=== "object")) var tipo_pago = x.nodeValue; 
            if(tipo_pago == 1) document.getElementById("txtTpoPagoP1").checked = true;
            if(tipo_pago == 2) document.getElementById("txtTpoPagoP2").checked = true;
            if(tipo_pago == 3) document.getElementById("txtTpoPagoP3").checked = true; 
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_proveedor")[0].childNodes[0])=== "object")) document.getElementById("txtNombreProveedor").value = x.nodeValue; 
            if((typeof(x = xmlDoc.getElementsByTagName("nombre_banco_procesado")[0].childNodes[0])=== "object")) document.getElementById("idBanco").value = x.nodeValue;
            document.getElementById("idBanco").focus();
            if((typeof(x = xmlDoc.getElementsByTagName("datos_transferancia")[0].childNodes[0])=== "object")) document.getElementById("txtDatosTransferencia").value = x.nodeValue;
            if((typeof(x = xmlDoc.getElementsByTagName("comprobante_pago")[0].childNodes[0])=== "object"))  document.getElementById("txtComprobantePago").value = x.nodeValue;
            document.getElementById("txtCodigoPagoP").value = codigo;
            document.getElementById("txtValorProcesado").focus(); 
            }
        else boton_nuevo();
        window.location = "#procesados";
        document.getElementById("txtValorProcesado").focus();    
        }
    function refresca_pendientes_con_filtro()
        {
        var filtro = 4;
        if(document.getElementById("txtFiltroPagoS1").checked == true) filtro = 1;
        if(document.getElementById("txtFiltroPagoS2").checked == true) filtro = 2;
        if(document.getElementById("txtFiltroPagoS3").checked == true) filtro = 3; 
        carga_pendientes(filtro);
        }
    function boton_nuevo()
        {
        boton_borrar();
        document.getElementById("idProveedor").focus();
        document.getElementById("idProveedor").value="                           ";
        document.getElementById("idProveedor").focus();
        window.document.frmPrincipal.reset(); 
        }
    function boton_ingresa_pago()
        {
        var codigo_proveedor = document.getElementById("idProveedorCodigo").value;
        var valor_solicitado = parseFloat(document.getElementById("txtValorSolicitado").value);
        var tipo_pago = 1;
        if(document.getElementById("txtTpoPagoS2").checked) tipo_pago = 2;
        if(document.getElementById("txtTpoPagoS3").checked) tipo_pago = 3;        
        var observaciones = escape(document.getElementById("txtObservaciones").value);
        var codigo_persona_solicita = <?php echo $_SESSION['s_codigo'];?>;
        if(!((!isNaN(valor_solicitado) && isFinite(valor_solicitado)) && valor_solicitado>0))
            { 
            alert("ATENCION: valor ingresado debe ser numérico y mayor que 0");
            document.getElementById("txtValorSolicitado").focus();
            return;
            }
        if(codigo_proveedor==0)
            { 
            alert("ATENCION: Es necesario ingresar un proveedor");
            document.getElementById("idProveedor").focus();
            return;
            } 
        var codigo_pago_editable = document.getElementById("idPagoEditado").value;
        if(codigo_pago_editable.length>0) var url = "funciones_ajax.php?funcion=modifica_pago&parametro1=" + codigo_pago_editable + "&parametro2=" + codigo_proveedor + "&parametro3=" + valor_solicitado + "&parametro4=" + tipo_pago + "&parametro5=" + observaciones + "&parametro6=" + codigo_persona_solicita;
        else var url = "funciones_ajax.php?funcion=ingresa_pago&parametro1=" + codigo_proveedor + "&parametro2=" + valor_solicitado + "&parametro3=" + tipo_pago + "&parametro4=" + observaciones + "&parametro5=" + codigo_persona_solicita;       
        var obj_ajax = $.get(url, function (data, status)
            {;});
        obj_ajax.success(function()
            {
            boton_nuevo();
            refresca_pendientes_con_filtro(); 
            });        
        }
    function boton_pagar()
        {
        var codigo_proveedor = document.getElementById("txtCodigoPagoP").value;
        var codigo_pago = document.getElementById("txtCodigoPagoP").value;
        var valor_procesado = parseFloat(document.getElementById("txtValorProcesado").value);
        var tipo_pago = 1;
        if(document.getElementById("txtTpoPagoP2").checked) tipo_pago = 2;
        if(document.getElementById("txtTpoPagoP3").checked) tipo_pago = 3;        
        var observaciones = escape(document.getElementById("txtObservaciones2").value);
        var codigo_banco = document.getElementById("idBancoCodigo").value;
        if((tipo_pago == 1 || tipo_pago == 2)&&(codigo_banco == 0 || codigo_banco == 7))
            {
            alert("Si la forma de pago es CHEQUE o TRANSFERENCIA, debe necesariamente llenar el campo de BANCO");
            document.getElementById("idBanco").focus();
            return;
            }
        if(tipo_pago == 3&&codigo_banco == 0)
            {
            document.getElementById("idBanco").value = x.nodeValue = "SIN BANCO";
            document.getElementById("idBanco").focus();
            document.getElementById("txtValorProcesado").focus();
            document.getElementById("idBanco").value = x.nodeValue = "SIN BANCO";
            document.getElementById("idBanco").focus();
            document.getElementById("txtValorProcesado").focus();
            alert("En el campo banco se ingresó un valor SIN BANCO, ya que el mismo no fue llenado");
            }       
        var comprobante_pago = escape(document.getElementById("txtComprobantePago").value);
        var codigo_persona_procesa = <?php echo $_SESSION['s_codigo'];?>;
        if(codigo_proveedor==0)
            { 
            alert("ATENCION: Seleccione un pago con el icóno verde en forma de billetes para procesarlo");
            document.getElementById("txtValorProcesado").focus();
        //    return;
            } 
        if(!((!isNaN(valor_procesado) && isFinite(valor_procesado)) && valor_procesado>0))
            { 
            alert("ATENCION: Valor ingresado debe ser numérico y mayor que 0");
            document.getElementById("txtValorProcesado").focus();
            return;
            } 
        if(codigo_banco==0) codigo_banco = 7;
        var url = "funciones_ajax.php?funcion=procesa_pago&parametro1=" + codigo_pago + "&parametro2=" + valor_procesado + "&parametro3=" + tipo_pago + "&parametro4=" + observaciones + "&parametro5=" + codigo_persona_procesa + "&parametro6=" + codigo_banco + "&parametro7=" + comprobante_pago;       
        var obj_ajax = $.get(url, function (data, status){;});
        obj_ajax.success(function()
            {
            boton_nuevo();
            refresca_pendientes_con_filtro(); 
            });
        }
    function retornar_pago(codigo)
        {
        var r = confirm("Está seguro que desea retornar el pago");
        if (r == true)
            {        
            var url = "funciones_ajax.php?funcion=retornar_pago&parametro1=" + codigo;
            var obj_ajax = $.get(url, function (data, status){;});
            obj_ajax.success(function()
                {
                boton_nuevo();
                refresca_pendientes_con_filtro(); 
                });
            }
        }
    </script>
<?php 

  $acd[1]="10"; $acd[2]="40"; $acd[3]="180"; $acd[4]="20"; $acd[5]="30"; $acd[6]="20"; 
  $acd[7]="30"; $acd[8]="50"; $acd[9]="20";  $acd[10]="43"; $acd[11]="55"; $acd[12]="38";
  $color_fondo_modificaciones = $cfm = "oldlace"; 
  $color_letra_modificaciones = $clm = "#dc4fad";
    
?>    
    <body class="metro" onload="<?php echo $onload; ?>">
        <a name="inicio"></a>
        <header class="bg-dark" data-load="barra_navegacion.php"></header>     
        <div class="documento" style="position:absolute;">
            <div class="pendientes">
                <div class="aida" style="width: 530px; margin-top: 25px; margin-left:10px;">
                    <div class="ribbed-crimson" style="height: 2px;"></div>            
                    <i class="icon-attachment fg-crimson" style="font-size: 25px"></i> FACTURAS
<!--FILTROS--> 
<!--1ERA LINEA DE FILTROS-->
                        <div class="input-control radio default-style" data-role="input-control" style="width: 110px; margin-top: 10px; margin-left:0px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS1" name="txtTpoPagoS" value = "1" checked /><span class="check"></span>Sin Crédito
                                <!--<i title="Sin crédito" class="icon-sun-3 fg-yellow"></i>-->
                            </label>
                        </div>
                        <div class="input-control radio default-style" data-role="input-control" style="width: 110px; margin-top: 10px; margin-left:0px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS2" name="txtTpoPagoS" value = "2" /><span class="check"></span>En Proceso</label>
                        </div> 
                        <div class="input-control radio default-style" data-role="input-control" style="width: 150px; margin-top: 10px; margin-left:0px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS2" name="txtTpoPagoS" value = "2" /><span class="check"></span>Procesadas</label>
                        </div>      
<!--2DA LINEA DE FILTROS-->
                    <div class="input-control text" style = "width: 120px; margin-top: 0px; margin-left:30px; font-size:<?php echo $tamano_texto; ?>px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" type="text" placeholder="DESDE" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px">
                        <button class="btn-date fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>                 
                    <div class="input-control text" data-role="input-control text" style = "width: 100px; font-size:<?php echo $tamano_texto; ?>px;" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="FINCA" onkeypress="return ce_solo_numeros(event,this);" onblur="(isNaN(parseFloat(this.value))) ? this.value = 0 : this.value = parseFloat(this.value);"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>
                    <div class="input-control text" data-role="input-control text" style = "width: 80px; font-size:<?php echo $tamano_texto; ?>px;" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="INVOICE"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>   
                    <div class="input-control text" data-role="input-control text" style = "width: 80px; font-size:<?php echo $tamano_texto; ?>px;" onChange="carga_procesados(3);">
                        <input name="txtFechaDesde" id="txtFechaDesde" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px" type="text" placeholder="CLIENTE"> 
                        <button class="btn-clear fg-crimson" tabindex="1" style = "top: 0px;"></button>
                    </div>  
                    <i class="icon-filter fg-crimson" style="font-size: 20px"></i>
<!--3ERA LINEA DE FILTROS--> 
                    <div class="input-control text" style = "width: 120px; margin-top: -15px; margin-left:30px; font-size:<?php echo $tamano_texto; ?>px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom">
                        <input name="txtFechaHasta" id="txtFechaHasta" type="text" placeholder="HASTA" style = "font-size:<?php echo $tamano_texto; ?>px; height:25px">
                        <button class="btn-date fg-crimson" tabindex="2" style="top: 0px;"></button>
                    </div>    
<!--FIN DE FILTROS-->                        
                    <table class="table hovered" style="padding:0px;">
                    <thead>
                        <tr>
                            <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>">FACTURA</th> 
                            <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>">FINCA</th>
                            <th class="text-left" style="<?php echo $estilo_tabla_aida; ?>">INVOICE</th>
                            <th class="text-left" style="<?php echo $estilo_tabla_aida; ?>">CLIENTE</th>
                            <th class="text-left" style="<?php echo $estilo_tabla_aida; ?>">VALOR</th>
                            <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>">Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">123-1234-00</td> 
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">ALMA ROSES</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">10001</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">RENOIR</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">$34,75</td>
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">
                                <a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                <a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-mail-2 fg-brown"></i></a>
                                <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a>
                            </td>      
                        </tr> 
                        <tr>
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">234-1234-99</td> 
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">STAR ROSES</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">91887</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">MISHKA</td>
                            <td class="text-left" style="<?php echo $estilo_tabla_aida; ?>">$101,25</td>
                            <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>">
                                <a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-pencil fg-brown"></i></a>
                                <a href="javascript: funcion2('1')"><i title="EDITAR PEDIDO" class="icon-mail-2 fg-brown"></i></a>
                                <a href="javascript: funcion3('1')"><i title="ELIMINAR PEDIDO" class="icon-remove fg-red"></i></a>
                            </td>     
                        </tr>                         
                    </tbody>
                    <tfoot></tfoot>
                    </table>                       
                    
                </div>
            </div>
        </div>
        <div style="position:absolute;">
            <div class="aida" style="position:absolute; width: 690px; margin-top: 25px; margin-left:545px;">
<div class="ribbed-crimson" style="height: 2px;"></div>                
                <legend style="width: 400px;"><i class="icon-history fg-crimson"></i> Crédito Clientes</legend>
<!--TABLA FACT-->   <?php $xt=0; $yt=-17; $at=0; $x=30; $y=7; $a=45;?>                         
                    <table  style="padding:0px; margin-top: <?php echo $yt;?>px; ">
                        <tbody>
                            <tr>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="0"></td>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="690"><i title="EDITAR PEDIDO" class="icon-forrst fg-crimson"></i><strong>FINCA:</strong> HERRADURA / FLORICOLA LA HERRADURA FLOHERRA S. A.
                                    </td>
                                <td class="text-right" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="0">
                                    </td>                          
                            </tr>
                            <tr>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="0"></td>
                                <td class="text-left" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="690"><i title="EDITAR PEDIDO" class="icon-attachment fg-crimson"></i><strong>FACTURA:</strong> 123456789-0AB &nbsp;&nbsp;<i title="EDITAR PEDIDO" class="icon-calendar fg-crimson"></i><strong>FECHA:</strong> 2015-08-17 &nbsp;&nbsp;
                                <td class="text-right" style="<?php echo $estilo_tabla_factura; ?>; background-color: oldlace;" width="0"></td>                          
                            </tr>                            
                        </tbody>
                        <tfoot></tfoot>
                        </table>    
    <!--TABLA DETA-->   
                    <table class="table hovered" style="padding:0px;">
                        <thead>
                            <tr>
                                <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </th> 
                                <th class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px"><strong>FB</strong></th>
                                <th class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px"><strong>VARIEDAD</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px"><strong>L</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px"><strong>T</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"><strong></strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px"><strong>CxT</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>Total</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"><strong></strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[10];?>px"><strong>Cred T</strong></th>
                                <th class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[11];?>px"><strong>Cred S</strong></th>  
                            </tr> 
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px">10.5</td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">CREAM DE LA CREAM</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">90</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">1650</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.40</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$260.00</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td>      
                            </tr>
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px">4.5</td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">FREEDOM</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">70</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">250</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.55</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$137.50</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td> 
                            </tr> 
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"></td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px"><i title="" class="icon-layers fg-green"></i></td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">EXPLORER</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">70</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">250</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.55</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$137.50</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td>   
                            </tr>        
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px"><i title="" class="icon-layers fg-green"></i></td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">BLACK BACKARA</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">70</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">250</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.55</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$137.50</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td>     
                            </tr>             
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px"><i title="" class="icon-layers fg-green"></i></td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">BLACK MAGIC</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">70</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">250</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.55</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$137.50</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td>    
                            </tr>                              
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px">3</td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px">PINK FLOYD</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px">90</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px">225</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px">0.70</td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$157.50</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;" >
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -17px; width: <?php echo $acd[10];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="0.00" id="txtValorSolicitado"   tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;"  >   
                                    </div>
                                </td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?> border: transparent;">
                                    <div class="input-control text" data-role="input-control text" style="position: absolute; border: solid; border-width: 1px; margin-top: -2px; margin-left: -24px; width: <?php echo $acd[11];?>px; height: 22px;">
                                        <input name="txtValorSolicitado" value="999.99" id="txtValorSolicitado" tabindex="<?php echo $tabindex++;?>" type="text" style="background-color:<?php echo $cfm;?>; font-size: 11px; color:<?php echo $clm;?>; display: block; font-weight: 500;" >
                                    </div>
                                </td>     
                            </tr>                         
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[1];?>px"> </td> 
                                <td class="text-center" style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[2];?>px"><strong>18</strong></td>
                                <td class="text-left"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[3];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[4];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[5];?>px"><strong>1125</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[6];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[7];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[8];?>px"><strong>$5550.00</strong></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[9];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[10];?>px"></td>
                                <td class="text-center"   style="<?php echo $estilo_tabla_aida; ?>" width="<?php echo $acd[11];?>px"><strong>$5550.00</strong></td>                                   
                            </tr>
                        </tfoot>
                    </table>
                <form name="frmPrincipal" target="_self">
                    <input type="hidden" name="txtCodigoPagoS" id="txtCodigoPagoS" value=""></input>
                    <fieldset style="">
    <!--Linea 1-->
                             
                        <div class="input-control text" data-role="input-control" style="margin-left:0px; width: 600px">
                            <label style="">Valor aprobado por finca: 
                            <input style="position: absolute; margin-left:10px; width: 100px" name="txtCodigoProducto"  value="0" type="text" >
                            </label>
                        </div>
    <!--Linea 2-->
                        <div class="input-control text" data-role="input-control" style="margin-left:0px; width: 600px">
                            <label style="">Crédito para el cliente:  
                            <input style="position: absolute; margin-left:32px; width: 100px" name="txtCodigoProducto"  value="0" type="text" >
                            </label>
                        </div> 
    <!--Linea 3-->
                        <div class="input-control text" data-role="input-control" style="margin-left:0px; width: 670px">
                            <label style="">Observaciones:   
                            <input style=" margin-left:80px; width: 400px" name="txtCodigoProducto"  value="" type="text" >    
                                </label>
                        </div> 
    <!--Linea 4-->  
                        <div class="input-control radio default-style" data-role="input-control" style="width: 110px; margin-top: 10px; margin-left:200px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS2" name="txtTpoPagoS" value = "2" /><span class="check"></span>En Proceso</label>
                        </div> 
                        <div class="input-control radio default-style" data-role="input-control" style="width: 150px; margin-top: 10px; margin-left:0px;">
                            <label><input type="radio" tabindex="<?php echo $tabindex++;?>" id="txtTpoPagoS2" name="txtTpoPagoS" value = "2" /><span class="check"></span>Procesadas</label>
                        </div>                           
    <!-- Linea 5--> 
                        <div>
                            <a href="javascript: boton_nuevo();" class="button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" style="margin-left:200px;" tabindex="<?php echo $tabindex++;?>">Guardar Crédito</a>
                        </div>
                    </fieldset>
               </form>       
  
            </div>
        </div>
     
<script language="javascript">
        carga_pendientes(4);
        </script>
    </body>
</html>
<?php
mysqli_close($link);
?>