<?php
   
// ============================================================================
//  test_markitdown.php  (TEMPORAL / diagnostico)
//  Recibe el CODIGO de factura_finca por GET, resuelve su CODIGOADJUNTO,
//  extrae el ARCHIVO (LONGBLOB) de archivo_correo, lo guarda en /tmp y lo
//  convierte a texto con markitdown (Python). Muestra el resultado.
//
//  Uso web: test_markitdown.php?codigo=16
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);
set_time_limit(120);
ini_set("max_execution_time", "120");
ini_set("memory_limit", "512M");

include("variables_globales.php");

// variables_globales.php solo define $link = NULL; abrir la conexion aqui
// (mismo patron que las demas consolas / scripts de prueba).
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo   = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 16;
$ruta_pdf = "/tmp/factura_".$codigo.".pdf";

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>test_markitdown - codigo '.(int)$codigo.'</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:20px;} pre{background:#f5f5f5;border:1px solid #ddd;padding:12px;overflow:auto;white-space:pre-wrap;} .err{color:#88010e;font-weight:bold;} .meta{font-size:13px;line-height:1.7;}</style>';
echo '</head><body>';
echo '<h1>test_markitdown - adjunto CODIGO '.(int)$codigo.'</h1>';

// ----------------------------------------------------------------------------
// 1) factura_finca (CODIGO) -> CODIGOADJUNTO.
// ----------------------------------------------------------------------------
$sql_ff = "SELECT CODIGOADJUNTO FROM factura_finca WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql_ff);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    echo '<p class="err">factura_finca CODIGO '.(int)$codigo.' no encontrada.</p>';
    echo '</body></html>';
    exit;
    }

mysqli_stmt_bind_result($stmt, $codigo_adjunto);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$codigo_adjunto = (int)$codigo_adjunto;

echo '<div class="meta"><b>factura_finca:</b> '.(int)$codigo.' &rarr; <b>CODIGOADJUNTO:</b> '.$codigo_adjunto.'</div>';

if($codigo_adjunto == 0)
    {
    echo '<p class="err">La factura '.(int)$codigo.' no tiene CODIGOADJUNTO asociado.</p>';
    echo '</body></html>';
    exit;
    }

// ----------------------------------------------------------------------------
// 2) archivo_correo (CODIGOADJUNTO) -> ARCHIVO (LONGBLOB).
// ----------------------------------------------------------------------------
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo_adjunto);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    echo '<p class="err">Adjunto CODIGO '.$codigo_adjunto.' no encontrado en archivo_correo.</p>';
    echo '</body></html>';
    exit;
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$tamano = strlen((string)$archivo);

echo '<div class="meta">';
echo '<b>NOMBREARCHIVO:</b> '.htmlspecialchars((string)$nombrearchivo, ENT_QUOTES, "UTF-8").'<br>';
echo '<b>MIMETYPE:</b> '.htmlspecialchars((string)$mimetype, ENT_QUOTES, "UTF-8").'<br>';
echo '<b>TAMANO:</b> '.number_format($tamano).' bytes ('.number_format($tamano / 1024, 1).' KB)';
echo '</div>';

// ----------------------------------------------------------------------------
// 2-3) Guardar el binario en /tmp.
// ----------------------------------------------------------------------------
$bytes = file_put_contents($ruta_pdf, (string)$archivo);
if($bytes === false)
    {
    echo '<p class="err">No se pudo escribir '.htmlspecialchars($ruta_pdf, ENT_QUOTES, "UTF-8").'</p>';
    echo '</body></html>';
    exit;
    }
echo '<p><b>Guardado:</b> '.htmlspecialchars($ruta_pdf, ENT_QUOTES, "UTF-8").' ('.number_format($bytes).' bytes)</p>';

// Debug: tamano real del archivo en disco.
echo "PDF guardado: " . filesize($ruta_pdf) . " bytes<br>";

// ----------------------------------------------------------------------------
// Convertir con markitdown (Python) via exec. El script python se crea como
// archivo en /tmp en vez de pasarlo inline con -c.
// ----------------------------------------------------------------------------
file_put_contents('/tmp/test_md.py', '
from markitdown import MarkItDown
md = MarkItDown()
result = md.convert("'.$ruta_pdf.'")
print(result.text_content)
');

$output  = array();
$retorno = 0;
$t_inicio = microtime(true);
exec('OPENBLAS_NUM_THREADS=1 python3 /tmp/test_md.py 2>&1', $output, $retorno);
$tiempo = round(microtime(true) - $t_inicio, 2);

echo '<h2>markitdown</h2>';
echo "Retorno: " . $retorno . "<br>";
echo '<b>Tiempo:</b> '.$tiempo.' s<br>';

echo '<h2>Resultado</h2>';
echo "<pre>" . implode("\n", $output) . "</pre>";

echo '</body></html>';
