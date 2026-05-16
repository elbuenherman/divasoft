<?php
// Fichero y nuevo tamaño
$CODIGO = $_GET["CODIGO"];
include("variables_globales.php");
$nombre_fichero = $url_sitio.'imagen_producto.php?CODIGO='.$CODIGO;
$nombre_logo = $url_sitio.'logo_grande.png';

// Tipo de contenido
header('Content-Type: image/png');
// Obtener los nuevos tamaños
list($ancho, $alto) = getimagesize($nombre_fichero);
$factor = $ancho/$alto;
$nuevo_ancho = 280;
$nuevo_alto = $nuevo_ancho / $factor;
// Cargar

$thumb = imagecreatetruecolor($nuevo_ancho, $nuevo_alto+1);
$fondo=imagecolorAllocate($thumb,0,0,0);
imagefill($thumb,1,1,$fondo);
//imagecolortransparent ($thumb ,$fondo);
$origen = imagecreatefrompng($nombre_fichero);
list($ancho_logo, $alto_logo) = getimagesize($nombre_logo);
$fondo_logo = imagecreatetruecolor($ancho, $alto);
$fondo2=imagecolorAllocate($fondo_logo,0,0,0);
$logo = imagecreatefrompng($nombre_logo);
imagecopyresized($fondo_logo, $logo, 0, 0, 0, 0, $ancho, $alto, $ancho, $alto);

// Cambiar el tamaño
imagecopyresized($thumb, $origen, 0, 1, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
imagecopymerge($thumb, $fondo_logo, 0, 0,-(($nuevo_ancho/2)-($ancho_logo/2)), -(($nuevo_alto/1.5)-($alto_logo/2)), $ancho, $alto, 20);


$color_letras=imagecolorallocate ($thumb, 200, 200, 200);
putenv('GDFONTPATH=' . realpath('.'));
$font = "fuente";

imagettftext($thumb, 20, 0, 10, $nuevo_alto, $color_letras, $font, "www.divaflor.com");
// Imprimir
imagepng($thumb,NULL,9);
imagedestroy($fondo_logo);
imagedestroy($thumb);
imagedestroy($origen);

?>
