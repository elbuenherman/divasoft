<?php

// ============================================================================
//  ver_adjunto.php
//  Sirve un adjunto guardado en archivo_correo (inline) para verlo/descargarlo.
//  Uso:  ver_adjunto.php?codigo=123
// ============================================================================

include("variables_globales.php");
 
// variables_globales.php solo define $link = NULL; abrir la conexion aqui
// con las mismas credenciales (igual que funciones.php).
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

// Validar parametro codigo (entero).
if(!isset($_GET['codigo']) || !ctype_digit((string)$_GET['codigo']))
    {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Falta parametro codigo";
    exit;
    }
$codigo = (int)$_GET['codigo'];

// Leer el adjunto con sentencia preparada (blob completo via store_result).
$sql = "SELECT NOMBREARCHIVO, MIMETYPE, TAMANOBYTES, ARCHIVO FROM archivo_correo WHERE CODIGO = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $codigo);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if(mysqli_stmt_num_rows($stmt) == 0)
    {
    mysqli_stmt_close($stmt);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "No existe el adjunto";
    exit;
    }

mysqli_stmt_bind_result($stmt, $nombrearchivo, $mimetype, $tamanobytes, $archivo);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Content-Type: si viene vacio u octet-stream, deducir por extension.
$ext = strtolower(pathinfo($nombrearchivo, PATHINFO_EXTENSION));
$content_type = $mimetype;
if($mimetype == "" || $mimetype == "application/octet-stream")
    {
    if($ext == "pdf")
        $content_type = "application/pdf";
    else if($ext == "xlsx")
        $content_type = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    else if($ext == "xls")
        $content_type = "application/vnd.ms-excel";
    }

// Servir el binario sin nada antes ni despues.
header('Content-Type: ' . $content_type);
header('Content-Disposition: inline; filename="' . $nombrearchivo . '"');
header('Content-Length: ' . $tamanobytes);
echo $archivo;
