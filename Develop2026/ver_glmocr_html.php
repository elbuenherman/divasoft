<?php

// ============================================================================
//  ver_glmocr_html.php
//  Lee el archivo .md mas reciente guardado por test_glmocr_validar.php para
//  el codigo dado y lo renderiza como HTML para validar visualmente las
//  tablas y el layout que extrajo GLM-OCR.
//
//  Uso: ver_glmocr_html.php?codigo=79
// ============================================================================

ini_set("display_errors", "1"); 
error_reporting(E_ALL); 
 
$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 79;

// Buscar el archivo md mas reciente para este codigo.
$patron   = "/tmp/glmval_md_".$codigo."_*.md";
$archivos = glob($patron);
if(empty($archivos))
    die("No hay archivo md guardado para codigo ".$codigo.". Ejecuta primero test_glmocr_validar.php?codigo=".$codigo);

usort($archivos, function($a, $b) { return filemtime($b) - filemtime($a); });
$archivo_md = $archivos[0];

$contenido = file_get_contents($archivo_md);
$mtime     = filemtime($archivo_md);
$tamano    = filesize($archivo_md);

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>GLM-OCR HTML - codigo '.(int)$codigo.'</title>';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; padding: 1rem; color:#222; }';
echo 'h1 { color: #88010e; margin: 0 0 8px 0; font-size: 20px; }';
echo 'p.info { margin: 4px 0; font-size: 13px; color: #555; }';
echo 'hr { border: none; border-top: 1px solid #ccc; margin: 12px 0 20px 0; }';
echo 'table { border-collapse: collapse; border: 1px solid #ccc; width: auto; margin: 12px 0; }';
echo 'th { background: #f0f0f0; padding: 6px 10px; border: 1px solid #aaa; text-align: left; font-size: 13px; }';
echo 'td { padding: 4px 8px; border: 1px solid #ddd; font-size: 13px; vertical-align: top; }';
echo 'td.numero { text-align: right; }';
echo 'div.bloque-texto { white-space: pre-wrap; font-size: 13px; margin: 1rem 0; }';
echo '</style></head><body>';

echo '<h1>GLM-OCR HTML - codigo '.(int)$codigo.'</h1>';
echo '<p class="info"><b>Archivo:</b> '.htmlspecialchars($archivo_md, ENT_QUOTES, 'UTF-8').'</p>';
echo '<p class="info"><b>Fecha:</b> '.date("Y-m-d H:i:s", $mtime).'</p>';
echo '<p class="info"><b>Tamano:</b> '.number_format($tamano).' bytes</p>';
echo '<hr>';

// Render directo: el md trae tablas <table><tr><td> y texto plano mezclado.
// Echo sin htmlspecialchars para que las tablas se rendericen reales.
echo $contenido;

echo '</body></html>';
