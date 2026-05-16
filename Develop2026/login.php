<?php
if(isset($_SESSION['s_usuario']))
	{
	session_unset();
	session_destroy();
	}
//session_start();
//session_unset();
//session_destroy();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php
include("css.php");
?>
    <title>Divasoft</title>
</head>
<body class="metro">
<div class="slider">
        <div style="background: url(images/b1.jpg) top left no-repeat; background-size: cover; height: 300px;">
            
            <div class="container" style="padding: 30px 10px; position: absolute">
                    <img src="images/divalogo.png" class="span3">
            </div>
            <div class="container" style="padding: 50px 250px; margin-top: 0px; position: absolute">

                <h1 class="fg-white">Divasoft Ver. 1.0</h1>
                <h2 class="fg-white">
                    Sistema informático para comercialización de flor<br /> Quito - Ecuador - 2015
                </h2>
            </div>
        </div>
</div>
    <div class="herman" style="position: absolute; left:200px; top:310px; width:500px;">   
    <h3>Ingrese sus datos:</h3>
    <form name="frmLogin" action="home.php" method="post">
    	<fieldset>
         <label>
        	<div class="input-control text" data-role="input-control">
            	<input name="txtNombre" type="text" placeholder="nombre de usuario" autofocus="autofocus" tabindex="1">
                <button class="btn-clear" tabindex="-1" ></button>
            </div>
         </label>
         <label>
			<div class="input-control text" data-role="input-control">
    			<input name="txtClave" type="password" placeholder="password"  autofocus="autofocus" tabindex="2">
    			<button class="btn-clear" tabindex="-1" ></button>
    		</div>
         </label>
         <label>
         <a href="javascript: document.frmLogin.submit();" class="place-right button bg-darkRed bg-hover-red fg-white fg-hover-white bd-orange" >
         	<h5 style="margin: 10px 40px">Aceptar</h5>
         </a>
         </label>
     	</fieldset>
     </form>
</div>
</body>
</html>