<?php

// ============================================================================
//  ver_cuerpo.php
//  Muestra el cuerpo de un correo de correo_facturas_fincas (cabecera + iframe).
//  Uso:  ver_cuerpo.php?codigo=123
// ============================================================================

include("variables_globales.php");
 
// variables_globales.php solo define $link = NULL; abrir la conexion aqui.
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

if(!isset($_GET['codigo']) || !ctype_digit((string)$_GET['codigo']))
    {
    echo "Falta parametro codigo";
    exit;
    } 
$codigo = (int)$_GET['codigo'];

$sql = "SELECT DE, PARA, CC, ASUNTO, FECHAHORA, CUERPOTEXTO, CUERPOHTML FROM correo_facturas_fincas WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    echo "No existe el correo";
    exit;
    }

mysqli_stmt_bind_result($stmt, $de, $para, $cc, $asunto, $fechahora, $cuerpo_texto, $cuerpo_html);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Contenido a mostrar (directo en la pagina, sin iframe).
if(trim((string)$cuerpo_html) != "")
    {
    // Saneo basico: quitar <script> e <iframe> (suficiente para proveedores conocidos).
    $html_limpio = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string)$cuerpo_html);
    $html_limpio = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html_limpio);
    $html_limpio = preg_replace('#</?(?:script|iframe)\b[^>]*>#is', '', $html_limpio);
    $contenido = '<div class="cuerpo_html">'.$html_limpio.'</div>';
    }
else if(trim((string)$cuerpo_texto) != "")
    {
    $contenido = '<pre class="cuerpo_texto">'.htmlspecialchars($cuerpo_texto, ENT_QUOTES, 'UTF-8').'</pre>';
    }
else
    {
    $contenido = '<div class="cuerpo_vacio">Sin contenido</div>';
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Cuerpo del correo</title>
<style>
    html, body { margin:0; padding:0; height:100%; font-family:-apple-system,"Segoe UI",Arial,sans-serif; }
    .cabecera { background:#f5f5f5; padding:12px 16px; font-size:13px; color:#222; line-height:1.6; }
    .cabecera .etq { font-weight:bold; color:#555; }
    .separador { border:none; border-top:1px solid #ddd; margin:0; }
    .cuerpo_html  { overflow:auto; max-height:70vh; border:1px solid #ddd; padding:15px; margin:12px 16px; }
    .cuerpo_texto { white-space:pre-wrap; word-wrap:break-word; font-family:Menlo,Consolas,monospace; font-size:13px; color:#222; background:#fafafa; border:1px solid #ddd; padding:15px; max-height:70vh; overflow:auto; margin:12px 16px; }
    .cuerpo_vacio { padding:20px; font-size:14px; color:#777; }
</style>
</head>
<body>
    <div class="cabecera">
        <div><span class="etq">De:</span> <?php echo htmlspecialchars((string)$de, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><span class="etq">Para:</span> <?php echo htmlspecialchars((string)$para, ENT_QUOTES, 'UTF-8'); ?></div>
<?php if(trim((string)$cc) != "") { ?>
        <div><span class="etq">Cc:</span> <?php echo htmlspecialchars((string)$cc, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
        <div><span class="etq">Asunto:</span> <?php echo htmlspecialchars((string)$asunto, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><span class="etq">Fecha:</span> <?php echo htmlspecialchars((string)$fechahora, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <hr class="separador" />
    <?php echo $contenido; ?>
</body>
</html>
