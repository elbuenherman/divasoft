<?php
// Barra de navegación superior - Común para todo el sistema
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");
$permiso[] = NULL;
consulta_permisos($_SESSION['s_codigo'],$permiso);
?>
<nav class="navigation-bar dark">
	<div class="navigation-bar-content container">
		<a href="home.php" class="element"><span class="icon-home"></span> DivaSOFT <sup>1.0</sup></a>
        	<span class="element-divider"></span>
            	<a class="pull-menu" href="#"></a>
                	<ul class="element-menu">
                    	<li>
                        <a class="dropdown-toggle" href="#">Procesos</a>
                            <ul class="dropdown-menu dark" data-role="dropdown">
                                <li <?php echo $permiso[35];?>><a href="consola_importar_facturas.php">Importar facturas</a></li> 
                               	<li <?php echo $permiso[35];?>><a href="consola_gestion_correos_facturas.php">Correos / Facturas</a></li>
                               	<li <?php echo $permiso[35];?>><a href="consola_consolidado.php">Consolidados</a></li>
                               	<li <?php echo $permiso[35];?>><a href="proceso_pedidos.php">Pedidos</a></li>
                               	<li <?php echo $permiso[44];?>><a href="proceso_awbs.php">Ingreso de AWBs</a></li>
                               	<li <?php echo $permiso[45];?>><a href="proceso_creditos_clientes.php">Aplicación de créditos</a></li>    
                               	<li <?php echo $permiso[46];?>><a href="#">Control de Calidad</a></li>                                  
                                <li><a href="#"class="dropdown-toggle">Balances</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                    <li <?php echo $permiso[47];?>><a href="proceso_balance_fincas.php">Fincas</a></li>
                                    <li <?php echo $permiso[48];?>><a href="#">Agencias</a></li>
                                    <li <?php echo $permiso[49];?>><a href="#">Clientes</a></li>
                                    </ul>
                                </li>        
                               	<li <?php echo $permiso[36];?>><a href="proceso_pagos_ingreso.php?hola=1">Pagos</a></li>                               
                               	<li>
                                    <a href="#" class="dropdown-toggle">Estadísticas</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                    <li><a href="#">Estadistica 100</a></li>
                                    <li><a href="#">Estadistica 2</a></li>
                                    <div class="divider"></div>
                                    <li><a href="#">Estadistica 3</a></li>
                                    <li><a href="#">Estadistica 4</a></li>
                                    </ul>
                               	</li>
                               	<li class="divider"></li>
                               	<li><a href="#">Procesos 1.3</a></li>
                               	<li class="disabled"><a href="#">Procesos 1.5</a></li>
                               	<li class="divider"></li>
                               	<li><a href="#">Proceso 1.6</a></li>
                            </ul>
                        </li>
                        <li>
                            <a class="dropdown-toggle"  href="#">Utilidades</a>
                            <ul class="dropdown-menu dark" data-role="dropdown">
                            	<li>
                                    <a href="#" class="dropdown-toggle">Promociones</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                        <li <?php echo $permiso[39];?>><a href="utilidad_promociones.php">Promociones WEB</a></li>
                                    </ul>
                                </li>
                            	<li>
                                    <a href="#" class="dropdown-toggle">Imágenes</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                        <li <?php echo $permiso[40];?>><a href="utilidad_imagenes.php">Edición Imágenes</a></li>                                     
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <a class="dropdown-toggle"  href="#">Consolas</a>
                            <ul class="dropdown-menu dark" data-role="dropdown"> 
                            	<li>
                                    <a href="#" class="dropdown-toggle">Clientes</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                        <!-- <li <?php echo $permiso[14];?>><a href="consola_clientes.php">Clientes</a></li> -->
                                        <li <?php echo $permiso[35];?>><a href="consola_clientes_dsft.php">Clientes</a></li>
                                        <li <?php echo $permiso[30];?>><a href="consola_marcaciones.php">Marcaciones</a></li>
                                        <li <?php echo $permiso[30];?>><a href="consola_trucks.php">Trucks</a></li>
                                        <li <?php echo $permiso[8];?>><a href="consola_paises.php">Países</a></li>
                                        <!-- <li <?php echo $permiso[9];?>><a href="consola_ciudad.php">Ciudades</a></li> -->
                                    </ul>
                                </li>
                            	<li>
                                    <a href="#" class="dropdown-toggle">Productos</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                        <li <?php echo $permiso[35];?>><a href="consola_tipo_producto.php">Tipo Productos</a></li>
                                        <li <?php echo $permiso[33];?>><a href="consola_productos_quick.php">Productos</a></li>
                                        <li <?php echo $permiso[11];?>><a href="consola_productos.php">Productos Web</a></li>
                                        <li <?php echo $permiso[10];?>><a href="consola_categoria_productos.php">Categorías</a></li>
                                        <li <?php echo $permiso[27];?>><a href="consola_categorias_facturacion.php">Categ. Facturación</a></li>                                        
                                    </ul>
                                </li>
                                <li>
                                    <a class="dropdown-toggle" href="#">Proveedores</a>
                                    <ul class="dropdown-menu dark" data-role="dropdown">
                                        <li <?php echo $permiso[5];?>><a href="consola_proveedores.php">Proveedores</a></li>
                                        <li <?php echo $permiso[6];?>><a href="consola_tipo_proveedores.php">Tipo de proveedor</a></li>
                                        <li <?php echo $permiso[7];?>><a href="consola_bancos.php">Bancos</a></li>
                                        <li <?php echo $permiso[12];?>><a href="consola_paises.php">Países</a></li>
                                        <li <?php echo $permiso[13];?>><a href="consola_ciudad.php">Ciudades</a></li>                                        
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                    <div class="no-tablet-portrait">
                        <div class="element place-right">
                            <a class="dropdown-toggle" href="#">
                                <span class="icon-cog"></span>
                            </a>
                            <ul class="dropdown-menu place-right " data-role="dropdown">
                                <li>
                                    <a href="#" class="dropdown-toggle">Usuarios</a>
                                    <ul class="dropdown-menu place-left drop-left" data-role="dropdown">
                                        <li <?php echo $permiso[4];?>><a href="consola_permisos_usuarios.php">Permisos de usuario</a></li>
                                        <li <?php echo $permiso[3];?>><a href="consola_usuarios.php">Usuarios / Perfiles</a></li>
                                        <li <?php echo $permiso[2];?>><a href="consola_permisos.php">Permisos</a></li>
                                    </ul>
                                </li>                                   
                                <li><a href="salir.php">Salir </a></li>                         
                            </ul>
                        </div>
                        <span class="element-divider place-right"></span>
                        <button class="element image-button image-left place-right"><?php echo $_SESSION['s_usuario'];?>
                        </button>
                    </div>
        </div>        
    </nav>
<?php
?>
