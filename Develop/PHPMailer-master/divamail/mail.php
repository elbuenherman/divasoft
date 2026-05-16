<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("../../variables_globales.php");
include("../../funciones.php");
include("../../valida_sesion.php");
//require_once('../class.phpmailer.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../src/Exception.php';
require '../src/PHPMailer.php';
require '../src/SMTP.php';


if (isset($_REQUEST['codigo'])) $codigo = $_REQUEST['codigo'];
if (isset($_REQUEST['destinatarios'])) $destinatarios = $_REQUEST['destinatarios'];

echo "El email referente al pago <strong>CODIGO: ".$codigo."</strong><br>";
echo "Con los destinatarios: <strong>".$destinatarios."</strong><br> muestra el siguiente mensaje:<br><br>";

    global $link;
    $respuesta = Array();
    $sql = "SELECT fecha_proceso, valor_solicitado, valor_procesado, observaciones, tipo_pago, nombre_banco, tipo_cuenta_banaria_proveedor, cuenta_bancaria_proveedor, nombre_beneficiario_cuenta_bancaria_proveedor, tipo_identificacion_cuenta_bancaria_proveedor, identificacion_cuenta_bancaria_proveedor, email_pagos_proveedor, nombre_proveedor, nombre_comercial_proveedor, comprobante_pago FROM pago, proveedor, banco WHERE pago.codigo_proveedor = proveedor.codigo_proveedor AND pago.codigo_banco = banco.codigo_banco AND codigo_pago = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $tipo_cuenta = "CA";
    if($fila_registros['tipo_cuenta_banaria_proveedor'] == 2) $tipo_cuenta = "CC";
    $tipo_id = "SD";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 1) $tipo_id = "RUC";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 2) $tipo_id = "CED";
    $datos_transferancia = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']."\n\n".$fila_registros['nombre_banco']."\n-".$tipo_cuenta.": ".$fila_registros['cuenta_bancaria_proveedor']."\n-B: ".$fila_registros['nombre_beneficiario_cuenta_bancaria_proveedor']."\n-".$tipo_id.": ".$fila_registros['identificacion_cuenta_bancaria_proveedor']."\n-@: ".$fila_registros['email_pagos_proveedor'];
    $respuesta['valor_solicitado'] = $fila_registros['valor_solicitado'];
    $respuesta['observaciones'] = $fila_registros['observaciones'];    
    $respuesta['tipo_pago'] = $fila_registros['tipo_pago']; 
    $respuesta['datos_transferancia'] = $datos_transferancia;
    $respuesta['nombre_proveedor'] = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']; 
    $respuesta['email_pagos_proveedor'] = $fila_registros['email_pagos_proveedor'];
    $respuesta['valor_procesado'] = $fila_registros['valor_procesado']; 
    $respuesta['nombre_banco'] = $fila_registros['nombre_banco']; 
    $respuesta['fecha_proceso'] = $fila_registros['fecha_proceso'];
    $respuesta['comprobante_pago'] = nl2br($fila_registros['comprobante_pago']);
    
    if($respuesta['tipo_pago'] == 1) $tipo_pago = "CHEQUE";
    if($respuesta['tipo_pago'] == 2) $tipo_pago = "TRANSFERENCIA ELECTRONICA";
    if($respuesta['tipo_pago'] == 3) $tipo_pago = "OTROS"; 

$mail = new PHPMailer();
$body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Confirmación de pago / Transferencia electrónica</title>
</head><body>
<table width="500" border="0" cellspacing="0" cellpadding="0">
  <tr><td align="center"><img src="cid:divasoft_recibo_logo" width="449" height="195" /></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><strong>CONFIRMACION DE PAGO / TRANSFERENCIA ELECTRONICA</strong></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr>
    <td><table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr><td width="32%" style="font-family:Courier New, Courier, monospace;"><strong>PAGO No:</strong></td><td width="68%" style="font-family:Courier New, Courier, monospace;">'.$codigo.'</td></tr>
      <tr><td width="32%" style="font-family:Courier New, Courier, monospace;"><strong>PROVEEDOR:</strong></td><td width="68%" style="font-family:Courier New, Courier, monospace;">'.$respuesta['nombre_proveedor'].'</td></tr>
      <tr><td style="font-family:Courier New, Courier, monospace;"><strong>VALOR:</strong></td><td style="font-family:Courier New, Courier, monospace;">$'.$respuesta['valor_procesado'].' USD</td></tr>
      <tr><td style="font-family:Courier New, Courier, monospace;"><strong>TIPO:</strong></td><td style="font-family:Courier New, Courier, monospace;">'.$tipo_pago.'</td></tr>
      <tr><td style="font-family:Courier New, Courier, monospace;"><strong>BANCO:</strong></td><td style="font-family:Courier New, Courier, monospace;">'.$respuesta['nombre_banco'].'</td></tr>
      <tr><td style="font-family:Courier New, Courier, monospace;"><strong>PROCESADO:</strong></td><td style="font-family:Courier New, Courier, monospace;">'.$respuesta['fecha_proceso'].'</td></tr>
      <tr><td style="font-family:Courier New, Courier, monospace;" valign="top"><strong>OBSERVACIONES:</strong></td><td style="font-family:Arial, Helvetica, Geneva;" SIZE=4><br>'.$respuesta['comprobante_pago'].'</td></tr>
    </table></td>
  </tr>
  <tr><td style="font-family:Courier New, Courier, monospace;">&nbsp;</td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr><td style="font-family:Courier New, Courier, monospace;">* Este mensaje se ha generado automáticamente a través del sistema informático Divasoft. Por favor no responda este mensaje. <strong>www.divaflor.com</strong></td></tr>
</table></body></html>';
//$body = preg_replace("[]",'',$body);
//echo $body;
$mail->IsSMTP(); 
//$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->Host = "mail.divaflor.com"; // SMTP server
// $mail->SMTPDebug  = 2; // enables SMTP debug information (for testing) 1 = errors and messages 2 = messages only
// $mail->Debugoutput = 'html';
$mail->SMTPAuth = true;                  // enable SMTP authentication
$mail->SMTPSecure = "ssl";                 // sets the prefix to the servier
$mail->Host = "smtp.gmail.com";      // sets GMAIL as the SMTP server
$mail->Port = 465;                   // set the SMTP port for the GMAIL server
$mail->Username = "contabilidad@divaflor.com";  // GMAIL username
// $mail->Username = "herman.diener@divaflor.com";  // GMAIL username
// $mail->Password = "michisito";            // GMAIL password
//$mail->Password = "dkjw dhiq kwiw miiw";            // GMAIL password
$mail->Password = "azpo huom njut bauf";            // GMAIL password
$mail->From = 'contabilidad@divaflor.com';
$mail->FromName = 'DIVAFLOR CONTABILIDAD';
$mail->AddReplyTo("no-responder@divaflor.com","DIVAFLOR PAGOS");
$mail->Subject    = "DIVAFLOR / ".$respuesta['nombre_proveedor']." / PAGO (".$codigo.") / $".$respuesta['valor_procesado']." USD / ".$tipo_pago;
$mail->AltBody    = "Para acceder a este mensaje, por favor utilice un cliente de correo compatible con HTML!"; // optional, comment out and test
$mail->MsgHTML($body);

//echo "--->".$destinatarios;
$address = preg_replace( "([ ]+)", "", $destinatarios);
//echo "--->".$address;
$delimitador = ";";
$arreglo_direcciones = explode($delimitador,$address);
//echo ":::<pre>";
//print_r($arreglo_direcciones);
//echo "</pre>";
$mail->AddAddress("contabilidad@divaflor.com", "contabilidad@divaflor.com");
$numero_direcciones = count($arreglo_direcciones);
for($conteo=1;$conteo<=$numero_direcciones;$conteo++)
    if(filter_var($arreglo_direcciones[$conteo-1], FILTER_VALIDATE_EMAIL))
         $mail->AddAddress($arreglo_direcciones[$conteo-1], $arreglo_direcciones[$conteo-1]);
    else
        if (strlen($arreglo_direcciones[$conteo-1])>1)
            echo "<br> LA DIRECCION: ".$arreglo_direcciones[$conteo-1]." ha sido rechazada";

//$mail->AddAttachment("divasoft_recibo_logo.png");
$mail->AddEmbeddedImage('divasoft_recibo_logo.png', 'divasoft_recibo_logo', 'divasoft_recibo_logo.png');
if(!$mail->Send()) echo "<br> MENSAJE: ERROR DE ENVIO: " . $mail->ErrorInfo;
else echo "<br>Mensaje enviado satisfactoriamente";

?>
<br><br>
<a href="#" onclick="javascript:window.close()"> CERRAR </A>