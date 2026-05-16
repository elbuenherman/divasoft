<?php
header("Content-type: image/jpeg");
//include("variables_globales.php");
$CODIGO = $_GET["CODIGO"];
$path = "files/".$CODIGO;
$variable_foto = imagecreatefrompng($path);
imagepng($variable_foto);    
?>