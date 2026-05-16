function bpp_desenfoca(id_desplegable,id,funcion) //////////
    {
    var indice = document.getElementById(id_desplegable+"seleccion").value;
    if(indice!=0)
        return 0;
    $("."+id_desplegable).html("");
    var nombre = escape(document.getElementById(id).value);    
    var url = "funciones_ajax.php?funcion=busqueda_"+funcion+"_x_nombre&parametro1=" + nombre;  // Se invoca a la función que devuelve el código por nombre o conincidencia de nombre
    var id_actual_codigo = id+"Codigo";
    $.get(url, function (data, status)
        {
        if(data==0) 
            {
            document.getElementById(id_actual_codigo).value = 0;
            return 0;
            }
        if(data!=0) // Si la función devuelve un codigo (no es 0)
            {
            document.getElementById(id_actual_codigo).value = data; // Asigna al campo de codigo el codigo de vuelto
            var url1 = "funciones_ajax.php?funcion=busqueda_"+funcion+"_x_codigo&parametro1=" + data;  // Función que devuelve la palabra completa en caso de que el código haya sido establecido
            $.get(url1, function (data1, status1)
                {
                document.getElementById(id).value = data1; // Escribe la palabra completa en el campo de nombre de busqueda
                $("."+id_desplegable).html(''); // Elimina el cuadro de busqueda predictiva
                return 0;
                });
            }
        });    
    }
function bpp_obkeydown(e, objeto, id_desplegable) 
    {  
    var event = window.event ? window.event : e;
    var id_actual_codigo = objeto.id+"Codigo";  // ID DE CODIGO ACTUAL CON NUMERAL
    var indice = document.getElementById(id_desplegable+"seleccion").value;
    if(event.keyCode == 9 || event.keyCode == 27) // Si pierde el enfoque por tab o esc cierra la lista desplegable
        $("."+id_desplegable).html("");   
    if(event.keyCode==13 && (document.getElementById("idBusqueda"+indice) != null))
        {
        var texto = document.getElementById("idNombre"+indice).value;
        var codigo = document.getElementById("idCodigo"+indice).value;
        objeto.value = texto;
        document.getElementById(id_actual_codigo).value = codigo;
        $("."+id_desplegable).html("");
        document.getElementById(id_desplegable+"seleccion").value = "0"
        document.getElementById(objeto.id).focus();        
        return 0;
        }   
 // Chequea que se seleccione de aforma adecuada con las flechas cada item de la liste desplegable                
    if(event.keyCode==40) 
        { 
        bpp_enfoca(++indice,id_desplegable);
        return 0;
        }
    if(event.keyCode==38 && indice!=0) 
        {
        bpp_enfoca(--indice,id_desplegable);
        return 0;
        }          
    }
function bpp_maneja_teclas(e,objeto,alto,ancho,margen_superior,margen_izquierdo,id_desplegable,funcion) 
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
    var url = "funciones_ajax.php?funcion=busqueda_"+funcion+"_x_nombre&parametro1=" + escape(objeto.value);  // Se invoca a la función que devuelve el código por nombre o conincidencia de nombre
    $.get(url, function (data, status)
        {
        if(data!=0) // Si la función devuelve un codigo (no es 0)
            {
            document.getElementById(id_actual_codigo).value = data; // Asigna al campo de codigo el codigo de vuelto
            var url1 = "funciones_ajax.php?funcion=busqueda_"+funcion+"_x_codigo&parametro1=" + data;  // Función que devuelve la palabra completa en caso de que el código haya sido establecido
            $.get(url1, function (data1, status1)
                {
                objeto.value = data1; // Escribe la palabra completa en el campo de nombre de busqueda
                data_x = data1;
                $("."+id_desplegable).html(''); // Elimina el cuadro de busqueda predictiva
                return 0;
                });
            }
        });    
    // 4. despliega busqueda predictiva
    var texto_ingresado = objeto.value; //extrae el texto ingresado
    var id = objeto.id;    // Extrae el ID del campo
    // Llama a la función de busqueda predictiva para crear la lista desplegable
    var url3 = "funciones_ajax.php?funcion=busqueda_predictiva_"+funcion+"&parametro1=" + texto_ingresado +"&parametro2=" + id + "&parametro3=" + alto + "&parametro4=" + ancho + "&parametro5=" + margen_superior + "&parametro6=" + margen_izquierdo + "&parametro7=" + id_desplegable;
    $.get(url3, function (data3, status3)
        {
        $("."+id_desplegable).html(data3); 
        if(data3.length==0) // Si no hay datos entonces pone en 0 codigo
            document.getElementById(id_actual_codigo).value = '0';
//        if(data3.length==0 || texto_ingresado.legth>0) // Si no hay datos entonces pone en 0 codigo
//            {
//            objeto.value = (objeto.value).substring(0,((objeto.value).length)-1);
//            return 0;
//            }
        return 0;
        });             
    // 5: SIEMPRE DEJA EL INDICE DEL SELECTOR en 0
    document.getElementById(id_desplegable+"seleccion").value = "0" 
    // OTRO NUMERO: SIEMPRE BORRA EL LISTADO, en caso de necesitarse desplegado la función retorna el control oportunamete antes de este paso
    $("."+id_desplegable).html('');
    }
function bpp_enfoca(indice_nuevo,id_desplegable)
    {
    var indice_anterior = document.getElementById(id_desplegable+"seleccion").value;
    var id_anterior = "#idBusqueda" + indice_anterior;
    var id_nuevo = "#idBusqueda" + indice_nuevo;
    if(document.getElementById("idBusqueda"+indice_nuevo) == null) return 0
    $(id_anterior).attr('style','font-size: 11px; background-color: white;');
    $(id_nuevo).attr('style','font-size: 11px; background-color: #d6d6d6');
    document.getElementById(id_desplegable+"seleccion").value = indice_nuevo;
    }
function bpp_onmouseout(indice_anterior,id_desplegable)
    {
 //   alert(id_desplegable);
    document.getElementById(id_desplegable+"seleccion").value = 0;
    var id_anterior = "#idBusqueda" + indice_anterior;
    $(id_anterior).attr('style','font-size: 11px; background-color: white;');
    }
function bpp_onclick(id,texto,codigo,id_desplegable)
    {
    document.getElementById(id).value = texto; 
    document.getElementById(id+'Codigo').value = codigo;
    document.getElementById(id_desplegable+"seleccion").value = "0";
    $("."+id_desplegable).html("");
    document.getElementById(id).focus();
    }
function ce_solo_numeros(evt, objeto) 
    {  
    var codigo_caracter = (evt.which) ? evt.which : evt.keyCode; 
    if(codigo_caracter == 46)
        {
        var cantidad = objeto.value;
        var existe = 0;
        var contador = 0;
        do
            {
            existe=cantidad.indexOf(".",existe);
            if(existe!=-1)
                {
                contador++;
                existe++;
                }
            } while(existe!=-1);
        if(existe==-1 && cantidad.length==0 && codigo_caracter== 46) return false;
        if(contador>=1 && codigo_caracter == 46) return false;
        if(contador)
            {
            var ultimoscaracteres=cantidad.substring(cantidad.indexOf(".")+1,cantidad.length);
            if(ultimoscaracteres.length>=2) return false;
            }
        return true;
        }
    if((codigo_caracter >= 48 && codigo_caracter <= 57) || (codigo_caracter >= 8 && codigo_caracter <= 9) || (codigo_caracter >= 37 && codigo_caracter <= 40)) // Si es numero o si es 
         return true;   
    return false;
    }
function espera(milisegundos) 
    {
    var comienzo = new Date().getTime();
    for (var i = 0; i < 1e7; i++) 
        if ((new Date().getTime() - comienzo) > milisegundos)
            break;
    }