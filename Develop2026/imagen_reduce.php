<?php
// Fichero y nuevo tamaño
$archivo = $_GET["archivo"];
include("variables_globales.php");


$nombre_fichero = "".$archivo;
$archivo_minusculas = strtolower($archivo);
$tipo = 0;
if (strpos ($archivo_minusculas ,".png")) $tipo=1;
if (strpos ($archivo_minusculas ,".jpg")) $tipo=2;
if (strpos ($archivo_minusculas ,".jpeg")) $tipo=2;
if (strpos ($archivo_minusculas ,".bmp")) $tipo=3;
if (strpos ($archivo_minusculas ,".gif")) $tipo=4;
if($tipo==0) return;


// Tipo de contenido
header('Content-Type: image/png');
// Obtener los nuevos tamaños
list($ancho, $alto) = getimagesize($nombre_fichero);
$factor = $ancho/$alto;
$nuevo_ancho = 500;
$nuevo_alto = $nuevo_ancho / $factor;

// Cargar
$thumb = imagecreatetruecolor($nuevo_ancho, $nuevo_alto+1);
$fondo=imagecolorAllocate($thumb,0,0,0);
imagefill($thumb,0,0,$fondo);
imagecolortransparent ($thumb ,$fondo);
if ($tipo==1) $origen = imagecreatefrompng($nombre_fichero);
if ($tipo==2) $origen = imagecreatefromjpeg($nombre_fichero);
if ($tipo==3) $origen = imagecreatefromwbmp($nombre_fichero);
if ($tipo==4) $origen = imagecreatefromgif($nombre_fichero);

//$origen = imagecreatefromjpeg($nombre_fichero);

//list($ancho_logo, $alto_logo) = getimagesize($nombre_logo);
//$fondo_logo = imagecreatetruecolor($ancho, $alto);
//$fondo2=imagecolorAllocate($fondo_logo,0,0,0);
//imagefill($fondo_logo,0,0,$fondo2);
//imagecolortransparent ($fondo_logo ,$fondo2);
//$logo = imagecreatefrompng($nombre_logo);
//imagecopyresized($fondo_logo, $logo, 0, 0, 0, 0, $ancho, $alto, $ancho, $alto);

// Cambiar el tamaño
imagecopyresized($thumb, $origen, 1, 1, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
//imagecopymerge($thumb, $fondo_logo, 0, 0,-(($nuevo_ancho/2)-($ancho_logo/2)), -(($nuevo_alto/2)-($alto_logo/2)), $ancho, $alto, 15);
// Imprimir
imagepng($thumb,NULL,9);
imagedestroy($thumb);
//imagedestroy($fondo_logo);
imagedestroy($origen);

?>
