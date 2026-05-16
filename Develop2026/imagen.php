<?php
header("Content-type: image/jpeg");
include("variables_globales.php");
$CODIGO = $_GET["CODIGO"];
$link = mysql_connect($ip_bd, $usuario_bd, $password_bd);
mysql_select_db($instancia_bd); 
$SQL="SELECT * from categoria_producto WHERE codigo_categoria = ".$CODIGO; 
$Resultado=mysql_query($SQL);
$fila=mysql_fetch_array($Resultado);
$LOGOTIPO=$fila['foto_categoria'];
echo $LOGOTIPO;
mysqli_close($link);
?>