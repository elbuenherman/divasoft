<?php 
require_once('class.phpmailer.php');
//require('phpmailer/class.smtp.php');
$mail = new PHPMailer(true);

$mail­>$Mailer = 'smtp';
////permite modo debug para ver mensajes de las cosas que van ocurriendo
//$mail­>SMTPDebug = 2;
////Debo de hacer autenticación SMTP
//$mail­>SMTPAuth = true;
//$mail­>SMTPSecure = "ssl";
////indico el servidor de Gmail para SMTP
//$mail­>Host = "smtp.gmail.com";
////indico el puerto que usa Gmail
//$mail­>Port = 465;
////indico un usuario / clave de un usuario de gmail
//$mail­>Username = "herman.diener@divaflor.com";
//$mail­>Password = "hema0905";
//$mail­>SetFrom('herman.diener@divaflor.com', 'Herman Diener');
//$mail­>AddReplyTo('herman.diener@divaflor.com', 'Herman Diener');
//$mail­>Subject = "Envío de email usando SMTP de Gmail desde DivaSoft";
//$mail­>MsgHTML("Hola que tal, esto es el cuerpo del mensaje!");
////indico destinatario
//$address = "herman.diener@gmail.com";
//$mail­>AddAddress($address, "El Jefe");
//if(!$mail­>Send()) {
//echo "Error al enviar: " . $mail­>ErrorInfo;
//} else {
//echo "Mensaje enviado!";
//} 
?>