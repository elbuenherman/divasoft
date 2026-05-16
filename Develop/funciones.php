<?php
//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
//    C O N E C T I V I D A D   C O N   L A   B A S E   D E   D A T O S     //
//                      Y   S C R I P T S   S Q L                           //
//////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////
// CONECTIVIDAD A LA BASE DE DATOS
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
date_default_timezone_set('America/Guayaquil');
//$link = mysql_connect($ip_bd, $usuario_bd, $password_bd) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
//mysql_select_db($instancia_bd);
//mysql_query("SET CHARACTER SET utf8");
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");
mysqli_query($link, "SET `time_zone` = '".date('P')."'");
function commit()
    {
    global $link;
    mysqli_query($link, "COMMIT");
    mysqli_query($link, "COMMIT");
    }
//// FUNCIONES PARA CAMPOS DESPLEGABLES
//// Función input_german para desplegar campos
function input_german($bsp_ancho,$bsp_alto,$bsp_margen_superior,$bsp_margen_izquierdo,$id_bsp,$ver_codigo,$place_holder,$funcion,$tabindex)
    {
    $herman2_apostrofe = "'".$id_bsp."herman2'";
    $funcion = "'".$funcion."'";
    $herman2 = $id_bsp."herman2";
    $id_bpp_seleccion = $herman2."seleccion";
    $visualiza = "HIDDEN";
    if($ver_codigo) $visualiza = "TEXT";
    $nombre_codigo = 'Codigo';
    $id_bsp_apostrofe = "'".$id_bsp."'";
//<!------ COLOCACION DEL CAMPO PARA LA AUTO-SELECCION ------>
    $html = '
        <div class="input-control text" style="position: absolute; height: '.$bsp_alto.'px; width: '.$bsp_ancho.'px; margin-top: '.$bsp_margen_superior.'px; margin-left: '.$bsp_margen_izquierdo.'px;">
            <input style="position: absolute;" 
                   tabindex = "'.$tabindex.'" 
                   placeholder="'.$place_holder.'" 
                   type="text" 
                   id="'.$id_bsp.'" 
                   name="'.$id_bsp.'" 
                   onblur="bpp_desenfoca('.$herman2_apostrofe.','.$id_bsp_apostrofe.','.$funcion.')" 
                   onkeydown="bpp_obkeydown(event, this,'.$herman2_apostrofe.')" 
                   onkeyup="bpp_maneja_teclas(event,this,'.$bsp_alto.','.$bsp_ancho.','.$bsp_margen_superior.','.$bsp_margen_izquierdo.','.$herman2_apostrofe.','.$funcion.')"> 
            <button class="btn-clear"></button>
        </div>    
    ';
//<!------ COLOCACION DEL CAMPO PARA EL CODIGO ------>
    if($ver_codigo) $html .= '<div class="input-control text" style="position: absolute; height: '.$bsp_alto.'px; width: 38px; margin-top: '.$bsp_margen_superior.'px; margin-left: '.($bsp_margen_izquierdo + 7 + $bsp_ancho).'px;">';
    $html .= '<input tabindex="1000" style="position: absolute;" type="'.$visualiza.'" id="'.$id_bsp.$nombre_codigo.'" value="0" READONLY></input>';
    if($ver_codigo) $html .= '</div>';
//<!------ COLOCACION DEL LA DIVISIÓN PARA DESPLEGAR LAS OPCIONES ------>
    $html .= '
       <div class="'.$herman2.'" style="position: absolute; z-index: 100"></div>
    ';
//<!------ COLOCACION DEL LA CAMPO PARA MANEJAR LA SELECCION ------>                    
    $html .= '
        <input type="hidden" value="0" id="'.$id_bpp_seleccion.'"></input> 
    ';                       
    echo $html;    
    }
//// Producto
function busqueda_predictiva_producto($nombre_producto,$idProducto,$alto,$ancho,$margen_superior,$margen_izquierdo,$id_desplegable)
    {
    global $link;
    $sql = "select codigo_producto, nombre_producto from producto where nombre_producto like '%".$nombre_producto."%' ORDER BY nombre_producto";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros==0||$nombre_producto=="")
        return "";
    $ancho = 1.1*$ancho;
    $margen_superior += $alto; 
    $html = "<div style='position: absolute; height: ".$alto."px; width: ".$ancho."px; margin-top: ".$margen_superior."px; margin-left: ".$margen_izquierdo."px;'><table style='border: 1px solid gray;'>";
    for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $respuesta[$i]['codigo_producto'] = $fila_registros['codigo_producto'];   
            $respuesta[$i]['nombre_producto'] = $fila_registros['nombre_producto']; 
            $id_busqueda = "".$i;
            $texto = $respuesta[$i]['nombre_producto']." - ".$respuesta[$i]['codigo_producto'];
            $texto_a_colocar = $respuesta[$i]['nombre_producto'];
            $codigo_producto = $respuesta[$i]['codigo_producto'];
            $texto = str_ireplace($nombre_producto,"<strong>".$nombre_producto."</strong>",$texto);
            $onmousemove = "onmousemove=".chr(34)."bpp_enfoca('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onmouseout = "onmouseout=".chr(34)."bpp_onmouseout('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onclick = "onclick=".chr(34)."bpp_onclick('".$idProducto."','".$texto_a_colocar."','".$codigo_producto."','".$id_desplegable."')".chr(34);
            $html.="<tr><td style='font-size: 11px' id='idBusqueda".$i."' ".$onmousemove." ".$onmouseout." ".$onclick." >".$texto."</td><input type='hidden' id='idNombre".$i."' value='".$texto_a_colocar."'></input><input type='hidden' id='idCodigo".$i."' value='".$codigo_producto."'></input></tr>";            
            }  
    $html.="</div></table>";
    return $html;
    }
function busqueda_producto_x_nombre($nombre_producto)
    {
    global $link;
    $sql = "select codigo_producto from producto where nombre_producto like '%".$nombre_producto."%'";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros!=1)
        return 0;
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['codigo_producto'];
    }
function busqueda_producto_x_codigo($codigo_producto)
    {
    global $link;
    $sql = "select nombre_producto from producto where codigo_producto  = '".$codigo_producto."'";
    $resultado_sql= mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['nombre_producto'];
    }
// Pais
function busqueda_predictiva_pais($nombre,$id,$alto,$ancho,$margen_superior,$margen_izquierdo,$id_desplegable)
    {
    global $link;
    $sql = "SELECT codigo_pais, nombre_pais FROM pais WHERE nombre_pais LIKE '%".$nombre."%' ORDER BY nombre_pais";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros==0||$nombre=="")
        return "";
    $ancho = 1.1*$ancho;
    $margen_superior += $alto; 
    $html = "<div style='position: absolute; height: ".$alto."px; width: ".$ancho."px; margin-top: ".$margen_superior."px; margin-left: ".$margen_izquierdo."px;'><table style='border: 1px solid gray;'>";
    for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $respuesta[$i]['codigo_pais'] = $fila_registros['codigo_pais'];   
            $respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais']; 
            $id_busqueda = "".$i;
            $texto = $respuesta[$i]['nombre_pais']." - ".$respuesta[$i]['codigo_pais'];
            $texto_a_colocar = $respuesta[$i]['nombre_pais'];
            $codigo = $respuesta[$i]['codigo_pais'];
            $texto = str_ireplace($nombre,"<strong>".$nombre."</strong>",$texto);
            $onmousemove = "onmousemove=".chr(34)."bpp_enfoca('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onmouseout = "onmouseout=".chr(34)."bpp_onmouseout('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onclick = "onclick=".chr(34)."bpp_onclick('".$id."','".$texto_a_colocar."','".$codigo."','".$id_desplegable."')".chr(34);
            $html.="<tr><td style='font-size: 11px' id='idBusqueda".$i."' ".$onmousemove." ".$onmouseout." ".$onclick." >".$texto."</td><input type='hidden' id='idNombre".$i."' value='".$texto_a_colocar."'></input><input type='hidden' id='idCodigo".$i."' value='".$codigo."'></input></tr>";            
            }  
    $html.="</div></table>";
    return $html;
    }
function busqueda_pais_x_nombre($nombre)
    {
    global $link;
    $sql = "SELECT codigo_pais FROM pais WHERE nombre_pais like '%".$nombre."%'";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros!=1)
        return 0;
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['codigo_pais'];
    }
function busqueda_pais_x_codigo($codigo)
    {
    global $link;
    $sql = "SELECT nombre_pais FROM pais WHERE codigo_pais = '".$codigo."'";
    $resultado_sql= mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['nombre_pais'];
    }
// Banco
function busqueda_predictiva_banco($nombre,$id,$alto,$ancho,$margen_superior,$margen_izquierdo,$id_desplegable)
    {
    global $link;
    $sql = "SELECT codigo_banco, nombre_banco FROM banco WHERE nombre_banco LIKE '%".$nombre."%' ORDER BY nombre_banco";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros==0||$nombre=="")
        return "";
    $ancho = 1.1*$ancho;
    $margen_superior += $alto; 
    $html = "<div style='position: absolute; height: ".$alto."px; width: ".$ancho."px; margin-top: ".$margen_superior."px; margin-left: ".$margen_izquierdo."px;'><table style='border: 1px solid gray;'>";
    for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $id_busqueda = "".$i;
            $texto = $fila_registros['nombre_banco']." - ".$fila_registros['codigo_banco'];
            $texto_a_colocar = $fila_registros['nombre_banco'];
            $codigo = $fila_registros['codigo_banco'];
            $texto = str_ireplace($nombre,"<strong>".$nombre."</strong>",$texto);
            $onmousemove = "onmousemove=".chr(34)."bpp_enfoca('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onmouseout = "onmouseout=".chr(34)."bpp_onmouseout('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onclick = "onclick=".chr(34)."bpp_onclick('".$id."','".$texto_a_colocar."','".$codigo."','".$id_desplegable."')".chr(34);
            $html.="<tr><td style='font-size: 11px' id='idBusqueda".$i."' ".$onmousemove." ".$onmouseout." ".$onclick." >".$texto."</td><input type='hidden' id='idNombre".$i."' value='".$texto_a_colocar."'></input><input type='hidden' id='idCodigo".$i."' value='".$codigo."'></input></tr>";            
            }  
    $html.="</div></table>";
    return $html;
    }
function busqueda_banco_x_nombre($nombre)
    {
    global $link;
    $sql = "SELECT codigo_banco FROM banco WHERE nombre_banco like '%".$nombre."%'";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros!=1)
        return 0;
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['codigo_banco'];
    }
function busqueda_banco_x_codigo($codigo)
    {
    global $link;
    $sql = "SELECT nombre_banco FROM banco WHERE codigo_banco = '".$codigo."'";
    $resultado_sql= mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['nombre_banco'];
    }
///// Proveedor
function busqueda_predictiva_proveedor($nombre,$id,$alto,$ancho,$margen_superior,$margen_izquierdo,$id_desplegable)
    {
    global $link;
    $sql = "SELECT codigo_proveedor, nombre_proveedor, nombre_comercial_proveedor FROM proveedor WHERE nombre_proveedor LIKE '%".$nombre."%' OR nombre_comercial_proveedor LIKE '%".$nombre."%' ORDER BY nombre_proveedor";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros==0||$nombre=="")
        return "";
    $ancho = 1.1*$ancho;
    $margen_superior += $alto; 
    $html = "<div style='position: absolute; height: ".$alto."px; width: ".$ancho."px; margin-top: ".$margen_superior."px; margin-left: ".$margen_izquierdo."px;'><table style='border: 1px solid gray;'>";
    for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $id_busqueda = "".$i;
            $texto = $fila_registros['nombre_proveedor']." - ".$fila_registros['nombre_comercial_proveedor']." - ".$fila_registros['codigo_proveedor'];
            $texto_a_colocar = $fila_registros['nombre_proveedor'];
            $codigo = $fila_registros['codigo_proveedor'];
            $texto = str_ireplace($nombre,"<strong>".$nombre."</strong>",$texto);
            $onmousemove = "onmousemove=".chr(34)."bpp_enfoca('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onmouseout = "onmouseout=".chr(34)."bpp_onmouseout('".$id_busqueda."','".$id_desplegable."')".chr(34);
            $onclick = "onclick=".chr(34)."bpp_onclick('".$id."','".$texto_a_colocar."','".$codigo."','".$id_desplegable."')".chr(34);
            $html.="<tr><td style='font-size: 11px' id='idBusqueda".$i."' ".$onmousemove." ".$onmouseout." ".$onclick." >".$texto."</td><input type='hidden' id='idNombre".$i."' value='".$texto_a_colocar."'></input><input type='hidden' id='idCodigo".$i."' value='".$codigo."'></input></tr>";            
            }  
    $html.="</div></table>";
    return $html;
    }
function busqueda_proveedor_x_nombre($nombre)
    {
    global $link;
    $sql = "SELECT codigo_proveedor FROM proveedor WHERE nombre_proveedor like '%".$nombre."%' OR nombre_comercial_proveedor like '%".$nombre."%'";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    if($numero_registros!=1)
        return 0;
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['codigo_proveedor'];
    }
function busqueda_proveedor_x_codigo($codigo)
    {
    global $link;
    $sql = "SELECT nombre_proveedor FROM proveedor WHERE codigo_proveedor = '".$codigo."'";
    $resultado_sql= mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    return $fila_registros['nombre_proveedor'];
    }
//// ************************************** TABLA PAISES
//// SELECT
function select_paises($opcion)
    {
    global $link;
    $respuesta = array();
    $sql_paises = "SELECT * FROM pais ORDER BY nombre_pais";
    $resultado_sql_paises = mysqli_query($link,$sql_paises);
    $numero_paises = mysqli_num_rows($resultado_sql_paises);
    for($i=1;$i<=$numero_paises;$i++)
        {
        $fila_paises = mysqli_fetch_array($resultado_sql_paises);
        $codigo_pais = $fila_paises['codigo_pais'];
        $respuesta[$i]['nombre_pais'] = $fila_paises['nombre_pais'];
        $respuesta[$i]['codigo_pais'] = $fila_paises['codigo_pais'];
        }
    return $respuesta;
    }
function devuelve_pais_por_codigo($codigo)
    {
    global $link;
    $sql_paises = "SELECT * FROM pais WHERE codigo_pais = ".$codigo;
    $resultado_sql_paises = mysqli_query($link,$sql_paises);
    $fila_paises = mysqli_fetch_array($resultado_sql_paises);
    $nombre_pais = $fila_paises['nombre_pais'];
    return $nombre_pais;		
    }
function busca_pais($nombre_pais)
    {
    global $link;
    $sql_paises = "SELECT count(*) FROM pais WHERE nombre_pais = '".$nombre_pais."'";
    $resultado_sql_paises = mysqli_query($link,$sql_paises);
    $fila_paises = mysqli_fetch_array($resultado_sql_paises);
    $numero_paises = $fila_paises[0];
    return $numero_paises;		
    }
function inserta_pais($nombre_pais,$codigo_pais)
    {
    global $link;
    if(busca_pais($nombre_pais))
        return 0; // Rertorna 0 si encuenta repetido el pais	
    $sql_paises = "INSERT INTO pais VALUES('".$codigo_pais."','".$nombre_pais."') ON DUPLICATE KEY UPDATE codigo_pais = '".$codigo_pais."', nombre_pais = '".$nombre_pais."'";
    $resultado_sql_paises = mysqli_query($link,$sql_paises);
    return 1;	// Retorna 1 si se realizo la insercion		
    }
function elimina_pais($codigo_pais)
    {
    global $link;	
    $puede_eliminar = 1;
    // Chequeo Ciudades
    $sql_ciudades = "SELECT count(*) FROM ciudad WHERE codigo_pais = '".$codigo_pais."'";
    $resultado_sql_ciudades = mysqli_query($link,$sql_ciudades);
    $fila_ciudades = mysqli_fetch_array($resultado_sql_ciudades);
    $numero_ciudades = $fila_ciudades[0];
    if($numero_ciudades>0)
        $puede_eliminar = 0;
    // Chequeo Proveedores
    $sql_proveedor = "SELECT count(*) FROM proveedor WHERE codigo_pais_proveedor = '".$codigo_pais."'";
    $resultado_sql_proveedor = mysqli_query($link,$sql_proveedor);
    $fila_proveedor = mysqli_fetch_array($resultado_sql_proveedor);
    $numero_proveedores = $fila_proveedor[0];
    if($numero_proveedores>0)
        $puede_eliminar = 0;
    // Chequeo Bancos
    $sql_bancos = "SELECT count(*) FROM banco WHERE codigo_pais = '".$codigo_pais."'";
    $resultado_sql_bancos = mysqli_query($link,$sql_bancos);
    $fila_bancos = mysqli_fetch_array($resultado_sql_bancos);
    $numero_bancos = $fila_bancos[0];
    if($numero_bancos>0)
        $puede_eliminar = 0;
    if ($puede_eliminar==1)
        {
        $sql_paises = "DELETE FROM pais WHERE codigo_pais = '".$codigo_pais."'";
        $resultado_sql_paises = mysqli_query($link,$sql_paises);
        return 1;	// Retorna 1 si se realizo la eliminación		
        }
    return 0;
    }
//// ************************************** TABLA USUARIOS
function select_usuarios($opcion)
    {
    global $link;
    $respuesta = array();
    $sql_usuarios = "SELECT codigo_usuario, nombre_usuario, apellido_usuario, username_usuario, clave_usuario, fecha_nacimiento_usuario FROM usuario ORDER BY apellido_usuario";
    $resultado_sql_usuarios = mysqli_query($link,$sql_usuarios);
    $numero_usuarios = mysqli_num_rows($resultado_sql_usuarios);
    for($i=1;$i<=$numero_usuarios;$i++)
        {
        $fila_usuarios = mysqli_fetch_array($resultado_sql_usuarios);
        $respuesta[$i]['codigo_usuario'] = $fila_usuarios['codigo_usuario'];
        $respuesta[$i]['nombre_usuario'] = $fila_usuarios['nombre_usuario'];
        $respuesta[$i]['apellido_usuario'] = $fila_usuarios['apellido_usuario'];
        $respuesta[$i]['username_usuario'] = $fila_usuarios['username_usuario'];
        $respuesta[$i]['clave_usuario'] = $fila_usuarios['clave_usuario'];
        $respuesta[$i]['fecha_nacimiento_usuario'] = $fila_usuarios['fecha_nacimiento_usuario'];
        }
    return $respuesta;
    }
function devuelve_usuario_por_codigo($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql_usuarios = "SELECT * FROM usuario WHERE codigo_usuario = ".$codigo;
    $resultado_sql_usuarios = mysqli_query($link,$sql_usuarios);
    $fila_usuarios = mysqli_fetch_array($resultado_sql_usuarios);
    $respuesta['codigo_usuario'] = $fila_usuarios['codigo_usuario'];
    $respuesta['nombre_usuario'] = $fila_usuarios['nombre_usuario'];
    $respuesta['apellido_usuario'] = $fila_usuarios['apellido_usuario'];
    $respuesta['username_usuario'] = $fila_usuarios['username_usuario'];
    $respuesta['clave_usuario'] = $fila_usuarios['clave_usuario'];
    $respuesta['fecha_nacimiento_usuario'] = $fila_usuarios['fecha_nacimiento_usuario'];
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function comprueba_si_existe_username($username)
    {
    global $link;
    $username = addslashes($username); 
    $sql_usuario = "SELECT count(*) AS CANTIDAD from usuario WHERE username_usuario = '$username';";
    $resultado_sql_usuario = mysqli_query($link,$sql_usuario);
    $fila_usuario = mysqli_fetch_array($resultado_sql_usuario);
    return $fila_usuario['CANTIDAD'];
    }
function comprueba_clave($username,$clave)
    {
    global $link;
    $username = addslashes($username);
    $clave = addslashes($clave);
    if(comprueba_si_existe_username($username)<>1) return 0;    
    $sql_usuario = "SELECT codigo_usuario FROM usuario WHERE username_usuario = '".$username."' AND clave_usuario = '".$clave."'";
    $resultado_sql_usuario = mysqli_query($link, $sql_usuario);
    $numero_usuario = mysqli_num_rows($resultado_sql_usuario);
    if($numero_usuario)
        {	
        $fila_usuario = mysqli_fetch_array($resultado_sql_usuario);
        $codigo_usuario = $fila_usuario['codigo_usuario'];
        return $codigo_usuario;
        }
    return 0;
    }
function devuelve_nombre_apellido($codigo_usuario)
    {
    global $link;
    $sql_usuario = "SELECT nombre_usuario, apellido_usuario FROM usuario WHERE codigo_usuario = '".$codigo_usuario."'";
    $resultado_sql_usuario = mysqli_query($link, $sql_usuario);
    $fila_usuario = mysqli_fetch_array($resultado_sql_usuario);
    $respuesta = $fila_usuario['nombre_usuario']." ".$fila_usuario['apellido_usuario'];
    return $respuesta;
    }
function busca_usuario($apellido_usuario,$nombre_usuario)
    {
    global $link;
    $sql_usuarios = "SELECT codigo_usuario FROM usuario WHERE nombre_usuario = '".$nombre_usuario."' AND apellido_usuario ='".$apellido_usuario."'";
    $resultado_sql_usuarios = mysqli_query($link,$sql_usuarios);
    $fila_usuarios = mysqli_fetch_array($resultado_sql_usuarios);
    $numero_usuarios = $fila_usuarios[0];
    return $numero_usuarios;		
    }
function inserta_usuario($codigo_usuario,$nombre_usuario,$apellido_usuario,$username_usuario,$clave_usuario,$fecha_usuario)
    {
    global $link;
    $codigo=busca_usuario($apellido_usuario,$nombre_usuario);
    if(($codigo>0)&&($codigo<>$codigo_usuario))
        return 0; // Rertorna 0 si encuenta repetido el pais	
    $sql_usuario = "INSERT INTO usuario VALUES('".$codigo_usuario."','".$nombre_usuario."','".$apellido_usuario."','".$username_usuario."','".$clave_usuario."','".$fecha_usuario."',NULL) ON DUPLICATE KEY UPDATE codigo_usuario = '".$codigo_usuario."', nombre_usuario = '".$nombre_usuario."', apellido_usuario = '".$apellido_usuario."', username_usuario = '".$username_usuario."', clave_usuario = '".$clave_usuario."', fecha_nacimiento_usuario = '".$fecha_usuario."'";
    $resultado_sql_usuario = mysqli_query($link,$sql_usuario);
    return 1;	// Retorna 1 si se realizo la insercion		
    }
function elimina_usuario($codigo_usuario)
    {
    global $link;	
    $puede_eliminar = 1;
    // Chequeo Ciudades
/* 	$sql_ciudades = "SELECT count(*) FROM ciudad WHERE codigo_pais = '".$codigo_pais."'";
    $resultado_sql_ciudades = mysql_query($sql_ciudades,$link);
    $fila_ciudades = mysqli_fetch_array($resultado_sql_ciudades);
    $numero_ciudades = $fila_ciudades[0];
    if($numero_ciudades>0)
        $puede_eliminar = 0;*/
    if ($puede_eliminar==1)
        {
        $sql_usuarios = "DELETE FROM usuario WHERE codigo_usuario = '".$codigo_usuario."'";
        $resultado_sql_usuarios = mysqli_query($link,$sql_usuarios);
        return 1;	// Retorna 1 si se realizo la eliminación		
        }
    return 0;
    }   
//// ************************************** TABLA CIUDADES
function select_ciudades($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_ciudad, nombre_ciudad, ciudad.codigo_pais, nombre_pais FROM ciudad, pais WHERE ciudad.codigo_pais = pais.codigo_pais ORDER BY nombre_pais,nombre_ciudad";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
		//$respuesta[$i][''] = $fila_registros[''];
		$respuesta[$i]['codigo_ciudad'] = $fila_registros['codigo_ciudad'];
		$respuesta[$i]['nombre_ciudad'] = $fila_registros['nombre_ciudad'];
		$respuesta[$i]['codigo_pais'] = $fila_registros['codigo_pais'];
		$respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais'];
		}
	return $respuesta;
	}
function devuelve_ciudad_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_ciudad, nombre_ciudad, ciudad.codigo_pais, nombre_pais FROM ciudad, pais WHERE ciudad.codigo_pais = pais.codigo_pais AND codigo_ciudad = ".$codigo;
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_ciudad'] = $fila_registros['codigo_ciudad'];
	$respuesta['nombre_ciudad'] = $fila_registros['nombre_ciudad'];
	$respuesta['codigo_pais'] = $fila_registros['codigo_pais'];
	$respuesta['nombre_pais'] = $fila_registros['nombre_pais'];
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
function busca_ciudad($nombre_ciudad)
	{
	global $link;
	$sql = "SELECT codigo_ciudad FROM ciudad WHERE nombre_ciudad = '".$nombre_ciudad."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_ciudad($codigo_ciudad,$nombre_ciudad,$codigo_pais)
	{
	global $link;
	$codigo=busca_ciudad($nombre_ciudad);
	if($codigo>0&&$codigo<>$codigo_ciudad)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO ciudad VALUES('".$codigo_ciudad."','".$nombre_ciudad."','".$codigo_pais."') ON DUPLICATE KEY UPDATE codigo_ciudad = '".$codigo_ciudad."', nombre_ciudad = '".$nombre_ciudad."', codigo_pais = '".$codigo_pais."'";
	$resultado_sql = mysqli_query($link,$sql);
	return 1;	// Retorna 1 si se realizo la insercion		
	}
function elimina_ciudad($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeo Proveedores
	$sql_proveedor = "SELECT count(*) FROM proveedor WHERE codigo_ciudad_proveedor = '".$codigo."'";
	$resultado_sql_proveedor = mysqli_query($link,$sql_proveedor);
	$fila_proveedor = mysqli_fetch_array($resultado_sql_proveedor);
	$numero_proveedores = $fila_proveedor[0];
	if($numero_proveedores>0)
		$puede_eliminar = 0;
	if ($puede_eliminar==1)
		{
		$sql = "DELETE FROM ciudad WHERE codigo_ciudad = '".$codigo."'";
		$resultado_sql = mysqli_query($link,$sql);
		return 1;	// Retorna 1 si se realizo la eliminación		
		}
	return 0;
	}
//// ************************************** TABLA BANCOS
function select_bancos($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_banco, nombre_banco, banco.codigo_pais, nombre_pais FROM banco, pais WHERE banco.codigo_pais = pais.codigo_pais ORDER BY nombre_pais,nombre_banco";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
		//$respuesta[$i][''] = $fila_registros[''];
		$respuesta[$i]['codigo_banco'] = $fila_registros['codigo_banco'];
		$respuesta[$i]['nombre_banco'] = $fila_registros['nombre_banco'];
		$respuesta[$i]['codigo_pais'] = $fila_registros['codigo_pais'];
		$respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais'];
		}
	return $respuesta;
	}
function devuelve_banco_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_banco, nombre_banco, banco.codigo_pais, nombre_pais FROM banco, pais WHERE banco.codigo_pais = pais.codigo_pais AND codigo_banco = ".$codigo;
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_banco'] = $fila_registros['codigo_banco'];
	$respuesta['nombre_banco'] = $fila_registros['nombre_banco'];
	$respuesta['codigo_pais'] = $fila_registros['codigo_pais'];
	$respuesta['nombre_pais'] = $fila_registros['nombre_pais'];
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
function busca_banco($nombre_banco)
	{
	global $link;
	$sql = "SELECT codigo_banco FROM banco WHERE nombre_banco = '".$nombre_banco."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_banco($codigo_banco,$nombre_banco,$codigo_pais)
	{
	global $link;
	$codigo=busca_banco($nombre_banco);
	if($codigo>0&&$codigo<>$codigo_banco)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO banco VALUES('".$codigo_banco."','".$nombre_banco."','".$codigo_pais."') ON DUPLICATE KEY UPDATE codigo_banco = '".$codigo_banco."', nombre_banco = '".$nombre_banco."', codigo_pais = '".$codigo_pais."'";
	$resultado_sql = mysqli_query($link,$sql);
	return 1;	// Retorna 1 si se realizo la insercion		
	}
function elimina_banco($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeo Proveedores
	$sql_proveedor = "SELECT count(*) FROM proveedor WHERE codigo_banco_proveedor = '".$codigo."'";
	$resultado_sql_proveedor = mysqli_query($link,$sql_proveedor);
	$fila_proveedor = mysqli_fetch_array($resultado_sql_proveedor);
	$numero_proveedores = $fila_proveedor[0];
	if($numero_proveedores>0)
		$puede_eliminar = 0;
	if ($puede_eliminar==1)
		{
		$sql = "DELETE FROM banco WHERE codigo_banco = '".$codigo."'";
		$resultado_sql = mysqli_query($link,$sql);
		return 1;	// Retorna 1 si se realizo la eliminación		
		}
	return 0;
	}
	
//// ************************************** TABLA TIPO PROVEEODR
function select_tipo_proveedor($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_tipo_proveedor, nombre_tipo_proveedor FROM tipo_proveedor ORDER BY nombre_tipo_proveedor";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
		//$respuesta[$i][''] = $fila_registros[''];
		$respuesta[$i]['codigo_tipo_proveedor'] = $fila_registros['codigo_tipo_proveedor'];
		$respuesta[$i]['nombre_tipo_proveedor'] = $fila_registros['nombre_tipo_proveedor'];
		}
	return $respuesta;
	}
function devuelve_tipo_proveedor_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_tipo_proveedor, nombre_tipo_proveedor FROM tipo_proveedor WHERE codigo_tipo_proveedor = ".$codigo;
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_tipo_proveedor'] = $fila_registros['codigo_tipo_proveedor'];
	$respuesta['nombre_tipo_proveedor'] = $fila_registros['nombre_tipo_proveedor'];
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
function busca_tipo_proveedor($nombre_tipo_proveedor)
	{
	global $link;
	$sql = "SELECT codigo_tipo_proveedor FROM tipo_proveedor WHERE nombre_tipo_proveedor = '".$nombre_tipo_proveedor."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_tipo_proveedor($codigo_tipo_proveedor,$nombre_tipo_proveedor)
	{
	global $link;
	$codigo=busca_tipo_proveedor($nombre_tipo_proveedor);
	if($codigo>0&&$codigo<>$codigo_tipo_proveedor)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO tipo_proveedor VALUES('".$codigo_tipo_proveedor."','".$nombre_tipo_proveedor."') ON DUPLICATE KEY UPDATE codigo_tipo_proveedor = '".$codigo_tipo_proveedor."', nombre_tipo_proveedor = '".$nombre_tipo_proveedor."'";
	$resultado_sql = mysqli_query($link,$sql);
	return 1;	// Retorna 1 si se realizo la inserción		
	}
function elimina_tipo_proveedor($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeo Proveedores
	$sql_proveedor = "SELECT count(*) FROM proveedor WHERE codigo_tipo_proveedor = '".$codigo."'";
	$resultado_sql_proveedor = mysqli_query($link,$sql_proveedor);
	$fila_proveedor = mysqli_fetch_array($resultado_sql_proveedor);
	$numero_proveedores = $fila_proveedor[0];
	if($numero_proveedores>0)
		$puede_eliminar = 0;
	if ($puede_eliminar==1)
		{
		$sql = "DELETE FROM tipo_proveedor WHERE codigo_tipo_proveedor = '".$codigo."'";
		$resultado_sql = mysqli_query($link,$sql);
		return 1;	// Retorna 1 si se realizo la eliminación		
		}
	return 0;
	}
	
//////////////// TABLA PROVEEDORES
function select_proveedor($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT nombre_tipo_proveedor, nombre_pais, nombre_ciudad, nombre_banco, codigo_proveedor, proveedor.codigo_tipo_proveedor, nombre_proveedor, nombre_comercial_proveedor, telefono_proveedor, codigo_banco_proveedor, tipo_cuenta_banaria_proveedor, cuenta_bancaria_proveedor,nombre_beneficiario_cuenta_bancaria_proveedor, tipo_identificacion_cuenta_bancaria_proveedor, identificacion_cuenta_bancaria_proveedor, nombre_vendedor_proveedor,email_vendedor_proveedor, telefono_vendedor_proveedor, skype_vendedor_proveedor, direccion_proveedor, codigo_ciudad_proveedor, codigo_pais_proveedor, direccion_web_proveedor,observaciones_proveedor, email_pagos_proveedor FROM proveedor, banco, pais, ciudad, tipo_proveedor WHERE tipo_proveedor.codigo_tipo_proveedor = proveedor.codigo_tipo_proveedor AND proveedor.codigo_banco_proveedor = banco.codigo_banco AND proveedor.codigo_pais_proveedor = pais.codigo_pais AND proveedor.codigo_ciudad_proveedor = ciudad.codigo_ciudad ORDER BY nombre_proveedor";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
                $status = 15;
		$fila_registros = mysqli_fetch_array($resultado_sql);
		//$respuesta[$i][''] = $fila_registros[''];
                $respuesta[$i]['codigo_proveedor'] = $fila_registros['codigo_proveedor'];   
                $respuesta[$i]['codigo_tipo_proveedor'] = $fila_registros['codigo_tipo_proveedor']; 
                $respuesta[$i]['nombre_proveedor'] = $fila_registros['nombre_proveedor'];    
                if(!($respuesta[$i]['nombre_comercial_proveedor'] = $fila_registros['nombre_comercial_proveedor'])) $status--;
                if(!($respuesta[$i]['telefono_proveedor'] = $fila_registros['telefono_proveedor'])) $status--;   
                if(($respuesta[$i]['codigo_banco_proveedor'] = $fila_registros['codigo_banco_proveedor'])=='7') $status--;  
                if(($respuesta[$i]['tipo_cuenta_banaria_proveedor'] = $fila_registros['tipo_cuenta_banaria_proveedor'])=='3') $status--;   
                if(!($respuesta[$i]['cuenta_bancaria_proveedor'] = $fila_registros['cuenta_bancaria_proveedor'])) $status--;
                if(!($respuesta[$i]['nombre_beneficiario_cuenta_bancaria_proveedor'] = $fila_registros['nombre_beneficiario_cuenta_bancaria_proveedor'])) $status--;  
                $respuesta[$i]['tipo_identificacion_cuenta_bancaria_proveedor'] = $fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'];   
                if(!($respuesta[$i]['identificacion_cuenta_bancaria_proveedor'] = $fila_registros['identificacion_cuenta_bancaria_proveedor'])) $status--;
                if(!($respuesta[$i]['nombre_vendedor_proveedor'] = $fila_registros['nombre_vendedor_proveedor'])) $status--;
                if(!($respuesta[$i]['email_vendedor_proveedor'] = $fila_registros['email_vendedor_proveedor'])) $status--;
                if(!($respuesta[$i]['telefono_vendedor_proveedor'] = $fila_registros['telefono_vendedor_proveedor'])) $status--;  
                if(!($respuesta[$i]['skype_vendedor_proveedor'] = $fila_registros['skype_vendedor_proveedor'])) $status--;  
                if(!($respuesta[$i]['direccion_proveedor'] = $fila_registros['direccion_proveedor'])) $status--;
                $respuesta[$i]['codigo_ciudad_proveedor'] = $fila_registros['codigo_ciudad_proveedor'];   
                $respuesta[$i]['codigo_pais_proveedor'] = $fila_registros['codigo_pais_proveedor'];   
                $respuesta[$i]['direccion_web_proveedor'] = $fila_registros['direccion_web_proveedor'];   
                $respuesta[$i]['observaciones_proveedor'] = $fila_registros['observaciones_proveedor'];
                if(!($respuesta[$i]['email_pagos_proveedor'] = $fila_registros['email_pagos_proveedor'])) $status--;
                $respuesta[$i]['nombre_banco'] = $fila_registros['nombre_banco'];
                $respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais'];
                $respuesta[$i]['nombre_ciudad'] = $fila_registros['nombre_ciudad'];
                $respuesta[$i]['nombre_tipo_proveedor'] = $fila_registros['nombre_tipo_proveedor'];
                $respuesta[$i]['status'] = floor($status/5);
                if($respuesta[$i]['tipo_cuenta_banaria_proveedor'] == '1') $respuesta[$i]['nombre_tipo_cuenta_banaria_proveedor'] = 'CTA. AHORRO';
                if($respuesta[$i]['tipo_cuenta_banaria_proveedor'] == '2') $respuesta[$i]['nombre_tipo_cuenta_banaria_proveedor'] = 'CTA. CORRIENTE';
                if($respuesta[$i]['tipo_cuenta_banaria_proveedor'] == '3') $respuesta[$i]['nombre_tipo_cuenta_banaria_proveedor'] = 'Sin definir';
                if($respuesta[$i]['tipo_identificacion_cuenta_bancaria_proveedor'] == '1') $respuesta[$i]['nombre_tipo_identificacion_cuenta_bancaria_proveedor'] = 'RUC';
                if($respuesta[$i]['tipo_identificacion_cuenta_bancaria_proveedor'] == '2') $respuesta[$i]['nombre_tipo_identificacion_cuenta_bancaria_proveedor'] = 'Cédula';
                if($respuesta[$i]['tipo_identificacion_cuenta_bancaria_proveedor'] == '3') $respuesta[$i]['nombre_tipo_identificacion_cuenta_bancaria_proveedor'] = 'Otro';
		}
	return $respuesta;
	}
function devuelve_proveedor_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_proveedor, codigo_tipo_proveedor, nombre_proveedor, nombre_comercial_proveedor, telefono_proveedor, codigo_banco_proveedor, tipo_cuenta_banaria_proveedor, 
        cuenta_bancaria_proveedor, nombre_beneficiario_cuenta_bancaria_proveedor, tipo_identificacion_cuenta_bancaria_proveedor, identificacion_cuenta_bancaria_proveedor, 
        nombre_vendedor_proveedor, email_vendedor_proveedor, telefono_vendedor_proveedor, skype_vendedor_proveedor, direccion_proveedor, codigo_ciudad_proveedor, codigo_pais_proveedor, 
        direccion_web_proveedor, observaciones_proveedor, email_pagos_proveedor FROM proveedor WHERE codigo_proveedor = ".$codigo;
        
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_proveedor'] = $fila_registros['codigo_proveedor'];   $respuesta['codigo_tipo_proveedor'] = $fila_registros['codigo_tipo_proveedor']; 
        $respuesta['nombre_proveedor'] = $fila_registros['nombre_proveedor'];   $respuesta['nombre_comercial_proveedor'] = $fila_registros['nombre_comercial_proveedor'];
	$respuesta['telefono_proveedor'] = $fila_registros['telefono_proveedor'];   $respuesta['codigo_banco_proveedor'] = $fila_registros['codigo_banco_proveedor'];   
        $respuesta['tipo_cuenta_banaria_proveedor'] = $fila_registros['tipo_cuenta_banaria_proveedor'];   $respuesta['cuenta_bancaria_proveedor'] = $fila_registros['cuenta_bancaria_proveedor'];
        $respuesta['nombre_beneficiario_cuenta_bancaria_proveedor'] = $fila_registros['nombre_beneficiario_cuenta_bancaria_proveedor'];   $respuesta['tipo_identificacion_cuenta_bancaria_proveedor'] = $fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'];   
        $respuesta['identificacion_cuenta_bancaria_proveedor'] = $fila_registros['identificacion_cuenta_bancaria_proveedor'];   $respuesta['nombre_vendedor_proveedor'] = $fila_registros['nombre_vendedor_proveedor'];
	$respuesta['email_vendedor_proveedor'] = $fila_registros['email_vendedor_proveedor'];   $respuesta['telefono_vendedor_proveedor'] = $fila_registros['telefono_vendedor_proveedor'];   
        $respuesta['skype_vendedor_proveedor'] = $fila_registros['skype_vendedor_proveedor'];   $respuesta['direccion_proveedor'] = $fila_registros['direccion_proveedor'];
        $respuesta['codigo_ciudad_proveedor'] = $fila_registros['codigo_ciudad_proveedor'];   $respuesta['codigo_pais_proveedor'] = $fila_registros['codigo_pais_proveedor'];   
        $respuesta['direccion_web_proveedor'] = $fila_registros['direccion_web_proveedor'];   $respuesta['observaciones_proveedor'] = $fila_registros['observaciones_proveedor'];
        $respuesta['email_pagos_proveedor'] = $fila_registros['email_pagos_proveedor'];
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
function busca_proveedor($nombre_proveedor)
	{
	global $link;
	$sql = "SELECT codigo_proveedor FROM proveedor WHERE nombre_proveedor = '".$nombre_proveedor."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_proveedor($nombre_proveedor,$codigo_tipo_proveedor,$nombre_comercial_proveedor,$telefono_proveedor,$codigo_banco_proveedor,$tipo_cuenta_banaria_proveedor,$cuenta_bancaria_proveedor,$nombre_beneficiario_cuenta_bancaria_proveedor,$tipo_identificacion_cuenta_bancaria_proveedor,$identificacion_cuenta_bancaria_proveedor,$nombre_vendedor_proveedor,$email_vendedor_proveedor,$telefono_vendedor_proveedor,$skype_vendedor_proveedor,$direccion_proveedor,$codigo_pais_proveedor,$codigo_ciudad_proveedor,$direccion_web_proveedor,$observaciones_proveedor,$codigo_proveedor,$email_pagos_proveedor)
	{
	global $link;
	$codigo=busca_proveedor($nombre_proveedor);
	if($codigo>0&&$codigo<>$codigo_proveedor)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	echo $sql = "INSERT INTO proveedor VALUES('".$codigo_proveedor."','".$codigo_tipo_proveedor."','".$nombre_proveedor."','".$nombre_comercial_proveedor."','".$telefono_proveedor."','".$codigo_banco_proveedor."','".$tipo_cuenta_banaria_proveedor."','".$cuenta_bancaria_proveedor."','".$nombre_beneficiario_cuenta_bancaria_proveedor."','".$tipo_identificacion_cuenta_bancaria_proveedor."','".$identificacion_cuenta_bancaria_proveedor."','".$nombre_vendedor_proveedor."','".$email_vendedor_proveedor."','".$telefono_vendedor_proveedor."','".$skype_vendedor_proveedor."','".$direccion_proveedor."','".$codigo_ciudad_proveedor."','".$codigo_pais_proveedor."','".$direccion_web_proveedor."','".$observaciones_proveedor."','".$email_pagos_proveedor."', NULL, NULL) ON DUPLICATE KEY UPDATE codigo_proveedor = '".$codigo_proveedor."', codigo_tipo_proveedor = '".$codigo_tipo_proveedor."',nombre_proveedor = '".$nombre_proveedor."', nombre_comercial_proveedor = '".$nombre_comercial_proveedor."', telefono_proveedor = '".$telefono_proveedor."', codigo_banco_proveedor = '".$codigo_banco_proveedor."', tipo_cuenta_banaria_proveedor = '".$tipo_cuenta_banaria_proveedor."', cuenta_bancaria_proveedor = '".$cuenta_bancaria_proveedor."', nombre_beneficiario_cuenta_bancaria_proveedor = '".$nombre_beneficiario_cuenta_bancaria_proveedor."', tipo_identificacion_cuenta_bancaria_proveedor = '".$tipo_identificacion_cuenta_bancaria_proveedor."', identificacion_cuenta_bancaria_proveedor = '".$identificacion_cuenta_bancaria_proveedor."', nombre_vendedor_proveedor = '".$nombre_vendedor_proveedor."', email_vendedor_proveedor = '".$email_vendedor_proveedor."', telefono_vendedor_proveedor = '".$telefono_vendedor_proveedor."', skype_vendedor_proveedor = '".$skype_vendedor_proveedor."',direccion_proveedor = '".$direccion_proveedor."',codigo_ciudad_proveedor = '".$codigo_ciudad_proveedor."', codigo_pais_proveedor = '".$codigo_pais_proveedor."', direccion_web_proveedor = '".$direccion_web_proveedor."', observaciones_proveedor = '".$observaciones_proveedor."', email_pagos_proveedor = '".$email_pagos_proveedor."'";
	$resultado_sql = mysqli_query($link,$sql);
	return 1;	// Retorna 1 si se realizo la inserción		
	}
function elimina_proveedor($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeos varios
//	$sql_proveedor = "SELECT count(*) FROM proveedor WHERE codigo_tipo_proveedor = '".$codigo."'";
//	$resultado_sql_proveedor = mysql_query($sql_proveedor,$link);
//	$fila_proveedor = mysqli_fetch_array($resultado_sql_proveedor);
//	$numero_proveedores = $fila_proveedor[0];
//	if($numero_proveedores>0)
//		$puede_eliminar = 0;
	if ($puede_eliminar==1)
		{
		$sql = "DELETE FROM proveedor WHERE codigo_proveedor = '".$codigo."'";
		$resultado_sql = mysqli_query($link,$sql);
		return 1;	// Retorna 1 si se realizo la eliminación		
		}
	return 0;
	}
// TABLA CATEGORIA_PRODUCTOS
function select_categoria_producto($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_categoria, nombre_categoria, color_categoria, foto_categoria, nombre_ruso_categoria, nombre_ingles_categoria FROM categoria_producto ORDER BY codigo_categoria";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
                $respuesta[$i]['codigo_categoria'] = $fila_registros['codigo_categoria'];   
                $respuesta[$i]['nombre_categoria'] = $fila_registros['nombre_categoria']; 
                $respuesta[$i]['color_categoria'] = $fila_registros['color_categoria']; 
                $respuesta[$i]['foto_categoria'] = $fila_registros['foto_categoria'];   
                $respuesta[$i]['nombre_ruso_categoria'] = $fila_registros['nombre_ruso_categoria'];   
                $respuesta[$i]['nombre_ingles_categoria'] = $fila_registros['nombre_ingles_categoria'];   
		}
	return $respuesta;
	}
function devuelve_categoria_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_categoria, nombre_categoria, color_categoria, foto_categoria, nombre_ruso_categoria, nombre_ingles_categoria FROM categoria_producto WHERE codigo_categoria = ".$codigo;
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_categoria'] = $fila_registros['codigo_categoria'];   
        $respuesta['nombre_categoria'] = $fila_registros['nombre_categoria'];   
        $respuesta['color_categoria'] = $fila_registros['color_categoria'];
        //$respuesta['foto_categoria'] = $fila_registros['foto_categoria'];   
        $respuesta['nombre_ruso_categoria'] = $fila_registros['nombre_ruso_categoria'];   
        $respuesta['nombre_ingles_categoria'] = $fila_registros['nombre_ingles_categoria']; 
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
function busca_categoria_productos($nombre)
	{
	global $link;
	$sql = "SELECT codigo_categoria FROM categoria_producto WHERE nombre_categoria = '".$nombre."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_categoria($codigo_categoria,$nombre_categoria,$color_categoria,$path_foto_categoria,$nombre_ruso_categoria,$nombre_ingles_categoria)
	{
	global $link;
        global $url_sitio;
        $caracteres_a_borrar = strlen($url_sitio);
        $path = substr($path_foto_categoria, $caracteres_a_borrar); //Deja solamenete la dirección del archivo subido en el server para que se acceda a través del filesystem    
        $color_categoria = "#".$color_categoria;    
	$codigo=busca_categoria_productos($nombre_categoria);
	if($codigo>0&&$codigo<>$codigo_categoria)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO categoria_producto (codigo_categoria,nombre_categoria,color_categoria,nombre_ruso_categoria,nombre_ingles_categoria) VALUES('".$codigo_categoria."','".$nombre_categoria."','".$color_categoria."',CONVERT(_utf8'".$nombre_ruso_categoria."' USING cp866),'".$nombre_ingles_categoria."') ON DUPLICATE KEY UPDATE codigo_categoria = '".$codigo_categoria."', nombre_categoria = '".$nombre_categoria."',color_categoria = '".$color_categoria."', nombre_ruso_categoria = CONVERT(_utf8'".$nombre_ruso_categoria."' USING cp866), nombre_ingles_categoria = '".$nombre_ingles_categoria."'";
	$resultado_sql = mysqli_query($link,$sql);
		// Retorna 1 si se realizo la inserción	
        if($codigo_categoria==0)
            $codigo_categoria = mysqli_insert_id($link);
        if(strpos($path_foto_categoria,"imagen.php?CODIGO=")==FALSE)
            {strpos($path_foto_categoria,"imagen.php?CODIGO=");
            mysqli_query($link,"SET CHARACTER SET latin1");
            $fp = fopen($path, 'r+b');
            $data = fread($fp, filesize($path));
            fclose($fp);
            $data = mysqli_escape_string($data);
            $sql_foto = "UPDATE categoria_producto SET foto_categoria = '".$data."' WHERE codigo_categoria = '".$codigo_categoria."'";
            $resultado_sql_foto = mysqli_query($link,$sql_foto);
            }
        return 1;
        }
function elimina_categoria($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeos PRODUCTO
	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
	$resultado_sql_1 = mysqli_query($link,$sql_1);
	$fila_1 = mysqli_fetch_array($resultado_sql_1);
	$numero_1 = $fila_1[0];
	if($numero_1>0)
		$puede_eliminar = 0;
	if ($puede_eliminar==1)
		{
		 $sql = "DELETE FROM categoria_producto WHERE codigo_categoria= '".$codigo."'";
		$resultado_sql = mysqli_query($link,$sql);
		return 1;	// Retorna 1 si se realizo la eliminación		
		}
	return 0;
	}
//TABLA PRODUCTOS 
function select_producto($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_producto, nombre_producto, producto.codigo_categoria, nombre_categoria FROM producto, categoria_producto WHERE producto.codigo_categoria = categoria_producto.codigo_categoria ORDER BY codigo_categoria,nombre_producto";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
                $respuesta[$i]['codigo_producto'] = $fila_registros['codigo_producto'];   
                $respuesta[$i]['nombre_producto'] = $fila_registros['nombre_producto']; 
                $respuesta[$i]['codigo_categoria'] = $fila_registros['codigo_categoria']; 
                $respuesta[$i]['nombre_categoria'] = $fila_registros['nombre_categoria'];     
		}
	return $respuesta;
	}
function select_producto_quick($opcion)
	{
	global $link;
	$respuesta = array();
	$sql = "SELECT codigo_producto, nombre_producto, producto.codigo_categoria_producto_facturacion AS codigo_categoria, descripcion_categoria_producto_facturacion AS nombre_categoria FROM producto, categoria_producto_facturacion WHERE producto.codigo_categoria_producto_facturacion = categoria_producto_facturacion.codigo_categoria_producto_facturacion ORDER BY producto.codigo_categoria_producto_facturacion,nombre_producto";
	$resultado_sql= mysqli_query($link,$sql);
	$numero_registros = mysqli_num_rows($resultado_sql);
	for($i=1;$i<=$numero_registros;$i++)
		{
		$fila_registros = mysqli_fetch_array($resultado_sql);
                $respuesta[$i]['codigo_producto'] = $fila_registros['codigo_producto'];   
                $respuesta[$i]['nombre_producto'] = $fila_registros['nombre_producto']; 
                $respuesta[$i]['codigo_categoria'] = $fila_registros['codigo_categoria']; 
                $respuesta[$i]['nombre_categoria'] = $fila_registros['nombre_categoria'];     
		}
	return $respuesta;
	}
function devuelve_producto_por_codigo($codigo)
	{
	global $link;
	$respuesta = Array();
	$sql = "SELECT codigo_producto, codigo_categoria, nombre_producto, nombre_ingles_producto, nombre_ruso_producto, tonalidad_producto, tonalidad_ingles_producto, tonalidad_ruso_producto, petalos_producto, "
                . "tallos_producto, boton_producto, duracion_producto, bs_producto, descripcion_producto, categoria_producto, dflt, codigo_categoria_producto_facturacion FROM producto WHERE codigo_producto = ".$codigo;
        
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$respuesta['codigo_producto'] = $fila_registros['codigo_producto'];
        $respuesta['codigo_categoria'] = $fila_registros['codigo_categoria'];   
	$respuesta['nombre_producto'] = $fila_registros['nombre_producto'];      
        $respuesta['nombre_ingles_producto'] = $fila_registros['nombre_ingles_producto'];   
        $respuesta['nombre_ruso_producto'] = $fila_registros['nombre_ruso_producto']; 
        $respuesta['tonalidad_producto'] = $fila_registros['tonalidad_producto'];   
	$respuesta['tonalidad_ingles_producto'] = $fila_registros['tonalidad_ingles_producto'];    
        $respuesta['tonalidad_ruso_producto'] = $fila_registros['tonalidad_ruso_producto'];   
        $respuesta['petalos_producto'] = $fila_registros['petalos_producto'];    
        $respuesta['tallos_producto'] = $fila_registros['tallos_producto'];   
        $respuesta['boton_producto'] = $fila_registros['boton_producto'];
        $respuesta['duracion_producto'] = $fila_registros['duracion_producto'];
        $respuesta['bs_producto'] = $fila_registros['bs_producto'];
        $respuesta['descripcion_producto'] = $fila_registros['descripcion_producto'];
        $respuesta['categoria_producto'] = $fila_registros['categoria_producto'];
        $respuesta['dflt'] = $fila_registros['dflt'];
        $respuesta['codigo_categoria_producto_facturacion'] = $fila_registros['codigo_categoria_producto_facturacion'];
	header ("Content-Type:text/xml");
	return $respuesta;		
	}
 function busca_productos($nombre)
	{
	global $link;
	$sql = "SELECT codigo_producto FROM producto WHERE nombre_producto = '".$nombre."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
 function inserta_producto($codigo_producto, $codigo_categoria, $nombre_producto, $nombre_ingles_producto, $nombre_ruso_producto, $tonalidad_producto, $tonalidad_ingles_producto, $tonalidad_ruso_producto, $petalos_producto, $tallos_producto, $boton_producto, $duracion_producto, $bs_producto, $descripcion_producto, $categoria_producto, $dflt, $path_foto_producto, $codigo_categoria_producto_facturacion)
	{     
	global $link;
        global $url_sitio;
        $caracteres_a_borrar = strlen($url_sitio);
        $path = substr($path_foto_producto, $caracteres_a_borrar); //Deja solamenete la dirección del archivo subido en el server para que se acceda a través del filesystem      
//	$codigo=busca_productos($nombre_producto);
//	if($codigo>0)
//		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO producto (codigo_producto,codigo_categoria,nombre_producto,nombre_ruso_producto,nombre_ingles_producto,tonalidad_producto,tonalidad_ingles_producto,tonalidad_ruso_producto,petalos_producto,tallos_producto,boton_producto,duracion_producto,bs_producto,descripcion_producto,categoria_producto,dflt,codigo_categoria_producto_facturacion) VALUES('".$codigo_producto."','".$codigo_categoria."','".$nombre_producto."',CONVERT(_utf8'".$nombre_ruso_producto."' USING cp866),'".$nombre_ingles_producto."','".$tonalidad_producto."','".$tonalidad_ingles_producto."',CONVERT(_utf8'".$tonalidad_ruso_producto."' USING cp866),'".$petalos_producto."','".$tallos_producto."','".$boton_producto."','".$duracion_producto."','".$bs_producto."','".$descripcion_producto."','".$categoria_producto."','".$dflt."','".$codigo_categoria_producto_facturacion."') ON DUPLICATE KEY UPDATE codigo_producto ='".$codigo_producto."' ,codigo_categoria='".$codigo_categoria."',nombre_producto='".$nombre_producto."',nombre_ruso_producto=CONVERT(_utf8'".$nombre_ruso_producto."' USING cp866), nombre_ingles_producto='".$nombre_ingles_producto."',tonalidad_producto='".$tonalidad_producto."',tonalidad_ingles_producto='".$tonalidad_ingles_producto."',tonalidad_ruso_producto=CONVERT(_utf8'".$tonalidad_ruso_producto."' USING cp866), petalos_producto='".$petalos_producto."',tallos_producto='".$tallos_producto."',boton_producto='".$boton_producto."',duracion_producto='".$duracion_producto."',bs_producto='".$bs_producto."', descripcion_producto='".$descripcion_producto."',categoria_producto='".$categoria_producto."',dflt='".$dflt."', codigo_categoria_producto_facturacion='".$codigo_categoria_producto_facturacion."'";
	$resultado_sql = mysqli_query($link,$sql);		
        if($codigo_producto==0)
            $codigo_producto = mysqli_insert_id($link);
        if(strpos($path_foto_producto,"imagen_producto.php?CODIGO=")==FALSE)
            {
            strpos($path_foto_producto,"imagen_producto.php?CODIGO=");
            mysqli_query($link,"SET CHARACTER SET latin1");
            $fp = fopen($path, 'r+b');
            $data = fread($fp, filesize($path));
            fclose($fp);
            $data = mysqli_escape_string($data);
            $sql_foto = "UPDATE producto SET imagen_producto = '".$data."' WHERE codigo_producto = '".$codigo_producto."'";
            $resultado_sql_foto = @mysqli_query($sql_foto,$link);
            }
        // Retorna 1 si se realizo la inserción	
        return 1;
        }
 function inserta_producto_quick($codigo_producto, $codigo_categoria, $nombre_producto, $nombre_ingles_producto, $nombre_ruso_producto, $tonalidad_producto, $tonalidad_ingles_producto, $tonalidad_ruso_producto, $petalos_producto, $tallos_producto, $boton_producto, $duracion_producto, $bs_producto, $descripcion_producto, $categoria_producto, $dflt, $path_foto_producto, $codigo_categoria_producto_facturacion)
	{     
	global $link;	
	$sql = "INSERT INTO producto (codigo_producto,codigo_categoria,nombre_producto,codigo_categoria_producto_facturacion) VALUES('".$codigo_producto."','".$codigo_categoria."','".$nombre_producto."','".$codigo_categoria_producto_facturacion."') ON DUPLICATE KEY UPDATE codigo_producto ='".$codigo_producto."' ,codigo_categoria='".$codigo_categoria."',nombre_producto='".$nombre_producto."', codigo_categoria_producto_facturacion='".$codigo_categoria_producto_facturacion."'";
	$resultado_sql = mysqli_query($link,$sql);		
        return 1;
        }
function elimina_producto($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeos PRODUCTO
//	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
//	$resultado_sql_1 = mysql_query($sql_1,$link);
//	$fila_1 = mysqli_fetch_array($resultado_sql_1);
//	$numero_1 = $fila_1[0];
//	if($numero_1>0)
//		$puede_eliminar = 0;
	if($puede_eliminar==1)
            {
            $sql = "DELETE FROM producto WHERE codigo_producto = '".$codigo."'";
            $resultado_sql = mysqli_query($link,$sql);
            return 1;	// Retorna 1 si se realizo la eliminación		
            }
	return 0;
	}
function busca_proveedor_producto($codigo_proveedor,$codigo_producto)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT codigo_proveedor FROM proveedor_producto WHERE codigo_proveedor = ".$codigo_proveedor." AND codigo_producto = ".$codigo_producto;
    $resultado_sql= mysqli_query($link,$sql);
    return mysqli_num_rows($resultado_sql); 
    }
function carga_productos($codigo_finca,$tamano_texto,$flores_proveedor_y,$flores_proveedor_x,$flores_proveedor_h)
    {
    $html_cabecera = ''
     . '<div class="herman" id="IdProductosProveedor" style="position: absolute; width: 660px; margin-top:'.$flores_proveedor_y.'px; margin-left:'.$flores_proveedor_x.'px;" height:'.$flores_proveedor_h.'px; ">
        <legend>Productos de la finca</legend>
        <table width="100%">
            <tbody>';
    echo $html_cabecera;
    global $link;
    $respuesta = array();
    $sql = "SELECT codigo_producto, nombre_producto, producto.codigo_categoria, nombre_categoria FROM producto, categoria_producto WHERE producto.codigo_categoria = categoria_producto.codigo_categoria ORDER BY codigo_categoria,nombre_producto";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    $columnas=3;
    $contador=0;
    $codigo_categoria_anterior = 0;
    for($i=1;$i<=$numero_registros;$i++)
            {
            if($contador==0) echo "<tr>";
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $codigo_producto = $fila_registros['codigo_producto'];   
            $nombre_producto = $fila_registros['nombre_producto']; 
            $codigo_categoria = $fila_registros['codigo_categoria']; 
            $nombre_categoria = $fila_registros['nombre_categoria'];     
            if($codigo_categoria_anterior!=$codigo_categoria)
                {
                $contador = 0;
                $codigo_categoria_anterior=$codigo_categoria;
                $html_categoria = '</tr><tr>'
                     . '<td class="text-left" style="font-size:'.$tamano_texto.'px"><strong>'.$nombre_categoria.'</strong></td>
                        <td class="text-right" style="font-size:'.$tamano_texto.'px"></td>
                        <td class="text-right" style="font-size:'.$tamano_texto.'px"></td>
                </tr>
                <tr>';
                echo $html_categoria;
                } 
            $checked = "";
            if(busca_proveedor_producto($codigo_finca,$codigo_producto)) $checked = "CHECKED";
            $html_fila = '<td class="text-left" style="font-size:'.$tamano_texto.'px"><input type="checkbox" '.$checked.'/ onChange="check_productos(this,'.$codigo_producto.');">'.$nombre_producto.'</td>';
            echo $html_fila;
            $contador++;
            if($contador==$columnas) 
                {
                echo "<tr>";
                $contador=0;
                }
             }
    $html_final = ''
          .'</tbody>
            <tfoot></tfoot>
        </table>            
        </div>';
    echo $html_final; 
    }
function check_productos($codigo_proveedor,$codigo_producto,$estado_check)
    {
    global $link;
    if($estado_check==1)
        $sql = "INSERT INTO proveedor_producto VALUES('".$codigo_proveedor."','".$codigo_producto."')";
    if($estado_check==0)
        $sql = "DELETE FROM proveedor_producto WHERE codigo_proveedor = '".$codigo_proveedor."' AND codigo_producto = '".$codigo_producto."'";
    $resultado_sql= mysqli_query($link,$sql);    
    }
function carga_productos_filtros()
    {
    $tamano_texto=9;
    global $link;
    $html_cabecera = ''
     . '<div class="herman" id="IdProductosProveedor" style="position: absolute; width: 900px; margin-top:10px; margin-left:10px;">
        <legend>Filtro productos</legend>
        <table  width="100%">
            <tbody>';
    echo $html_cabecera;
    
    $respuesta = array();
    $sql = "SELECT codigo_producto, nombre_producto, producto.codigo_categoria, nombre_categoria, color_categoria FROM producto, categoria_producto WHERE producto.codigo_categoria = categoria_producto.codigo_categoria ORDER BY nombre_producto";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    $columnas=8;
    $contador=0;
    for($i=1;$i<=$numero_registros;$i++)
            {
            if($contador==0) echo "<tr>";
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $codigo_producto = $fila_registros['codigo_producto'];   
            $nombre_producto = $fila_registros['nombre_producto']; 
            $codigo_categoria = $fila_registros['codigo_categoria']; 
            $nombre_categoria = $fila_registros['nombre_categoria'];    
            $color_categoria = $fila_registros['color_categoria'];
            $html_fila = '<td class="text-left" style="font-size:'.$tamano_texto.'px"><input type="checkbox" / onChange="check_filtro_producto(this,'.$codigo_producto.')">'.$nombre_producto.'</td>';
            echo $html_fila;
            $contador++;
            if($contador==$columnas) 
                {
                echo "<tr>";
                $contador=0;
                }
             }
    $html_final = ''
          .'</tbody>
            <tfoot></tfoot>
        </table>    
        ';
    echo $html_final; 
    $html_cabecera_paises = '<legend>Filtro países</legend><table width="100%"><tbody>';
    echo $html_cabecera_paises;
    $sql_paises = "SELECT DISTINCT(proveedor.codigo_pais_proveedor), pais.nombre_pais FROM pais, proveedor WHERE pais.codigo_pais = proveedor.codigo_pais_proveedor ORDER BY nombre_pais";
    $resultado_sql_paises= mysqli_query($link,$sql_paises);
    $numero_registros_paises = mysqli_num_rows($resultado_sql_paises);
    $columnas=8;
    $contador=0;
    for($i=1;$i<=$numero_registros_paises;$i++)
            {
            if($contador==0) echo "<tr>";
            $fila_registros_pais = mysqli_fetch_array($resultado_sql_paises);
            $codigo_pais = $fila_registros_pais['codigo_pais_proveedor'];   
            $nombre_pais = $fila_registros_pais['nombre_pais'];
            $chequed_pais = "";
            if($nombre_pais=="ECUADOR") $chequed_pais="CHECKED";
            $html_fila = '<td class="text-left" style="font-size:'.$tamano_texto.'px"><input type="checkbox" '.$chequed_pais.' / onChange="check_filtro_pais(this,'.$codigo_pais.')">'.$nombre_pais.'</td>';
            echo $html_fila;
            $contador++;
            if($contador==$columnas) 
                {
                echo "<tr>";
                $contador=0;
                }
             }
    $html_cierre = '</tbody></table>'
            . '</div><i class="icon-filter" style="position: absolute; background: darkRed; color: white; padding: 10px; border-radius: 50%; margin-top:35px; margin-left:20px;"></i>'; 
    echo $html_cierre;
    }
function actualiza_proveedores($filtro,$filtro_paises)
    {
    $html_cabecera = ''
     . '<div class="herman" id="IdProductosProveedor" style="position: absolute; width: 350px; margin-top:10px; margin-left:920px;"><legend>Proveedores</legend><table width="100%"><tbody>';
    echo $html_cabecera;
    $longitud_filtro = strlen($filtro);
    $longitud_filtro_paises = strlen($filtro_paises);
    if($longitud_filtro>0&&$longitud_filtro_paises>0)
        {
        $tamano_texto=11;
        global $link;
        // filtro paises
        $filtro_paises = substr($filtro_paises,1,$longitud_filtro_paises);
        $filtro_paises = substr($filtro_paises,0,$longitud_filtro_paises-2);
        $filtro_paises = str_replace("><", " OR proveedor.codigo_pais_proveedor = ", $filtro_paises);
        // filtro productos
        $filtro = substr($filtro,1,$longitud_filtro);
        $filtro = substr($filtro,0,$longitud_filtro-2);
        $filtro_org = $filtro;
        $arreglo_variedades = split("><", $filtro_org);
        $filtro = str_replace("><", " OR proveedor_producto.codigo_producto = ", $filtro);
        $sql = "SELECT nombre_proveedor, codigo_proveedor, nombre_pais FROM pais, proveedor where pais.codigo_pais = proveedor.codigo_pais_proveedor AND (proveedor.codigo_pais_proveedor = ".$filtro_paises.") AND codigo_proveedor IN (SELECT codigo_proveedor FROM proveedor_producto WHERE proveedor_producto.codigo_producto =".$filtro.") ORDER BY nombre_proveedor";
        $resultado_sql= mysqli_query($link,$sql);
        $numero_registros = mysqli_num_rows($resultado_sql);
        for($i=1;$i<=$numero_registros;$i++)
            {
            $variedades_producto = "";
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $nombre_proveedor = $fila_registros['nombre_proveedor'];   
            $codigo_proveedor = $fila_registros['codigo_proveedor']; 
            $nombre_pais = $fila_registros['nombre_pais'];
            $html_fila = '<tr><td class="text-left" style="font-size:'.$tamano_texto.'px"><b>'.$nombre_proveedor.'</b> ('.$nombre_pais.')</td><tr>';
            echo $html_fila;
            for($v=0;$v<count($arreglo_variedades);$v++)
                {
                $sql_variedades = "SELECT DISTINCT(nombre_producto) FROM proveedor_producto, producto WHERE proveedor_producto.codigo_producto = producto.codigo_producto AND proveedor_producto.codigo_proveedor = ".$codigo_proveedor." AND proveedor_producto.codigo_producto = ".$arreglo_variedades[$v];
                $resultado_sql_variedades= mysqli_query($link,$sql_variedades);
                $fila_registros_variedaes = mysqli_fetch_array($resultado_sql_variedades);
                $nueva_variedad = $fila_registros_variedaes[0];
                if($nueva_variedad!=NULL)
                    $variedades_producto .= $nueva_variedad.", ";
                }
            $longitud_variedades = strlen($variedades_producto);
            $variedades_producto = substr($variedades_producto,0,$longitud_variedades-2);
            $html_fila = '<tr><td class="text-left" style="font-size:'.($tamano_texto-2).'px">'.$variedades_producto.'</td><tr>';
            echo $html_fila;
            }   
        }

    $html_final = '</table></div>';
    echo $html_final;
    }
//// GESTION DE PERMISOS
function consulta_permisos($codigo_usuario,&$permiso)
    {
    global $link;
    $sql = "SELECT codigo_permiso, valor_falso_permiso FROM permiso ORDER BY codigo_permiso ASC";
    $resultado_sql= mysqli_query($link, $sql);
    $numero_permisos = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_permisos;$i++)
        {
        $fila_permisos = mysqli_fetch_array($resultado_sql);
        $codigo_permiso = $fila_permisos['codigo_permiso'];
        $valor_falso_permiso = $fila_permisos['valor_falso_permiso'];
        $permiso[$codigo_permiso] = $valor_falso_permiso;
        }
    $sql_permisos = "SELECT permiso.codigo_permiso, valor_verdadero_permiso FROM usuario_permiso, permiso WHERE usuario_permiso.codigo_permiso = permiso.codigo_permiso AND usuario_permiso.codigo_usuario = ".$codigo_usuario;
    $resultado_sql_permisos= mysqli_query($link, $sql_permisos);
    $numero_permisos_positivos = mysqli_num_rows($resultado_sql_permisos);
    for($j=1;$j<=$numero_permisos_positivos;$j++)
        {
        $fila_permisos_positivos = mysqli_fetch_array($resultado_sql_permisos);
        $codigo_permiso = $fila_permisos_positivos['codigo_permiso'];
        $valor_verdadero_permiso = $fila_permisos_positivos['valor_verdadero_permiso'];
        $permiso[$codigo_permiso] = $valor_verdadero_permiso;
        }      
    return 0;
    }
function select_permisos($opcion)
    {
    global $link;
    $respuesta = array();
    $sql = "SELECT codigo_permiso, valor_verdadero_permiso, valor_falso_permiso, valor_sin_establecer_permiso, descripcion_permiso FROM permiso ORDER BY descripcion_permiso ASC";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $respuesta[$i]['codigo_permiso'] = $fila_registros['codigo_permiso'];   
            $respuesta[$i]['valor_verdadero_permiso'] = $fila_registros['valor_verdadero_permiso']; 
            $respuesta[$i]['valor_falso_permiso'] = $fila_registros['valor_falso_permiso']; 
            $respuesta[$i]['valor_sin_establecer_permiso'] = $fila_registros['valor_sin_establecer_permiso'];     
            $respuesta[$i]['descripcion_permiso'] = $fila_registros['descripcion_permiso'];               
            }
    return $respuesta;
    }
 function inserta_permiso($codigo_permiso, $valor_verdadero_permiso, $valor_falso_permiso, $valor_sin_establecer_permiso, $descripcion_permiso)
    {     
    global $link;	
    $sql = "INSERT INTO permiso VALUES('".$codigo_permiso."','".$valor_verdadero_permiso."','".$valor_falso_permiso."','".$valor_sin_establecer_permiso."','".$descripcion_permiso."') ON DUPLICATE KEY UPDATE codigo_permiso = '".$codigo_permiso."', valor_verdadero_permiso = '".$valor_verdadero_permiso."',valor_falso_permiso = '".$valor_falso_permiso."', valor_sin_establecer_permiso = '".$valor_sin_establecer_permiso."', descripcion_permiso = '".$descripcion_permiso."'";
    $resultado_sql = mysqli_query($link,$sql);
    return 1;	// Retorna 1 si se realizo la inserción	
    }
function devuelve_permiso_por_codigo($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT codigo_permiso, valor_verdadero_permiso, valor_falso_permiso, valor_sin_establecer_permiso, descripcion_permiso FROM permiso WHERE codigo_permiso = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_permiso'] = $fila_registros['codigo_permiso'];
    $respuesta['valor_verdadero_permiso'] = $fila_registros['valor_verdadero_permiso'];   
    $respuesta['valor_falso_permiso'] = $fila_registros['valor_falso_permiso'];      
    $respuesta['valor_sin_establecer_permiso'] = $fila_registros['valor_sin_establecer_permiso'];   
    $respuesta['descripcion_permiso'] = $fila_registros['descripcion_permiso']; 
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function carga_permisos_usuarios($codigo_usuario)
    {
    $tamano_texto=11;
    global $link;
    $html_cabecera = '<div class="german"><div class="herman" style="position:absolute; width: 660px; margin-top: 188px; margin-left:585px;">';
    echo $html_cabecera;    
    $sql = "SELECT codigo_permiso, descripcion_permiso FROM permiso ORDER BY descripcion_permiso ASC";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
            {           
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $codigo_permiso = $fila_registros['codigo_permiso'];   
            $sql_busca_permiso = "SELECT * FROM usuario_permiso WHERE codigo_usuario = '".$codigo_usuario."' AND codigo_permiso = '".$codigo_permiso."'";
            $resultado_sql_permiso= mysqli_query($link,$sql_busca_permiso);
            $numero_registros_permiso = mysqli_num_rows($resultado_sql_permiso);
            $checked = "checked";
            if($numero_registros_permiso==0) $checked = "";
            $descripcion_permiso = "(".$codigo_permiso.") ".$fila_registros['descripcion_permiso']; 
            $html_fila = '<div style = "font-size:'.$tamano_texto.'px"><input type="checkbox" onChange="check_permisos(this,'.$codigo_permiso.')" '.$checked.'> '.$descripcion_permiso.'</div>';
            echo $html_fila;
            }
    $html_cierre = '</div></div>'; 
    echo $html_cierre;
    }    
function check_permisos($codigo_permiso,$codigo_usuario,$estado_check)
    {
    global $link;
    if($estado_check==1)
        $sql = "INSERT INTO usuario_permiso VALUES('".$codigo_usuario."','".$codigo_permiso."')";
    if($estado_check==0)
        $sql = "DELETE FROM usuario_permiso WHERE codigo_usuario = '".$codigo_usuario."' AND codigo_permiso = '".$codigo_permiso."'";
    $resultado_sql= mysqli_query($link,$sql);    
    }
function copia_permisos($codigo_perfil,$codigo_usuario)
    {
    global $link;
    $sql = "INSERT divasoft.usuario_permiso SELECT ".$codigo_usuario." as codigo_usuario, codigo_permiso FROM usuario_permiso WHERE codigo_usuario = ".$codigo_perfil;
    $resultado_sql= mysqli_query($link,$sql);
    }
function verifica_permisos_url($nombre_hoja,$codigo_usuario)
    {
    global $link;
    $sql = "SELECT codigo_permiso FROM permiso WHERE valor_sin_establecer_permiso = '".$nombre_hoja."'";
    $resultado_sql= mysqli_query($link, $sql);    
    $fila_registros = mysqli_fetch_array($resultado_sql);    
    $codigo_permiso = $fila_registros['codigo_permiso'];
    $sql_busca_permiso = "SELECT * FROM usuario_permiso WHERE codigo_usuario = '".$codigo_usuario."' AND codigo_permiso = '".$codigo_permiso."'";
    $resultado_sql_permiso= mysqli_query($link, $sql_busca_permiso);
    $numero_registros_permiso = mysqli_num_rows($resultado_sql_permiso);
    if($numero_registros_permiso==0)  header("location:home.php");
    return 0;
    }
//// CATEGORIAS PRODUCTO FACTURACION
function select_categorias_producto_facturacion($opcion)
    {
    global $link;
    $respuesta = array();
    $sql = "SELECT codigo_categoria_producto_facturacion, descripcion_categoria_producto_facturacion FROM categoria_producto_facturacion ORDER BY descripcion_categoria_producto_facturacion ASC";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
        {
        $fila_registros = mysqli_fetch_array($resultado_sql);
        $respuesta[$i]['codigo_categoria_producto_facturacion'] = $fila_registros['codigo_categoria_producto_facturacion'];   
        $respuesta[$i]['descripcion_categoria_producto_facturacion'] = $fila_registros['descripcion_categoria_producto_facturacion'];             
        }
    return $respuesta;
    }    
function devuelve_categoria_producto_facturacion($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT codigo_categoria_producto_facturacion, descripcion_categoria_producto_facturacion, totalizacion_medidas FROM categoria_producto_facturacion WHERE codigo_categoria_producto_facturacion = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_categoria_producto_facturacion'] = $fila_registros['codigo_categoria_producto_facturacion'];
    $respuesta['descripcion_categoria_producto_facturacion'] = $fila_registros['descripcion_categoria_producto_facturacion'];
    $respuesta['totalizacion_medidas'] = $fila_registros['totalizacion_medidas'];
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function busca_categoria_productos_facturacion($nombre)
	{
	global $link;
	$sql = "SELECT codigo_categoria_producto_facturacion FROM categoria_producto_facturacion WHERE descripcion_categoria_producto_facturacion = '".$nombre."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_categoria_producto_facturacion($codigo_categoria_producto_facturacion,$descripcion_categoria_producto_facturacion,$totalizacion_medidas)
	{
	global $link;
        $descripcion_categoria_producto_facturacion = strtoupper($descripcion_categoria_producto_facturacion);
	$codigo=busca_categoria_productos_facturacion($descripcion_categoria_producto_facturacion);
	if($codigo>0&&$codigo<>$codigo_categoria_producto_facturacion)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO categoria_producto_facturacion (codigo_categoria_producto_facturacion,descripcion_categoria_producto_facturacion,totalizacion_medidas) VALUES('".$codigo_categoria_producto_facturacion."','".$descripcion_categoria_producto_facturacion."','".$totalizacion_medidas."') ON DUPLICATE KEY UPDATE codigo_categoria_producto_facturacion = '".$codigo_categoria_producto_facturacion."', descripcion_categoria_producto_facturacion = '".$descripcion_categoria_producto_facturacion."', totalizacion_medidas = '".$totalizacion_medidas."'";
	$resultado_sql = mysqli_query($link,$sql);
        return 1;
        }
function elimina_catgeoria_producto_facturacion($codigo)
	{
	global $link;	
	$puede_eliminar = 0;
	// Chequeos 
//	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
//	$resultado_sql_1 = mysql_query($sql_1,$link);
//	$fila_1 = mysqli_fetch_array($resultado_sql_1);
//	$numero_1 = $fila_1[0];
//	if($numero_1>0)
//		$puede_eliminar = 0;
	if($puede_eliminar==1)
            {
            $sql = "DELETE FROM categoria_producto_facturacion WHERE codigo_categoria_producto_facturacion = '".$codigo."'";
            $resultado_sql = mysqli_query($link,$sql);
            return 1;	// Retorna 1 si se realizo la eliminación		
            }
	return 0;
	}        
//// CATEGORIAS MARCACIONES
function select_marcaciones($opcion)
    {
    global $link;
    $respuesta = array();
    $sql = "SELECT codigo_marcacion, nombre_marcacion, codigo_cliente_marcacion, nombre_cliente, codigo_ciudad_cliente, codigo_pais_cliente, nombre_pais, nombre_ciudad FROM marcacion, cliente, pais, ciudad WHERE marcacion.codigo_cliente_marcacion = cliente.codigo_cliente AND cliente.codigo_pais_cliente = pais.codigo_pais AND cliente.codigo_ciudad_cliente = ciudad.codigo_ciudad ORDER BY nombre_marcacion ASC";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
        {
        $fila_registros = mysqli_fetch_array($resultado_sql);
        $respuesta[$i]['codigo_marcacion'] = $fila_registros['codigo_marcacion'];   
        $respuesta[$i]['nombre_marcacion'] = $fila_registros['nombre_marcacion'];
        $respuesta[$i]['nombre_cliente'] = $fila_registros['nombre_cliente'];
        $respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais'];
        $respuesta[$i]['nombre_ciudad'] = $fila_registros['nombre_ciudad'];
        }
    return $respuesta;
    }   
function devuelve_marcacion($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT codigo_marcacion, nombre_marcacion, codigo_cliente_marcacion FROM marcacion WHERE codigo_marcacion = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_marcacion'] = $fila_registros['codigo_marcacion'];
    $respuesta['nombre_marcacion'] = $fila_registros['nombre_marcacion'];   
    $respuesta['codigo_cliente_marcacion'] = $fila_registros['codigo_cliente_marcacion']; 
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function busca_marcacion($nombre)
	{
	global $link;
	$sql = "SELECT codigo_marcacion FROM marcacion WHERE nombre_marcacion = '".$nombre."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_marcacion($codigo_marcacion,$nombre_marcacion,$codigo_cliente_marcacion)
	{
	global $link;
        $nombre_marcacion = strtoupper($nombre_marcacion);
	$codigo=busca_marcacion($nombre_marcacion);
	if($codigo>0&&$codigo<>$codigo_marcacion)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO marcacion (codigo_marcacion,nombre_marcacion,codigo_cliente_marcacion) VALUES('".$codigo_marcacion."','".$nombre_marcacion."','".$codigo_cliente_marcacion."') ON DUPLICATE KEY UPDATE codigo_marcacion = '".$codigo_marcacion."', nombre_marcacion = '".$nombre_marcacion."', codigo_cliente_marcacion = '".$codigo_cliente_marcacion."'";
	$resultado_sql = mysqli_query($link,$sql);
        return 1;
        }
function elimina_marcacion($codigo)
	{
	global $link;	
	$puede_eliminar = 0;
	// Chequeos 
//	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
//	$resultado_sql_1 = mysql_query($sql_1,$link);
//	$fila_1 = mysqli_fetch_array($resultado_sql_1);
//	$numero_1 = $fila_1[0];
//	if($numero_1>0)
//		$puede_eliminar = 0;
	if($puede_eliminar==1)
            {
            $sql = "DELETE FROM marcacion WHERE codigo_marcacion = '".$codigo."'";
            $resultado_sql = mysqli_query($link,$sql);
            return 1;	// Retorna 1 si se realizo la eliminación		
            }
	return 0;
	}
// CATEGORIAS CLIENTES
function select_clientes($opcion)
    {
    global $link;
    $respuesta = array();
    $sql = "SELECT codigo_cliente, nombre_pais, nombre_ciudad, nombre_cliente FROM cliente, pais, ciudad WHERE codigo_ciudad_cliente = codigo_ciudad AND codigo_pais_cliente = pais.codigo_pais ORDER BY nombre_cliente ASC";
    $resultado_sql= mysqli_query($link,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
        {
        $fila_registros = mysqli_fetch_array($resultado_sql);
        $respuesta[$i]['codigo_cliente'] = $fila_registros['codigo_cliente'];   
        $respuesta[$i]['nombre_cliente'] = $fila_registros['nombre_cliente']; 
        $respuesta[$i]['nombre_pais'] = $fila_registros['nombre_pais'];
        $respuesta[$i]['nombre_ciudad'] = $fila_registros['nombre_ciudad'];
        }
    return $respuesta;
    }   
function busca_cliente($nombre)
	{
	global $link;
	$sql = "SELECT codigo_cliente FROM cliente WHERE nombre_cliente = '".$nombre."'";
	$resultado_sql = mysqli_query($link,$sql);
	$fila_registros = mysqli_fetch_array($resultado_sql);
	$numero = $fila_registros[0];
	return $numero;		
	}
function inserta_cliente($codigo_cliente,$nombre_cliente,$email_facturas_cliente,$email_estado_cuenta_cliente,$telefono_cliente,$direccion_cliente,$codigo_pais_cliente,$codigo_ciudad_cliente,$observaciones_cliente)
	{
	global $link;
        $nombre_cliente = strtoupper($nombre_cliente);
	$codigo=busca_cliente($nombre_cliente);
	if($codigo>0&&$codigo<>$codigo_cliente)
		return 0; // Rertorna 0 si encuenta repetido el registro	
	$sql = "INSERT INTO cliente (codigo_cliente, nombre_cliente, email_facturas_cliente, email_estado_cuenta_cliente, telefono_cliente, direccion_cliente, codigo_ciudad_cliente, codigo_pais_cliente, observaciones_cliente) VALUES('".$codigo_cliente."','".$nombre_cliente."','".$email_facturas_cliente."','".$email_estado_cuenta_cliente."','".$telefono_cliente."','".$direccion_cliente."','".$codigo_ciudad_cliente."','".$codigo_pais_cliente."','".$observaciones_cliente."') ON DUPLICATE KEY UPDATE codigo_cliente = '".$codigo_cliente."', nombre_cliente = '".$nombre_cliente."', email_facturas_cliente = '".$email_facturas_cliente."', email_estado_cuenta_cliente = '".$email_estado_cuenta_cliente."', telefono_cliente = '".$telefono_cliente."', direccion_cliente = '".$direccion_cliente."', codigo_ciudad_cliente = '".$codigo_ciudad_cliente."', codigo_pais_cliente = '".$codigo_pais_cliente."', observaciones_cliente = '".$observaciones_cliente."'";
	$resultado_sql = mysqli_query($link,$sql);
        return 1;
        }
function devuelve_cliente($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT codigo_cliente, nombre_cliente, email_facturas_cliente, email_estado_cuenta_cliente, telefono_cliente, direccion_cliente, codigo_ciudad_cliente, codigo_pais_cliente, observaciones_cliente FROM cliente WHERE codigo_cliente = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_cliente'] = $fila_registros['codigo_cliente'];
    $respuesta['nombre_cliente'] = $fila_registros['nombre_cliente']; 
    $respuesta['email_facturas_cliente'] = $fila_registros['email_facturas_cliente'];
    $respuesta['email_estado_cuenta_cliente'] = $fila_registros['email_estado_cuenta_cliente'];
    $respuesta['telefono_cliente'] = $fila_registros['telefono_cliente'];
    $respuesta['direccion_cliente'] = $fila_registros['direccion_cliente'];
    $respuesta['codigo_ciudad_cliente'] = $fila_registros['codigo_ciudad_cliente'];
    $respuesta['codigo_pais_cliente'] = $fila_registros['codigo_pais_cliente'];
    $respuesta['observaciones_cliente'] = $fila_registros['observaciones_cliente'];
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function elimina_cliente($codigo)
	{
	global $link;	
	$puede_eliminar = 1;
	// Chequeos 
//	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
//	$resultado_sql_1 = mysql_query($sql_1,$link);
//	$fila_1 = mysqli_fetch_array($resultado_sql_1);
//	$numero_1 = $fila_1[0];
//	if($numero_1>0)
//		$puede_eliminar = 0;
	if($puede_eliminar==1)
            {
            $sql = "DELETE FROM cliente WHERE codigo_cliente = '".$codigo."'";
            $resultado_sql = mysqli_query($link,$sql);
            return 1;	// Retorna 1 si se realizo la eliminación		
            }
	return 0;
	}
// PAGOS
function ingresa_pago($codigo_proveedor, $valor_solicitado, $tipo_pago, $observaciones, $codigo_persona_solicita)
    {
    global $link;
    $sql = "INSERT INTO pago (codigo_pago, codigo_proveedor, valor_solicitado, tipo_pago, observaciones, codigo_persona_solicita, fecha_solicitud, codigo_banco) VALUES ('0', '".$codigo_proveedor."', '".$valor_solicitado."', '".$tipo_pago."', '".$observaciones."', '".$codigo_persona_solicita."', NOW(),'0')";
    $resultado_sql = mysqli_query($link,$sql);
    return 1;    
    }
function select_pagos($opcion)
    {
    global $link;
    commit();
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
    $respuesta = array();
    if($opcion==4) // TODOS
       { 
        $sql = "SELECT codigo_pago, DATE_FORMAT(fecha_solicitud,'%Y-%m-%d') AS fecha_solicitud, username_usuario, nombre_proveedor, nombre_comercial_proveedor, valor_solicitado, observaciones, tipo_pago FROM pago, usuario, proveedor WHERE codigo_persona_solicita = usuario.codigo_usuario AND proveedor.codigo_proveedor = pago.codigo_proveedor AND IsNULL(valor_procesado) ORDER BY fecha_solicitud DESC";
        $resultado_sql= mysqli_query($link,$sql);
        $numero_registros = mysqli_num_rows($resultado_sql);
        for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $respuesta[$i]['codigo_pago'] = $fila_registros['codigo_pago'];   
            $respuesta[$i]['fecha_solicitud'] = $fila_registros['fecha_solicitud']; 
            $respuesta[$i]['username_usuario'] = $fila_registros['username_usuario'];
            $respuesta[$i]['nombre_proveedor'] = $fila_registros['nombre_proveedor'];
            $respuesta[$i]['nombre_comercial_proveedor'] = $fila_registros['nombre_comercial_proveedor'];
            $respuesta[$i]['valor_solicitado'] = $fila_registros['valor_solicitado'];
            $respuesta[$i]['observaciones'] = $fila_registros['observaciones'];
            $respuesta[$i]['tipo_pago'] = $fila_registros['tipo_pago'];
            }
        }
    if($opcion<4) // 
       { 
        $sql = "SELECT codigo_pago, DATE_FORMAT(fecha_solicitud,'%Y-%m-%d') AS fecha_solicitud, username_usuario, nombre_proveedor, nombre_comercial_proveedor, valor_solicitado, observaciones, tipo_pago FROM pago, usuario, proveedor WHERE codigo_persona_solicita = usuario.codigo_usuario AND proveedor.codigo_proveedor = pago.codigo_proveedor AND IsNULL(valor_procesado) AND tipo_pago =".$opcion." ORDER BY fecha_solicitud DESC";
        $resultado_sql= mysqli_query($link,$sql);
        $numero_registros = mysqli_num_rows($resultado_sql);
        for($i=1;$i<=$numero_registros;$i++)
            {
            $fila_registros = mysqli_fetch_array($resultado_sql);
            $respuesta[$i]['codigo_pago'] = $fila_registros['codigo_pago'];   
            $respuesta[$i]['fecha_solicitud'] = $fila_registros['fecha_solicitud']; 
            $respuesta[$i]['username_usuario'] = $fila_registros['username_usuario'];
            $respuesta[$i]['nombre_proveedor'] = $fila_registros['nombre_proveedor'];
            $respuesta[$i]['nombre_comercial_proveedor'] = $fila_registros['nombre_comercial_proveedor'];
            $respuesta[$i]['valor_solicitado'] = $fila_registros['valor_solicitado'];
            $respuesta[$i]['observaciones'] = $fila_registros['observaciones'];
            $respuesta[$i]['tipo_pago'] = $fila_registros['tipo_pago'];
            }
        }
    return $respuesta;
    }   
function devuelve_pago_por_codigo($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT pago.codigo_proveedor, nombre_proveedor, valor_solicitado, observaciones, tipo_pago FROM pago, proveedor WHERE pago.codigo_proveedor = proveedor.codigo_proveedor AND codigo_pago = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_proveedor'] = $fila_registros['codigo_proveedor'];
    $respuesta['nombre_proveedor'] = $fila_registros['nombre_proveedor'];    
    $respuesta['valor_solicitado'] = $fila_registros['valor_solicitado'];   
    $respuesta['observaciones'] = $fila_registros['observaciones'];      
    $respuesta['tipo_pago'] = $fila_registros['tipo_pago'];   
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
 function devuelve_pago_proceso($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT valor_solicitado, valor_procesado, observaciones, tipo_pago, nombre_banco, tipo_cuenta_banaria_proveedor, cuenta_bancaria_proveedor, nombre_beneficiario_cuenta_bancaria_proveedor, tipo_identificacion_cuenta_bancaria_proveedor, identificacion_cuenta_bancaria_proveedor, email_pagos_proveedor, nombre_proveedor, nombre_comercial_proveedor FROM pago, proveedor, banco WHERE pago.codigo_proveedor = proveedor.codigo_proveedor AND proveedor.codigo_banco_proveedor = banco.codigo_banco AND codigo_pago = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $tipo_cuenta = "CA";
    if($fila_registros['tipo_cuenta_banaria_proveedor'] == 2) $tipo_cuenta = "CC";
    $tipo_id = "SD";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 1) $tipo_id = "RUC";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 2) $tipo_id = "CED";
    $datos_transferancia = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']."\n\n".$fila_registros['nombre_banco']."\n-".$tipo_cuenta.": ".$fila_registros['cuenta_bancaria_proveedor']."\n-B: ".$fila_registros['nombre_beneficiario_cuenta_bancaria_proveedor']."\n-".$tipo_id.": ".$fila_registros['identificacion_cuenta_bancaria_proveedor']."\n-@: ".$fila_registros['email_pagos_proveedor'];
    $respuesta['valor_solicitado'] = $fila_registros['valor_solicitado'];
    $respuesta['observaciones'] = $fila_registros['observaciones'];    
    $respuesta['tipo_pago'] = $fila_registros['tipo_pago']; 
    $respuesta['datos_transferancia'] = $datos_transferancia;
    $respuesta['nombre_proveedor'] = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']; 
    $respuesta['email_pagos_proveedor'] = $fila_registros['email_pagos_proveedor'];
    $respuesta['valor_procesado'] = $fila_registros['valor_procesado'];
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
 function devuelve_pago_proceso_edicion($codigo)
    {
    global $link;
    $respuesta = Array();
    $sql = "SELECT valor_solicitado, valor_procesado, observaciones, tipo_pago, b1.nombre_banco AS nombre_banco, b2.nombre_banco AS nombre_banco_procesado, tipo_cuenta_banaria_proveedor, cuenta_bancaria_proveedor, nombre_beneficiario_cuenta_bancaria_proveedor, tipo_identificacion_cuenta_bancaria_proveedor, identificacion_cuenta_bancaria_proveedor, email_pagos_proveedor, nombre_proveedor, nombre_comercial_proveedor, comprobante_pago FROM pago, proveedor, banco b1, banco b2 WHERE pago.codigo_proveedor = proveedor.codigo_proveedor AND proveedor.codigo_banco_proveedor = b1.codigo_banco AND pago.codigo_banco = b2.codigo_banco AND codigo_pago = ".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $tipo_cuenta = "CA";
    if($fila_registros['tipo_cuenta_banaria_proveedor'] == 2) $tipo_cuenta = "CC";
    $tipo_id = "SD";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 1) $tipo_id = "RUC";
    if($fila_registros['tipo_identificacion_cuenta_bancaria_proveedor'] == 2) $tipo_id = "CED";
    $datos_transferancia = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']."\n\n".$fila_registros['nombre_banco']."\n-".$tipo_cuenta.": ".$fila_registros['cuenta_bancaria_proveedor']."\n-B: ".$fila_registros['nombre_beneficiario_cuenta_bancaria_proveedor']."\n-".$tipo_id.": ".$fila_registros['identificacion_cuenta_bancaria_proveedor']."\n-@: ".$fila_registros['email_pagos_proveedor'];
    $respuesta['valor_solicitado'] = $fila_registros['valor_solicitado'];
    $respuesta['observaciones'] = $fila_registros['observaciones'];    
    $respuesta['tipo_pago'] = $fila_registros['tipo_pago']; 
    $respuesta['datos_transferancia'] = $datos_transferancia;
    $respuesta['nombre_proveedor'] = $fila_registros['nombre_proveedor']." / ".$fila_registros['nombre_comercial_proveedor']; 
    $respuesta['email_pagos_proveedor'] = $fila_registros['email_pagos_proveedor'];
    $respuesta['valor_procesado'] = $fila_registros['valor_procesado'];
    $respuesta['nombre_banco_procesado'] = $fila_registros['nombre_banco_procesado'];
    $respuesta['comprobante_pago'] = $fila_registros['comprobante_pago'];
    header ("Content-Type:text/xml");
    return $respuesta;		
    }
function elimina_pago($codigo)
    {
    global $link;	
    $puede_eliminar = 1;
    // Chequeos 
//		$puede_eliminar = 0;
    if($puede_eliminar==1)
        {
        $sql = "DELETE FROM pago WHERE codigo_pago = '".$codigo."'";
        $resultado_sql = mysqli_query($link,$sql);
        return 1;	// Retorna 1 si se realizo la eliminación		
        }
    return 0;
    }
function carga_pagos_pendientes($opcion)
    {
    $arreglo_registros = select_pagos($opcion);
    $total_registros = count($arreglo_registros);
    $tamano_texto = 11;
    $padding_tabla_aida = 0;
    $estilo_tabla_aida = "padding: ".$padding_tabla_aida."px; font-size:".$tamano_texto."px;";
    $checked[1] = $checked[2] = $checked[3]= "";
    $checked[$opcion] = "CHECKED";
    $html_cabecera = '
        <div class="aida" style="width: 795px; margin-top: 25px; margin-left:10px;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <b>PAGOS PENDIENTES</b>
                    <div class="input-control radio default-style" data-role="input-control" style="margin-left:210px;">
                        <label><input type="radio" id="txtFiltroPagoS1" name="r1" '.$checked[1].' onClick="carga_pendientes(1);"><span class="check"></span>Cheque <i class="icon-ticket fg-pink"></i></label>
                    </div>
                    <div class="input-control radio  default-style" data-role="input-control">
                       <label>&nbsp;&nbsp;<input type="radio" id="txtFiltroPagoS2" name="r1" '.$checked[2].' onClick="carga_pendientes(2);"><span class="check"></span>Trans <i class="icon-keyboard fg-crimson"></i></label>
                    </div>          
                    <div class="input-control radio  default-style" data-role="input-control">
                        <label>&nbsp;&nbsp;<input type="radio" id="txtFiltroPagoS3" name="r1" '.$checked[3].' onClick="carga_pendientes(3);"><span class="check"></span>Otro <i class="icon-target fg-blue"></i>&nbsp;&nbsp;</label>
                    </div>
                    <a href="javascript: carga_pendientes(4);" class="button bg-crimson bg-hover-red fg-white fg-hover-white bd-orange" style = "font-size:'.$tamano_texto.'px; height:24px" tabindex="1">Todos</a>       
                    <table class="table hovered" style="padding:0px;">
                    <thead>
                        <tr>
                            <th class="text-left" style="'.$estilo_tabla_aida.'">&nbsp;</th> 
                            <th class="text-left" style="'.$estilo_tabla_aida.'">No.&nbsp;</th>
                            <th class="text-left" style="'.$estilo_tabla_aida.'">SOLICITADO</th>
                            <th class="text-left" style="'.$estilo_tabla_aida.'">PROVEEDOR</th>
                            <th class="text-left" style="'.$estilo_tabla_aida.'">VALOR</th>
                            <th class="text-left" style="'.$estilo_tabla_aida.'"> </th>
                            <th class="text-left" style="'.$estilo_tabla_aida.'">OBSERVACIONES</th>
                            <th class="text-center" style="'.$estilo_tabla_aida.'">Opciones</th>
                        </tr>
                    </thead>
                    <tbody>       
            ';
    $html_cuerpo = "";
    for ($j = 1; $j <= $total_registros; $j++) 
        {
        $codigo_pago = $arreglo_registros[$j]['codigo_pago'];
        $fecha_solicitud = $arreglo_registros[$j]['fecha_solicitud']." (".$arreglo_registros[$j]['username_usuario'].")";                    
        $nombre_proveedor = $arreglo_registros[$j]['nombre_proveedor'];
        $nombre_comercial_proveedor = $arreglo_registros[$j]['nombre_comercial_proveedor'];
        if($nombre_proveedor!=$nombre_comercial_proveedor)
            $nombre_proveedor .= " / ".$nombre_comercial_proveedor;
        $valor_solicitado = $arreglo_registros[$j]['valor_solicitado'];
        $observaciones = $arreglo_registros[$j]['observaciones'];                        
        $tipo_pago = $arreglo_registros[$j]['tipo_pago'];
        $class = "icon-ticket fg-pink";
        if($tipo_pago==2) $class = "icon-keyboard fg-crimson";
        if($tipo_pago==3) $class = "icon-target fg-blue";
        $html_cuerpo .= '
            <tr>
                    <td class="text-center" style="'.$estilo_tabla_aida.'">&nbsp;<i class="'.$class.'"></i></td>
                    <td class="text-center" style="'.$estilo_tabla_aida.'">'.$codigo_pago.'</td>
                    <td class="right" style="'.$estilo_tabla_aida.'">'.$fecha_solicitud.'</td>
                    <td class="right" style="'.$estilo_tabla_aida.'"><strong>'.$nombre_proveedor.'</strong></td>
                    <td class="right" style="'.$estilo_tabla_aida.'"><strong>$'.$valor_solicitado.'</strong></td>
                    <td class="right" style="'.$estilo_tabla_aida.'"><strong>&nbsp;&nbsp;</strong></td>
                    <td class="right" style="'.$estilo_tabla_aida.'">'.$observaciones.'</td>
                    <td class="text-center" style="'.$estilo_tabla_aida.'" >
                        <a href="javascript: devuelve_pago_para_proceso('.$codigo_pago.')"><i title="Procesar el Pago" class="icon-pencil icon-dollar-2 fg-green"></i></a>
                        &nbsp;<a href="javascript: devuelve_pago('.$codigo_pago.')"><i title="Editar el Pago" class="icon-pencil fg-brown"></i></a>
                        <a href="javascript: elimina_pago('.$codigo_pago.')"><i title="Eliminar el Pago" class="icon-remove fg-red"></i></a>
                    </td>
            </tr>';
        }
    $html_pie = '
                        </tbody>
                    <tfoot>
                </tfoot>
            </table>
        </div>';
    echo $html_cabecera.$html_cuerpo.$html_pie; 
    }
function modifica_pago($codigo,$codigo_proveedor, $valor_solicitado, $tipo_pago, $observaciones, $codigo_persona_solicita)
    {
    global $link;
    $sql = "UPDATE pago SET codigo_proveedor = '".$codigo_proveedor."', valor_solicitado = '".$valor_solicitado."', tipo_pago = '".$tipo_pago."', observaciones = '".$observaciones."', codigo_persona_solicita = '".$codigo_persona_solicita."', fecha_solicitud = NOW() WHERE codigo_pago=".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    return 1;    
    }
function procesa_pago($codigo, $valor_procesado, $tipo_pago, $observaciones, $codigo_persona_procesa, $codigo_banco, $comprobante_pago)
    {
    global $link;
   echo $sql = "UPDATE pago SET valor_procesado = '".$valor_procesado."', tipo_pago = '".$tipo_pago."', codigo_banco = '".$codigo_banco."', observaciones = '".$observaciones."', codigo_persona_procesa = '".$codigo_persona_procesa."', fecha_proceso = NOW(), comprobante_pago = '".$comprobante_pago."' WHERE codigo_pago=".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    return 1;    
    }
function select_pagos_procesados($fecha_inicio,$fecha_final,$opcion)
    {
    global $link;
    commit();
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");
    $respuesta = array();
    if($opcion==1) // TODOS
       { 
        $sql = "SELECT codigo_pago, nombre_banco, DATE_FORMAT(fecha_proceso,'%Y-%m-%d') AS fecha_proceso, DATE_FORMAT(fecha_solicitud,'%Y-%m-%d') AS fecha_solicitud, up.username_usuario AS username_usuario_procesa, us.username_usuario AS username_usuario_solicita, nombre_proveedor, nombre_comercial_proveedor, valor_solicitado, valor_procesado, observaciones, tipo_pago  FROM pago, usuario up, proveedor, banco, usuario us WHERE pago.codigo_banco = banco.codigo_banco AND codigo_persona_solicita = us.codigo_usuario AND codigo_persona_procesa = up.codigo_usuario AND proveedor.codigo_proveedor = pago.codigo_proveedor AND !IsNULL(valor_procesado) ORDER BY fecha_proceso DESC";
        $resultado_sql= mysqli_query($link, $sql);
        $numero_registros = mysqli_num_rows($resultado_sql);
       }
    else // con fecha
       { 
        $sql = "SELECT codigo_pago, nombre_banco, DATE_FORMAT(fecha_proceso,'%Y-%m-%d') AS fecha_proceso, DATE_FORMAT(fecha_solicitud,'%Y-%m-%d') AS fecha_solicitud, up.username_usuario AS username_usuario_procesa, us.username_usuario AS username_usuario_solicita, nombre_proveedor, nombre_comercial_proveedor, valor_solicitado, valor_procesado, observaciones, tipo_pago  FROM pago, usuario up, proveedor, banco, usuario us WHERE pago.codigo_banco = banco.codigo_banco AND codigo_persona_solicita = us.codigo_usuario AND codigo_persona_procesa = up.codigo_usuario AND proveedor.codigo_proveedor = pago.codigo_proveedor AND !IsNULL(valor_procesado) AND fecha_proceso >= '".$fecha_inicio." 00:00:00' AND fecha_proceso <= '".$fecha_final." 23:59:59' ORDER BY fecha_proceso DESC";
        $resultado_sql= mysqli_query($link, $sql);
        $numero_registros = mysqli_num_rows($resultado_sql);
       }   
    for($i=1;$i<=$numero_registros;$i++)
        {
        $fila_registros = mysqli_fetch_array($resultado_sql);
        $respuesta[$i]['codigo_pago'] = $fila_registros['codigo_pago'];   
        $respuesta[$i]['fecha_proceso'] = $fila_registros['fecha_proceso']; 
        $respuesta[$i]['username_usuario_procesa'] = $fila_registros['username_usuario_procesa'];
        $respuesta[$i]['username_usuario_solicita'] = $fila_registros['username_usuario_solicita'];
        $respuesta[$i]['nombre_proveedor'] = $fila_registros['nombre_proveedor'];
        $respuesta[$i]['nombre_comercial_proveedor'] = $fila_registros['nombre_comercial_proveedor'];
        $respuesta[$i]['valor_procesado'] = $fila_registros['valor_procesado'];
        $respuesta[$i]['valor_solicitado'] = $fila_registros['valor_solicitado'];
        $respuesta[$i]['observaciones'] = $fila_registros['observaciones'];
        $respuesta[$i]['nombre_banco'] = $fila_registros['nombre_banco'];
        $respuesta[$i]['tipo_pago'] = $fila_registros['tipo_pago'];
        $respuesta[$i]['fecha_solicitud'] = $fila_registros['fecha_solicitud'];
        }
    return $respuesta;
    }   

function carga_pagos_procesados($fecha_inicial,$fecha_final,$opcion)
    {
    global $url_sitio;
    if($opcion == 2) $fecha_inicial=$fecha_final = date("Y-m-d");; //$fecha_inicial=$fecha_final = "2015-07-06";    
    $arreglo_registros = select_pagos_procesados($fecha_inicial,$fecha_final,$opcion);
    $total_registros = count($arreglo_registros);
    $html_cuerpo="";
    $tamano_texto = 11;
    $padding_tabla_aida = 0;
    $estilo_tabla_aida = "padding: ".$padding_tabla_aida."px; font-size:".$tamano_texto."px;";
    $html_cabecera = '   
        <div class="aida" style="width: 795px; margin-top: 10px; margin-left:10px;">
            <div class="ribbed-crimson" style="height: 2px;"></div>
            <b>PAGOS PROCESADOS.</b>          
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
            Desde: 
            <div class="input-control text" style = "width: 140px; font-size:'.$tamano_texto.'px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom">
                <input name="txtFechaDesde" id="txtFechaDesde" type="text" placeholder="aaaa-mm-dd" style = "font-size:'.$tamano_texto.'px; height:25px" value="'.$fecha_inicial.'" onBlur="carga_procesados(3);">
                <button class="btn-date fg-crimson" tabindex="1" style = "top: 0px;"></button>
            </div>
            Hasta: 
            <div class="input-control text" style = "width: 140px; font-size:<?php echo $tamano_texto; ?>px;" data-role="datepicker" data-format="yyyy-mm-dd" data-position="bottom">
                <input name="txtFechaHasta" id="txtFechaHasta" type="text" placeholder="aaaa-mm-dd" style = "font-size:'.$tamano_texto.'px; height:25px" value="'.$fecha_final.'" onBlur="carga_procesados(3);">
                <button class="btn-date fg-crimson" tabindex="2" style="top: 0px;"></button>
            </div>
            <i class="icon-filter fg-crimson" onClick="carga_procesados(3);"></i> 
            &nbsp;&nbsp;
            <a href="javascript: carga_procesados(1);" class="button bg-crimson bg-hover-red fg-white fg-hover-white bd-orange" style = "font-size:'.$tamano_texto.'px; height:24px" tabindex="3">Todos</a>
            <table class="table hovered" style="padding:0px;">
                <thead>
                    <tr>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">No.</th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">PROCESADO</th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">PROVEEDOR</th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">VALOR</th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'"> </th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">BANCO</th>
                        <th class="text-left" style="'.$estilo_tabla_aida.'">OBSERVACIONES</th>
                        <th class="text-center" style="'.$estilo_tabla_aida.'">Opciones</th>
                    </tr>
                </thead>
                <tbody>';
    for ($j = 1; $j <= $total_registros; $j++) 
        {
        $codigo_pago = $arreglo_registros[$j]['codigo_pago'];
        $fecha_proceso = $arreglo_registros[$j]['fecha_proceso']." (".$arreglo_registros[$j]['username_usuario_procesa'].")";                    
        $nombre_proveedor = $arreglo_registros[$j]['nombre_proveedor'];
        $nombre_comercial_proveedor = $arreglo_registros[$j]['nombre_comercial_proveedor'];
        if($nombre_proveedor!=$nombre_comercial_proveedor) $nombre_proveedor .= " / ".$nombre_comercial_proveedor;
        $valor_procesado = $arreglo_registros[$j]['valor_procesado'];
        $valor_solicitado_texto = "VALOR SOLICITADO:\n".$arreglo_registros[$j]['valor_solicitado']." (".$arreglo_registros[$j]['username_usuario_solicita'].")"; 
        $valor_solicitado = $arreglo_registros[$j]['valor_solicitado'];
        $observaciones = $arreglo_registros[$j]['observaciones'];                        
        $tipo_pago = $arreglo_registros[$j]['tipo_pago'];
        $class = "icon-ticket fg-pink";
        if($tipo_pago==2) $class = "icon-keyboard fg-crimson";
        if($tipo_pago==3) $class = "icon-target fg-blue";
        $nombre_banco = $arreglo_registros[$j]['nombre_banco'];
        $color_diferencia_valor = "";
        if($valor_procesado!=$valor_solicitado) $color_diferencia_valor=" color: red;";
        $fecha_solicitud = $arreglo_registros[$j]['fecha_solicitud']." (".$arreglo_registros[$j]['username_usuario_solicita'].")"; 
        $html_cuerpo .= '
            <tr>
                <td class="text-center" style="'.$estilo_tabla_aida.'"><i class="'.$class.'"></i>&nbsp;&nbsp;'.$codigo_pago.'</td>
                <td class="right" style="'.$estilo_tabla_aida.'" title="'.$fecha_solicitud.'">'.$fecha_proceso.'</td>
                <td class="right" style="'.$estilo_tabla_aida.'"><strong>'.$nombre_proveedor.'</strong></td>
                <td class="right" style="'.$estilo_tabla_aida.$color_diferencia_valor.'" title="'.$valor_solicitado_texto.'"><strong>$'.$valor_procesado.'</strong></td>
                <td class="right" style="'.$estilo_tabla_aida.'"><strong>&nbsp;</strong></td>
                <td class="right" style="'.$estilo_tabla_aida.'">'.$nombre_banco.'</td>
                <td class="right" style="'.$estilo_tabla_aida.'">&nbsp;'.$observaciones.'</td>
                <td class="text-center" style="'.$estilo_tabla_aida.'">
                    <a href="javascript: devuelve_pago_procesado_para_edicion('.$codigo_pago.')"><i title="Editar" class="icon-pencil fg-brown"></i></a>
                    <a href="javascript: envia_email('.$codigo_pago.')"><i title="Enviar email"  class="icon-mail-2"></i></a>
                    <a href="javascript: retornar_pago('.$codigo_pago.')"><i title="Retornar" class="icon-upload-3 fg-crimson"></i></a>
                </td>
            </tr>';
        }                           
    $html_final = '        
                </tbody>
                <tfoot></tfoot>
            </table>            
        </div>';
    echo $html_cabecera.$html_cuerpo.$html_final;
    }


function retornar_pago($codigo)
    {
    global $link;
    $sql = "UPDATE pago SET valor_procesado = NULL, codigo_banco = '0', codigo_persona_procesa = NULL, fecha_proceso = NULL, fecha_solicitud = NOW(), comprobante_pago = NULL WHERE codigo_pago=".$codigo;
    $resultado_sql = mysqli_query($link,$sql);
    return 1;    
    }
// PROMOCIONES
function select_suscripciones($opcion)
    {
    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysql_select_db($dbname_web);
    $respuesta = array();
    mysqli_query($link,"SET CHARACTER SET utf8");
    $sql = "SELECT codigo_promocion, nombre_promocion, telefono_promocion, email_promocion, lugar_promocion, DATE_FORMAT(fecha_promocion,'%Y-%m-%d') AS fecha_promocion, envio_promocion FROM divabase.promocion ORDER BY codigo_promocion DESC";
    $resultado_sql= mysqli_query($link_web,$sql);
    $numero_registros = mysqli_num_rows($resultado_sql);
    for($i=1;$i<=$numero_registros;$i++)
        {
        $fila_registros = mysqli_fetch_array($resultado_sql);
        $respuesta[$i]['codigo_promocion'] = $fila_registros['codigo_promocion'];   
        $respuesta[$i]['nombre_promocion'] = $fila_registros['nombre_promocion'];
        $respuesta[$i]['telefono_promocion'] = $fila_registros['telefono_promocion'];
        $respuesta[$i]['email_promocion'] = $fila_registros['email_promocion'];
        $respuesta[$i]['lugar_promocion'] = $fila_registros['lugar_promocion'];
        $respuesta[$i]['fecha_promocion'] = $fila_registros['fecha_promocion'];
        $respuesta[$i]['envio_promocion'] = $fila_registros['envio_promocion'];
        }
    mysql_close($link_web);    
    return $respuesta;

    }   
function devuelve_suscripcion($codigo)
    {
    return NULL;
    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysqli_select_db($dbname_web);
    mysqli_query($link_web, "SET CHARACTER SET utf8");
    $respuesta = Array();
    $sql = "SELECT codigo_promocion, nombre_promocion, telefono_promocion, email_promocion, lugar_promocion, DATE_FORMAT(fecha_promocion,'%Y-%m-%d') AS fecha_promocion FROM divabase.promocion WHERE codigo_promocion = ".$codigo;
    $resultado_sql = mysqli_query($link_web,$sql);
    $fila_registros = mysqli_fetch_array($resultado_sql);
    $respuesta['codigo_promocion'] = $fila_registros['codigo_promocion'];   
    $respuesta['nombre_promocion'] = $fila_registros['nombre_promocion'];
    $respuesta['telefono_promocion'] = $fila_registros['telefono_promocion'];
    $respuesta['email_promocion'] = $fila_registros['email_promocion'];
    $respuesta['lugar_promocion'] = $fila_registros['lugar_promocion'];
    $respuesta['fecha_promocion'] = $fila_registros['fecha_promocion'];
    mysqli_close($link_web);
    header ("Content-Type:text/xml");
    return $respuesta;	
    }
function elimina_suscripcion($codigo)
    {
    return NULL;
    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysql_select_db($dbname_web);
    $puede_eliminar = 1;
    // Chequeos 
//	$sql_1 = "SELECT count(*) FROM producto WHERE codigo_categoria = '".$codigo."'";
//	$resultado_sql_1 = mysql_query($sql_1,$link);
//	$fila_1 = mysqli_fetch_array($resultado_sql_1);
//	$numero_1 = $fila_1[0];
//	if($numero_1>0)
//		$puede_eliminar = 0;
    if($puede_eliminar==1)
        {
        $sql = "DELETE FROM divabase.promocion WHERE codigo_promocion = '".$codigo."'";
        $resultado_sql = mysqli_query($link_web,$sql);
        mysqli_close($link_web);
        return 1;	// Retorna 1 si se realizo la eliminación		
        }
    return 0;
    }
function raw_json_encode($input) 
    {
    $input = str_replace("%", "\\", $input);
    return preg_replace_callback('/\\\\u([0-9a-zA-Z]{4})/',function ($matches) 
        {
            return mb_convert_encoding(pack('H*',$matches[1]),'UTF-8','UTF-16');
        },json_encode($input)
        );
    }
function raw_json_encode_herman($input)
    {
    $salida = str_replace('"','',raw_json_encode($input));
    return $salida;
    }
function inserta_suscripcion($codigo_promocion,$nombre_promocion,$telefono_promocion, $email_promocion, $lugar_promocion, $fecha_promocion)
    {
    return NULL;
    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysql_select_db($dbname_web);
    mysqli_query($link_web,"SET CHARACTER SET utf8");
    $sql = "INSERT INTO promocion (codigo_promocion,nombre_promocion,telefono_promocion,email_promocion,lugar_promocion,fecha_promocion,envio_promocion) VALUES('".$codigo_promocion."',CONVERT(_utf8'".$nombre_promocion."' USING cp866),CONVERT(_utf8'".$telefono_promocion."' USING cp866),CONVERT(_utf8'".$email_promocion."' USING cp866),CONVERT(_utf8'".$lugar_promocion."' USING cp866),NOW(),0) ON DUPLICATE KEY UPDATE nombre_promocion = CONVERT(_utf8'".$nombre_promocion."' USING cp866), telefono_promocion = CONVERT(_utf8'".$telefono_promocion."' USING cp866), email_promocion = CONVERT(_utf8'".$email_promocion."' USING cp866), lugar_promocion = CONVERT(_utf8'".$lugar_promocion."' USING cp866)";
    $resultado = mysqli_query($link_web, $sql); 
    mysqli_close($link_web);
    return 1;
    }
function check_email($codigo_promocion,$valor)
    {
    return NULL;
    // Conectividad a la base de datos
    $hostname_web = "divabase.db.11010228.hostedresource.com";
    $username_web = "divabase";
    $dbname_web = "divabase";
    $password_web = "Hema0905!";
    $link_web = mysql_connect($hostname_web, $username_web, $password_web) OR DIE ("Problema de Red, pruebe nuevamente en unos minutos");
    mysqli_select_db($dbname_web);
    mysqli_query($link_web, "SET CHARACTER SET utf8");
    $sql = "UPDATE promocion SET envio_promocion = ".$valor." WHERE codigo_promocion = ".$codigo_promocion;
    $resultado = mysqli_query($link_web,$sql); 
    mysqli_close($link_web);
    return 1;
    }
?>
