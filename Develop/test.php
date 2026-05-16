<?php 
include("variables_globales.php");
include("funciones.php");
include("valida_sesion.php");

$numero_filas = 7;      $numero_columnas = 10;
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
function maneja_teclas(e) 
    {
    var event = window.event ? window.event : e;
    if (event.keyCode == 8)
      if ((event.target || event.srcElement).type == "text")
        event.returnValue = false; // si no se hace asi entonces igual retrocede la pagina el chrome porque detecta el delete como propio de la etiqueta input // hay que borrar los caracteres desde la función de manejo    
    var indice = document.getElementById("id_enfoque").value;
    cambia_enfoque_por_tecla(indice,event.keyCode);
    }
function doble_click()
    {
    var indice = document.getElementById("id_enfoque").value;
    cambia_enfoque_por_tecla(indice,13);               
    }
function actualiza_enfoque(indice)
    {
    document.getElementById("id_enfoque").value=indice;
    var agent = navigator.userAgent.toLowerCase();
    if(agent.indexOf('iphone') >= 0 || agent.indexOf('ipad') >= 0) // Para que edite en un dispositivo mobile
        {
        var indice = document.getElementById("id_enfoque").value;
        cambia_enfoque_por_tecla(indice,13);
        }
    }
function cambia_enfoque_por_tecla(indice,direccion)
    {
    if(direccion==9||direccion==16||direccion==224||direccion==18||direccion==17||direccion==112||(direccion>=112&&direccion<=123)||direccion==145||direccion==19||direccion==45||direccion==20||direccion==33||direccion==34) return 0; // Si hace tab shift cmd alt ctrl teclas de funcion deslp pausa ins blck-may pg down pg up no ejecuta la función
    var campo_desenfoque = document.getElementById("id_auxiliar_desenfoque").value; // Saca la longitud del campo de desenfoque para saber si está dado el enter y editando
    if(campo_desenfoque.length>0&&(direccion==38||direccion==40||direccion==37||direccion==39)) return 0;
    var numero_filas = <?php echo $numero_filas;?>;      
    var numero_columnas = <?php echo $numero_columnas;?>;
    var contador = 0;
    var nueva_x = 1;
    var nueva_y = 1;
    var id_nuevo = "";
    for(var y=1;y<=numero_filas;y++)
        for(var x=1;x<=numero_columnas;x++)
            if(indice==++contador)
                {
                var id_actual = 'celdax'+x+'y'+y;
                var n_id_actual = '#'+id_actual;
                var valor_actual = document.getElementById(id_actual).value;
                if(direccion==27) //escape
                    {
                    if(campo_desenfoque.length>0) // para cuando ya esta dado el enter
                        {                          
                        document.getElementById(id_actual).value = document.getElementById("id_escape").value;    
                        document.getElementById("id_escape").value = "";
                        $(n_id_actual).prop('readonly',true);
                        var estilo_anterior = document.getElementById("id_auxiliar_desenfoque").value
                        document.getElementById("id_auxiliar_desenfoque").value ="";
                        if(estilo_anterior.length>0) $(n_id_actual).attr('style',estilo_anterior); 
                        }
                    return 0;    
                    }
                    if(direccion==8&&campo_desenfoque.length>0) // PROGRAMACION DEL DELETE ya que se deshabilitó para que no retroceda toda la hoja                   
                        {
                        if(navigator.vendor == 'Google Inc.' || navigator.vendor =='Apple Computer, Inc.')
                            document.getElementById(id_actual).value = valor_actual.substring(0,valor_actual.length-1);
                        return 0;   
                        }
                if((direccion==35||direccion==36)&&(campo_desenfoque.length>=0)) // Si no estaba dado enter ya se sale de la función si pone inicio fin pg up o pg down
                    return 0;                        
                if(direccion==13||direccion==38||direccion==40||direccion==37||direccion==39||direccion==35||direccion==36)
                    {    
                    if(direccion==13)
                        {
                        if(campo_desenfoque.length>0) // para cuando ya esta dado el enter
                            {  
                            $(n_id_actual).prop('readonly',true);
                            var estilo_anterior = document.getElementById("id_auxiliar_desenfoque").value
                            document.getElementById("id_auxiliar_desenfoque").value ="";
                            if(estilo_anterior.length>0) $(n_id_actual).attr('style',estilo_anterior);  
                            document.getElementById("id_escape").value="";
                            return 0;
                            }
                        document.getElementById(id_actual).focus();
                        $(n_id_actual).prop('readonly',false);
                        var estilo = $(n_id_actual).attr('style');           
                        document.getElementById("id_auxiliar_desenfoque").value = estilo;
                        estilo += ' background-color: mistyrose;'
                        $(n_id_actual).attr('style',estilo);
                        document.getElementById("id_escape").value=$(n_id_actual).prop('value'); // Control escape                       
                        // para que se ponga al final el cursor cuando se da enter
                        var longitud_actual = $(n_id_actual).val().length * 2;
                        var elemento = $(n_id_actual)[0];
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
                $(n_id_actual).prop('readonly',false);       // ACTIVA EDICION
                var estilo = $(n_id_actual).attr('style');   // PONE EN VARIABLE EL ESTILO CON FONDO BLANCO LA PRIMERA VEZ           
                if(campo_desenfoque.length==0)
                    {
                    document.getElementById("id_auxiliar_desenfoque").value=estilo;
                    estilo += ' background-color: mistyrose;'
                    $(n_id_actual).attr('style',estilo);
                    document.getElementById("id_escape").value=$(n_id_actual).prop('value'); // control para escape
                    $(n_id_actual).prop('value',''); // borra y deja listo para escribir
                    }
                return 0;                    
                }
    }

function bpp_onclick(id,texto,codigo)
    {
    document.getElementById(id).value = texto; 
    document.getElementById(id+'Codigo').value = codigo;
    document.getElementById("id_bpp_seleccion").value = "0";
    $(".herman2").html("");
    document.getElementById(id).focus();
    }
function bpp_maneja_teclas(e,objeto,alto,ancho,margen_superior,margen_izquierdo) 
    {  
    var event = window.event ? window.event : e;
    // 1. No hace nada si se precionan flechas horizontales
    if(event.keyCode==37) return 0; // No hace nada si se usa felcha izquierda
    if(event.keyCode==39) return 0; // No hace nada si se usa felcha derecha
    if(event.keyCode==38) return 0; // No hace nada si se usa felcha arriba
    if(event.keyCode==40) return 0; // No hace nada si se usa felcha abajo
    // 2. Pone 0 en el código actual ya que siempre se cambiará posteriormente
    var id_actual_codigo = objeto.id+"Codigo";  // ID DE CODIGO ACTUAL CON NUMERAL
    document.getElementById(id_actual_codigo).value = 0;
    // 3. Controla que se llene automáticamente el campo en caso de que se escriba una combinación de letras única, mientras esto no se cumpla en el código pone 0 
    var url = "funciones_ajax.php?funcion=busqueda_producto_x_nombre&parametro1=" + objeto.value;  // Se invoca a la función que devuelve el código por nombre o conincidencia de nombre
    $.get(url, function (data, status)
        {
        if(data!=0) // Si la función devuelve un codigo (no es 0)
            {
            document.getElementById(id_actual_codigo).value = data; // Asigna al campo de codigo el codigo de vuelto
            var url1 = "funciones_ajax.php?funcion=busqueda_producto_x_codigo&parametro1=" + data;  // Función que devuelve la palabra completa en caso de que el código haya sido establecido
            $.get(url1, function (data1, status1)
                {
                objeto.value = data1; // Escribe la palabra completa en el campo de nombre de busqueda
                $(".herman2").html(''); // Elimina el cuadro de busqueda predictiva
                return 0;
                });
            }
        });    
    // 4. despliega busqueda predictiva
    var texto_ingresado = objeto.value; //extrae el texto ingresado
    var id = objeto.id;    // Extrae el ID del campo
    // Llama a la función de busqueda predictiva para crear la lista desplegable
    var url3 = "funciones_ajax.php?funcion=busqueda_predictiva_producto&parametro1=" + texto_ingresado +"&parametro2=" + id + "&parametro3=" + alto + "&parametro4=" + ancho + "&parametro5=" + margen_superior + "&parametro6=" + margen_izquierdo;
    $.get(url3, function (data3, status3)
        {
        $(".herman2").html(data3); 
        if(data3.length==0) // Si no hay datos entonces pone en 0 codigo
            document.getElementById(id_actual_codigo).value = '0';
        return 0;
        });             
    // 5: SIEMPRE DEJA EL INDICE DEL SELECTOR en 0
    document.getElementById("id_bpp_seleccion").value = "0" 
    // OTRO NUMERO: SIEMPRE BORRA EL LISTADO, en caso de necesitarse desplegado la función retorna el control oportunamete antes de este paso
    $(".herman2").html('');
    }
function bpp_onmouseout(indice_anterior)
    {
    document.getElementById("id_bpp_seleccion").value = 0;
    var id_anterior = "#idBusqueda" + indice_anterior;
    $(id_anterior).attr('style','font-size: 11px; background-color: white;');
    }
function bpp_enfoca(indice_nuevo)
    {
    var indice_anterior = document.getElementById("id_bpp_seleccion").value;
    var id_anterior = "#idBusqueda" + indice_anterior;
    var id_nuevo = "#idBusqueda" + indice_nuevo;
    if(document.getElementById("idBusqueda"+indice_nuevo) == null) return 0
    $(id_anterior).attr('style','font-size: 11px; background-color: white;');
    $(id_nuevo).attr('style','font-size: 11px; background-color: #d6d6d6');
    document.getElementById("id_bpp_seleccion").value = indice_nuevo;
    }
function bpp_desenfoca()
    {
    var indice = document.getElementById("id_bpp_seleccion").value;
    if(indice!=0)
        return 0;
    $(".herman2").html("");    
    }
function bpp_obkeydown(e, objeto) 
    {  
    var event = window.event ? window.event : e;
    var id_actual_codigo = objeto.id+"Codigo";  // ID DE CODIGO ACTUAL CON NUMERAL
    var indice = document.getElementById("id_bpp_seleccion").value;
    if(event.keyCode == 9 || event.keyCode == 27) // Si pierde el enfoque por tab o esc cierra la lista desplegable
        $(".herman2").html("");   
    if(event.keyCode==13 && (document.getElementById("idBusqueda"+indice) != null))
        {
        var texto = document.getElementById("idNombre"+indice).value;
        var codigo = document.getElementById("idCodigo"+indice).value;
        objeto.value = texto;
        document.getElementById(id_actual_codigo).value = codigo;
        $(".herman2").html("");
        document.getElementById("id_bpp_seleccion").value = "0"
        document.getElementById(objeto.id).focus();        
        return 0;
        }   
 // Chequea que se seleccione de aforma adecuada con las flechas cada item de la liste desplegable                
    if(event.keyCode==40) 
        { 
        bpp_enfoca(++indice);
        return 0;
        }
    if(event.keyCode==38 && indice!=0) 
        {
        bpp_enfoca(--indice);
        return 0;
        }          
    }
</script>
<title>Divasoft - HOME</title>
</head>
<body class="metro">
    <header class="bg-dark" data-load="barra_navegacion.php"></header>
    <div style="position: absolute;">
<?php
for($y=1;$y<=$numero_filas;$y++)
    for($x=1;$x<=$numero_columnas;$x++)
        echo $html_celda='<input id="celdax'.$x.'y'.$y.'" style="position: absolute; height: '.$celda[$x][$y]["ALTO"].'px; width: '.$celda[$x][$y]["ANCHO"].'px; margin-top: '.$celda[$x][$y]["MS"].'px; margin-left:'.$celda[$x][$y]["MI"].'px; " type="text" value="'.$celda[$x][$y]["INDICE"].'" onKeyDown="maneja_teclas(event)" onfocus="actualiza_enfoque('.$celda[$x][$y]["INDICE"].')" onblur="desenfoca(this)" ondblclick="doble_click()" READONLY></input>';    
?>   
    </div>
    <input type="hidden" value="" id="id_enfoque"></input>
    <input type="hidden" value="" id="id_escape"></input>
    <input type="hidden" value="" id="id_auxiliar_desenfoque" onclick="" ></input>

<?php 
$bsp_ancho = 200;  $bsp_alto = 24;   $bsp_margen_izquierdo = 100;
$bsp_margen_superior = 180;
?>
<div class="input-control text" style="position: absolute; height: <?php echo $bsp_alto;?>px; width: <?php echo $bsp_ancho;?>px; margin-top: <?php echo $bsp_margen_superior;?>px; margin-left: <?php echo $bsp_margen_izquierdo;?>px;">
    <!--<input style="position: absolute;" type="text" value="" id="idProducto" onkeydown="bpp_maneja_teclas(event,this)" onblur="bpp_desenfoca()" onkeyup="busqueda_predictiva_producto(event,this,'<?php echo $bsp_alto;?>','<?php echo $bsp_ancho;?>','<?php echo $bsp_margen_superior;?>','<?php echo $bsp_margen_izquierdo;?>')">-->   
    .<input style="position: absolute;" type="text" value="" id="idProducto" onblur="bpp_desenfoca()" onkeydown="bpp_obkeydown(event, this)" onkeyup="bpp_maneja_teclas(event,this,'<?php echo $bsp_alto;?>','<?php echo $bsp_ancho;?>','<?php echo $bsp_margen_superior;?>','<?php echo $bsp_margen_izquierdo;?>')">   
 
    
</div> 
    <div class="input-control text" style="position: absolute; height: <?php echo $bsp_alto;?>px; width: <?php echo $bsp_ancho;?>px; margin-top: <?php echo $bsp_margen_superior;?>px; margin-left: <?php echo $bsp_margen_izquierdo + 10 + $bsp_ancho;?>px;"><input style="position: absolute;" type="text" id="idProductoCodigo" value="0"></input>
    </div>
    <div class="herman2" style="position: absolute; z-index: 1">
<!--<table width="500px" style="border: 1px solid gray;"><tr><td  style="background-color: " id="idProducto1" onmousemove="bpp_onmousemove('idProducto1')" onmouseout="bpp_onmouseout('idProducto1')" >Purple Haze - 504 - Purple Haze - Пурпл Хэйз</td></tr></table>-->
        
    </div><input type="hidden" value="0" id="id_bpp_seleccion"></input>  
</body>

</html>
