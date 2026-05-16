<?php
header("Content-type: image/png");
include("variables_globales.php");
$CODIGO = $_GET["CODIGO"];
$link = mysql_connect($ip_bd, $usuario_bd, $password_bd);
mysql_select_db($instancia_bd); 
$SQL="SELECT * from producto WHERE codigo_producto = ".$CODIGO; 
$Resultado=mysql_query($SQL);
$fila=mysql_fetch_array($Resultado);
$LOGOTIPO=$fila['imagen_producto'];
echo $LOGOTIPO;
mysqli_close($link);
?>