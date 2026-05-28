<?php

// ============================================================================
//  funciones_v2.php  -  Logica nueva (estilo v3).
//  Consola de Correos / Facturas: extraccion desde Gmail.
// ============================================================================
  

// Normaliza texto: minusculas y sin tildes/dieresis/enie.
function normalizar_texto_correo($texto)
    {
    $texto = mb_strtolower((string)$texto, 'UTF-8');
    $reemplazos = array(
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n'
        );
    return strtr($texto, $reemplazos);
    }


// Retorna true si el texto contiene una palabra prohibida o la combinacion estado+cuenta.
function texto_es_prohibido_correo($texto)
    {
    $norm = normalizar_texto_correo($texto);
    if($norm == "")
        return false;

    $palabras_sueltas = array(1 => 'credito', 'disponible', 'disponibilidad', 'availability', 'statement');
    $total_sueltas = count($palabras_sueltas);
    for($i=1; $i<=$total_sueltas; $i++)
        {
        if(strpos($norm, $palabras_sueltas[$i]) !== false)
            return true;
        }

    if(strpos($norm, 'estado') !== false && strpos($norm, 'cuenta') !== false)
        return true;

    return false;
    }


// Retorna true si el texto contiene factura/facturas/invoice/invoices.
function texto_es_factura_correo($texto)
    {
    $norm = normalizar_texto_correo($texto);
    if($norm == "")
        return false;

    $palabras_factura = array(1 => 'factura', 'facturas', 'invoice', 'invoices');
    $total_factura = count($palabras_factura);
    for($i=1; $i<=$total_factura; $i++)
        {
        if(strpos($norm, $palabras_factura[$i]) !== false)
            return true;
        }
    return false;
    }


// Extrae recursivamente el cuerpo de un mime especifico (text/plain o text/html).
function extraer_cuerpo_mime_correo($payload, $mime_buscado)
    {
    $acumulado = "";
    if(!$payload)
        return $acumulado;

    $mime = method_exists($payload, 'getMimeType') ? $payload->getMimeType() : "";
    if($mime == $mime_buscado)
        {
        $body = $payload->getBody();
        if($body)
            {
            $data = $body->getData();
            if(!empty($data))
                $acumulado .= base64_decode(strtr($data, '-_', '+/')) . " ";
            }
        }

    $sub = method_exists($payload, 'getParts') ? $payload->getParts() : null;
    if(!empty($sub))
        {
        $total_sub = count($sub);
        for($i=1; $i<=$total_sub; $i++)
            $acumulado .= extraer_cuerpo_mime_correo($sub[$i-1], $mime_buscado);
        }
    return $acumulado;
    }


// Recolecta recursivamente nombres de adjuntos PDF/XLSX/XLS.
function buscar_adjuntos_correo($parts)
    {
    $encontrados = array();
    if(!is_array($parts))
        return $encontrados;

    $extensiones_validas = array(1 => 'pdf', 'xlsx', 'xls');
    $total_parts = count($parts);
    for($i=1; $i<=$total_parts; $i++)
        {
        $part = $parts[$i-1];
        $filename = $part->getFilename();
        if(!empty($filename))
            {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if(in_array($ext, $extensiones_validas, true))
                $encontrados[] = $filename;
            }
        $sub = $part->getParts();
        if(!empty($sub))
            $encontrados = array_merge($encontrados, buscar_adjuntos_correo($sub));
        }
    return $encontrados;
    }


// Recolecta recursivamente las parts (objetos) que son adjuntos PDF/XLSX/XLS.
function recolecta_parts_adjuntos_correo($payload, &$acumulado)
    {
    if(!$payload)
        return;

    $filename = method_exists($payload, 'getFilename') ? $payload->getFilename() : "";
    if(!empty($filename))
        {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(in_array($ext, array('pdf', 'xlsx', 'xls'), true))
            $acumulado[] = $payload;
        }

    $sub = method_exists($payload, 'getParts') ? $payload->getParts() : null;
    if(!empty($sub))
        {
        $total_sub = count($sub);
        for($i=1; $i<=$total_sub; $i++)
            recolecta_parts_adjuntos_correo($sub[$i-1], $acumulado);
        }
    }


// Descarga y guarda en archivo_correo los adjuntos PDF/XLSX/XLS de un mensaje.
// Idempotente: salta los que ya existan (mismo IDCORREO + NOMBREARCHIVO).
// Retorna el numero de adjuntos guardados.
function guarda_adjuntos_correo($service, $id_mensaje, $payload)
    {
    global $link;

    $guardados = 0;
    if(!$payload)
        return $guardados;

    // Recolectar las parts (objetos) que son adjuntos PDF/XLSX/XLS.
    $parts_adjuntos = array();
    recolecta_parts_adjuntos_correo($payload, $parts_adjuntos);

    $total_parts = count($parts_adjuntos);
    for($i=1; $i<=$total_parts; $i++)
        {
        $part     = $parts_adjuntos[$i-1];
        $filename = $part->getFilename();

        $body = $part->getBody();
        if(!$body)
            continue;
        $attachment_id = $body->getAttachmentId();
        if(empty($attachment_id))
            continue;

        // ¿Ya existe este adjunto (IDCORREO + NOMBREARCHIVO)?
        $idcorreo_sql = mysqli_real_escape_string($link, $id_mensaje);
        $filename_sql = mysqli_real_escape_string($link, $filename);
        $sql_existe = "SELECT CODIGO FROM archivo_correo WHERE IDCORREO = '".$idcorreo_sql."' AND NOMBREARCHIVO = '".$filename_sql."'";
        $resultado_existe = mysqli_query($link, $sql_existe);
        if($resultado_existe && mysqli_num_rows($resultado_existe) > 0)
            continue;

        // Descargar el binario del adjunto (viene en base64url).
        $adjunto = $service->users_messages_attachments->get('me', $id_mensaje, $attachment_id);
        $data    = $adjunto->getData();
        if(empty($data))
            continue;
        $binario = base64_decode(strtr($data, '-_', '+/'));

        $tamano   = strlen($binario);
        $hash     = md5($binario);
        $mimetype = $part->getMimeType();

        // INSERT con sentencia preparada para el LONGBLOB (no concatenar el binario).
        $sql_insert = "INSERT INTO archivo_correo
            (IDCORREO, CODIGOFINCA, CODIGOCONSOLIDADO, NOMBREARCHIVO, MIMETYPE, TAMANOBYTES, HASHARCHIVO, ARCHIVO, RUTA, FECHAGUARDADO)
            VALUES
            (?, NULL, NULL, ?, ?, ?, ?, ?, NULL, NULL)";
        $stmt = mysqli_prepare($link, $sql_insert);
        if($stmt === false)
            continue;

        $blob_nulo = NULL;
        mysqli_stmt_bind_param($stmt, "sssisb", $id_mensaje, $filename, $mimetype, $tamano, $hash, $blob_nulo);
        mysqli_stmt_send_long_data($stmt, 5, $binario);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $guardados++;
        }
    return $guardados;
    }


// Procesa un mensaje: aplica filtros, verifica duplicado por IDCORREO e inserta.
// Retorna 'descartado' (no paso filtros), 'saltado' (ya existia) o 'guardado'.
// $payload_out devuelve el payload del mensaje (para guardar adjuntos sin re-descargar).
function procesa_mensaje_factura($service, $msg, $tz, &$payload_out)
    {
    global $link;

    $detalle = $service->users_messages->get('me', $msg->getId(), array('format' => 'full'));
    $payload = $detalle->getPayload();
    $payload_out = $payload;
    $headers = $payload ? $payload->getHeaders() : array();

    $fecha_header = "";
    $de = ""; $para = ""; $cc = ""; $bcc = ""; $asunto = ""; $messageid = "";

    $numero_headers = count($headers);
    for($j=1; $j<=$numero_headers; $j++)
        {
        $h = $headers[$j-1];
        $nombre = strtolower($h->getName());
        if($nombre == 'date')            $fecha_header = $h->getValue();
        else if($nombre == 'from')       $de        = $h->getValue();
        else if($nombre == 'to')         $para      = $h->getValue();
        else if($nombre == 'cc')         $cc        = $h->getValue();
        else if($nombre == 'bcc')        $bcc       = $h->getValue();
        else if($nombre == 'subject')    $asunto    = $h->getValue();
        else if($nombre == 'message-id') $messageid = $h->getValue();
        }

    // 1) Remitente compras2@divaflor.com -> descartar.
    if(stripos($de, 'compras2@divaflor.com') !== false)
        return 'descartado';

    // 2) Adjuntos PDF/XLSX/XLS y cuerpos.
    $adjuntos = array();
    if($payload)
        {
        $filename_raiz = $payload->getFilename();
        if(!empty($filename_raiz))
            {
            $ext = strtolower(pathinfo($filename_raiz, PATHINFO_EXTENSION));
            if(in_array($ext, array('pdf', 'xlsx', 'xls'), true))
                $adjuntos[] = $filename_raiz;
            }
        $adjuntos = array_merge($adjuntos, buscar_adjuntos_correo($payload->getParts()));
        }
    $cuerpo_texto       = extraer_cuerpo_mime_correo($payload, 'text/plain');
    $cuerpo_html        = extraer_cuerpo_mime_correo($payload, 'text/html');
    $nombres_adj_concat = implode(' ', $adjuntos);

    // 3) Override factura/invoice (asunto, cuerpo o adjuntos) - prioridad sobre prohibidas.
    $es_factura = (
        texto_es_factura_correo($asunto) ||
        texto_es_factura_correo($cuerpo_texto . ' ' . $cuerpo_html) ||
        texto_es_factura_correo($nombres_adj_concat)
        );

    // 4) Filtros normales (solo si NO es factura).
    if(!$es_factura)
        {
        if(empty($adjuntos))
            return 'descartado';
        if(texto_es_prohibido_correo($asunto))
            return 'descartado';
        if(texto_es_prohibido_correo($nombres_adj_concat))
            return 'descartado';
        }

    // Verificar duplicado por IDCORREO.
    $idcorreo     = $msg->getId();
    $idcorreo_sql = mysqli_real_escape_string($link, $idcorreo);
    $sql_existe   = "SELECT CODIGO FROM correo_facturas_fincas WHERE IDCORREO = '".$idcorreo_sql."'";
    $resultado_existe = mysqli_query($link, $sql_existe);
    if($resultado_existe && mysqli_num_rows($resultado_existe) > 0)
        return 'saltado';

    // FECHAHORA: header Date -> Y-m-d H:i:s (America/Guayaquil).
    $fechahora = date('Y-m-d H:i:s');
    if($fecha_header != "")
        {
        try
            {
            $fecha_obj = new DateTime($fecha_header);
            $fecha_obj->setTimezone($tz);
            $fechahora = $fecha_obj->format('Y-m-d H:i:s');
            }
        catch(Throwable $e)
            {
            $fechahora = date('Y-m-d H:i:s');
            }
        }

    // Escapar texto e insertar.
    $threadid     = mysqli_real_escape_string($link, $detalle->getThreadId());
    $messageid    = mysqli_real_escape_string($link, $messageid);
    $de           = mysqli_real_escape_string($link, $de);
    $para         = mysqli_real_escape_string($link, $para);
    $cc           = mysqli_real_escape_string($link, $cc);
    $bcc          = mysqli_real_escape_string($link, $bcc);
    $asunto       = mysqli_real_escape_string($link, $asunto);
    $cuerpo_texto = mysqli_real_escape_string($link, $cuerpo_texto);
    $cuerpo_html  = mysqli_real_escape_string($link, $cuerpo_html);

    $sql_insert = "INSERT INTO correo_facturas_fincas
        (CODIGOFINCA, CODIGOCONSOLIDADO, IDCORREO, MESSAGEID, THREADID, FECHAHORA, FECHAPROCESADO,
         DE, PARA, CC, BCC, ASUNTO, CUERPOTEXTO, CUERPOHTML, ESTADO, CODIGOUSUARIOPROCESO, OBSERVACIONES)
        VALUES
        (NULL, NULL, '".$idcorreo_sql."', '".$messageid."', '".$threadid."', '".$fechahora."', NULL,
         '".$de."', '".$para."', '".$cc."', '".$bcc."', '".$asunto."', '".$cuerpo_texto."', '".$cuerpo_html."', 1, NULL, NULL)";
    mysqli_query($link, $sql_insert);
    return 'guardado';
    }


// Extrae correos de facturas desde Gmail en un rango (Y-m-d), recorriendo dia por dia,
// y los guarda en correo_facturas_fincas sin reprocesar los ya existentes.
function extraer_correos_facturas($fecha_desde, $fecha_hasta)
    {
    global $link;
    $tiempo_inicio = microtime(true);

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta))
        return "Por favor seleccione un rango de fechas valido";

    require_once __DIR__ . '/vendor/autoload.php';

    $ruta_client_secret = '/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json';
    $ruta_token         = '/home/u154-6g3keph3vtcn/credenciales_correos/token.json';

    try 
        {
        $client = new Google\Client();
        $client->setAuthConfig($ruta_client_secret);
        $client->addScope(Google\Service\Gmail::GMAIL_READONLY);
        $client->setAccessType('offline');
 
        if(!file_exists($ruta_token))
            return "ERROR: No existe token.json. Autorice primero con oauth_callback.php";
        $token = json_decode(file_get_contents($ruta_token), true);
        $client->setAccessToken($token);
 
        if($client->isAccessTokenExpired())
            {
            $refresh_token = $client->getRefreshToken();
            if(empty($refresh_token))
                return "ERROR: Token expirado y sin refresh token. Re-autorice con oauth_callback.php";
            $nuevo_token = $client->fetchAccessTokenWithRefreshToken($refresh_token);
            if(isset($nuevo_token['error']))
                return "ERROR: No se pudo refrescar el token: " . $nuevo_token['error'];
            if(!isset($nuevo_token['refresh_token']) && !empty($refresh_token))
                $nuevo_token['refresh_token'] = $refresh_token;
            file_put_contents($ruta_token, json_encode($nuevo_token));
            }

        $service = new Google\Service\Gmail($client);
        $tz = new DateTimeZone('America/Guayaquil');

        $total_guardados = 0;
        $total_saltados  = 0;
        $total_adjuntos  = 0;

        // Recorrer el rango dia por dia.
        $primer_dia  = new DateTime($fecha_desde . ' 00:00:00', $tz);
        $ultimo_dia  = new DateTime($fecha_hasta . ' 00:00:00', $tz);
        $numero_dias = (int)$primer_dia->diff($ultimo_dia)->days + 1;

        $dia = clone $primer_dia;
        for($d=1; $d<=$numero_dias; $d++)
            {
            // Rango del dia: 00:00:00 a 23:59:59 -> timestamps.
            $inicio_dia = new DateTime($dia->format('Y-m-d') . ' 00:00:00', $tz);
            $fin_dia    = new DateTime($dia->format('Y-m-d') . ' 23:59:59', $tz);
            $ts_inicio  = $inicio_dia->getTimestamp();
            $ts_fin     = $fin_dia->getTimestamp();

            // Mismo query que prueba_listado.php.
            $query = 'in:anywhere -from:compras2@divaflor.com has:attachment after:' . $ts_inicio . ' before:' . $ts_fin;

            // Paginacion dentro del dia (red de seguridad si hay > 100).
            $page_token = "";
            for($pagina=1; ; $pagina++)
                {
                $params = array('q' => $query, 'maxResults' => 100);
                if($page_token != "")
                    $params['pageToken'] = $page_token;

                $lista    = $service->users_messages->listUsersMessages('me', $params);
                $mensajes = $lista->getMessages();

                if(!empty($mensajes))
                    {
                    $numero_mensajes = count($mensajes);
                    for($i=1; $i<=$numero_mensajes; $i++)
                        {
                        $payload_msg = null;
                        $estado_msg = procesa_mensaje_factura($service, $mensajes[$i-1], $tz, $payload_msg);
                        if($estado_msg == 'guardado')
                            {
                            $total_guardados++;
                            $total_adjuntos += guarda_adjuntos_correo($service, $mensajes[$i-1]->getId(), $payload_msg);
                            }
                        else if($estado_msg == 'saltado')
                            $total_saltados++;
                        }
                    }

                $page_token = $lista->getNextPageToken();
                if(empty($page_token))
                    break;
                }

            $dia->modify('+1 day');
            }

        $tiempo_total = round(microtime(true) - $tiempo_inicio, 2);
        $total_procesados = $total_guardados + $total_saltados;
        return "Se procesaron ".$total_procesados." correos, se guardaron ".$total_guardados." nuevos, se saltaron ".$total_saltados." ya existentes. Adjuntos guardados: ".$total_adjuntos.". Tiempo: ".$tiempo_total." segundos";
        }
    catch(Throwable $e)
        {
        return "ERROR: " . $e->getMessage();
        }
    }


// Lista los correos de correo_facturas_fincas de los ultimos 5 dias (HTML de la tabla).
function lista_correos_facturas()
    {
    global $link;

    $sql = "SELECT
        CODIGO AS CODIGO,
        CODIGOFINCA AS CODIGOFINCA,
        CODIGOCONSOLIDADO AS CODIGOCONSOLIDADO,
        IDCORREO AS IDCORREO,
        FECHAHORA AS FECHAHORA,
        DE AS DE,
        PARA AS PARA,
        ASUNTO AS ASUNTO,
        ESTADO AS ESTADO,
        OBSERVACIONES AS OBSERVACIONES
        FROM correo_facturas_fincas
        WHERE FECHAHORA >= DATE_SUB(NOW(), INTERVAL 5 DAY)
        ORDER BY FECHAHORA DESC";
    $resultado = mysqli_query($link, $sql);
    $numero_correos = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=1; $i<=$numero_correos; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']            = $fila['CODIGO'];
        $arreglo[$i]['CODIGOFINCA']       = $fila['CODIGOFINCA'];
        $arreglo[$i]['CODIGOCONSOLIDADO'] = $fila['CODIGOCONSOLIDADO'];
        $arreglo[$i]['IDCORREO']          = $fila['IDCORREO'];
        $arreglo[$i]['FECHAHORA']         = $fila['FECHAHORA'];
        $arreglo[$i]['DE']                = $fila['DE'];
        $arreglo[$i]['PARA']              = $fila['PARA'];
        $arreglo[$i]['ASUNTO']            = $fila['ASUNTO'];
        $arreglo[$i]['ESTADO']            = $fila['ESTADO'];
        $arreglo[$i]['OBSERVACIONES']     = $fila['OBSERVACIONES'];
        }

    $html = '<table class="grid_correos">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 5%;">COD</th>';
    $html .= '<th style="width: 12%;">FINCA</th>';
    $html .= '<th style="width: 8%;">CONS</th>';
    $html .= '<th style="width: 6%;">ID</th>';
    $html .= '<th style="width: 14%;">FH REC</th>';
    $html .= '<th style="width: 18%;">DE</th>';
    $html .= '<th style="width: 15%;">PARA</th>';
    $html .= '<th style="width: 9%;">EST</th>';
    $html .= '<th style="width: 13%;">OPC</th>';
    $html .= '</tr></thead>';

    for($i=1; $i<=$numero_correos; $i++)
        {
        $codigo    = $arreglo[$i]['CODIGO'];
        $finca     = ($arreglo[$i]['CODIGOFINCA'] === null || $arreglo[$i]['CODIGOFINCA'] === '') ? '&mdash;' : htmlspecialchars($arreglo[$i]['CODIGOFINCA'], ENT_QUOTES, 'UTF-8');
        $cons      = ($arreglo[$i]['CODIGOCONSOLIDADO'] === null || $arreglo[$i]['CODIGOCONSOLIDADO'] === '') ? '&mdash;' : htmlspecialchars($arreglo[$i]['CODIGOCONSOLIDADO'], ENT_QUOTES, 'UTF-8');
        $idcorreo  = htmlspecialchars((string)$arreglo[$i]['IDCORREO'], ENT_QUOTES, 'UTF-8');
        $fechahora = htmlspecialchars((string)$arreglo[$i]['FECHAHORA'], ENT_QUOTES, 'UTF-8');
        $de        = htmlspecialchars((string)$arreglo[$i]['DE'], ENT_QUOTES, 'UTF-8');
        $para      = htmlspecialchars((string)$arreglo[$i]['PARA'], ENT_QUOTES, 'UTF-8');
        $asunto    = htmlspecialchars((string)$arreglo[$i]['ASUNTO'], ENT_QUOTES, 'UTF-8');
        $estado    = htmlspecialchars((string)$arreglo[$i]['ESTADO'], ENT_QUOTES, 'UTF-8');
        $obs       = ($arreglo[$i]['OBSERVACIONES'] === null || $arreglo[$i]['OBSERVACIONES'] === '') ? '&mdash;' : htmlspecialchars($arreglo[$i]['OBSERVACIONES'], ENT_QUOTES, 'UTF-8');

        $html .= '<tbody id="id_grupo_correo_'.$codigo.'" class="grupo_correo" onclick="devuelve_correo('.$codigo.');">';
        $html .= '<tr>';
        $html .= '<td class="td_centro"><strong>'.$codigo.'</strong></td>';
        $html .= '<td>'.$finca.'</td>';
        $html .= '<td class="td_centro">'.$cons.'</td>';
        $html .= '<td class="td_centro">'.$idcorreo.'</td>';
        $html .= '<td class="td_centro">'.$fechahora.'</td>';
        $html .= '<td>'.$de.'</td>';
        $html .= '<td>'.$para.'</td>';
        $html .= '<td class="td_centro">'.$estado.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: devuelve_correo('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: muestra_trazabilidad_correo('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: ver_adjunto_correo('.$codigo.');" title="Ver adjunto PDF/Excel"><i class="icon-clipboard-2 fg-darkRed"></i></a>';
        $html .= '<a href="javascript: ver_cuerpo_correo('.$codigo.');" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '<tr class="fila_asunto">';
        $html .= '<td colspan="6"><span class="etiqueta_asunto">SUBJ:</span>'.$asunto.'</td>';
        $html .= '<td colspan="3"><span class="etiqueta_asunto">OBS:</span>'.$obs.'</td>';
        $html .= '</tr>';
        $html .= '</tbody>';
        }

    $html .= '</table>';
    $html .= '<div style="text-align:right; font-size:11px; color:#666; padding:5px;">Total: '.$numero_correos.' correos (ultimos 5 dias)</div>';
    return $html;
    }