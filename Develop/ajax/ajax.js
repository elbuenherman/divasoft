function getXMLHTTPRequest()
	{
	var req;
	try {req = new XMLHttpRequest();} 
	catch(err1) 
		{ 
		try 
			{
			req = new ActiveXObject("Msxml2.XMLHTTP");
			} 
	    catch(err2) 
			{
			try 
				{
				req = new ActiveXObject("Microsoft.XMLHTTP");
				} 
			catch(err3) 
				{
				req = false;
				}	
			}	
		}	
	return req;
	}
function loadXMLDoc(filename)
	{
	if (window.XMLHttpRequest) xhttp=new XMLHttpRequest();
	else xhttp=new ActiveXObject("Microsoft.XMLHTTP");
	xhttp.open("GET",filename,false);
	xhttp.send();
	return xhttp.responseXML;
	}
function pone_opcion_en_select_x_codigo(codigo,objeto)
	{
        if(codigo=="-1")
            {
            objeto.selectedIndex = 1;
            return 0;
            }
            
	var contador = 0;
	var indice = 0;
	var valor = 0;
	var total_opciones = objeto.options.length;
	for (contador=0;contador<total_opciones;contador++)
		{
		valor = objeto.options[contador].value;
		if (valor == codigo)
			indice = contador;
		}	
	objeto.selectedIndex = indice;
	return 0;
	}
function devuelve_codigo_select(objeto)
        {
        var indice = objeto.selectedIndex;
        var codigo = objeto.options[indice].value;      
        return codigo;
        }
function isImage(extension)
    {
    switch(extension.toLowerCase()) 
        {
        //case 'jpg': case 'gif': case 'png': case 'jpeg':
        case 'png':
            return true;
        break;
        default:
            return false;
        break;
        }
    }