<?php            
//comprobamos que sea una petición ajax
if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
    {
    $file = $_FILES['imagen']['name'];
    if(!is_dir("files/")) 
        mkdir("files/", 0777);
    if ($file && move_uploaded_file($_FILES['imagen']['tmp_name'],"files/".$file))
        {
       sleep(0);
       echo $file;
        }
    }
else
    throw new Exception("Error Processing Request", 1);
