<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

// echo $_POST['txtNombre']." ".$_POST['txtClave'];
// $codigo_usuario = comprueba_clave($_POST['txtNombre'],$_POST['txtClave']);
// echo codigo_usuario;
// exit;
session_start();
if (!isset($_SESSION['s_usuario']))
    {
    if (isset($_POST['txtNombre']) && isset($_POST['txtClave']))
        {
        $codigo_usuario = comprueba_clave($_POST['txtNombre'],$_POST['txtClave']);
        if ($codigo_usuario>0)
            {
            session_start();
            $_SESSION['s_usuario']=devuelve_nombre_apellido($codigo_usuario);
            $_SESSION['s_codigo']=$codigo_usuario;
            header("location:home.php");
            }
        else
            header("location:login.php");
        }
    else
        header("location:login.php");
    }
?>