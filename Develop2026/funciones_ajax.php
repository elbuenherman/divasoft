<?php
include("variables_globales.php");
include("funciones.php");
include("funciones_v2.php");

// Dispatch POST con archivo: detectado antes del flujo normal porque
// usa $_FILES en vez de los $parametroN tradicionales.
if(isset($_POST["funcion"]) && $_POST["funcion"] == "subir_archivo_factura_dsft")
    {
    echo subir_archivo_factura_dsft();
    exit;
    }

$funcion = $_REQUEST['funcion'];
if (isset($_REQUEST['parametro1'])) $parametro1 = urldecode($_REQUEST['parametro1']);
if (isset($_REQUEST['parametro2'])) $parametro2 = $_REQUEST['parametro2'];
if (isset($_REQUEST['parametro3'])) $parametro3 = urldecode($_REQUEST['parametro3']);
if (isset($_REQUEST['parametro4'])) $parametro4 = urldecode($_REQUEST['parametro4']);
if (isset($_REQUEST['parametro5'])) $parametro5 = $_REQUEST['parametro5'];
if (isset($_REQUEST['parametro6'])) $parametro6 = $_REQUEST['parametro6'];
if (isset($_REQUEST['parametro7'])) $parametro7 = urldecode($_REQUEST['parametro7']);
if (isset($_REQUEST['parametro8'])) $parametro8 = $_REQUEST['parametro8'];
if (isset($_REQUEST['parametro9'])) $parametro9 = $_REQUEST['parametro9'];
if (isset($_REQUEST['parametro10'])) $parametro10 = $_REQUEST['parametro10'];
if (isset($_REQUEST['parametro11'])) $parametro11 = $_REQUEST['parametro11'];
if (isset($_REQUEST['parametro12'])) $parametro12 = $_REQUEST['parametro12'];
if (isset($_REQUEST['parametro13'])) $parametro13 = $_REQUEST['parametro13'];
if (isset($_REQUEST['parametro14'])) $parametro14 = $_REQUEST['parametro14'];
if (isset($_REQUEST['parametro15'])) $parametro15 = $_REQUEST['parametro15'];
if (isset($_REQUEST['parametro16'])) $parametro16 = $_REQUEST['parametro16'];
if (isset($_REQUEST['parametro17'])) $parametro17 = $_REQUEST['parametro17'];
if (isset($_REQUEST['parametro18'])) $parametro18 = urldecode($_REQUEST['parametro18']);
if (isset($_REQUEST['parametro19'])) $parametro19 = $_REQUEST['parametro19'];
if (isset($_REQUEST['parametro20'])) $parametro20 = $_REQUEST['parametro20'];
if (isset($_REQUEST['parametro21'])) $parametro21 = $_REQUEST['parametro21'];
if (isset($_REQUEST['parametro22'])) $parametro22 = $_REQUEST['parametro22'];


// Funciones XML
function array2xml($array, $xml = false){
    if($xml === false) $xml = new SimpleXMLElement('<root/>');
    foreach($array as $key => $value)
        {
        if(is_array($value))
            array2xml($value, $xml->addChild($key));
        else
            $xml->$key = $value;
        }
    return $xml->asXML();
}

// Funciones Ajax
// BUSQUEDAS PREDICTIVAS
// PRODUCTO
if($funcion == 'busqueda_predictiva_producto') echo busqueda_predictiva_producto($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7);
if($funcion == 'busqueda_producto_x_nombre') echo busqueda_producto_x_nombre($parametro1);
if($funcion == 'busqueda_producto_x_codigo') echo busqueda_producto_x_codigo($parametro1);
// PROVEEDORES
if($funcion == 'busqueda_predictiva_proveedor') echo busqueda_predictiva_proveedor($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7);
if($funcion == 'busqueda_proveedor_x_nombre') echo busqueda_proveedor_x_nombre($parametro1);
if($funcion == 'busqueda_proveedor_x_codigo') echo busqueda_proveedor_x_codigo($parametro1);
// PAISES
if($funcion == 'busqueda_predictiva_pais') echo busqueda_predictiva_pais($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7);
if($funcion == 'busqueda_pais_x_nombre') echo busqueda_pais_x_nombre($parametro1);
if($funcion == 'busqueda_pais_x_codigo') echo busqueda_pais_x_codigo($parametro1);
// BANCO
if($funcion == 'busqueda_predictiva_banco') echo busqueda_predictiva_banco($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7);
if($funcion == 'busqueda_banco_x_nombre') echo busqueda_banco_x_nombre($parametro1);
if($funcion == 'busqueda_banco_x_codigo') echo busqueda_banco_x_codigo($parametro1);
// PAIS
// funcion devuelve_pais por codigo
if($funcion == 'devuelve_pais')
    echo devuelve_pais_por_codigo($parametro1);
	
// Función busca_pais Devuelve 1 si econtro el pais
if($funcion == 'busca_pais')
	echo busca_pais($parametro1);

// Función inserta_pais Devuelve 1 si se inserta el país, 2 si estaba repetido
if($funcion == 'inserta_pais')
	echo inserta_pais($parametro1,$parametro2);	
	
// Función inserta_pais Devuelve 1 si se inserta el país, 2 si estaba repetido
if($funcion == 'elimina_pais')
	echo elimina_pais($parametro1);	
	
// USUARIO
// funcion devuelve_usuario por codigo
if($funcion == 'devuelve_usuario')
	echo array2xml(devuelve_usuario_por_codigo($parametro1));
	
// Función busca_pais Devuelve 1 si econtro el usuario
if($funcion == 'busca_pais')
	echo busca_pais($parametro1);

// Función inserta_pais Devuelve 1 si se inserta el usuario, 2 si estaba repetido
if($funcion == 'inserta_usuario')
	echo inserta_usuario($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6);	
	
// Función inserta_pais Devuelve 1 si se inserta el usuario, 2 si estaba repetido
if($funcion == 'elimina_usuario')
	echo elimina_usuario($parametro1);	

	
	
// CIUDAD
// funcion devuelve_ciudad por codigo
if($funcion == 'devuelve_ciudad')
	echo array2xml(devuelve_ciudad_por_codigo($parametro1));
	
// Función busca_ciudad Devuelve 1 si econtro el usuario
if($funcion == 'busca_ciudad')
	echo busca_ciudad($parametro1);

// Función inserta_ciudad Devuelve 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_ciudad')
	echo inserta_ciudad($parametro1,$parametro2,$parametro3);	
	
// Función elimina_ciudad Devuelve 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'elimina_ciudad')
	echo elimina_ciudad($parametro1);	
	
// BANCO
// funcion devuelve_banco por codigo
if($funcion == 'devuelve_banco')
	echo array2xml(devuelve_banco_por_codigo($parametro1));
	
// Función busca_banco Devuelve 1 si econtro el usuario
if($funcion == 'busca_banco')
	echo busca_banco($parametro1);

// Función inserta_banco inserta_banco 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_banco')
	echo inserta_banco($parametro1,$parametro2,$parametro3);	
	
// Función elimina_banco Devuelve 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'elimina_banco')
	echo elimina_banco($parametro1);	

// TIPO PROVEEDOR
// funcion devuelve_tipo_proveedor por codigo
if($funcion == 'devuelve_tipo_proveedor')
	echo array2xml(devuelve_tipo_proveedor_por_codigo($parametro1));
	
// Función busca_tipo_proveedor Devuelve 1 si econtro el registro
if($funcion == 'busca_tipo_proveedor')
	echo busca_tipo_proveedor($parametro1);

// Función inserta_tipo_proveedor inserta_banco 1 si se inserta, 2 si estaba repetido
if($funcion == 'inserta_tipo_proveedor')
	echo inserta_tipo_proveedor($parametro1,$parametro2);	
	
// Función elimina_tipo_proveedor
if($funcion == 'elimina_tipo_proveedor')
	echo elimina_tipo_proveedor($parametro1);

// PROVEEDOR
// funcion devuelve_banco por codigo
if($funcion == 'devuelve_proveedor')
	echo array2xml(devuelve_proveedor_por_codigo($parametro1));
//	
//// Función busca_banco Devuelve 1 si econtro el usuario
//if($funcion == 'busca_banco')
//	echo busca_banco($parametro1);
//
// Función inserta_banco inserta_banco 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_proveedor')
	echo inserta_proveedor($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7,$parametro8,$parametro9,$parametro10,$parametro11,$parametro12,$parametro13,$parametro14,$parametro15,$parametro16,$parametro17,$parametro18,$parametro19,$parametro20,$parametro21);		
// Función elimina_proveedor Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_proveedor')
	echo elimina_proveedor($parametro1);	

// CATEGORIA PRODUCTO
// funcion devuelve_banco por codigo
if($funcion == 'devuelve_categoria')
	echo array2xml(devuelve_categoria_por_codigo($parametro1));
//	
//// Función busca_banco Devuelve 1 si econtro el usuario
//if($funcion == 'busca_banco')
//	echo busca_banco($parametro1);
//
// Función inserta_categoria 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_categoria')
        echo inserta_categoria($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6);

//// Función elimina_categoria Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_categoria')
	echo elimina_categoria($parametro1);	

// CATEGORIA PRODUCTO
// funcion devuelve_banco por codigo
if($funcion == 'devuelve_producto')
	echo array2xml(devuelve_producto_por_codigo($parametro1));
//	
//// Función busca_banco Devuelve 1 si econtro el usuario
//if($funcion == 'busca_banco')
//	echo busca_banco($parametro1);
//
// Función inserta_producto 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_producto')
        echo inserta_producto($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7,$parametro8,$parametro9,$parametro10,$parametro11,$parametro12,$parametro13,$parametro14,$parametro15,$parametro16,$parametro17,$parametro18);
// Función inserta_producto_quick 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_producto_quick')
        echo inserta_producto_quick($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7,$parametro8,$parametro9,$parametro10,$parametro11,$parametro12,$parametro13,$parametro14,$parametro15,$parametro16,$parametro17,$parametro18);

//
////// Función elimina_producto Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_producto')
	echo elimina_producto($parametro1);

// PRODUCTOS POR FINCA
if($funcion == 'carga_productos')
	echo carga_productos($parametro1,$parametro2,$parametro3,$parametro4,$parametro5);

// Función para chequear y des-chequear los input ckeck de productos por proveedor/finca
if($funcion == 'check_productos')
	echo check_productos($parametro1,$parametro2,$parametro3);
// Función para cargar los filtros de productos por proveedor/finca
if($funcion == 'carga_productos_filtros')
	echo carga_productos_filtros();
// Función para filtrar los proveedores por filtro
if($funcion == 'actualiza_proveedores')
	actualiza_proveedores($parametro1,$parametro2);

// PERMISOS
// Función inserta_permiso 1 si se inserta, 2 si estaba repetido
if($funcion == 'inserta_permiso')
        echo inserta_permiso($parametro1,$parametro2,$parametro3,$parametro4,$parametro5);
// funcion devuelve_permiso por codigo
if($funcion == 'devuelve_permiso')
	echo array2xml(devuelve_permiso_por_codigo($parametro1));
// Función para cargar los permisos de usuarios 
if($funcion == 'carga_permisos_usuarios')
	echo carga_permisos_usuarios($parametro1);
// Función para chequear y des-chequear los input ckeck de permisos por usuairo
if($funcion == 'check_permisos')
	echo check_permisos($parametro1,$parametro2,$parametro3);
// Función para copiar un usuario los permisos de un usuario dentro de otro
if($funcion == 'copia_permisos')
	echo copia_permisos($parametro1,$parametro2);

// CATEGORIAS DE PRODUCTOS PARA FACTURACION
// funcion devuelve_categoria_producto_facturacion por codigo
if($funcion == 'devuelve_categoria_producto_facturacion')
	echo array2xml(devuelve_categoria_producto_facturacion($parametro1));
// Función inserta_categoria 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_categoria_producto_facturacion')
        echo inserta_categoria_producto_facturacion($parametro1,$parametro2,$parametro3);
////// Función elimina_catgeoria_producto_facturacion Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_catgeoria_producto_facturacion')
	echo elimina_catgeoria_producto_facturacion($parametro1);

// MARCACIONES
// funcion devuelve_marcacion por codigo
if($funcion == 'devuelve_marcacion')
	echo array2xml(devuelve_marcacion($parametro1));
// Función inserta_marcacion 1 si se inserta la ciudad, 2 si estaba repetido
if($funcion == 'inserta_marcacion')
        echo inserta_marcacion($parametro1,$parametro2,$parametro3);
////// Función elimina_marcacion Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_marcacion')
	echo elimina_marcacion($parametro1);

// CLIENTES
// funcion devuelve_cliente por codigo
if($funcion == 'devuelve_cliente')
	echo array2xml(devuelve_cliente($parametro1));
// Función inserta_cliente 1 si se inserta 2 si estaba repetido
if($funcion == 'inserta_cliente')
        echo inserta_cliente($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7,$parametro8,$parametro9);
////// Función elimina_cliente Devuelve 1 si se inserta el resgitsro, 2 si estaba repetido
if($funcion == 'elimina_cliente')
	echo elimina_cliente($parametro1);

//PAGOS
if($funcion == 'ingresa_pago')
	echo ingresa_pago($parametro1,$parametro2,$parametro3,$parametro4,$parametro5);
if($funcion == 'devuelve_pago')
	echo array2xml(devuelve_pago_por_codigo($parametro1));
if($funcion == 'devuelve_pago_proceso')
	echo array2xml(devuelve_pago_proceso($parametro1));
if($funcion == 'devuelve_pago_proceso_edicion')
	echo array2xml(devuelve_pago_proceso_edicion($parametro1));
if($funcion == 'elimina_pago')
	echo elimina_pago($parametro1);
if($funcion == 'carga_pagos_pendientes')
	echo carga_pagos_pendientes($parametro1);
if($funcion == 'modifica_pago')
	echo modifica_pago($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6);
if($funcion == 'procesa_pago')
	echo procesa_pago($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6,$parametro7);
if($funcion == 'retornar_pago')
	echo retornar_pago($parametro1);
if($funcion == 'carga_pagos_procesados')
	echo carga_pagos_procesados($parametro1,$parametro2,$parametro3);
// SUBSCRIPCIONES
if($funcion == 'devuelve_suscripcion')
    echo array2xml(devuelve_suscripcion($parametro1));
if($funcion == 'elimina_suscripcion')
    echo elimina_suscripcion($parametro1);
if($funcion == 'inserta_suscripcion')
    echo inserta_suscripcion($parametro1,$parametro2,$parametro3,$parametro4,$parametro5,$parametro6);
if($funcion == 'check_email') 
    echo check_email($parametro1,$parametro2);

////   
if($funcion == 'extraer_correos_facturas')
    echo extraer_correos_facturas($parametro1,$parametro2);
if($funcion == 'progreso_extraccion')
    {
    $ruta_progreso_extr = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_progreso_extraccion.json";
    if(file_exists($ruta_progreso_extr))
        echo file_get_contents($ruta_progreso_extr);
    else
        echo '{"estado":"inactivo"}';
    }
if($funcion == 'procesar_factura_web')
    echo procesar_factura_web($parametro1);
if($funcion == 'asignar_factura_consolidado_dsft')
    echo asignar_factura_consolidado_dsft($parametro1, $parametro2);
if($funcion == 'desasignar_factura_consolidado_dsft')
    echo desasignar_factura_consolidado_dsft($parametro1);
if($funcion == 'progreso_factura')
    {
    $ruta_progreso_fac = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_progreso_factura.json";
    $codigo_adj_fac    = isset($parametro1) ? (int)$parametro1 : 0;
    $ruta_output_fac   = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_factura_output_".$codigo_adj_fac.".txt";

    // Si el archivo de output existe, el proceso CLI ya emitio algo. Buscar
    // marca de fin "=== FIN ===" o "Fatal error" para detectar si termino.
    if($codigo_adj_fac > 0 && file_exists($ruta_output_fac))
        {
        $contenido_out = file_get_contents($ruta_output_fac);
        if(strpos($contenido_out, "=== FIN ===") !== false)
            {
            // Buscar el CODIGO de factura_finca en el output.
            $codigo_ff_fac = 0;
            if(preg_match('/INSERT en factura_finca: CODIGO=(\d+)/', $contenido_out, $m_fac))
                $codigo_ff_fac = (int)$m_fac[1];
            echo json_encode(array(
                "estado"         => "finalizado",
                "mensaje"        => "Procesamiento completado",
                "codigo_factura" => $codigo_ff_fac
                ));
            // Limpiar archivo temporal para que la proxima corrida arranque limpia.
            unlink($ruta_output_fac);
            }
        else if(strpos($contenido_out, "Fatal error") !== false)
            {
            echo json_encode(array(
                "estado"  => "error",
                "mensaje" => "Error fatal en el procesamiento"
                ));
            unlink($ruta_output_fac);
            }
        else
            {
            // Proceso aun corriendo: devolver progreso normal.
            if(file_exists($ruta_progreso_fac))
                echo file_get_contents($ruta_progreso_fac);
            else
                echo '{"estado":"inactivo"}';
            }
        }
    else
        {
        // No hay archivo de output todavia (proceso recien lanzado o nunca).
        if(file_exists($ruta_progreso_fac))
            echo file_get_contents($ruta_progreso_fac);
        else
            echo '{"estado":"inactivo"}';
        }
    }
if($funcion == 'lista_correos_facturas')
    echo lista_correos_facturas($parametro1, $parametro2, $parametro3, $parametro4);

// CLIENTES (consola nueva _dsft - independiente del legacy)
if($funcion == 'lista_clientes_dsft')
    echo lista_clientes_dsft($parametro1, $parametro2);
if($funcion == 'devuelve_cliente_dsft')
    echo devuelve_cliente_dsft($parametro1);
if($funcion == 'graba_cliente_dsft')
    echo graba_cliente_dsft($parametro1, $parametro2, $parametro3, $parametro4, $parametro5, $parametro6, $parametro7, $parametro8, $parametro9, $parametro10, $parametro11, $parametro12);
if($funcion == 'elimina_cliente_dsft')
    echo elimina_cliente_dsft($parametro1, $parametro2);
if($funcion == 'trazabilidad_cliente_dsft')
    echo trazabilidad_cliente_dsft($parametro1);

// TRUCKS (consola nueva _dsft)
if($funcion == 'lista_trucks_dsft')
    echo lista_trucks_dsft($parametro1, $parametro2);
if($funcion == 'devuelve_truck_dsft')
    echo devuelve_truck_dsft($parametro1);
if($funcion == 'graba_truck_dsft')
    echo graba_truck_dsft($parametro1, $parametro2, $parametro3, $parametro4, $parametro5, $parametro6, $parametro7);
if($funcion == 'elimina_truck_dsft')
    echo elimina_truck_dsft($parametro1, $parametro2);
if($funcion == 'trazabilidad_truck_dsft')
    echo trazabilidad_truck_dsft($parametro1);

// MARCACIONES (consola nueva _dsft)
if($funcion == 'lista_marcaciones_dsft')
    echo lista_marcaciones_dsft($parametro1, $parametro2);
if($funcion == 'devuelve_marcacion_dsft')
    echo devuelve_marcacion_dsft($parametro1);
if($funcion == 'graba_marcacion_dsft')
    echo graba_marcacion_dsft($parametro1, $parametro2, $parametro3, $parametro4, $parametro5, $parametro6, $parametro7);
if($funcion == 'elimina_marcacion_dsft')
    echo elimina_marcacion_dsft($parametro1, $parametro2);
if($funcion == 'trazabilidad_marcacion_dsft')
    echo trazabilidad_marcacion_dsft($parametro1);

// CONSOLIDADOS (consola nueva _dsft)
if($funcion == 'lista_consolidados_dsft')
    echo lista_consolidados_dsft($parametro1, $parametro2, $parametro3, $parametro4);
if($funcion == 'devuelve_consolidado_dsft')
    echo devuelve_consolidado_dsft($parametro1);
if($funcion == 'graba_consolidado_dsft') 
    echo graba_consolidado_dsft($parametro1, $parametro2, $parametro3, $parametro4, $parametro5, $parametro6, $parametro7, $parametro8, $parametro9, $parametro10);
if($funcion == 'elimina_consolidado_dsft')
    echo elimina_consolidado_dsft($parametro1, $parametro2);
if($funcion == 'trazabilidad_consolidado_dsft') 
    echo trazabilidad_consolidado_dsft($parametro1);
if($funcion == 'opciones_marcaciones_por_cliente_dsft')
    echo opciones_marcaciones_por_cliente_dsft($parametro1);
if($funcion == 'detalle_consolidado_dsft')
    echo detalle_consolidado_dsft($parametro1);     
if($funcion == 'render_grid_factura_dsft') 
    echo render_grid_factura_dsft($parametro1); 
if($funcion == 'actualizar_celda_detalle_dsft')
    echo actualizar_celda_detalle_dsft($parametro1, $parametro2, $parametro3);
if($funcion == 'eliminar_linea_detalle_dsft')
    echo eliminar_linea_detalle_dsft($parametro1);
if($funcion == 'regenerar_detalle_factura_dsft')
    echo regenerar_detalle_factura_dsft($parametro1);
if($funcion == 'agregar_linea_a_caja_dsft')
    echo agregar_linea_a_caja_dsft($parametro1, $parametro2, $parametro3);
if($funcion == 'agregar_caja_detalle_dsft')
    echo agregar_caja_detalle_dsft($parametro1, $parametro2);
if($funcion == 'confirmar_finca_factura_dsft')
    echo confirmar_finca_factura_dsft($parametro1, $parametro2);
if($funcion == 'render_totales_factura_dsft')
    echo render_totales_factura_dsft($parametro1);
if($funcion == 'quitar_factura_consolidado_dsft')
    echo quitar_factura_consolidado_dsft($parametro1);
if($funcion == 'crear_factura_manual_dsft')
    echo crear_factura_manual_dsft($parametro1, $parametro2, $parametro3, $parametro4);
if($funcion == 'asignar_consolidado_factura_dsft')
    echo asignar_consolidado_factura_dsft($parametro1, $parametro2, $parametro3, $parametro4);
if($funcion == 'asignar_consolidado_post_ia_dsft')
    echo asignar_consolidado_post_ia_dsft($parametro1, $parametro2);
if($funcion == 'agregar_guia_consolidado_dsft') 
    echo agregar_guia_consolidado_dsft($parametro1, $parametro2, $parametro3);
if($funcion == 'quitar_guia_consolidado_dsft')
    echo quitar_guia_consolidado_dsft($parametro1, $parametro2);
if($funcion == 'lista_guias_consolidado_dsft')
    echo lista_guias_consolidado_dsft($parametro1);
if($funcion == 'opciones_guias_recientes_dsft')
    echo opciones_guias_recientes_dsft();
// Tablita read-only en consola_clientes_dsft.php
if($funcion == 'lista_marcaciones_por_cliente_dsft')
    echo lista_marcaciones_por_cliente_dsft($parametro1);


mysqli_close($link);

?>