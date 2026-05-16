<?php 
// MAIL PROMOCIONES ----------

include("../../variables_globales.php");
include("../../funciones.php");
include("../../valida_sesion.php");
require_once('../class.phpmailer.php');

if (isset($_REQUEST['texto'])) $texto = raw_json_encode_herman(urldecode(nl2br($_REQUEST['texto'])));

$texto = eregi_replace("[\]",'',$texto);
$texto = eregi_replace("<br />n",'<br />',$texto);

echo "El email de promociones <br>";

    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysql_select_db($dbname_web);
    mysql_query("SET CHARACTER SET utf8",$link_web);
    $sql = "SELECT group_concat(email_promocion) AS destinatarios, NOW() AS fecha FROM divabase.promocion WHERE envio_promocion = 1";
    $resultado_sql = mysql_query($sql,$link_web);
    $fila_registros = mysql_fetch_array($resultado_sql);
    $destinatarios = $fila_registros['destinatarios'];
    $fecha = $fila_registros['fecha'];
    
echo "Con los destinatarios: <strong>".$destinatarios."</strong> <br> Muestra el siguiente mensaje:<br>";

$mail = new PHPMailer();
$body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>DIVAFLOR / PROMOS </title>
</head><body>
<table width="500" border="0" cellspacing="0" cellpadding="0">
  <tr><td align="center"><img src="divasoft_recibo_logo.png" width="449" height="195" /></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><strong>DIVAFLOR / SPECIAL OFFERS </strong></td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr>
    <td>
    '.$texto.'
    </td>
  </tr>
  <tr><td style="font-family:Courier New, Courier, monospace;">&nbsp;</td></tr>
  <tr><td align="center" style="font-family:Arial Black, Gadget, sans-serif;"><hr /></td></tr>
  <tr><td style="font-family:Courier New, Courier, monospace;">* Divasoft automatic message. <strong>www.divaflor.com</strong></td></tr>
</table></body></html>';
$body = eregi_replace("[\]",'',$body);

$mail->IsSMTP(); 
$mail->Host = "mail.divaflor.com"; // SMTP server
//$mail->SMTPDebug  = 2; // enables SMTP debug information (for testing) 1 = errors and messages 2 = messages only
$mail->SMTPAuth = true;                  // enable SMTP authentication
$mail->SMTPSecure = "tls";                 // sets the prefix to the servier
$mail->Host = "smtp.gmail.com";      // sets GMAIL as the SMTP server
$mail->Port = 465;                   // set the SMTP port for the GMAIL server
$mail->Username = "sales@divaflor.com";  // GMAIL username
$mail->Password = "hema0905";            // GMAIL password
$mail->From = 'sales@divaflor.com';
$mail->FromName = 'DIVAFLOR SALES';
$mail->AddReplyTo("sales@divaflor.com","DIVAFLOR SALES");
$mail->Subject    = "DIVAFLOR / SPECIAL OFFERS / ".$fecha;
$mail->AltBody    = "HTML FORMAT!"; // optional, comment out and test
$mail->MsgHTML($body);
//
$address = ereg_replace( "([ ]+)", "", $destinatarios);
$arreglo_direcciones = split(",",$address);
$numero_direcciones = count($arreglo_direcciones);
$mail->AddAddress("sales@divaflor.com","DIVAFLOR SALES");
for($conteo=1;$conteo<=$numero_direcciones;$conteo++)
    if(filter_var($arreglo_direcciones[$conteo-1], FILTER_VALIDATE_EMAIL))
         $mail->AddBCC($arreglo_direcciones[$conteo-1], $arreglo_direcciones[$conteo-1]);
    else
        if (strlen($arreglo_direcciones[$conteo-1])>1)
            echo "<br> LA DIRECCION: ".$arreglo_direcciones[$conteo-1]." ha sido rechazada";

$mail->AddAttachment("divasoft_recibo_logo.png");

if(!$mail->Send())echo "<br>ERROR DE ENVIO: " . $mail->ErrorInfo;
else echo "<br>Mensaje enviado satisfactoriamente";

?>
<br><br>
<a href="#" onclick="javascript:window.close()"> CERRAR </A>

<?php 
    mysql_close($link_web);
?>