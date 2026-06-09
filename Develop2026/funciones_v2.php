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


// Retorna true si el dominio del remitente esta en la lista de bloqueados.
// Esta verificacion gana sobre todo lo demas y se ejecuta antes de cualquier
// otra regla. Tres patrones soportados:
//   - direccion exacta:  compras2@divaflor.com  (solo esa direccion)
//   - dominio completo:  @ifc.net.co            (cualquier address @ifc.net.co)
//   - dominio completo:  @saftec.com.ec, @directcargo.ec
// Comparacion case-insensitive (strtolower previo + patrones en minusculas).
function remitente_dominio_bloqueado($de)
    {
    if($de == "")
        return false;
    $de_lower = strtolower((string)$de);

    $patrones = array(
        1 => 'compras2@divaflor.com',
        2 => '@ifc.net.co',
        3 => '@saftec.com.ec',
        4 => '@directcargo.ec'
        );
    $total = count($patrones);
    for($i=1; $i<=$total; $i++)
        {
        if(strpos($de_lower, $patrones[$i]) !== false)
            return true;
        }
    return false;
    }


// Retorna la palabra/frase prohibida encontrada en el texto, o cadena vacia
// si no hay match. Las prohibidas GANAN sobre las gatillo.
// Pre-normalizacion: '_' se reemplaza por espacio, asi "EST_CUENTA" matchea
// la frase "est cuenta". Luego se aplica normalizar_texto_correo (lowercase
// + sin tildes/enie).
function texto_es_prohibido_correo($texto)
    {
    $texto = str_replace('_', ' ', (string)$texto);
    $norm = normalizar_texto_correo($texto);
    if($norm == "")
        return "";

    // 1) Palabras sueltas.
    $palabras_sueltas = array(1 => 'credito', 'credit', 'disponible', 'disponibilidad', 'availability',
        'statement', 'balance', 'corte', 'reporte', 'promotion', 'promocion', 'oferta', 'offer');
    $total_sueltas = count($palabras_sueltas);
    for($i=1; $i<=$total_sueltas; $i++)
        {
        if(strpos($norm, $palabras_sueltas[$i]) !== false)
            return $palabras_sueltas[$i];
        }

    // 2) Frases (substring).
    $frases = array(1 => 'est cuenta', 'est cta', 'nota de credito', 'credit note',
        'pending invoices', 'facturas pendientes');
    $total_frases = count($frases);
    for($i=1; $i<=$total_frases; $i++)
        {
        if(strpos($norm, $frases[$i]) !== false)
            return $frases[$i];
        }

    // 3) Combinacion: ambas palabras presentes (no necesariamente juntas).
    if(strpos($norm, 'estado') !== false && strpos($norm, 'cuenta') !== false)
        return "estado+cuenta";

    return "";
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
// Si $fh_log no es null, escribe en el log el nombre y tamano de cada
// adjunto que efectivamente se descarga.
function guarda_adjuntos_correo($service, $id_mensaje, $payload, $fh_log = null)
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

        if($fh_log !== null)
            fwrite($fh_log, date("H:i:s")."   Adjunto: ".$filename." (".$tamano." bytes)\n");

        $guardados++;
        }
    return $guardados;
    }


// Procesa un mensaje: aplica filtros, verifica duplicado por IDCORREO e inserta.
// Retorna un veredicto descriptivo: 'guardado', 'saltado:duplicado', o
// 'descartado:<motivo>[:detalle]'.
// $payload_out devuelve el payload del mensaje (para guardar adjuntos sin
// re-descargar). $de_out / $asunto_out devuelven los headers ya extraidos
// del payload, para que el caller los loggee sin tener que volver a
// llamar a la API de Gmail.
function procesa_mensaje_factura($service, $msg, $tz, &$payload_out, &$de_out, &$asunto_out)
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

    // Devolver De y Asunto al caller para que pueda loggear (sin nueva llamada API).
    $de_out     = $de;
    $asunto_out = $asunto;

    // 1) Dominio bloqueado -> descartar absoluto (gana sobre todo).
    if(remitente_dominio_bloqueado($de))
        return 'descartado:dominio_bloqueado';

    // 2) Recolectar adjuntos PDF/XLSX/XLS y cuerpos (cuerpos se usan solo
    //    para guardar en BD, ya NO para filtrar).
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

    // 3) Sin adjuntos PDF/XLSX/XLS validos -> descartar.
    if(empty($adjuntos))
        return 'descartado:sin_adjuntos';

    // 4) Prohibida en ASUNTO -> descartar (gana sobre factura).
    $palabra_prohibida = texto_es_prohibido_correo($asunto);
    if($palabra_prohibida != "")
        return 'descartado:prohibida_asunto:'.$palabra_prohibida;

    // 5) Prohibida en NOMBRES DE ADJUNTOS -> descartar (gana sobre factura).
    $palabra_prohibida = texto_es_prohibido_correo($nombres_adj_concat);
    if($palabra_prohibida != "")
        return 'descartado:prohibida_adjunto:'.$palabra_prohibida;

    // 6/7) El correo paso los filtros: tiene adjuntos validos y ninguna
    //      prohibida. Sea factura explicita (gatillo en asunto/adjunto) o
    //      no, se guarda. Las gatillo ya no influyen en la decision
    //      porque el descarte por prohibida ocurrio antes.

    // Verificar duplicado por IDCORREO.
    $idcorreo     = $msg->getId();
    $idcorreo_sql = mysqli_real_escape_string($link, $idcorreo);
    $sql_existe   = "SELECT CODIGO FROM correo_facturas_fincas WHERE IDCORREO = '".$idcorreo_sql."'";
    $resultado_existe = mysqli_query($link, $sql_existe);
    if($resultado_existe && mysqli_num_rows($resultado_existe) > 0)
        return 'saltado:duplicado';

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


// Helper para escribir el archivo de progreso (consumido por el endpoint
// 'progreso_extraccion' que el frontend polea cada 2 segundos).
function _escribir_progreso_extraccion($ruta, $estado, $procesados, $guardados, $saltados, $dia_actual, $total_dias, $extra = array())
    {
    $data = array(
        "estado"     => $estado,
        "procesados" => $procesados,
        "guardados"  => $guardados,
        "saltados"   => $saltados,
        "dia_actual" => $dia_actual,
        "total_dias" => $total_dias
        );
    if(!empty($extra))
        $data = array_merge($data, $extra);
    file_put_contents($ruta, json_encode($data));
    }


// Extrae correos de facturas desde Gmail en un rango (Y-m-d), recorriendo dia por dia,
// y los guarda en correo_facturas_fincas sin reprocesar los ya existentes.
function extraer_correos_facturas($fecha_desde, $fecha_hasta)
    {
    global $link;
    $tiempo_inicio = microtime(true); 

    // Ruta fija conocida por el frontend (polling) y por este script.
    $ruta_progreso    = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_progreso_extraccion.json";
    $procesados_total = 0;
    $total_guardados  = 0;
    $total_saltados   = 0;
    $numero_dias      = 0;

    // Estado inicial. total_dias todavia 0 (se actualizara apenas se calcule).
    _escribir_progreso_extraccion($ruta_progreso, "en_curso", 0, 0, 0, "", 0);

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta))
        {
        _escribir_progreso_extraccion($ruta_progreso, "error", 0, 0, 0, "", 0, array("mensaje" => "Rango de fechas invalido"));
        return "Por favor seleccione un rango de fechas valido";
        }

    require_once __DIR__ . '/vendor/autoload.php';

    $ruta_client_secret = '/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json';
    $ruta_token         = '/home/u154-6g3keph3vtcn/credenciales_correos/token.json';

    // Log de la extraccion (un archivo nuevo por corrida).
    $ruta_log = "/tmp/extraccion_correos_".date("Ymd_His").".log";
    $fh_log   = fopen($ruta_log, "w");

    if($fh_log)
        fwrite($fh_log, date("H:i:s")." - Extraccion iniciada. Rango: ".$fecha_desde." a ".$fecha_hasta."\n");

    try
        {
        $client = new Google\Client();
        $client->setAuthConfig($ruta_client_secret);
        $client->addScope(Google\Service\Gmail::GMAIL_READONLY);
        $client->setAccessType('offline');

        if(!file_exists($ruta_token))
            {
            if($fh_log) fwrite($fh_log, date("H:i:s")." - ERROR API: No existe token.json\n");
            if($fh_log) fclose($fh_log);
            _escribir_progreso_extraccion($ruta_progreso, "error", 0, 0, 0, "", 0, array("mensaje" => "No existe token.json"));
            return "ERROR: No existe token.json. Autorice primero con oauth_callback.php. Log: ".$ruta_log;
            }
        $token = json_decode(file_get_contents($ruta_token), true);
        $client->setAccessToken($token);

        if($client->isAccessTokenExpired())
            {
            $refresh_token = $client->getRefreshToken();
            if(empty($refresh_token))
                {
                if($fh_log) fwrite($fh_log, date("H:i:s")." - ERROR API: Token expirado sin refresh token\n");
                if($fh_log) fclose($fh_log);
                _escribir_progreso_extraccion($ruta_progreso, "error", 0, 0, 0, "", 0, array("mensaje" => "Token expirado sin refresh token"));
                return "ERROR: Token expirado y sin refresh token. Re-autorice con oauth_callback.php. Log: ".$ruta_log;
                }
            $nuevo_token = $client->fetchAccessTokenWithRefreshToken($refresh_token);
            if(isset($nuevo_token['error']))
                {
                if($fh_log) fwrite($fh_log, date("H:i:s")." - ERROR API: No se pudo refrescar el token: ".$nuevo_token['error']."\n");
                if($fh_log) fclose($fh_log);
                _escribir_progreso_extraccion($ruta_progreso, "error", 0, 0, 0, "", 0, array("mensaje" => "No se pudo refrescar el token: ".$nuevo_token['error']));
                return "ERROR: No se pudo refrescar el token: ".$nuevo_token['error'].". Log: ".$ruta_log;
                }
            if(!isset($nuevo_token['refresh_token']) && !empty($refresh_token))
                $nuevo_token['refresh_token'] = $refresh_token;
            file_put_contents($ruta_token, json_encode($nuevo_token));
            if($fh_log) fwrite($fh_log, date("H:i:s")." - Token refrescado y persistido\n");
            }

        $service = new Google\Service\Gmail($client);
        $tz = new DateTimeZone('America/Guayaquil');

        // total_guardados, total_saltados y numero_dias ya inicializados a 0
        // al inicio de la funcion (para que esten disponibles en el catch).
        $total_adjuntos = 0;

        // Recorrer el rango dia por dia.
        $primer_dia  = new DateTime($fecha_desde . ' 00:00:00', $tz);
        $ultimo_dia  = new DateTime($fecha_hasta . ' 00:00:00', $tz);
        $numero_dias = (int)$primer_dia->diff($ultimo_dia)->days + 1;

        // Re-escribir progreso ahora que conocemos total_dias.
        _escribir_progreso_extraccion($ruta_progreso, "en_curso", 0, 0, 0, "", $numero_dias);

        $dia = clone $primer_dia;
        for($d=1; $d<=$numero_dias; $d++)
            {
            $fecha_dia_str = $dia->format('Y-m-d');
            if($fh_log) fwrite($fh_log, date("H:i:s")." - Procesando dia: ".$fecha_dia_str."\n");

            $guardados_dia  = 0;
            $procesados_dia = 0;

            // Rango del dia: 00:00:00 a 23:59:59 -> timestamps.
            $inicio_dia = new DateTime($fecha_dia_str . ' 00:00:00', $tz);
            $fin_dia    = new DateTime($fecha_dia_str . ' 23:59:59', $tz);
            $ts_inicio  = $inicio_dia->getTimestamp();
            $ts_fin     = $fin_dia->getTimestamp();

            // Excluir dominios bloqueados ya desde el query Gmail (defensa en
            // capas: PHP tambien los chequea con remitente_dominio_bloqueado).
            $query = 'in:anywhere -from:*@ifc.net.co -from:*@saftec.com.ec -from:*@directcargo.ec -from:compras2@divaflor.com has:attachment after:' . $ts_inicio . ' before:' . $ts_fin;

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
                        $msg_actual   = $mensajes[$i-1];
                        $idcorreo_log = $msg_actual->getId();
                        $procesados_dia++;

                        // Una sola llamada a la API (format:full) dentro de
                        // procesa_mensaje_factura. De y Asunto vienen de ahi
                        // por referencia, listos para loggear despues del veredicto.
                        $payload_msg = null;
                        $de_msg      = "";
                        $asunto_msg  = "";
                        $estado_msg  = procesa_mensaje_factura($service, $msg_actual, $tz, $payload_msg, $de_msg, $asunto_msg);

                        if($fh_log)
                            fwrite($fh_log, date("H:i:s")." - Correo ID=".$idcorreo_log.", De=".$de_msg.", Asunto=".$asunto_msg."\n");

                        if($estado_msg == 'guardado')
                            {
                            $total_guardados++;
                            $guardados_dia++;
                            $adj_n = guarda_adjuntos_correo($service, $idcorreo_log, $payload_msg, $fh_log);
                            $total_adjuntos += $adj_n;
                            if($fh_log) fwrite($fh_log, date("H:i:s")." -> guardado (adjuntos: ".$adj_n.")\n");
                            }
                        else if(strpos($estado_msg, 'saltado') === 0)
                            {
                            $total_saltados++;
                            if($fh_log) fwrite($fh_log, date("H:i:s")." -> ".$estado_msg."\n");
                            }
                        else  // descartado:...
                            {
                            if($fh_log) fwrite($fh_log, date("H:i:s")." -> ".$estado_msg."\n");
                            }

                        // Actualizar el archivo de progreso despues de cada correo.
                        $procesados_total++;
                        _escribir_progreso_extraccion($ruta_progreso, "en_curso", $procesados_total, $total_guardados, $total_saltados, $fecha_dia_str, $numero_dias);
                        }
                    }

                $page_token = $lista->getNextPageToken();
                if(empty($page_token))
                    break;
                }

            if($fh_log) fwrite($fh_log, date("H:i:s")." - Dia ".$fecha_dia_str." completado. Correos: ".$procesados_dia.", Guardados: ".$guardados_dia."\n");

            $dia->modify('+1 day');
            }

        $tiempo_total = round(microtime(true) - $tiempo_inicio, 2);
        $total_procesados = $total_guardados + $total_saltados;

        if($fh_log)
            {
            fwrite($fh_log, date("H:i:s")." - Extraccion finalizada. Total procesados: ".$total_procesados.", guardados: ".$total_guardados.", saltados: ".$total_saltados.", adjuntos: ".$total_adjuntos.". Tiempo: ".$tiempo_total."s\n");
            fwrite($fh_log, date("H:i:s")." - Log guardado en: ".$ruta_log."\n");
            fclose($fh_log);
            }

        // Estado final OK. El frontend detecta esto y detiene el polling.
        _escribir_progreso_extraccion($ruta_progreso, "finalizado", $total_procesados, $total_guardados, $total_saltados, "", $numero_dias);

        return "Se procesaron ".$total_procesados." correos, se guardaron ".$total_guardados." nuevos, se saltaron ".$total_saltados." ya existentes. Adjuntos guardados: ".$total_adjuntos.". Tiempo: ".$tiempo_total." segundos. Log: ".$ruta_log;
        }
    catch(Throwable $e)
        {
        if($fh_log)
            {
            fwrite($fh_log, date("H:i:s")." - ERROR API: ".$e->getMessage()."\n");
            fclose($fh_log);
            }
        // Estado error. El frontend detecta esto y detiene el polling.
        _escribir_progreso_extraccion($ruta_progreso, "error", $procesados_total, $total_guardados, $total_saltados, "", $numero_dias, array("mensaje" => $e->getMessage()));
        return "ERROR: ".$e->getMessage().". Log: ".$ruta_log;
        }
    }

 
// ============================================================================
// Procesar factura desde la web (invoca procesa_factura_final_cli_haiku.php
// por CLI con exec). Llamado desde el icono "Procesar factura con IA" en el
// grid de correos. Retorna texto plano con el resultado para el messageBox.
// ============================================================================
function procesar_factura_web($codigo_adjunto)
    {
    global $link;
    $codigo_adjunto = (int)$codigo_adjunto;
    if($codigo_adjunto <= 0)
        return "Error: codigo de adjunto invalido";

    // Verificar que el adjunto existe.
    $sql_check = "SELECT CODIGO, NOMBREARCHIVO FROM archivo_correo WHERE CODIGO = ".$codigo_adjunto;
    $res_check = mysqli_query($link, $sql_check);
    if(!$res_check || mysqli_num_rows($res_check) == 0)
        return "Error: adjunto no encontrado (codigo ".$codigo_adjunto.")";
    $fila = mysqli_fetch_assoc($res_check);

    // Rutas fijas conocidas.
    $ruta_script   = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/procesa_factura_final_cli_haiku.php";
    $ruta_progreso = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_progreso_factura.json";
    $ruta_output   = "/home/u154-6g3keph3vtcn/www/dienersoft.com/public_html/carpeta/divasoft1/Develop2026/tmp_factura_output_".$codigo_adjunto.".txt";

    // Escribir progreso inicial (consumido por el endpoint progreso_factura).
    file_put_contents($ruta_progreso, json_encode(array(
        "estado"  => "en_curso",
        "mensaje" => "Iniciando procesamiento de ".$fila["NOMBREARCHIVO"]."..."
        )));

    // Lanzar el script CLI en SEGUNDO PLANO (no bloqueante).
    // El endpoint progreso_factura detecta el final leyendo $ruta_output (busca
    // "=== FIN ===" o "Fatal error"). El script CLI tambien escribe su propio
    // log detallado en /tmp/final_log_*.txt para diagnostico.
    $comando = "nohup php ".$ruta_script." ".$codigo_adjunto." > ".$ruta_output." 2>&1 &";
    exec($comando);

    return "Procesamiento iniciado para ".$fila["NOMBREARCHIVO"].". El proceso corre en segundo plano.";
    }


// Extrae solo el/los email(s) de un campo De/Para (quita nombres, comillas y < >).
function limpia_email($texto)
    {
    $texto = (string)$texto;
    if(trim($texto) == "")
        return "";
    $partes = explode(',', $texto);
    $emails = array();
    $total = count($partes);
    for($i=1; $i<=$total; $i++)
        {
        $parte = trim($partes[$i-1]);
        if($parte == "")
            continue;
        if(preg_match('/<([^>]+)>/', $parte, $m))
            $emails[] = trim($m[1]);
        else if(preg_match('/[^\s<>,"\']+@[^\s<>,"\']+/', $parte, $m))
            $emails[] = $m[0];
        }
    return implode(', ', $emails);
    }


// Devuelve el indicador (triangulo) de ordenamiento para una columna.
function indicador_orden($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }


// Lista los correos de correo_facturas_fincas de los ultimos 5 dias (HTML de la tabla).
// Debajo de cada correo agrega una fila por cada adjunto guardado en archivo_correo.
function lista_correos_facturas($campo_orden = "FECHAHORA", $direccion_orden = "DESC", $fecha_desde = "", $fecha_hasta = "")
    {
    global $link;

    // Set de adjuntos que ya fueron procesados (un solo query, no uno por adjunto).
    // Clave = CODIGOADJUNTO, valor = array con datos de factura_finca para mostrar
    // en el grid (CODIGO, FINCA, CLIENTEMARCACION, NUMEROFACTURA, GUIA, FULLES).
    $adjuntos_procesados = array();
    $sql_procesados = "SELECT CODIGOADJUNTO, CODIGO, FINCA, CLIENTEMARCACION, NUMEROFACTURA, GUIA, TOTALCAJASEQUIVALENTES
        FROM factura_finca WHERE CODIGOADJUNTO IS NOT NULL";
    $res_procesados = mysqli_query($link, $sql_procesados);
    if($res_procesados)
        {
        $numero_procesados = mysqli_num_rows($res_procesados);
        for($p=1; $p<=$numero_procesados; $p++)
            {
            $fila_p = mysqli_fetch_assoc($res_procesados);
            $adjuntos_procesados[(int)$fila_p["CODIGOADJUNTO"]] = array(
                "CODIGO"           => (int)$fila_p["CODIGO"],
                "FINCA"            => $fila_p["FINCA"],
                "CLIENTEMARCACION" => $fila_p["CLIENTEMARCACION"],
                "NUMEROFACTURA"    => $fila_p["NUMEROFACTURA"],
                "GUIA"             => $fila_p["GUIA"],
                "FULLES"           => $fila_p["TOTALCAJASEQUIVALENTES"]
                );
            }
        }

    // Validar campo y direccion de ordenamiento.
    $campos_permitidos = array(1=>"CODIGO", 2=>"CODIGOFINCA", 3=>"CODIGOCONSOLIDADO", 4=>"ASUNTO", 5=>"FECHAHORA", 6=>"DE", 7=>"PARA", 8=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "FECHAHORA";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "ASC") ? "ASC" : "DESC";

    // Filtro por rango de fechas: si vienen ambas, usar ese rango.
    // Si no, mantener el comportamiento por defecto (ultimos 5 dias).
    $valida_desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_desde);
    $valida_hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_hasta);
    if($valida_desde && $valida_hasta)
        {
        $fecha_desde = mysqli_real_escape_string($link, $fecha_desde);
        $fecha_hasta = mysqli_real_escape_string($link, $fecha_hasta);
        $where_fechas = "FECHAHORA >= '".$fecha_desde." 00:00:00' AND FECHAHORA <= '".$fecha_hasta." 23:59:59'";
        }
    else
        {
        $where_fechas = "FECHAHORA >= DATE_SUB(NOW(), INTERVAL 5 DAY)";
        }

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
        WHERE ".$where_fechas."
        ORDER BY ".$orden_valido." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    $numero_correos = mysqli_num_rows($resultado);

    $arreglo_correos = array();
    for($i=1; $i<=$numero_correos; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo_correos[$i]['CODIGO']            = $fila['CODIGO'];
        $arreglo_correos[$i]['CODIGOFINCA']       = $fila['CODIGOFINCA'];
        $arreglo_correos[$i]['CODIGOCONSOLIDADO'] = $fila['CODIGOCONSOLIDADO'];
        $arreglo_correos[$i]['IDCORREO']          = $fila['IDCORREO'];
        $arreglo_correos[$i]['FECHAHORA']         = $fila['FECHAHORA'];
        $arreglo_correos[$i]['DE']                = $fila['DE'];
        $arreglo_correos[$i]['PARA']              = $fila['PARA'];
        $arreglo_correos[$i]['ASUNTO']            = $fila['ASUNTO'];
        $arreglo_correos[$i]['ESTADO']            = $fila['ESTADO'];
        $arreglo_correos[$i]['OBSERVACIONES']     = $fila['OBSERVACIONES'];
        }

    // Traer en UNA sola consulta los adjuntos de todos los correos del listado.
    $adjuntos_por_correo = array();
    if($numero_correos > 0)
        {
        $lista_ids = array();
        for($i=1; $i<=$numero_correos; $i++)
            $lista_ids[$i] = "'".mysqli_real_escape_string($link, $arreglo_correos[$i]['IDCORREO'])."'";
        $in = implode(',', $lista_ids);

        $sql_adj = "SELECT
            CODIGO AS CODIGO,
            IDCORREO AS IDCORREO,
            CODIGOFINCA AS CODIGOFINCA,
            CODIGOCONSOLIDADO AS CODIGOCONSOLIDADO,
            NOMBREARCHIVO AS NOMBREARCHIVO,
            MIMETYPE AS MIMETYPE,
            TAMANOBYTES AS TAMANOBYTES
            FROM archivo_correo
            WHERE IDCORREO IN (".$in.")";
        $resultado_adj = mysqli_query($link, $sql_adj);
        $numero_adj = mysqli_num_rows($resultado_adj);
        for($i=1; $i<=$numero_adj; $i++)
            {
            $fila = mysqli_fetch_array($resultado_adj);
            $idc = $fila['IDCORREO'];
            if(!isset($adjuntos_por_correo[$idc]))
                $adjuntos_por_correo[$idc] = array();
            $adjuntos_por_correo[$idc][] = array(
                'CODIGO'            => $fila['CODIGO'],
                'CODIGOFINCA'       => $fila['CODIGOFINCA'],
                'CODIGOCONSOLIDADO' => $fila['CODIGOCONSOLIDADO'],
                'NOMBREARCHIVO'     => $fila['NOMBREARCHIVO'],
                'MIMETYPE'          => $fila['MIMETYPE'],
                'TAMANOBYTES'       => $fila['TAMANOBYTES']
                );
            }
        }

    $html = '<table class="grid_correos">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 5%; cursor:pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.indicador_orden("CODIGO", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 14%; text-align:center; cursor:pointer;" onclick="ordenar_por(\'CODIGOFINCA\')">MARCA'.indicador_orden("CODIGOFINCA", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 15%;">FINCA</th>';
    $html .= '<th style="width: 5%;">FULLES</th>';
    // Columna sin header (25%) donde va el asunto en la fila de correo
    // y el nombre de archivo en la fila de adjunto.
    $html .= '<th style="width: 25%;"></th>';
    $html .= '<th style="width: 108px; cursor:pointer;" onclick="ordenar_por(\'FECHAHORA\')">FH REC'.indicador_orden("FECHAHORA", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 30px; font-size:10px; cursor:pointer;" onclick="ordenar_por(\'ESTADO\')">E'.indicador_orden("ESTADO", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 95px; text-align:center;">OPC</th>';
    $html .= '</tr></thead>';
   
    for($i=1; $i<=$numero_correos; $i++)
        {
        $codigo    = $arreglo_correos[$i]['CODIGO'];
        $finca     = ($arreglo_correos[$i]['CODIGOFINCA'] === null || $arreglo_correos[$i]['CODIGOFINCA'] === '') ? '&mdash;' : htmlspecialchars($arreglo_correos[$i]['CODIGOFINCA'], ENT_QUOTES, 'UTF-8');
        $cons      = ($arreglo_correos[$i]['CODIGOCONSOLIDADO'] === null || $arreglo_correos[$i]['CODIGOCONSOLIDADO'] === '') ? '&mdash;' : htmlspecialchars($arreglo_correos[$i]['CODIGOCONSOLIDADO'], ENT_QUOTES, 'UTF-8');
        $fechahora = '&mdash;';
        $fh_raw    = (string)$arreglo_correos[$i]['FECHAHORA'];
        if($fh_raw != "" && $fh_raw != "0000-00-00 00:00:00")
            {
            $fh_ts = strtotime($fh_raw);
            if($fh_ts !== false)
                $fechahora = date('m', $fh_ts).'-'.date('d', $fh_ts).' '.date('H:i:s', $fh_ts);
            }
        $de_limpio   = limpia_email((string)$arreglo_correos[$i]['DE']);
        $para_limpio = limpia_email((string)$arreglo_correos[$i]['PARA']);
        $tooltip_departa = htmlspecialchars("De: ".$de_limpio."\nPara: ".$para_limpio, ENT_QUOTES, 'UTF-8');
        $tooltip_departa = str_replace("\n", "&#10;", $tooltip_departa);
        $asunto    = htmlspecialchars((string)$arreglo_correos[$i]['ASUNTO'], ENT_QUOTES, 'UTF-8');
        $asunto_js = htmlspecialchars(addslashes((string)$arreglo_correos[$i]['ASUNTO']), ENT_QUOTES, 'UTF-8');
        $estado    = htmlspecialchars((string)$arreglo_correos[$i]['ESTADO'], ENT_QUOTES, 'UTF-8');
        $obs       = ($arreglo_correos[$i]['OBSERVACIONES'] === null || $arreglo_correos[$i]['OBSERVACIONES'] === '') ? '&mdash;' : htmlspecialchars($arreglo_correos[$i]['OBSERVACIONES'], ENT_QUOTES, 'UTF-8');
  
        $est_14   = 'background-color:#f2f2f2; font-size:14px;';
        $est_12   = 'background-color:#f2f2f2; font-size:12px;';
        $est_11   = 'background-color:#f2f2f2; font-size:11px;';
        $est_asun = 'background-color:#f2f2f2; font-size:13px; font-weight:normal; font-style:italic; color:#333;';
        $est_obs  = 'background-color:#f2f2f2; font-size:14px; font-weight:normal; font-style:normal; color:#333;';

        $html .= '<tbody id="id_grupo_correo_'.$codigo.'" class="grupo_correo" onclick="devuelve_correo('.$codigo.');">';
        $html .= '<tr>';
        $html .= '<td class="td_centro" style="'.$est_14.'"><i class="icon-mail" title="'.$codigo.'" style="color:#c97b85; font-size:13px;"></i></td>';
        $html .= '<td class="td_centro" style="'.$est_14.'"><strong>'.$finca.'</strong></td>';
        $html .= '<td colspan="3" style="'.$est_asun.'">'.$asunto.'</td>';
        $html .= '<td class="td_centro" style="'.$est_14.'"><strong>'.$fechahora.'</strong></td>';
        $html .= '<td class="td_centro" style="background-color:#f2f2f2; font-size:10px;">'.$estado.'</td>';
        $html .= '<td class="td_opc" style="'.$est_14.' text-align:right;">';
         // Icono lapiz (editar) removido: el panel derecho con el formulario ya no se muestra.
        $html .= '<a href="javascript: muestra_trazabilidad_correo('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="#" onclick="ver_cuerpo_correo('.$codigo.', \''.$asunto_js.'\'); return false;" title="Ver cuerpo del correo"><i class="icon-mail fg-darkRed"></i></a>';
        $html .= '<a href="javascript:void(0);" class="tooltip_correo" data-tooltip="'.$tooltip_departa.'" onclick="return false;"><i class="icon-user-2" style="color:#155a60;"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';

        // Filas de adjuntos del correo (si tiene).
        $idc = $arreglo_correos[$i]['IDCORREO'];
        if(isset($adjuntos_por_correo[$idc]))
            {
            $lista_adj = $adjuntos_por_correo[$idc];
            $numero_adj_correo = count($lista_adj);
            for($k=1; $k<=$numero_adj_correo; $k++)
                {
                $adj = $lista_adj[$k-1];
                $adj_codigo = $adj['CODIGO'];
                $adj_finca  = ($adj['CODIGOFINCA'] === null || $adj['CODIGOFINCA'] === '') ? '&mdash;' : htmlspecialchars($adj['CODIGOFINCA'], ENT_QUOTES, 'UTF-8');
                $adj_cons   = ($adj['CODIGOCONSOLIDADO'] === null || $adj['CODIGOCONSOLIDADO'] === '') ? '&mdash;' : htmlspecialchars($adj['CODIGOCONSOLIDADO'], ENT_QUOTES, 'UTF-8');
                $adj_nombre = htmlspecialchars((string)$adj['NOMBREARCHIVO'], ENT_QUOTES, 'UTF-8');
                $adj_nombre_js = htmlspecialchars(addslashes((string)$adj['NOMBREARCHIVO']), ENT_QUOTES, 'UTF-8');
                $adj_mime   = (string)$adj['MIMETYPE']; 
                $adj_ext    = strtolower(pathinfo((string)$adj['NOMBREARCHIVO'], PATHINFO_EXTENSION));

                if(stripos($adj_mime, 'pdf') !== false || $adj_ext == 'pdf')
                    {
                    $adj_tipo  = 'PDF';
                    $adj_visor_titulo_base = 'Ver adjunto';
                    $adj_visor_template = '<a href="#" onclick="ver_adjunto_pdf('.$adj_codigo.', \''.$adj_nombre_js.'\'); return false;" title="VISOR_TITLE"><i class="icon-file-pdf" style="color:#88010e; background:#fff;"></i></a>';
                    $adj_nombre_link = '<a href="#" onclick="ver_adjunto_pdf('.$adj_codigo.', \''.$adj_nombre_js.'\'); return false;" title="'.$adj_nombre.'" style="color:#003366; text-decoration:underline; cursor:pointer;">'.$adj_nombre.'</a>';
                    }
                else if(stripos($adj_mime, 'spreadsheet') !== false || $adj_ext == 'xlsx' || $adj_ext == 'xls')
                    {
                    $adj_tipo  = 'EXCEL';
                    $adj_visor_titulo_base = 'Descargar adjunto';
                    $adj_visor_template = '<a target="_blank" href="ver_adjunto.php?codigo='.$adj_codigo.'" title="VISOR_TITLE"><i class="icon-file-excel" style="color:#006400; background:#fff;"></i></a>';
                    $adj_nombre_link = '<a target="_blank" href="ver_adjunto.php?codigo='.$adj_codigo.'" title="'.$adj_nombre.'" style="color:#003366; text-decoration:underline; cursor:pointer;">'.$adj_nombre.'</a>';
                    }
                else
                    {
                    $adj_tipo  = strtoupper($adj_ext);
                    $adj_visor_titulo_base = 'Ver adjunto';
                    $adj_visor_template = '<a target="_blank" href="ver_adjunto.php?codigo='.$adj_codigo.'" title="VISOR_TITLE"><i class="icon-file" style="color:#666; background:#fff;"></i></a>';
                    $adj_nombre_link = '<a target="_blank" href="ver_adjunto.php?codigo='.$adj_codigo.'" title="'.$adj_nombre.'" style="color:#003366; text-decoration:underline; cursor:pointer;">'.$adj_nombre.'</a>';
                    }
                $adj_tamano = number_format(((float)$adj['TAMANOBYTES']) / 1024, 1) . ' KB';

                $est_adj = 'background-color:rgba(195,195,195,0.4); color:#000; font-size:13px; font-weight:normal;';

                // Determinar las celdas de FINCA/CONS/TAMANO segun si esta procesado.
                // Cuando ya esta procesado, mostrar datos de factura_finca; sino,
                // mostrar guiones y tamano normales.
                $proc = isset($adjuntos_procesados[(int)$adj_codigo]) ? $adjuntos_procesados[(int)$adj_codigo] : null;

                $celda_finca  = $adj_finca;   // por defecto: "—" o codigo_finca de archivo_correo
                $celda_cons   = $adj_cons;    // por defecto: "—" o codigo_consolidado de archivo_correo
                $celda_tam    = $adj_tamano;  // por defecto: "38.8 KB"
                $celda_fulles = '';           // TOTALCAJASEQUIVALENTES solo cuando esta procesado
                $visor_title  = $adj_visor_titulo_base;

                if($proc !== null)
                    {
                    // Procesado: 2da celda = CLIENTEMARCACION, 3ra = FINCA, 4ta = FULLES, tam = GUIA.
                    $cm     = isset($proc["CLIENTEMARCACION"]) ? trim((string)$proc["CLIENTEMARCACION"]) : '';
                    $fn     = isset($proc["FINCA"])            ? trim((string)$proc["FINCA"])            : '';
                    $guia   = isset($proc["GUIA"])             ? trim((string)$proc["GUIA"])             : '';
                    $fulles = isset($proc["FULLES"])           ? $proc["FULLES"]                          : null;
                    if($cm   !== '') $celda_finca = htmlspecialchars($cm, ENT_QUOTES, 'UTF-8');
                    if($fn   !== '') $celda_cons  = htmlspecialchars($fn, ENT_QUOTES, 'UTF-8');
                    if($guia !== '') $celda_tam   = htmlspecialchars($guia, ENT_QUOTES, 'UTF-8');
                    // FULLES: si es null o 0, dejar vacio.
                    if($fulles !== null && (float)$fulles > 0)
                        $celda_fulles = htmlspecialchars((string)$fulles, ENT_QUOTES, 'UTF-8');
                    // Visor con tamano en el title para no perder el dato del KB.
                    $visor_title = $adj_visor_titulo_base.' ('.$adj_tamano.')';
                    }
                $adj_visor = str_replace('VISOR_TITLE', $visor_title, $adj_visor_template);

                $html .= '<tr class="fila_adjunto">';
                $html .= '<td class="td_centro" style="'.$est_adj.' box-shadow:none;"><i class="icon-arrow-right-2" title="'.$adj_codigo.'" style="color:#7fa7c9;"></i></td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_finca.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_cons.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_fulles.'</td>';
                $html .= '<td style="'.$est_adj.'">'.$adj_nombre_link.'</td>';
                $html .= '<td colspan="2" class="td_centro" style="'.$est_adj.'">'.$celda_tam.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.' text-align:right;">';
                if($proc !== null)
                    {
                    // Ya procesado: icono target verde pastel, clickeable.
                    // Abre ver_factura_finca.php con el CODIGO de factura_finca en pestana nueva.
                    $codigo_ff = (int)$proc["CODIGO"];
                    $html .= '<a onclick="window.open(\'ver_factura_finca.php?codigo='.$codigo_ff.'\', \'_blank\');" title="Procesada - factura_finca CODIGO: '.$codigo_ff.'" style="cursor:pointer; color:#8fbc8f; margin-right:6px;"><i class="icon-target"></i></a>';
                    }
                else
                    {
                    // No procesado: icono code rojo crimson con onclick para procesar.
                    $html .= '<a onclick="procesar_factura('.$adj_codigo.', \''.$adj_nombre_js.'\'); return false;" title="Procesar factura con IA" style="cursor:pointer; color:#88010e; margin-right:6px;"><i class="icon-code"></i></a>';
                    }
                $html .= $adj_visor;
                $html .= '</td>';
                $html .= '</tr>';
                }
            }

        $html .= '</tbody>';
        }

    $html .= '</table>';
    $html .= '<div style="text-align:right; font-size:11px; color:#666; padding:5px;">Total: '.$numero_correos.' correos (ultimos 5 dias)</div>';
    return $html;
    }


// ============================================================================
//  Comparacion de doble extraccion (procesa_factura_test)
// ============================================================================

function compara_extracciones($json1, $json2)
    {
    $discrepancias = array();

    $campos_texto_cabecera = array("FINCA_PROVEEDOR", "RUC_PROVEEDOR", "NUMERO_FACTURA", "FECHA_FACTURACION", "CLIENTE_MARCACION");
    for($i = 0; $i < count($campos_texto_cabecera); $i++)
        {
        $campo = $campos_texto_cabecera[$i];
        $v1 = isset($json1["CABECERA"][$campo]) ? $json1["CABECERA"][$campo] : null;
        $v2 = isset($json2["CABECERA"][$campo]) ? $json2["CABECERA"][$campo] : null;
        if(!compara_texto($v1, $v2))
            $discrepancias[] = "CABECERA.".$campo.": [".$v1."] vs [".$v2."]";
        }

    if(!compara_decimal($json1["CABECERA"]["SUBTOTAL"] ?? 0, $json2["CABECERA"]["SUBTOTAL"] ?? 0, 0.01))
        $discrepancias[] = "CABECERA.SUBTOTAL: [".($json1["CABECERA"]["SUBTOTAL"] ?? "null")."] vs [".($json2["CABECERA"]["SUBTOTAL"] ?? "null")."]";
    if(!compara_decimal($json1["CABECERA"]["TOTAL"] ?? 0, $json2["CABECERA"]["TOTAL"] ?? 0, 0.01))
        $discrepancias[] = "CABECERA.TOTAL: [".($json1["CABECERA"]["TOTAL"] ?? "null")."] vs [".($json2["CABECERA"]["TOTAL"] ?? "null")."]";
    if(!compara_decimal($json1["CABECERA"]["IVA_VALOR"] ?? 0, $json2["CABECERA"]["IVA_VALOR"] ?? 0, 0.01))
        $discrepancias[] = "CABECERA.IVA_VALOR: [".($json1["CABECERA"]["IVA_VALOR"] ?? "null")."] vs [".($json2["CABECERA"]["IVA_VALOR"] ?? "null")."]";

    $campos_logistica = array("PAIS_DESTINO", "MAWB", "HAWB", "DAE");
    for($i = 0; $i < count($campos_logistica); $i++)
        {
        $campo = $campos_logistica[$i];
        $v1 = isset($json1["LOGISTICA"][$campo]) ? $json1["LOGISTICA"][$campo] : null;
        $v2 = isset($json2["LOGISTICA"][$campo]) ? $json2["LOGISTICA"][$campo] : null;
        if(!compara_texto($v1, $v2))
            $discrepancias[] = "LOGISTICA.".$campo.": [".$v1."] vs [".$v2."]";
        }

    if(!compara_decimal($json1["RESUMEN_EMPAQUE"]["TOTAL_CAJAS_EQUIVALENTES"] ?? 0, $json2["RESUMEN_EMPAQUE"]["TOTAL_CAJAS_EQUIVALENTES"] ?? 0, 0.001))
        $discrepancias[] = "RESUMEN_EMPAQUE.TOTAL_CAJAS_EQUIVALENTES: [".($json1["RESUMEN_EMPAQUE"]["TOTAL_CAJAS_EQUIVALENTES"] ?? "null")."] vs [".($json2["RESUMEN_EMPAQUE"]["TOTAL_CAJAS_EQUIVALENTES"] ?? "null")."]";
    if(($json1["RESUMEN_EMPAQUE"]["TOTAL_RAMOS"] ?? 0) != ($json2["RESUMEN_EMPAQUE"]["TOTAL_RAMOS"] ?? 0))
        $discrepancias[] = "RESUMEN_EMPAQUE.TOTAL_RAMOS: [".($json1["RESUMEN_EMPAQUE"]["TOTAL_RAMOS"] ?? "null")."] vs [".($json2["RESUMEN_EMPAQUE"]["TOTAL_RAMOS"] ?? "null")."]";
    if(($json1["RESUMEN_EMPAQUE"]["TOTAL_TALLOS"] ?? 0) != ($json2["RESUMEN_EMPAQUE"]["TOTAL_TALLOS"] ?? 0))
        $discrepancias[] = "RESUMEN_EMPAQUE.TOTAL_TALLOS: [".($json1["RESUMEN_EMPAQUE"]["TOTAL_TALLOS"] ?? "null")."] vs [".($json2["RESUMEN_EMPAQUE"]["TOTAL_TALLOS"] ?? "null")."]";

    $cajas1 = isset($json1["CAJAS"]) ? $json1["CAJAS"] : array();
    $cajas2 = isset($json2["CAJAS"]) ? $json2["CAJAS"] : array();
    if(count($cajas1) != count($cajas2))
        {
        $discrepancias[] = "CAJAS.cantidad: [".count($cajas1)."] vs [".count($cajas2)."]";
        return $discrepancias;
        }

    $lineas1 = aplana_lineas($cajas1);
    $lineas2 = aplana_lineas($cajas2);

    if(count($lineas1) != count($lineas2))
        {
        $discrepancias[] = "DETALLE.cantidad_lineas: [".count($lineas1)."] vs [".count($lineas2)."]";
        return $discrepancias;
        }

    for($i = 0; $i < count($lineas1); $i++)
        {
        $l1 = $lineas1[$i];
        $l2 = $lineas2[$i];
        if(!compara_texto($l1["VARIEDAD_NORM"], $l2["VARIEDAD_NORM"]))
            $discrepancias[] = "LINEA[".$i."].VARIEDAD: [".$l1["VARIEDAD"]."] vs [".$l2["VARIEDAD"]."]";
        if(!compara_texto($l1["PRODUCTO"] ?? null, $l2["PRODUCTO"] ?? null))
            $discrepancias[] = "LINEA[".$i."].PRODUCTO: [".($l1["PRODUCTO"] ?? "null")."] vs [".($l2["PRODUCTO"] ?? "null")."]";
        if(($l1["LARGO"] ?? null) != ($l2["LARGO"] ?? null))
            $discrepancias[] = "LINEA[".$i."].LARGO: [".($l1["LARGO"] ?? "null")."] vs [".($l2["LARGO"] ?? "null")."]";
        if(!compara_texto($l1["GRADO"] ?? null, $l2["GRADO"] ?? null))
            $discrepancias[] = "LINEA[".$i."].GRADO: [".($l1["GRADO"] ?? "null")."] vs [".($l2["GRADO"] ?? "null")."]";
        if(($l1["TALLOS_POR_RAMO"] ?? 0) != ($l2["TALLOS_POR_RAMO"] ?? 0))
            $discrepancias[] = "LINEA[".$i."].TALLOS_POR_RAMO: [".($l1["TALLOS_POR_RAMO"] ?? "null")."] vs [".($l2["TALLOS_POR_RAMO"] ?? "null")."]";
        if(($l1["RAMOS"] ?? 0) != ($l2["RAMOS"] ?? 0))
            $discrepancias[] = "LINEA[".$i."].RAMOS: [".($l1["RAMOS"] ?? "null")."] vs [".($l2["RAMOS"] ?? "null")."]";
        if(($l1["TALLOS_TOTAL"] ?? 0) != ($l2["TALLOS_TOTAL"] ?? 0))
            $discrepancias[] = "LINEA[".$i."].TALLOS_TOTAL: [".($l1["TALLOS_TOTAL"] ?? "null")."] vs [".($l2["TALLOS_TOTAL"] ?? "null")."]";
        if(!compara_decimal($l1["PRECIO_UNITARIO"] ?? 0, $l2["PRECIO_UNITARIO"] ?? 0, 0.0001))
            $discrepancias[] = "LINEA[".$i."].PRECIO_UNITARIO: [".($l1["PRECIO_UNITARIO"] ?? "null")."] vs [".($l2["PRECIO_UNITARIO"] ?? "null")."]";
        if(!compara_decimal($l1["PRECIO_TOTAL"] ?? 0, $l2["PRECIO_TOTAL"] ?? 0, 0.01))
            $discrepancias[] = "LINEA[".$i."].PRECIO_TOTAL: [".($l1["PRECIO_TOTAL"] ?? "null")."] vs [".($l2["PRECIO_TOTAL"] ?? "null")."]";
        }

    return $discrepancias;
    }


function compara_texto($v1, $v2)
    {
    if($v1 === null && $v2 === null)
        return true;
    if($v1 === null || $v2 === null)
        return false;
    return strtoupper(trim((string)$v1)) === strtoupper(trim((string)$v2));
    }


function compara_decimal($v1, $v2, $tolerancia)
    {
    $n1 = is_numeric($v1) ? (float)$v1 : 0;
    $n2 = is_numeric($v2) ? (float)$v2 : 0;
    return abs($n1 - $n2) <= $tolerancia;
    }


function normaliza_variedad($texto)
    {
    $t = strtoupper(trim((string)$texto));
    $t = str_replace("X-PRESSION", "XPRESSION", $t);
    $t = str_replace("O HARA", "OHARA", $t);
    $t = preg_replace("/\\s+/", " ", $t);
    return $t;
    }


function aplana_lineas($cajas)
    {
    $lineas = array();
    for($i = 0; $i < count($cajas); $i++)
        {
        $contenido = isset($cajas[$i]["CONTENIDO"]) ? $cajas[$i]["CONTENIDO"] : array();
        for($j = 0; $j < count($contenido); $j++)
            {
            $l = $contenido[$j];
            $l["VARIEDAD_NORM"] = normaliza_variedad($l["VARIEDAD"] ?? "");
            $lineas[] = $l;
            }
        }

    usort($lineas, function($a, $b)
        {
        $cmp = strcmp($a["VARIEDAD_NORM"], $b["VARIEDAD_NORM"]);
        if($cmp != 0)
            return $cmp;
        $cmp = (($a["LARGO"] ?? 0) - ($b["LARGO"] ?? 0));
        if($cmp != 0)
            return $cmp;
        return (float)($a["PRECIO_UNITARIO"] ?? 0) <=> (float)($b["PRECIO_UNITARIO"] ?? 0);
        });

    return $lineas;
    }


function normaliza_decimales(&$dato, $decimales = 4)
    {
    if(is_array($dato))
        {
        foreach($dato as $k => $v)
            {
            normaliza_decimales($dato[$k], $decimales);
            }
        }
    elseif(is_float($dato))
        {
        $dato = round($dato, $decimales);
        }
    }


function limpia_json_decimales(&$dato)
    {
    $campos_decimales = array(
        "PRECIO_UNITARIO", "PRECIO_TOTAL", "SUBTOTAL", "IVA_VALOR",
        "DESCUENTO", "TOTAL", "CARGO_FLETE", "CARGO_CAJAS", "CARGO_OTROS",
        "TOTAL_CAJAS_EQUIVALENTES", "PESO_BRUTO_KG", "PESO_NETO_KG"
        );
    if(is_array($dato))
        {
        foreach($dato as $clave => $valor)
            {
            if(in_array($clave, $campos_decimales, true) && is_numeric($valor))
                {
                $dato[$clave] = round((float)$valor, 4);
                }
            elseif(is_array($valor))
                {
                limpia_json_decimales($dato[$clave]);
                }
            }
        }
    }


// ============================================================================
// CLIENTES - consola nueva (_dsft). Independiente del legacy en funciones.php.
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_cliente($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de clientes (HTML completo: thead + tbody + total).
function lista_clientes_dsft($campo_orden = "NOMBRECLIENTE", $direccion_orden = "ASC")
    {
    global $link;

    // Validar campo y direccion contra lista blanca.
    // NOMBREPAIS se ordena por el alias del LEFT JOIN con pais.
    $campos_permitidos = array(1=>"CODIGO", 2=>"NOMBRECLIENTE", 3=>"CORREOFACTURAS", 4=>"TELEFONO", 5=>"NOMBREPAIS", 6=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "NOMBRECLIENTE";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "DESC") ? "DESC" : "ASC";

    $sql = "SELECT cliente.CODIGO         AS CODIGO,
        cliente.NOMBRECLIENTE  AS NOMBRECLIENTE,
        cliente.NOMBRECORTO    AS NOMBRECORTO,
        cliente.CORREOFACTURAS AS CORREOFACTURAS,
        cliente.TELEFONO       AS TELEFONO,
        cliente.CIUDAD         AS CIUDAD,
        pais.nombre_pais       AS NOMBREPAIS,
        cliente.ESTADO         AS ESTADO
        FROM cliente
        LEFT JOIN pais ON cliente.CODIGOPAIS = pais.codigo_pais
        WHERE cliente.ESTADO >= 0
        ORDER BY ".$orden_valido." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=0; $i<$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']         = $fila['CODIGO'];
        $arreglo[$i]['NOMBRECLIENTE']  = $fila['NOMBRECLIENTE'];
        $arreglo[$i]['NOMBRECORTO']    = $fila['NOMBRECORTO'];
        $arreglo[$i]['CORREOFACTURAS'] = $fila['CORREOFACTURAS'];
        $arreglo[$i]['TELEFONO']       = $fila['TELEFONO'];
        $arreglo[$i]['CIUDAD']         = $fila['CIUDAD'];
        $arreglo[$i]['NOMBREPAIS']     = $fila['NOMBREPAIS'];
        $arreglo[$i]['ESTADO']         = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo   = _ind_orden_cliente("CODIGO",         $orden_valido, $direccion_valida);
    $ind_nombre   = _ind_orden_cliente("NOMBRECLIENTE",  $orden_valido, $direccion_valida);
    $ind_correo   = _ind_orden_cliente("CORREOFACTURAS", $orden_valido, $direccion_valida);
    $ind_telefono = _ind_orden_cliente("TELEFONO",       $orden_valido, $direccion_valida);
    $ind_pais     = _ind_orden_cliente("NOMBREPAIS",     $orden_valido, $direccion_valida);
    $ind_estado   = _ind_orden_cliente("ESTADO",         $orden_valido, $direccion_valida);

    $html  = '<table class="grid_clientes">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 5%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 30%; cursor: pointer;" onclick="ordenar_por(\'NOMBRECLIENTE\')">NOMBRE'.$ind_nombre.'</th>';
    $html .= '<th style="width: 20%; cursor: pointer;" onclick="ordenar_por(\'CORREOFACTURAS\')">MAIL FACT'.$ind_correo.'</th>';
    $html .= '<th style="width: 13%; cursor: pointer;" onclick="ordenar_por(\'TELEFONO\')">TELEFONO'.$ind_telefono.'</th>';
    $html .= '<th style="width: 12%; cursor: pointer;" onclick="ordenar_por(\'NOMBREPAIS\')">PAIS'.$ind_pais.'</th>';
    $html .= '<th style="width: 7%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 13%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=0; $i<$numero; $i++)
        {
        $codigo   = (int)$arreglo[$i]['CODIGO'];
        $nombre   = htmlspecialchars((string)$arreglo[$i]['NOMBRECLIENTE'], ENT_QUOTES, 'UTF-8');
        $correo   = htmlspecialchars((string)$arreglo[$i]['CORREOFACTURAS'], ENT_QUOTES, 'UTF-8');
        $telefono = htmlspecialchars((string)$arreglo[$i]['TELEFONO'], ENT_QUOTES, 'UTF-8');
        $pais     = htmlspecialchars((string)(isset($arreglo[$i]['NOMBREPAIS']) ? $arreglo[$i]['NOMBREPAIS'] : ''), ENT_QUOTES, 'UTF-8');
        $estado_n = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_cliente" id="id_grupo_cliente_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$nombre.'" onclick="devuelve_cliente('.$codigo.');"><strong>'.$nombre.'</strong></td>';
        $html .= '<td title="'.$correo.'" onclick="devuelve_cliente('.$codigo.');">'.$correo.'</td>';
        $html .= '<td onclick="devuelve_cliente('.$codigo.');">'.$telefono.'</td>';
        $html .= '<td onclick="devuelve_cliente('.$codigo.');">'.$pais.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: muestra_trazabilidad_cliente('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: devuelve_cliente('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_cliente_dsft('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve un cliente como JSON para llenar el formulario.
function devuelve_cliente_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, NOMBRECLIENTE, NOMBRECORTO, CORREOFACTURAS, CORREOESTADOSCUENTA,
        TELEFONO, DIRECCION, CIUDAD, CODIGOPAIS, OBSERVACIONES, ESTADO
        FROM cliente
        WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Cliente no encontrado"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']              = $fila['CODIGO'];
    $respuesta['NOMBRECLIENTE']       = $fila['NOMBRECLIENTE'];
    $respuesta['NOMBRECORTO']         = $fila['NOMBRECORTO'];
    $respuesta['CORREOFACTURAS']      = $fila['CORREOFACTURAS'];
    $respuesta['CORREOESTADOSCUENTA'] = $fila['CORREOESTADOSCUENTA'];
    $respuesta['TELEFONO']            = $fila['TELEFONO'];
    $respuesta['DIRECCION']           = $fila['DIRECCION'];
    $respuesta['CIUDAD']              = $fila['CIUDAD'];
    $respuesta['CODIGOPAIS']          = $fila['CODIGOPAIS'];
    $respuesta['OBSERVACIONES']       = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']              = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0. Eliminacion logica via ESTADO = -1.
function graba_cliente_dsft($codigo, $nombrecliente, $nombrecorto, $correofacturas, $correoestadoscuenta, $telefono, $direccion, $ciudad, $observaciones, $estado, $codigo_usuario, $codigopais)
    {
    global $link;

    // Validacion identica al cliente JS.
    $nombrecliente = strtoupper(trim((string)$nombrecliente));
    if($nombrecliente == "")
        return "Por favor ingrese el NOMBRE del cliente";

    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    $estado         = (int)$estado;
    $codigopais     = (int)$codigopais;
    $valor_codigopais = ($codigopais == 0) ? "NULL" : $codigopais;

    // Escape de strings (sobrescribir misma variable).
    $nombrecliente       = mysqli_real_escape_string($link, $nombrecliente);
    $nombrecorto         = mysqli_real_escape_string($link, strtoupper(trim((string)$nombrecorto)));
    $correofacturas      = mysqli_real_escape_string($link, trim((string)$correofacturas));
    $correoestadoscuenta = mysqli_real_escape_string($link, trim((string)$correoestadoscuenta));
    $telefono            = mysqli_real_escape_string($link, trim((string)$telefono));
    $direccion           = mysqli_real_escape_string($link, strtoupper(trim((string)$direccion)));
    $ciudad              = mysqli_real_escape_string($link, strtoupper(trim((string)$ciudad)));
    $observaciones       = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    if($codigo == 0)
        {
        $sql = "INSERT INTO cliente (
            CODIGO, NOMBRECLIENTE, NOMBRECORTO, CORREOFACTURAS, CORREOESTADOSCUENTA,
            TELEFONO, DIRECCION, CIUDAD, CODIGOPAIS, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            0, '".$nombrecliente."', '".$nombrecorto."', '".$correofacturas."', '".$correoestadoscuenta."',
            '".$telefono."', '".$direccion."', '".$ciudad."', ".$valor_codigopais.", '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        }
    else
        {
        $sql = "UPDATE cliente SET
            NOMBRECLIENTE       = '".$nombrecliente."',
            NOMBRECORTO         = '".$nombrecorto."',
            CORREOFACTURAS      = '".$correofacturas."',
            CORREOESTADOSCUENTA = '".$correoestadoscuenta."',
            TELEFONO            = '".$telefono."',
            DIRECCION           = '".$direccion."',
            CIUDAD              = '".$ciudad."',
            CODIGOPAIS          = ".$valor_codigopais.",
            OBSERVACIONES       = '".$observaciones."',
            ESTADO              = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION   = NOW()
            WHERE CODIGO = ".$codigo;
        }

    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_cliente_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE cliente SET
        ESTADO              = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION   = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Devuelve HTML formateado con la trazabilidad (quien registro/modifico, cuando).
function trazabilidad_cliente_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "SELECT CODIGO, NOMBRECLIENTE,
        CODIGOUSUARIOREGISTRA, FECHAREGISTRO,
        CODIGOUSUARIOMODIFICA, FECHAMODIFICACION
        FROM cliente WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return "Cliente no encontrado";

    $fila = mysqli_fetch_array($resultado);

    $usuario_reg = (isset($fila['CODIGOUSUARIOREGISTRA']) && $fila['CODIGOUSUARIOREGISTRA'] !== null) ? $fila['CODIGOUSUARIOREGISTRA'] : "N/A";
    $fecha_reg   = (isset($fila['FECHAREGISTRO'])         && $fila['FECHAREGISTRO']         !== null) ? $fila['FECHAREGISTRO']         : "N/A";
    $usuario_mod = (isset($fila['CODIGOUSUARIOMODIFICA']) && $fila['CODIGOUSUARIOMODIFICA'] !== null) ? $fila['CODIGOUSUARIOMODIFICA'] : "N/A";
    $fecha_mod   = (isset($fila['FECHAMODIFICACION'])     && $fila['FECHAMODIFICACION']     !== null) ? $fila['FECHAMODIFICACION']     : "N/A";

    $html  = '<div style="font-size: 12px; line-height: 1.7;">';
    $html .= '<b>Cliente:</b> ('.$fila['CODIGO'].') '.htmlspecialchars((string)$fila['NOMBRECLIENTE'], ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Registrado por usuario:</b> '.htmlspecialchars((string)$usuario_reg, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha registro:</b> '.htmlspecialchars((string)$fecha_reg, ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Ultima modificacion por usuario:</b> '.htmlspecialchars((string)$usuario_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha modificacion:</b> '.htmlspecialchars((string)$fecha_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '</div>';
    return $html;
    }


// ============================================================================
// TRUCKS - consola nueva (_dsft).
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_truck($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de trucks (HTML completo: thead + tbody + total).
function lista_trucks_dsft($campo_orden = "NOMBRETRUCK", $direccion_orden = "ASC")
    {
    global $link;

    // Validar campo y direccion contra lista blanca.
    $campos_permitidos = array(1=>"CODIGO", 2=>"NOMBRETRUCK", 3=>"CORREOTRUCK", 4=>"TELEFONO", 5=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "NOMBRETRUCK";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "DESC") ? "DESC" : "ASC";

    $sql = "SELECT CODIGO, NOMBRETRUCK, CORREOTRUCK, TELEFONO, ESTADO
        FROM truck
        WHERE ESTADO >= 0
        ORDER BY ".$orden_valido." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=0; $i<$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']      = $fila['CODIGO'];
        $arreglo[$i]['NOMBRETRUCK'] = $fila['NOMBRETRUCK'];
        $arreglo[$i]['CORREOTRUCK'] = $fila['CORREOTRUCK'];
        $arreglo[$i]['TELEFONO']    = $fila['TELEFONO'];
        $arreglo[$i]['ESTADO']      = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo   = _ind_orden_truck("CODIGO",      $orden_valido, $direccion_valida);
    $ind_nombre   = _ind_orden_truck("NOMBRETRUCK", $orden_valido, $direccion_valida);
    $ind_correo   = _ind_orden_truck("CORREOTRUCK", $orden_valido, $direccion_valida);
    $ind_telefono = _ind_orden_truck("TELEFONO",    $orden_valido, $direccion_valida);
    $ind_estado   = _ind_orden_truck("ESTADO",      $orden_valido, $direccion_valida);

    $html  = '<table class="grid_trucks">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 6%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 30%; cursor: pointer;" onclick="ordenar_por(\'NOMBRETRUCK\')">NOMBRE'.$ind_nombre.'</th>';
    $html .= '<th style="width: 25%; cursor: pointer;" onclick="ordenar_por(\'CORREOTRUCK\')">CORREO'.$ind_correo.'</th>';
    $html .= '<th style="width: 15%; cursor: pointer;" onclick="ordenar_por(\'TELEFONO\')">TELEFONO'.$ind_telefono.'</th>';
    $html .= '<th style="width: 8%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 16%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=0; $i<$numero; $i++)
        {
        $codigo   = (int)$arreglo[$i]['CODIGO'];
        $nombre   = htmlspecialchars((string)$arreglo[$i]['NOMBRETRUCK'], ENT_QUOTES, 'UTF-8');
        $correo   = htmlspecialchars((string)$arreglo[$i]['CORREOTRUCK'], ENT_QUOTES, 'UTF-8');
        $telefono = htmlspecialchars((string)$arreglo[$i]['TELEFONO'], ENT_QUOTES, 'UTF-8');
        $estado_n = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_truck" id="id_grupo_truck_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$nombre.'" onclick="devuelve_truck('.$codigo.');"><strong>'.$nombre.'</strong></td>';
        $html .= '<td title="'.$correo.'" onclick="devuelve_truck('.$codigo.');">'.$correo.'</td>';
        $html .= '<td onclick="devuelve_truck('.$codigo.');">'.$telefono.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: muestra_trazabilidad_truck('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: devuelve_truck('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_truck_dsft('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve un truck como JSON para llenar el formulario.
function devuelve_truck_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, NOMBRETRUCK, CORREOTRUCK, TELEFONO, OBSERVACIONES, ESTADO
        FROM truck
        WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Truck no encontrado"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']        = $fila['CODIGO'];
    $respuesta['NOMBRETRUCK']   = $fila['NOMBRETRUCK'];
    $respuesta['CORREOTRUCK']   = $fila['CORREOTRUCK'];
    $respuesta['TELEFONO']      = $fila['TELEFONO'];
    $respuesta['OBSERVACIONES'] = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']        = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0.
function graba_truck_dsft($codigo, $nombretruck, $correotruck, $telefono, $observaciones, $estado, $codigo_usuario)
    {
    global $link;

    // Validacion identica al cliente JS.
    $nombretruck = strtoupper(trim((string)$nombretruck));
    if($nombretruck == "")
        return "Por favor ingrese el NOMBRE del truck";

    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    $estado         = (int)$estado;

    // Escape de strings (sobrescribir misma variable).
    $nombretruck   = mysqli_real_escape_string($link, $nombretruck);
    $correotruck   = mysqli_real_escape_string($link, trim((string)$correotruck));
    $telefono      = mysqli_real_escape_string($link, trim((string)$telefono));
    $observaciones = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    if($codigo == 0)
        {
        $sql = "INSERT INTO truck (
            CODIGO, NOMBRETRUCK, CORREOTRUCK, TELEFONO, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            0, '".$nombretruck."', '".$correotruck."', '".$telefono."', '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        }
    else
        {
        $sql = "UPDATE truck SET
            NOMBRETRUCK         = '".$nombretruck."',
            CORREOTRUCK         = '".$correotruck."',
            TELEFONO            = '".$telefono."',
            OBSERVACIONES       = '".$observaciones."',
            ESTADO              = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION   = NOW()
            WHERE CODIGO = ".$codigo;
        }

    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_truck_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE truck SET
        ESTADO              = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION   = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Devuelve HTML formateado con la trazabilidad (quien registro/modifico, cuando).
function trazabilidad_truck_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "SELECT CODIGO, NOMBRETRUCK,
        CODIGOUSUARIOREGISTRA, FECHAREGISTRO,
        CODIGOUSUARIOMODIFICA, FECHAMODIFICACION
        FROM truck WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return "Truck no encontrado";

    $fila = mysqli_fetch_array($resultado);

    $usuario_reg = (isset($fila['CODIGOUSUARIOREGISTRA']) && $fila['CODIGOUSUARIOREGISTRA'] !== null) ? $fila['CODIGOUSUARIOREGISTRA'] : "N/A";
    $fecha_reg   = (isset($fila['FECHAREGISTRO'])         && $fila['FECHAREGISTRO']         !== null) ? $fila['FECHAREGISTRO']         : "N/A";
    $usuario_mod = (isset($fila['CODIGOUSUARIOMODIFICA']) && $fila['CODIGOUSUARIOMODIFICA'] !== null) ? $fila['CODIGOUSUARIOMODIFICA'] : "N/A";
    $fecha_mod   = (isset($fila['FECHAMODIFICACION'])     && $fila['FECHAMODIFICACION']     !== null) ? $fila['FECHAMODIFICACION']     : "N/A";

    $html  = '<div style="font-size: 12px; line-height: 1.7;">';
    $html .= '<b>Truck:</b> ('.$fila['CODIGO'].') '.htmlspecialchars((string)$fila['NOMBRETRUCK'], ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Registrado por usuario:</b> '.htmlspecialchars((string)$usuario_reg, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha registro:</b> '.htmlspecialchars((string)$fecha_reg, ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Ultima modificacion por usuario:</b> '.htmlspecialchars((string)$usuario_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha modificacion:</b> '.htmlspecialchars((string)$fecha_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '</div>';
    return $html;
    }


// ============================================================================
// MARCACIONES - consola nueva (_dsft). LEFT JOIN a cliente y truck.
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_marcacion($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de marcaciones (HTML completo: thead + tbody + total).
function lista_marcaciones_dsft($campo_orden = "NOMBREMARCACION", $direccion_orden = "ASC")
    {
    global $link;

    // Validar campo y direccion contra lista blanca.
    // NOMBRECLIENTE y NOMBRETRUCK se ordenan por los alias del LEFT JOIN.
    $campos_permitidos = array(1=>"CODIGO", 2=>"NOMBREMARCACION", 3=>"NOMBRECLIENTE", 4=>"NOMBRETRUCK", 5=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "NOMBREMARCACION";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "DESC") ? "DESC" : "ASC";

    // Mapear el alias logico a la columna real en el ORDER BY.
    $map_orden = array(
        "CODIGO"          => "m.CODIGO",
        "NOMBREMARCACION" => "m.NOMBREMARCACION",
        "NOMBRECLIENTE"   => "c.NOMBRECLIENTE",
        "NOMBRETRUCK"     => "t.NOMBRETRUCK",
        "ESTADO"          => "m.ESTADO",
        );
    $columna_order_by = $map_orden[$orden_valido];

    $sql = "SELECT m.CODIGO          AS CODIGO,
        m.NOMBREMARCACION AS NOMBREMARCACION,
        m.CODIGOCLIENTE   AS CODIGOCLIENTE,
        m.CODIGOTRUCK     AS CODIGOTRUCK,
        m.ESTADO          AS ESTADO,
        c.NOMBRECLIENTE   AS NOMBRECLIENTE,
        t.NOMBRETRUCK     AS NOMBRETRUCK
        FROM marcacion m
        LEFT JOIN cliente c ON m.CODIGOCLIENTE = c.CODIGO
        LEFT JOIN truck   t ON m.CODIGOTRUCK   = t.CODIGO
        WHERE m.ESTADO >= 0
        ORDER BY ".$columna_order_by." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=0; $i<$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']          = $fila['CODIGO'];
        $arreglo[$i]['NOMBREMARCACION'] = $fila['NOMBREMARCACION'];
        $arreglo[$i]['NOMBRECLIENTE']   = $fila['NOMBRECLIENTE'];
        $arreglo[$i]['NOMBRETRUCK']     = $fila['NOMBRETRUCK'];
        $arreglo[$i]['ESTADO']          = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo     = _ind_orden_marcacion("CODIGO",          $orden_valido, $direccion_valida);
    $ind_marcacion  = _ind_orden_marcacion("NOMBREMARCACION", $orden_valido, $direccion_valida);
    $ind_cliente    = _ind_orden_marcacion("NOMBRECLIENTE",   $orden_valido, $direccion_valida);
    $ind_truck      = _ind_orden_marcacion("NOMBRETRUCK",     $orden_valido, $direccion_valida);
    $ind_estado     = _ind_orden_marcacion("ESTADO",          $orden_valido, $direccion_valida);

    $html  = '<table class="grid_marcaciones">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 5%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 25%; cursor: pointer;" onclick="ordenar_por(\'NOMBREMARCACION\')">MARCACION'.$ind_marcacion.'</th>';
    $html .= '<th style="width: 25%; cursor: pointer;" onclick="ordenar_por(\'NOMBRECLIENTE\')">CLIENTE'.$ind_cliente.'</th>';
    $html .= '<th style="width: 20%; cursor: pointer;" onclick="ordenar_por(\'NOMBRETRUCK\')">TRUCK'.$ind_truck.'</th>';
    $html .= '<th style="width: 8%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 17%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=0; $i<$numero; $i++)
        {
        $codigo    = (int)$arreglo[$i]['CODIGO'];
        $marcacion = htmlspecialchars((string)$arreglo[$i]['NOMBREMARCACION'], ENT_QUOTES, 'UTF-8');
        $cliente   = htmlspecialchars((string)(isset($arreglo[$i]['NOMBRECLIENTE']) ? $arreglo[$i]['NOMBRECLIENTE'] : ''), ENT_QUOTES, 'UTF-8');
        $truck     = htmlspecialchars((string)(isset($arreglo[$i]['NOMBRETRUCK'])   ? $arreglo[$i]['NOMBRETRUCK']   : ''), ENT_QUOTES, 'UTF-8');
        $estado_n  = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_marcacion" id="id_grupo_marcacion_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$marcacion.'" onclick="devuelve_marcacion('.$codigo.');"><strong>'.$marcacion.'</strong></td>';
        $html .= '<td title="'.$cliente.'" onclick="devuelve_marcacion('.$codigo.');">'.$cliente.'</td>';
        $html .= '<td title="'.$truck.'" onclick="devuelve_marcacion('.$codigo.');">'.$truck.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: muestra_trazabilidad_marcacion('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: devuelve_marcacion('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_marcacion_dsft('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve una marcacion como JSON para llenar el formulario.
function devuelve_marcacion_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, NOMBREMARCACION, CODIGOCLIENTE, CODIGOTRUCK, OBSERVACIONES, ESTADO
        FROM marcacion
        WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Marcacion no encontrada"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']          = $fila['CODIGO'];
    $respuesta['NOMBREMARCACION'] = $fila['NOMBREMARCACION'];
    $respuesta['CODIGOCLIENTE']   = $fila['CODIGOCLIENTE'];
    $respuesta['CODIGOTRUCK']     = $fila['CODIGOTRUCK'];
    $respuesta['OBSERVACIONES']   = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']          = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0. CODIGOTRUCK: 0 se guarda como NULL.
function graba_marcacion_dsft($codigo, $nombremarcacion, $codigocliente, $codigotruck, $observaciones, $estado, $codigo_usuario)
    {
    global $link;

    // Validacion identica al cliente JS.
    $nombremarcacion = strtoupper(trim((string)$nombremarcacion));
    if($nombremarcacion == "")
        return "Por favor ingrese la MARCACION";

    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    $estado         = (int)$estado;
    $codigocliente  = (int)$codigocliente;
    $codigotruck    = (int)$codigotruck;

    if($codigocliente <= 0)
        return "Por favor seleccione el CLIENTE";

    $valor_codigotruck = ($codigotruck == 0) ? "NULL" : $codigotruck;

    // Escape de strings (sobrescribir misma variable).
    $nombremarcacion = mysqli_real_escape_string($link, $nombremarcacion);
    $observaciones   = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    if($codigo == 0)
        {
        $sql = "INSERT INTO marcacion (
            CODIGO, NOMBREMARCACION, CODIGOCLIENTE, CODIGOTRUCK, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            0, '".$nombremarcacion."', ".$codigocliente.", ".$valor_codigotruck.", '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        }
    else
        {
        $sql = "UPDATE marcacion SET
            NOMBREMARCACION       = '".$nombremarcacion."',
            CODIGOCLIENTE         = ".$codigocliente.",
            CODIGOTRUCK           = ".$valor_codigotruck.",
            OBSERVACIONES         = '".$observaciones."',
            ESTADO                = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION     = NOW()
            WHERE CODIGO = ".$codigo;
        }

    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_marcacion_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE marcacion SET
        ESTADO                = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION     = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Devuelve HTML formateado con la trazabilidad (quien registro/modifico, cuando).
function trazabilidad_marcacion_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "SELECT CODIGO, NOMBREMARCACION,
        CODIGOUSUARIOREGISTRA, FECHAREGISTRO,
        CODIGOUSUARIOMODIFICA, FECHAMODIFICACION
        FROM marcacion WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return "Marcacion no encontrada";

    $fila = mysqli_fetch_array($resultado);

    $usuario_reg = (isset($fila['CODIGOUSUARIOREGISTRA']) && $fila['CODIGOUSUARIOREGISTRA'] !== null) ? $fila['CODIGOUSUARIOREGISTRA'] : "N/A";
    $fecha_reg   = (isset($fila['FECHAREGISTRO'])         && $fila['FECHAREGISTRO']         !== null) ? $fila['FECHAREGISTRO']         : "N/A";
    $usuario_mod = (isset($fila['CODIGOUSUARIOMODIFICA']) && $fila['CODIGOUSUARIOMODIFICA'] !== null) ? $fila['CODIGOUSUARIOMODIFICA'] : "N/A";
    $fecha_mod   = (isset($fila['FECHAMODIFICACION'])     && $fila['FECHAMODIFICACION']     !== null) ? $fila['FECHAMODIFICACION']     : "N/A";

    $html  = '<div style="font-size: 12px; line-height: 1.7;">';
    $html .= '<b>Marcacion:</b> ('.$fila['CODIGO'].') '.htmlspecialchars((string)$fila['NOMBREMARCACION'], ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Registrado por usuario:</b> '.htmlspecialchars((string)$usuario_reg, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha registro:</b> '.htmlspecialchars((string)$fecha_reg, ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Ultima modificacion por usuario:</b> '.htmlspecialchars((string)$usuario_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha modificacion:</b> '.htmlspecialchars((string)$fecha_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '</div>';
    return $html;
    }

// Tabla read-only de marcaciones de un cliente, usada en consola_clientes_dsft.php.
// Retorna solo las filas <tr>...</tr> (sin thead ni tbody wrapper).
// Si no hay marcaciones, retorna una sola fila con colspan informativa.
function lista_marcaciones_por_cliente_dsft($codigo_cliente)
    {
    global $link;
    $codigo_cliente = (int)$codigo_cliente;
    if($codigo_cliente <= 0)
        return '<tr><td colspan="4" class="td_vacio">Codigo de cliente invalido</td></tr>';

    $sql = "SELECT m.CODIGO          AS CODIGO,
        m.NOMBREMARCACION AS NOMBREMARCACION,
        m.CODIGOTRUCK     AS CODIGOTRUCK,
        m.ESTADO          AS ESTADO,
        t.NOMBRETRUCK     AS NOMBRETRUCK
        FROM marcacion m
        LEFT JOIN truck t ON m.CODIGOTRUCK = t.CODIGO
        WHERE m.CODIGOCLIENTE = ".$codigo_cliente."
          AND m.ESTADO >= 0
        ORDER BY m.NOMBREMARCACION ASC";
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<tr><td colspan="4" class="td_vacio">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</td></tr>';
    $numero = mysqli_num_rows($resultado);

    if($numero == 0)
        return '<tr><td colspan="4" class="td_vacio">Sin marcaciones asignadas</td></tr>';

    $html = "";
    for($i=0; $i<$numero; $i++)
        {
        $fila      = mysqli_fetch_array($resultado);
        $codigo    = (int)$fila['CODIGO'];
        $marcacion = htmlspecialchars((string)$fila['NOMBREMARCACION'], ENT_QUOTES, 'UTF-8');
        $truck     = htmlspecialchars((string)(isset($fila['NOMBRETRUCK']) ? $fila['NOMBRETRUCK'] : ''), ENT_QUOTES, 'UTF-8');
        $estado_n  = (int)$fila['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_marc_link" onclick="window.open(\'consola_marcaciones.php?codigo='.$codigo.'\', \'_blank\');">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$marcacion.'">'.$marcacion.'</td>';
        $html .= '<td title="'.$truck.'">'.$truck.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '</tr>';
        }

    return $html;
    }


// ============================================================================
// CONSOLIDADOS - consola nueva (_dsft).
// LEFT JOIN a marcacion, cliente y proveedor (agencia) para mostrar nombres
// en el grid sin queries extra por fila.
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_consolidado($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de consolidados (HTML completo: thead + tbody + total).
function lista_consolidados_dsft($campo_orden = "FECHAVUELO", $direccion_orden = "DESC", $fecha_desde = "", $fecha_hasta = "")
    {
    global $link;

    // Filtro por rango de FECHAVUELO si ambas fechas son validas (Y-m-d).
    $valida_desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_desde);
    $valida_hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_hasta);
    $where_fechas = "";
    if($valida_desde && $valida_hasta)
        {
        $fecha_desde = mysqli_real_escape_string($link, $fecha_desde);
        $fecha_hasta = mysqli_real_escape_string($link, $fecha_hasta);
        $where_fechas = " AND c.FECHAVUELO >= '".$fecha_desde."' AND c.FECHAVUELO <= '".$fecha_hasta."'";
        }

    // Validar campo y direccion contra lista blanca.
    // AGENCIA ya no es columna del grid; queda fuera de la lista de orden permitido.
    $campos_permitidos = array(1=>"CODIGO", 2=>"FECHAVUELO", 3=>"GUIA", 4=>"NOMBREMARCACION", 5=>"NOMBRECLIENTE", 6=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "FECHAVUELO";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "ASC") ? "ASC" : "DESC";

    // Mapear alias logico a la columna real del JOIN para el ORDER BY.
    $map_orden = array(
        "CODIGO"          => "c.CODIGO",
        "FECHAVUELO"      => "c.FECHAVUELO",
        "GUIA"            => "c.GUIA",
        "NOMBREMARCACION" => "m.NOMBREMARCACION",
        "NOMBRECLIENTE"   => "cl.NOMBRECLIENTE",
        "ESTADO"          => "c.ESTADO"
        );
    $columna_order_by = $map_orden[$orden_valido];

    // Se elimino el LEFT JOIN con proveedor (ya no se muestra NOMBREAGENCIA en el grid).
    $sql = "SELECT c.CODIGO          AS CODIGO,
        c.FECHAVUELO       AS FECHAVUELO,
        (SELECT GROUP_CONCAT(g.NUMEROGUIA SEPARATOR ', ')
            FROM guia_consolidado gc
            INNER JOIN guia g ON gc.CODIGOGUIA = g.CODIGO
            WHERE gc.CODIGOCONSOLIDADO = c.CODIGO) AS GUIAS_CONCAT,
        c.CODIGOMARCACION  AS CODIGOMARCACION,
        c.CODIGOCLIENTE    AS CODIGOCLIENTE,
        c.CODIGOTRUCK      AS CODIGOTRUCK,
        c.CODIGOAGENCIA    AS CODIGOAGENCIA,
        c.CODIGOPAIS       AS CODIGOPAIS,
        c.DESTINO          AS DESTINO,
        c.ESTADO           AS ESTADO,
        m.NOMBREMARCACION  AS NOMBREMARCACION,
        cl.NOMBRECLIENTE   AS NOMBRECLIENTE
        FROM consolidado c
        LEFT JOIN marcacion m  ON c.CODIGOMARCACION = m.CODIGO
        LEFT JOIN cliente   cl ON c.CODIGOCLIENTE   = cl.CODIGO
        WHERE c.ESTADO >= 0".$where_fechas."
        ORDER BY ".$columna_order_by." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=0; $i<$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']          = $fila['CODIGO'];
        $arreglo[$i]['FECHAVUELO']      = $fila['FECHAVUELO'];
        $arreglo[$i]['GUIA']            = $fila['GUIAS_CONCAT'];
        $arreglo[$i]['NOMBREMARCACION'] = $fila['NOMBREMARCACION'];
        $arreglo[$i]['NOMBRECLIENTE']   = $fila['NOMBRECLIENTE'];
        $arreglo[$i]['ESTADO']          = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo  = _ind_orden_consolidado("CODIGO",          $orden_valido, $direccion_valida);
    $ind_fecha   = _ind_orden_consolidado("FECHAVUELO",      $orden_valido, $direccion_valida);
    $ind_guia    = _ind_orden_consolidado("GUIA",            $orden_valido, $direccion_valida);
    $ind_marca   = _ind_orden_consolidado("NOMBREMARCACION", $orden_valido, $direccion_valida);
    $ind_cliente = _ind_orden_consolidado("NOMBRECLIENTE",   $orden_valido, $direccion_valida);
    $ind_estado  = _ind_orden_consolidado("ESTADO",          $orden_valido, $direccion_valida);

    // AGENCIA queda fuera del grid; GUIA es ahora la columna mas ancha (30%)
    // para que entren 2 guias concatenadas.
    $html  = '<table class="grid_consolidados">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 5%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 10%; cursor: pointer;" onclick="ordenar_por(\'FECHAVUELO\')">FECHA VUELO'.$ind_fecha.'</th>';
    $html .= '<th style="width: 30%; cursor: pointer;" onclick="ordenar_por(\'GUIA\')">GUIA'.$ind_guia.'</th>';
    $html .= '<th style="width: 18%; cursor: pointer;" onclick="ordenar_por(\'NOMBREMARCACION\')">MARCA'.$ind_marca.'</th>';
    $html .= '<th style="width: 17%; cursor: pointer;" onclick="ordenar_por(\'NOMBRECLIENTE\')">CLIENTE'.$ind_cliente.'</th>';
    $html .= '<th style="width: 7%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 13%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=0; $i<$numero; $i++)
        {
        $codigo  = (int)$arreglo[$i]['CODIGO'];
        $fecha   = htmlspecialchars((string)(isset($arreglo[$i]['FECHAVUELO']) ? $arreglo[$i]['FECHAVUELO'] : ''), ENT_QUOTES, 'UTF-8');
        $guia    = htmlspecialchars((string)(isset($arreglo[$i]['GUIA']) ? $arreglo[$i]['GUIA'] : ''), ENT_QUOTES, 'UTF-8');
        $marca   = htmlspecialchars((string)(isset($arreglo[$i]['NOMBREMARCACION']) ? $arreglo[$i]['NOMBREMARCACION'] : ''), ENT_QUOTES, 'UTF-8');
        $cliente = htmlspecialchars((string)(isset($arreglo[$i]['NOMBRECLIENTE']) ? $arreglo[$i]['NOMBRECLIENTE'] : ''), ENT_QUOTES, 'UTF-8');
        $estado_n = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_consolidado" id="id_grupo_consolidado_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td class="td_centro" onclick="devuelve_consolidado('.$codigo.');">'.$fecha.'</td>';
        $html .= '<td title="'.$guia.'" onclick="devuelve_consolidado('.$codigo.');"><strong>'.$guia.'</strong></td>';
        $html .= '<td title="'.$marca.'" onclick="devuelve_consolidado('.$codigo.');">'.$marca.'</td>';
        $html .= '<td title="'.$cliente.'" onclick="devuelve_consolidado('.$codigo.');">'.$cliente.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: muestra_trazabilidad_consolidado('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: devuelve_consolidado('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_consolidado_dsft('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve un consolidado como JSON para llenar el formulario.
function devuelve_consolidado_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, FECHAVUELO, GUIA, CODIGOMARCACION, CODIGOCLIENTE,
        CODIGOTRUCK, CODIGOAGENCIA, CODIGOPAIS, OBSERVACIONES, ESTADO
        FROM consolidado WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Consolidado no encontrado"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']          = $fila['CODIGO'];
    $respuesta['FECHAVUELO']      = $fila['FECHAVUELO'];
    $respuesta['GUIA']            = $fila['GUIA'];
    $respuesta['CODIGOMARCACION'] = $fila['CODIGOMARCACION'];
    $respuesta['CODIGOCLIENTE']   = $fila['CODIGOCLIENTE'];
    $respuesta['CODIGOTRUCK']     = $fila['CODIGOTRUCK'];
    $respuesta['CODIGOAGENCIA']   = $fila['CODIGOAGENCIA'];
    $respuesta['CODIGOPAIS']      = $fila['CODIGOPAIS'];
    $respuesta['OBSERVACIONES']   = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']          = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0. Los FK con valor 0 se guardan NULL.
// La GUIA ya NO se escribe en consolidado.GUIA -> se maneja por la tabla
// guia_consolidado (uno a muchos). La columna legacy queda en la BD pero
// no se toca desde esta consola.
function graba_consolidado_dsft($codigo, $fechavuelo, $codigomarcacion, $codigocliente, $codigotruck, $codigopais, $codigoagencia, $observaciones, $estado, $codigo_usuario)
    {
    global $link;

    // Validaciones identicas al cliente JS.
    $fechavuelo = trim((string)$fechavuelo);
    if($fechavuelo == "")
        return "Por favor ingrese la FECHA DE VUELO";
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechavuelo))
        return "FECHA DE VUELO con formato invalido (Y-m-d)";

    $codigo          = (int)$codigo;
    $codigo_usuario  = (int)$codigo_usuario;
    $estado          = (int)$estado;
    $codigomarcacion = (int)$codigomarcacion;
    $codigocliente   = (int)$codigocliente;
    $codigotruck     = (int)$codigotruck;
    $codigopais      = (int)$codigopais;
    $codigoagencia   = (int)$codigoagencia;

    if($codigomarcacion <= 0)
        return "Por favor seleccione la MARCACION";

    // FK = 0 -> NULL en SQL (excepto CODIGOMARCACION que ya validamos > 0).
    $valor_codigocliente = ($codigocliente == 0) ? "NULL" : $codigocliente;
    $valor_codigotruck   = ($codigotruck   == 0) ? "NULL" : $codigotruck;
    $valor_codigopais    = ($codigopais    == 0) ? "NULL" : $codigopais;
    $valor_codigoagencia = ($codigoagencia == 0) ? "NULL" : $codigoagencia;

    // Escape de strings (sobrescribir misma variable).
    $fechavuelo    = mysqli_real_escape_string($link, $fechavuelo);
    $observaciones = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    if($codigo == 0)
        {
        // GUIA y DESTINO quedan en la BD pero la consola no los escribe.
        $sql = "INSERT INTO consolidado (
            CODIGO, FECHAVUELO, CODIGOMARCACION, CODIGOCLIENTE, CODIGOTRUCK,
            CODIGOAGENCIA, CODIGOPAIS, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            0, '".$fechavuelo."', ".$codigomarcacion.", ".$valor_codigocliente.", ".$valor_codigotruck.",
            ".$valor_codigoagencia.", ".$valor_codigopais.", '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        }
    else
        {
        // GUIA y DESTINO no se tocan en el UPDATE.
        $sql = "UPDATE consolidado SET
            FECHAVUELO            = '".$fechavuelo."',
            CODIGOMARCACION       = ".$codigomarcacion.",
            CODIGOCLIENTE         = ".$valor_codigocliente.",
            CODIGOTRUCK           = ".$valor_codigotruck.",
            CODIGOAGENCIA         = ".$valor_codigoagencia.",
            CODIGOPAIS            = ".$valor_codigopais.",
            OBSERVACIONES         = '".$observaciones."',
            ESTADO                = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION     = NOW()
            WHERE CODIGO = ".$codigo;
        }

    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_consolidado_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE consolidado SET
        ESTADO                = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION     = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Devuelve HTML formateado con la trazabilidad (quien registro/modifico, cuando).
function trazabilidad_consolidado_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "SELECT CODIGO, GUIA, FECHAVUELO,
        CODIGOUSUARIOREGISTRA, FECHAREGISTRO,
        CODIGOUSUARIOMODIFICA, FECHAMODIFICACION
        FROM consolidado WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return "Consolidado no encontrado";

    $fila = mysqli_fetch_array($resultado);

    $usuario_reg = (isset($fila['CODIGOUSUARIOREGISTRA']) && $fila['CODIGOUSUARIOREGISTRA'] !== null) ? $fila['CODIGOUSUARIOREGISTRA'] : "N/A";
    $fecha_reg   = (isset($fila['FECHAREGISTRO'])         && $fila['FECHAREGISTRO']         !== null) ? $fila['FECHAREGISTRO']         : "N/A";
    $usuario_mod = (isset($fila['CODIGOUSUARIOMODIFICA']) && $fila['CODIGOUSUARIOMODIFICA'] !== null) ? $fila['CODIGOUSUARIOMODIFICA'] : "N/A";
    $fecha_mod   = (isset($fila['FECHAMODIFICACION'])     && $fila['FECHAMODIFICACION']     !== null) ? $fila['FECHAMODIFICACION']     : "N/A";

    $html  = '<div style="font-size: 12px; line-height: 1.7;">';
    $html .= '<b>Consolidado:</b> ('.$fila['CODIGO'].') GUIA: '.htmlspecialchars((string)$fila['GUIA'], ENT_QUOTES, 'UTF-8').' / FECHA VUELO: '.htmlspecialchars((string)$fila['FECHAVUELO'], ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Registrado por usuario:</b> '.htmlspecialchars((string)$usuario_reg, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha registro:</b> '.htmlspecialchars((string)$fecha_reg, ENT_QUOTES, 'UTF-8').'<br><br>';
    $html .= '<b>Ultima modificacion por usuario:</b> '.htmlspecialchars((string)$usuario_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '<b>Fecha modificacion:</b> '.htmlspecialchars((string)$fecha_mod, ENT_QUOTES, 'UTF-8').'<br>';
    $html .= '</div>';
    return $html;
    }


// Retorna las options HTML del <select> de MARCACION filtradas por cliente.
// Cada option lleva data-truck con el CODIGOTRUCK asociado, para que el
// frontend pueda autopoblar el Select2 de TRUCK al elegir una marcacion.
function opciones_marcaciones_por_cliente_dsft($codigo_cliente)
    {
    global $link;
    $codigo_cliente = (int)$codigo_cliente;
    $html = '<option value="0">-- SELECCIONE --</option>';
    if($codigo_cliente <= 0)
        return $html;

    $sql = "SELECT CODIGO, NOMBREMARCACION, CODIGOTRUCK
        FROM marcacion
        WHERE CODIGOCLIENTE = ".$codigo_cliente."
          AND ESTADO >= 0
        ORDER BY NOMBREMARCACION";
    $res = mysqli_query($link, $sql);
    if(!$res)
        return $html;
    $total = mysqli_num_rows($res);
    for($i=1; $i<=$total; $i++)
        {
        $f     = mysqli_fetch_assoc($res);
        $truck = ($f["CODIGOTRUCK"] !== null) ? (int)$f["CODIGOTRUCK"] : 0;
        $html .= '<option value="'.(int)$f["CODIGO"].'" data-truck="'.$truck.'">'.htmlspecialchars((string)$f["NOMBREMARCACION"], ENT_QUOTES, 'UTF-8').'</option>';
        }
    return $html;
    }


// ============================================================================
// GUIAS DEL CONSOLIDADO (consola_consolidado.php).
// Tablas:
//   guia                (CODIGO PK, NUMEROGUIA, ESTADO, FECHAREGISTRO, ...)
//   guia_consolidado     (CODIGOGUIA, CODIGOCONSOLIDADO)  -- join NxN
// ============================================================================

// Asocia una guia a un consolidado. $valor puede ser:
//   - numerico puro -> es el CODIGO de una guia existente.
//   - no numerico   -> es un NUMEROGUIA nuevo (el usuario lo escribio).
// Si es nuevo, inserta en guia y luego usa el CODIGO autogenerado.
// El INSERT en guia_consolidado usa IGNORE para evitar duplicar el par.
function agregar_guia_consolidado_dsft($codigo_consolidado, $valor, $codigo_usuario)
    {
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    $codigo_usuario     = (int)$codigo_usuario;
    if($codigo_consolidado <= 0)
        return "Codigo de consolidado invalido";

    $valor = trim((string)$valor);
    if($valor == "")
        return "Valor de guia vacio";

    $codigo_guia = 0;

    // Detectar si $valor es codigo numerico (guia existente) o numeroguia nuevo.
    if(ctype_digit($valor) && (int)$valor > 0)
        {
        // Verificar que esa guia existe.
        $codigo_check = (int)$valor;
        $sql_check = "SELECT CODIGO FROM guia WHERE CODIGO = ".$codigo_check;
        $res_check = mysqli_query($link, $sql_check);
        if($res_check && mysqli_num_rows($res_check) > 0)
            $codigo_guia = $codigo_check;
        }

    if($codigo_guia == 0)
        {
        // Es un NUMEROGUIA nuevo (o el codigo no existia).
        $numeroguia = strtoupper($valor);
        // Validar formato: solo numeros y guiones, max 15 chars.
        if(!preg_match('/^[0-9\-]{1,15}$/', $numeroguia))
            return "La guia solo admite numeros y guiones (max 15 caracteres)";
        $numeroguia_sql = mysqli_real_escape_string($link, $numeroguia);

        // ¿Ya existe el NUMEROGUIA en la tabla guia?
        $sql_busca = "SELECT CODIGO FROM guia WHERE NUMEROGUIA = '".$numeroguia_sql."' LIMIT 1";
        $res_busca = mysqli_query($link, $sql_busca);
        if($res_busca && mysqli_num_rows($res_busca) > 0)
            {
            $fila_b = mysqli_fetch_assoc($res_busca);
            $codigo_guia = (int)$fila_b["CODIGO"];
            }
        else
            {
            // INSERT nueva guia.
            $sql_ins = "INSERT INTO guia (CODIGO, NUMEROGUIA, ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO)
                VALUES (0, '".$numeroguia_sql."', 1, ".$codigo_usuario.", NOW())";
            $r_ins = mysqli_query($link, $sql_ins);
            if(!$r_ins)
                return "Error SQL al crear guia: ".mysqli_error($link);
            $codigo_guia = (int)mysqli_insert_id($link);
            }
        }

    if($codigo_guia <= 0)
        return "No se pudo determinar el CODIGO de guia";

    // Asociar al consolidado (IGNORE por si ya estaba).
    $sql_aso = "INSERT IGNORE INTO guia_consolidado (CODIGOGUIA, CODIGOCONSOLIDADO)
        VALUES (".$codigo_guia.", ".$codigo_consolidado.")";
    $r_aso = mysqli_query($link, $sql_aso);
    if(!$r_aso)
        return "Error SQL al asociar guia: ".mysqli_error($link);

    return "OK";
    }

// Desasocia una guia de un consolidado (no toca la tabla guia).
function quitar_guia_consolidado_dsft($codigo_consolidado, $codigo_guia)
    {
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    $codigo_guia        = (int)$codigo_guia;
    if($codigo_consolidado <= 0 || $codigo_guia <= 0)
        return "Codigos invalidos";

    $sql = "DELETE FROM guia_consolidado
        WHERE CODIGOGUIA = ".$codigo_guia."
          AND CODIGOCONSOLIDADO = ".$codigo_consolidado;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Retorna el HTML de la lista de guias asociadas a un consolidado: badges con
// el NUMEROGUIA y boton X para quitar.
function lista_guias_consolidado_dsft($codigo_consolidado)
    {
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    if($codigo_consolidado <= 0)
        return "";

    $sql = "SELECT g.CODIGO, g.NUMEROGUIA
        FROM guia_consolidado gc
        INNER JOIN guia g ON gc.CODIGOGUIA = g.CODIGO
        WHERE gc.CODIGOCONSOLIDADO = ".$codigo_consolidado."
        ORDER BY g.NUMEROGUIA";
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="color:#88010e; font-size:11px;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';

    $numero = mysqli_num_rows($resultado);
    if($numero == 0)
        return '<div style="font-size:12px; color:#888; padding:4px 0;">Sin guias asignadas</div>';

    $html = '';
    for($i=1; $i<=$numero; $i++)
        {
        $f         = mysqli_fetch_assoc($resultado);
        $cg        = (int)$f["CODIGO"];
        $numguia   = htmlspecialchars((string)$f["NUMEROGUIA"], ENT_QUOTES, 'UTF-8');
        // Cada badge envuelto en un <div> para que se muestren uno por linea.
        $html .= '<div style="margin:3px 0;">';
        $html .= '<span class="badge_guia" style="display:inline-block; background:#f2f2f2; border:1px solid #ccc; border-radius:4px; padding:3px 8px; font-size:13px;">';
        $html .= $numguia;
        $html .= '<a onclick="quitar_guia_consolidado('.$cg.');" style="cursor:pointer; color:#88010e; margin-left:4px; font-weight:bold;" title="Quitar guia">&times;</a>';
        $html .= '</span>';
        $html .= '</div>';
        }
    return $html;
    }


// Retorna las options HTML de guias recientes (ultimos 15 dias) para refrescar
// el Select2 con tags. La opcion default "-- Buscar o crear guia --" la pone
// el frontend; aqui solo van las options reales.
function opciones_guias_recientes_dsft()
    {
    global $link;
    $sql = "SELECT CODIGO, NUMEROGUIA FROM guia
        WHERE ESTADO >= 0
          AND FECHAREGISTRO >= DATE_SUB(NOW(), INTERVAL 15 DAY)
        ORDER BY NUMEROGUIA";
    $res = mysqli_query($link, $sql);
    if(!$res)
        return "";
    $total = mysqli_num_rows($res);
    $html  = "";
    for($i=1; $i<=$total; $i++)
        {
        $f = mysqli_fetch_assoc($res);
        $html .= '<option value="'.(int)$f["CODIGO"].'">'.htmlspecialchars((string)$f["NUMEROGUIA"], ENT_QUOTES, 'UTF-8').'</option>';
        }
    return $html;
    }