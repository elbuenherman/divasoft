<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");

$numero_filas = 5;      $numero_columnas = 5;
$margen_superior = 10;  $margen_izquierdo = 10; $ancho_celdas = 100;
$alto_celdas = 24;      $tamano_texto = 10;     $separacion_vertical = -1;
$separacion_horizontal = -1;
$indice = 0;
for($y=1;$y<=$numero_filas;$y++)
    for($x=1;$x<=$numero_columnas;$x++)
        {
        $MS = $margen_superior+($y-1)*($alto_celdas+$separacion_vertical);
        $MI = $margen_izquierdo+($ancho_celdas+$separacion_horizontal)*($x-1);
        $celda[$x][$y]["MS"] = $MS;
        $celda[$x][$y]["MI"] = $MI;
        $celda[$x][$y]["ANCHO"] = $ancho_celdas;
        $celda[$x][$y]["ALTO"] = $alto_celdas;
        $celda[$x][$y]["INDICE"] = ++$indice;
        $cordenada_x_inidce[$indice]["X"]=$x;
        $cordenada_y_inidce[$indice]["Y"]=$y;
        }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
include("css.php");
?>
<style type="text/css">
input[type="text"]:focus {
  outline: none;
  border-color: red;
  border-width: 2px;
}
</style>
<script language="javascript">
function desenfoca(objeto)
    {
    var identificador = '#'+objeto.id;   
    $(identificador).prop('readonly',true);
    var estilo_anterior = document.getElementById("id_auxiliar_desenfoque").value
    document.getElementById("id_auxiliar_desenfoque").value ="";
    if(estilo_anterior.length>0) $(identificador).attr('style',estilo_anterior);
    document.getElementById("id_escape").value=""; // control para escape borrado    
    }
function crea_arreglo_2d(filas) 
    {
    var arr = [];
    for (var i=0;i<=filas;i++)
        arr[i] = [];
    return arr;
    }
function maneja_teclas(e) {
    var event = window.event ? window.event : e;
    if (true)
        {
        var indice = document.getElementById("id_enfoque").value;
        cambia_enfoque_por_tecla(indice,event.keyCode);
        }
    }
function actualiza_enfoque(indice)
    {
    document.getElementById("id_enfoque").value=indice;
    }
function cambia_enfoque_por_tecla(indice,direccion)
    {
    if(direccion==9) return 0; // Si hace tab no ejecuta la función
    var aux_b = document.getElementById("id_auxiliar_desenfoque").value; // Saca la longitud del 
    if(aux_b.length>0&&(direccion==38||direccion==40||direccion==37||direccion==39)) return 0;
    var numero_filas = <?php echo $numero_filas;?>;      
    var numero_columnas = <?php echo $numero_columnas;?>;
    var contador = 0;
    var matriz = crea_arreglo_2d(numero_filas); 
    var nueva_x = 1;
    var nueva_y = 1;
    var id_nuevo = "";
    for(var y=1;y<=numero_filas;y++)
        {
        for(var x=1;x<=numero_columnas;x++)
            {
            matriz[x][y]=++contador; 
            if(indice==contador)
                {
                if(direccion==27) //escape
                    {
                    var aux_a = document.getElementById("id_auxiliar_desenfoque").value;
                    if(aux_a.length>0) // para cuando ya esta dado el enter
                        {
                        var id_actual = 'celdax'+x+'y'+y;                           
                        document.getElementById(id_actual).value = document.getElementById("id_escape").value;    
                        document.getElementById("id_escape").value = "";
                        var identificador = '#celdax'+x+'y'+y;   
                        $(identificador).prop('readonly',true);
                        var estilo_anterior = document.getElementById("id_auxiliar_desenfoque").value
                        document.getElementById("id_auxiliar_desenfoque").value ="";
                        if(estilo_anterior.length>0) $(identificador).attr('style',estilo_anterior); 
                        }
                    return 0;    
                    }               
                if(direccion==13||direccion==38||direccion==40||direccion==37||direccion==39)
                    {    
                    if(direccion==13)
                        {
                        var aux = document.getElementById("id_auxiliar_desenfoque").value;
                        if(aux.length>0) // para cuando ya esta dado el enter
                            {
                            var identificador = '#celdax'+x+'y'+y;   
                            $(identificador).prop('readonly',true);
                            var estilo_anterior = document.getElementById("id_auxiliar_desenfoque").value
                            document.getElementById("id_auxiliar_desenfoque").value ="";
                            if(estilo_anterior.length>0) $(identificador).attr('style',estilo_anterior);  
                            document.getElementById("id_escape").value="";
                            return 0;
                            }
                        nueva_x = x;
                        nueva_y = y;  
                        id_nuevo = "celdax"+nueva_x+"y"+nueva_y;
                        document.getElementById(id_nuevo).focus();
                        var id_enter = '#'+id_nuevo;
                        $(id_enter).prop('readonly',false);
                        var estilo = $(id_enter).attr('style');           
                        document.getElementById("id_auxiliar_desenfoque").value=estilo;
                        estilo += ' background-color: mistyrose;'
                        $(id_enter).attr('style',estilo);
                        document.getElementById("id_escape").value=$(id_enter).prop('value'); // Control escape                       
                        // para que se ponga al final el cursor cuando se da enter
                        var longitud_actual = $(id_enter).val().length * 2;
                        var elemento = $(id_enter)[0];
                        elemento.setSelectionRange(longitud_actual, longitud_actual);                    
                        return 0;
                        }                     
                    if(direccion==38)
                        {
                        if(y>1) nueva_y = y-1;
                        nueva_x = x;
                        }
                    if(direccion==40)
                        {
                        if(y!=numero_filas) nueva_y = y+1;
                        else nueva_y = y;
                        nueva_x = x;
                        }
                    if(direccion==37)
                        {
                        if(x!=1) { nueva_x = x-1; nueva_y = y; }
                        else if(y!=1) { nueva_x = numero_columnas; nueva_y = y-1; }
                        }
                    if(direccion==39)
                        {
                        if(x!=numero_columnas) { nueva_x = x+1; nueva_y = y; }
                        else
                            {
                            if(y!=numero_filas) { nueva_x = 1; nueva_y = y+1; }
                            else { nueva_x = numero_columnas; nueva_y = numero_filas; }
                            }
                        }                
                    id_nuevo = "celdax"+nueva_x+"y"+nueva_y;
                    document.getElementById(id_nuevo).focus();
                    return 0;
                    }
                // Para cualquier otra letra
                nueva_x = x;                              // SACA X
                nueva_y = y;                              // SACA Y
                id_nuevo = "celdax"+nueva_x+"y"+nueva_y;  // SACA ID DE LA MISMA CELDA
                var id_enter = '#'+id_nuevo;              // EN FORMATO PARA JQUERY
                $(id_enter).prop('readonly',false);       // ACTIVA EDICION
                var estilo = $(id_enter).attr('style');   // PONE EN VARIABLE EL ESTILO CON FONDO BLANCO LA PRIMERA VEZ           
                var aux = document.getElementById("id_auxiliar_desenfoque").value;   // SACA EN AUX EL ESTILO (DE GANA) LA PRIMERA VEZ
                if(aux.length==0)
                    {
                    document.getElementById("id_auxiliar_desenfoque").value=estilo;
                    estilo += ' background-color: mistyrose;'
                    $(id_enter).attr('style',estilo);
                    document.getElementById("id_escape").value=$(id_enter).prop('value'); // control para escape
                    $(id_enter).prop('value',''); // borra y deja listo para escribir
                    }
                return 0;                    
                }
            }
        }
    }
</script>
<title>Divasoft - HOME</title>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>

<?php
for($y=1;$y<=$numero_filas;$y++)
    for($x=1;$x<=$numero_columnas;$x++)
        echo $html_celda='<input id="celdax'.$x.'y'.$y.'" style="position: absolute; height: '.$celda[$x][$y]["ALTO"].'px; width: '.$celda[$x][$y]["ANCHO"].'px; margin-top: '.$celda[$x][$y]["MS"].'px; margin-left:'.$celda[$x][$y]["MI"].'px; " type="text" value="'.$celda[$x][$y]["INDICE"].'" onKeyDown="maneja_teclas(event)" onfocus="actualiza_enfoque('.$celda[$x][$y]["INDICE"].')" onblur="desenfoca(this)" READONLY></input>';
        
?>   
</body>
    <input type="hidden" value="" id="id_enfoque"></input>
    <input type="hidden" value="" id="id_escape"></input>
    <input type="hidden" value="" id="id_auxiliar_desenfoque"></input>
</html>
