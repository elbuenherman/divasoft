<?php
include ("valida_sesion.php");
session_unset();
session_destroy();
header("Location: divasoft.php");
?>