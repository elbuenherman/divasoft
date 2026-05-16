<?php
// Fichero y nuevo tamaño
$CODIGO = $_GET["CODIGO"];
include("variables_globales.php");
$nombre_fichero = $url_sitio.'imagen_producto.php?CODIGO='.$CODIGO;
// Tipo de contenido
header('Content-Type: image/png');
// Obtener los nuevos tamaños
list($ancho, $alto) = getimagesize($nombre_fichero);
$factor = $ancho/$alto;
$nuevo_ancho = 96;
$nuevo_alto = $nuevo_ancho / $factor;

// Cargar
$thumb = imagecreatetruecolor($nuevo_ancho, $nuevo_alto+1);
$fondo=imagecolorAllocate($thumb,0,0,0);
imagefill($thumb,0,0,$fondo);
imagecolortransparent ($thumb ,$fondo);
$origen = imagecreatefrompng($nombre_fichero);

//list($ancho_logo, $alto_logo) = getimagesize($nombre_logo);
//$fondo_logo = imagecreatetruecolor($ancho, $alto);
//$fondo2=imagecolorAllocate($fondo_logo,0,0,0);
//imagefill($fondo_logo,0,0,$fondo2);
//imagecolortransparent ($fondo_logo ,$fondo2);
//$logo = imagecreatefrompng($nombre_logo);
//imagecopyresized($fondo_logo, $logo, 0, 0, 0, 0, $ancho, $alto, $ancho, $alto);

// Cambiar el tamaño
imagecopyresized($thumb, $origen, 10, 1, 0, 0, $nuevo_ancho-20, $nuevo_alto-20, $ancho-20, $alto-20);
//imagecopymerge($thumb, $fondo_logo, 0, 0,-(($nuevo_ancho/2)-($ancho_logo/2)), -(($nuevo_alto/2)-($alto_logo/2)), $ancho, $alto, 15);
// Imprimir
imagepng($thumb,NULL,9);
imagedestroy($thumb);
//imagedestroy($fondo_logo);
imagedestroy($origen);

?>
