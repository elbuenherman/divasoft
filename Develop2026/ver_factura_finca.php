<?php

// ============================================================================
//  ver_factura_finca.php
//  Vista web de una factura ya procesada (cabecera + detalle), con formato
//  parecido al Excel CONSOLIDADO.
//
//  Uso: ver_factura_finca.php?codigo=N   (CODIGO de factura_finca)
// ============================================================================

ini_set("display_errors", "1");
error_reporting(E_ALL);

include("variables_globales.php");
$link = mysqli_connect($ip_bd, $usuario_bd, $password_bd, $instancia_bd);
mysqli_query($link, "SET CHARACTER SET utf8");

$codigo = isset($_GET["codigo"]) ? (int)$_GET["codigo"] : 0;
if($codigo == 0)
    die("Codigo invalido");

// ----------------------------------------------------------------------------
// Cabecera.
// ----------------------------------------------------------------------------
$sql = "SELECT * FROM factura_finca WHERE CODIGO = ".$codigo;
$resultado = mysqli_query($link, $sql);
if(!$resultado || mysqli_num_rows($resultado) == 0)
    die("Factura no encontrada");
$cabecera = mysqli_fetch_assoc($resultado);

// ----------------------------------------------------------------------------
// Detalle.
// ----------------------------------------------------------------------------
$sql_det = "SELECT *
    FROM detalle_factura_finca
    WHERE CODIGOFACTURAFINCA = ".$codigo."
    ORDER BY NUMEROCAJA, INDICELINEA";
$resultado_det = mysqli_query($link, $sql_det);
$total_lineas = mysqli_num_rows($resultado_det);

$detalle = array();
for($i=0; $i<$total_lineas; $i++)
    $detalle[] = mysqli_fetch_assoc($resultado_det);

// ----------------------------------------------------------------------------
// Helpers.
// ----------------------------------------------------------------------------
function get_fb_equivalente($tipo)
    {
    $tipo = strtoupper((string)$tipo);
    if($tipo == "FB") return "1";
    if($tipo == "HB") return "0.5";
    if($tipo == "QB") return "0.25";
    if($tipo == "OB") return "0.125";
    if($tipo == "EB") return "0.125";
    return "";
    }

function nombre_producto_plural($producto)
    {
    $p = strtoupper((string)$producto);
    if($p == "ROSA")         return "ROSES";
    if($p == "SPRAY")        return "SPRAY";
    if($p == "GYPSO")        return "GYPSO";
    if($p == "CARNATION")    return "CARNATIONS";
    if($p == "ALSTROEMERIA") return "ALSTROEMERIA";
    if($p == "MATTHIOLA")    return "MATTHIOLA";
    if($p == "OTRO")         return "OTRO";
    return $p;
    }

// Formato ST PRICE: number_format 4 decimales, trim de ceros finales,
// minimo 2 decimales.
function formato_st_price($valor)
    {
    $s = number_format((float)$valor, 4, '.', '');
    $pos = strpos($s, '.');
    if($pos === false)
        return $s.".00";
    $entero  = substr($s, 0, $pos);
    $decimal = rtrim(substr($s, $pos + 1), '0');
    if(strlen($decimal) < 2)
        $decimal = str_pad($decimal, 2, '0');
    return $entero.".".$decimal;
    }

// ----------------------------------------------------------------------------
// Agrupar detalle por PRODUCTO (preservando el orden de aparicion).
// ----------------------------------------------------------------------------
$grupos        = array();
$orden_grupos  = array();
$total_det     = count($detalle);
for($i=0; $i<$total_det; $i++)
    {
    $producto = isset($detalle[$i]["PRODUCTO"]) ? strtoupper($detalle[$i]["PRODUCTO"]) : "OTRO";
    if(!isset($grupos[$producto]))
        {
        $grupos[$producto] = array();
        $orden_grupos[]    = $producto;
        }
    $grupos[$producto][] = $detalle[$i];
    }

$largos = array(40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150);
$num_largos = count($largos);

// Suma SUBTOTAL del detalle.
$suma_subtotal = 0;
for($i=0; $i<$total_det; $i++)
    $suma_subtotal += (float)$detalle[$i]["PRECIOTOTAL"];

// ----------------------------------------------------------------------------
// Render HTML.
// ----------------------------------------------------------------------------
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />';
echo '<title>Factura '.htmlspecialchars((string)$cabecera["NUMEROFACTURA"]).'</title>';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; background: #fff; color: #222; }';
echo 'table.cabecera { width: 100%; border-collapse: collapse; margin-bottom: 16px; }';
echo 'table.cabecera td { vertical-align: top; padding: 4px 8px; }';
echo 'table.cabecera td.izq, table.cabecera td.der { width: 50%; }';
echo 'table.kv { width: 100%; border-collapse: collapse; }';
echo 'table.kv td { padding: 2px 4px; vertical-align: top; }';
echo 'table.kv td.k { width: 35%; font-weight: bold; color: #555; }';
echo 'h3 { margin: 16px 0 4px 0; font-size: 13px; color: #222; }';
echo 'table.detalle { border-collapse: collapse; border: 1px solid #999; width: 100%; margin-bottom: 8px; }';
echo 'table.detalle th, table.detalle td { border: 1px solid #999; padding: 3px 6px; font-size: 11px; }';
echo 'table.detalle th { background: #f0f0f0; text-align: center; font-weight: bold; }';
echo 'table.detalle td.num { text-align: right; }';
echo 'table.detalle td.cm, table.detalle th.cm { width: 45px; text-align: right; }';
echo 'table.totales { margin-top: 16px; border-collapse: collapse; }';
echo 'table.totales td { padding: 4px 12px; font-weight: bold; border: 1px solid #999; }';
echo 'table.totales td.num { text-align: right; }';
echo '</style></head><body>';

// Encabezado superior.
echo '<table class="cabecera"><tr>';

echo '<td class="izq">';
echo '<strong>IP Herman Diener</strong> &nbsp; RUC 1707490098001<br>';
echo 'El Tiempo E6-42 y El Tel&eacute;grafo, Quito, Ecuador<br>';
echo 'Phone number: +593999135857, +59326010256';
echo '</td>';

echo '<td class="der">';
echo '<table class="kv">';
echo '<tr><td class="k">INVOICE NUM:</td><td>'.htmlspecialchars((string)$cabecera["NUMEROFACTURA"]).'</td></tr>';
echo '<tr><td class="k">SHIP DATE:</td><td>'.htmlspecialchars((string)$cabecera["FECHAFACTURACION"]).'</td></tr>';
echo '<tr><td class="k">CUSTOMER NAME:</td><td>'.htmlspecialchars((string)$cabecera["CLIENTEMARCACION"]).'</td></tr>';
echo '<tr><td class="k">ADDRESS:</td><td>'.htmlspecialchars((string)$cabecera["PAISDESTINO"]).'</td></tr>';
echo '<tr><td class="k">LABEL:</td><td>'.htmlspecialchars((string)$cabecera["CLIENTEMARCACION"]).'</td></tr>';
echo '<tr><td class="k">AWB NUMBER:</td><td>'.htmlspecialchars((string)$cabecera["GUIA"]).'</td></tr>';
echo '</table>';
echo '</td></tr></table>';

// Una seccion por PRODUCTO.
$num_grupos = count($orden_grupos);
for($g=0; $g<$num_grupos; $g++)
    {
    $producto      = $orden_grupos[$g];
    $lineas_grupo  = $grupos[$producto];
    $nombre_plural = nombre_producto_plural($producto);

    echo '<h3>'.htmlspecialchars($nombre_plural).'</h3>';
    echo '<table class="detalle">';

    // Encabezado de columnas.
    echo '<tr>';
    echo '<th>FB</th>';
    echo '<th>FARM</th>';
    echo '<th>VARIETY</th>';
    for($k=0; $k<$num_largos; $k++)
        echo '<th class="cm">'.$largos[$k].'cm</th>';
    echo '<th>ST PRICE</th>';
    echo '<th>TOTAL</th>';
    echo '<th>ALERTA</th>';
    echo '</tr>';

    // Filas.
    $ultimo_numero_caja = -1;
    $num_lineas_grupo   = count($lineas_grupo);
    for($k=0; $k<$num_lineas_grupo; $k++)
        {
        $L = $lineas_grupo[$k];
        $caja_actual = isset($L["NUMEROCAJA"]) ? (int)$L["NUMEROCAJA"] : -1;

        // FB solo cuando cambia NUMEROCAJA respecto a la fila anterior.
        $fb_val = "";
        if($caja_actual !== $ultimo_numero_caja)
            {
            $fb_val = get_fb_equivalente(isset($L["TIPOCAJA"]) ? $L["TIPOCAJA"] : "");
            $ultimo_numero_caja = $caja_actual;
            }

        $largo_linea  = isset($L["LARGO"])       ? (int)$L["LARGO"]       : 0;
        $tallos_total = isset($L["TALLOSTOTAL"]) ? (int)$L["TALLOSTOTAL"] : 0;
        $st_price     = formato_st_price(isset($L["PRECIOUNITARIO"]) ? $L["PRECIOUNITARIO"] : 0);
        $total_line   = number_format(isset($L["PRECIOTOTAL"]) ? (float)$L["PRECIOTOTAL"] : 0, 2, '.', ',');
        $alerta_line  = isset($L["ALERTA"])      ? (string)$L["ALERTA"]   : "";

        echo '<tr>';
        echo '<td class="num">'.htmlspecialchars($fb_val).'</td>';
        echo '<td>'.htmlspecialchars((string)$cabecera["FINCA"]).'</td>';
        echo '<td>'.htmlspecialchars((string)$L["VARIEDAD"]).'</td>';

        // Una columna por cada largo. Pongo TALLOSTOTAL solo en la columna
        // que matchea con LARGO de la linea.
        for($m=0; $m<$num_largos; $m++)
            {
            $valor_cm = "";
            if($largo_linea == $largos[$m] && $tallos_total > 0)
                $valor_cm = $tallos_total;
            echo '<td class="cm">'.$valor_cm.'</td>';
            }

        echo '<td class="num">'.$st_price.'</td>';
        echo '<td class="num">'.$total_line.'</td>';
        echo '<td>'.htmlspecialchars($alerta_line).'</td>';
        echo '</tr>';
        }
    echo '</table>';
    }

// Pie con totales para verificacion visual.
echo '<table class="totales">';
echo '<tr><td>SUBTOTAL (suma detalle):</td><td class="num">'.number_format($suma_subtotal, 2, '.', ',').'</td></tr>';
echo '<tr><td>TOTAL CABECERA:</td><td class="num">'.number_format((float)$cabecera["TOTAL"], 2, '.', ',').'</td></tr>';
echo '</table>';

echo '</body></html>';
