<?php 
                               
// ============================================================================
//  funciones_v2.php  -  Logica nueva (estilo v3).
//  Consola de Correos / Facturas: extraccion desde Gmail.
// ============================================== ==============================
                          
             
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


// Asigna una factura procesada a un consolidado: factura_finca.CODIGOCONSOLIDADO = $codigo_consolidado.
function asignar_factura_consolidado_dsft($codigo_factura, $codigo_consolidado)
    {
    global $link;
    $codigo_factura     = (int)$codigo_factura;
    $codigo_consolidado = (int)$codigo_consolidado;
    if($codigo_factura <= 0 || $codigo_consolidado <= 0)
        return "Codigos invalidos";
    $sql = "UPDATE factura_finca SET CODIGOCONSOLIDADO = ".$codigo_consolidado."
        WHERE CODIGO = ".$codigo_factura;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Quita la asignacion de consolidado de una factura: factura_finca.CODIGOCONSOLIDADO = NULL.
function desasignar_factura_consolidado_dsft($codigo_factura)
    {
    global $link;
    $codigo_factura = (int)$codigo_factura;
    if($codigo_factura <= 0)
        return "Codigo invalido";
    $sql = "UPDATE factura_finca SET CODIGOCONSOLIDADO = NULL
        WHERE CODIGO = ".$codigo_factura;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
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
    $sql_procesados = "SELECT CODIGOADJUNTO, CODIGO, FINCA, CLIENTEMARCACION, NUMEROFACTURA, GUIA, TOTALCAJASEQUIVALENTES, ESTADO, CODIGOCONSOLIDADO
        FROM factura_finca WHERE CODIGOADJUNTO IS NOT NULL";
    $res_procesados = mysqli_query($link, $sql_procesados);
    if($res_procesados)
        {
        $numero_procesados = mysqli_num_rows($res_procesados);
        for($p=1; $p<=$numero_procesados; $p++)
            {
            $fila_p = mysqli_fetch_assoc($res_procesados);
            $adjuntos_procesados[(int)$fila_p["CODIGOADJUNTO"]] = array(
                "CODIGO"            => (int)$fila_p["CODIGO"],
                "FINCA"             => $fila_p["FINCA"],
                "CLIENTEMARCACION"  => $fila_p["CLIENTEMARCACION"],
                "NUMEROFACTURA"     => $fila_p["NUMEROFACTURA"],
                "GUIA"              => $fila_p["GUIA"],
                "FULLES"            => $fila_p["TOTALCAJASEQUIVALENTES"],
                "ESTADO"            => $fila_p["ESTADO"],
                "CODIGOCONSOLIDADO" => $fila_p["CODIGOCONSOLIDADO"]
                );
            }
        }

    // Lookup de consolidados (CODIGO -> FECHAVUELO) para mostrar "COD - FECHA"
    // en la celda CONSOLIDADO de las filas de adjunto procesado.
    $consolidados_lookup = array();
    $sql_cl = "SELECT CODIGO, FECHAVUELO FROM consolidado WHERE ESTADO >= 0";
    $res_cl = mysqli_query($link, $sql_cl);
    if($res_cl)
        {
        $total_cl = mysqli_num_rows($res_cl);
        for($cl=1; $cl<=$total_cl; $cl++)
            {
            $fila_cl = mysqli_fetch_assoc($res_cl);
            $consolidados_lookup[(int)$fila_cl["CODIGO"]] = $fila_cl["FECHAVUELO"];
            }
        }

    // Validar campo y direccion de ordenamiento.
    // CLIENTEMARCACION, FINCA_PROCESADA y ORD_CONSOLIDADO son alias logicos que
    // apuntan a subqueries contra factura_finca (ver $map_orden).
    $campos_permitidos = array(1=>"CODIGO", 2=>"CODIGOFINCA", 3=>"CODIGOCONSOLIDADO", 4=>"ASUNTO", 5=>"FECHAHORA", 6=>"DE", 7=>"PARA", 8=>"ESTADO", 9=>"CLIENTEMARCACION", 10=>"FINCA_PROCESADA", 11=>"ORD_CONSOLIDADO");
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

    // Mapear alias logico a la expresion real para el ORDER BY.
    $map_orden = array(
        "CODIGO"            => "c.CODIGO",
        "CODIGOFINCA"       => "c.CODIGOFINCA",
        "CODIGOCONSOLIDADO" => "c.CODIGOCONSOLIDADO",
        "ASUNTO"            => "c.ASUNTO",
        "FECHAHORA"         => "c.FECHAHORA",
        "DE"                => "c.DE",
        "PARA"              => "c.PARA",
        "ESTADO"            => "c.ESTADO",
        "CLIENTEMARCACION"  => "ORD_MARCA",
        "FINCA_PROCESADA"   => "ORD_FINCA",
        "ORD_CONSOLIDADO"   => "ORD_CONSOLIDADO"
        );
    $columna_order_by = $map_orden[$orden_valido];

    // Filtro por rango de fechas: si vienen ambas, usar ese rango.
    // Si no, mantener el comportamiento por defecto (ultimos 5 dias).
    $valida_desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_desde);
    $valida_hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_hasta);
    if($valida_desde && $valida_hasta)
        {
        $fecha_desde = mysqli_real_escape_string($link, $fecha_desde);
        $fecha_hasta = mysqli_real_escape_string($link, $fecha_hasta);
        $where_fechas = "c.FECHAHORA >= '".$fecha_desde." 00:00:00' AND c.FECHAHORA <= '".$fecha_hasta." 23:59:59'";
        $texto_rango  = $fecha_desde." al ".$fecha_hasta;
        }
    else
        {
        $where_fechas = "c.FECHAHORA >= DATE_SUB(NOW(), INTERVAL 5 DAY)";
        $texto_rango  = "ultimos 5 dias";
        }

    // Subqueries ORD_MARCA y ORD_FINCA: traen la marca/finca del PRIMER adjunto
    // procesado del correo. LIMIT 1 evita ambiguedad si hay varios adjuntos.
    $sql = "SELECT
        c.CODIGO AS CODIGO,
        c.CODIGOFINCA AS CODIGOFINCA,
        c.CODIGOCONSOLIDADO AS CODIGOCONSOLIDADO,
        c.IDCORREO AS IDCORREO,
        c.FECHAHORA AS FECHAHORA,
        c.DE AS DE,
        c.PARA AS PARA,
        c.ASUNTO AS ASUNTO,
        c.ESTADO AS ESTADO,
        c.OBSERVACIONES AS OBSERVACIONES,
        (SELECT ff2.CLIENTEMARCACION FROM archivo_correo ac2
            INNER JOIN factura_finca ff2 ON ff2.CODIGOADJUNTO = ac2.CODIGO
            WHERE ac2.IDCORREO = c.IDCORREO LIMIT 1) AS ORD_MARCA,
        (SELECT ff3.FINCA FROM archivo_correo ac3
            INNER JOIN factura_finca ff3 ON ff3.CODIGOADJUNTO = ac3.CODIGO
            WHERE ac3.IDCORREO = c.IDCORREO LIMIT 1) AS ORD_FINCA,
        (SELECT ff4.CODIGOCONSOLIDADO FROM archivo_correo ac4
            INNER JOIN factura_finca ff4 ON ff4.CODIGOADJUNTO = ac4.CODIGO
            WHERE ac4.IDCORREO = c.IDCORREO LIMIT 1) AS ORD_CONSOLIDADO
        FROM correo_facturas_fincas c
        WHERE ".$where_fechas."
        ORDER BY ".$columna_order_by." ".$direccion_valida;
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
    $html .= '<th style="width: 4%; cursor:pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.indicador_orden("CODIGO", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 12%; cursor:pointer;" onclick="ordenar_por(\'ORD_CONSOLIDADO\')">CONSOLIDADO'.indicador_orden("ORD_CONSOLIDADO", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 12%; text-align:center; cursor:pointer;" onclick="ordenar_por(\'CLIENTEMARCACION\')">MARCA'.indicador_orden("CLIENTEMARCACION", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 13%; cursor:pointer;" onclick="ordenar_por(\'FINCA_PROCESADA\')">FINCA'.indicador_orden("FINCA_PROCESADA", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 4%;">FULLES</th>';
    $html .= '<th style="width: 15%;">ARCHIVO</th>';
    $html .= '<th style="width: 90px; cursor:pointer;" onclick="ordenar_por(\'FECHAHORA\')">FH REC'.indicador_orden("FECHAHORA", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 25px; font-size:10px; cursor:pointer;" onclick="ordenar_por(\'ESTADO\')">E'.indicador_orden("ESTADO", $orden_valido, $direccion_valida).'</th>';
    $html .= '<th style="width: 80px; text-align:center;">OPC</th>';
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
        // Celda CONSOLIDADO (vacia en la fila principal del correo). Va en segunda posicion, despues de COD.
        $html .= '<td class="td_centro" style="'.$est_14.'"></td>';
         // Celda MARCA vacia en la fila principal: solo la fila de adjunto muestra CLIENTEMARCACION.
        $html .= '<td class="td_centro" style="'.$est_14.'"></td>';
        $html .= '<td colspan="3" style="'.$est_asun.'">'.$asunto.'</td>';
        $html .= '<td class="td_centro" style="'.$est_14.'">'.$fechahora.'</td>';
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

                // Determinar las celdas de FINCA/CONS/TAMANO segun si esta procesado.
                // Cuando ya esta procesado, mostrar datos de factura_finca; sino,
                // mostrar guiones y tamano normales.
                $proc = isset($adjuntos_procesados[(int)$adj_codigo]) ? $adjuntos_procesados[(int)$adj_codigo] : null;

                // Color de fondo de la fila de adjunto:
                //  - procesado    -> verde pastel claro
                //  - no procesado -> gris (default)
                if($proc !== null)
                    $est_adj_bg = 'rgba(143,188,143,0.2)';
                else 
                    $est_adj_bg = 'rgba(195,195,195,0.4)';
                $est_adj = 'background-color:'.$est_adj_bg.'; color:#000; font-size:13px; font-weight:normal;';

                $celda_finca       = $adj_finca;   // por defecto: "—" o codigo_finca de archivo_correo
                $celda_cons        = $adj_cons;    // por defecto: "—" o codigo_consolidado de archivo_correo
                $celda_tam         = $adj_tamano;  // por defecto: "38.8 KB"
                $celda_fulles      = '';           // TOTALCAJASEQUIVALENTES solo cuando esta procesado
                $celda_estado_adj  = '';           // ESTADO de factura_finca solo cuando esta procesado
                $celda_consolidado = '';           // "COD - FECHAVUELO" solo si esta procesado y asignado
                $visor_title       = $adj_visor_titulo_base;

                if($proc !== null)
                    {
                    // Procesado: 2da celda = CLIENTEMARCACION, 3ra = FINCA, 4ta = FULLES, tam = GUIA.
                    // Todo el contenido de las celdas del adjunto procesado va en <strong>
                    // para distinguir visualmente del adjunto no procesado.
                    $cm     = isset($proc["CLIENTEMARCACION"]) ? trim((string)$proc["CLIENTEMARCACION"]) : '';
                    $fn     = isset($proc["FINCA"])            ? trim((string)$proc["FINCA"])            : '';
                    $guia   = isset($proc["GUIA"])             ? trim((string)$proc["GUIA"])             : '';
                    $fulles = isset($proc["FULLES"])           ? $proc["FULLES"]                          : null;
                    if($cm   !== '') $celda_finca = '<strong>'.htmlspecialchars($cm, ENT_QUOTES, 'UTF-8').'</strong>';
                    if($fn   !== '') $celda_cons  = '<strong>'.htmlspecialchars($fn, ENT_QUOTES, 'UTF-8').'</strong>';
                    if($guia !== '') $celda_tam   = '<strong>'.htmlspecialchars($guia, ENT_QUOTES, 'UTF-8').'</strong>';
                    // FULLES: si es null o 0, dejar vacio.
                    if($fulles !== null && (float)$fulles > 0)
                        $celda_fulles = '<strong>'.htmlspecialchars((string)$fulles, ENT_QUOTES, 'UTF-8').'</strong>';
                    // ESTADO de factura_finca (1=activo normal, 3=OK ambas Haiku coinciden, 4=REVISAR).
                    $est_ff = isset($proc["ESTADO"]) ? (string)$proc["ESTADO"] : '';
                    if($est_ff !== '')  
                        $celda_estado_adj = '<strong>'.htmlspecialchars($est_ff, ENT_QUOTES, 'UTF-8').'</strong>';
                    // CONSOLIDADO asignado a la factura: "COD - FECHAVUELO" como link
                    // a consola_consolidado.php?codigo=N (abre el consolidado).
                    $cc_asignado = isset($proc["CODIGOCONSOLIDADO"]) ? (int)$proc["CODIGOCONSOLIDADO"] : 0;
                    if($cc_asignado > 0 && isset($consolidados_lookup[$cc_asignado]))
                        {
                        $fechavuelo_lookup = htmlspecialchars((string)$consolidados_lookup[$cc_asignado], ENT_QUOTES, 'UTF-8');
                        $celda_consolidado = '<a href="consola_consolidado.php?codigo='.$cc_asignado.'" style="color:#88010e; text-decoration:underline; cursor:pointer;" title="Ir al consolidado"><strong>'.$cc_asignado.' - '.$fechavuelo_lookup.'</strong></a>';
                        }
                    // Agregar font-weight:bold al style del <a> del nombre del archivo.
                    $adj_nombre_link = str_replace('color:#003366;', 'color:#003366; font-weight:bold;', $adj_nombre_link);
                    // Visor con tamano en el title para no perder el dato del KB.
                    $visor_title = $adj_visor_titulo_base.' ('.$adj_tamano.')';
                    }
                $adj_visor = str_replace('VISOR_TITLE', $visor_title, $adj_visor_template);

                $html .= '<tr class="fila_adjunto">';
                $html .= '<td class="td_centro" style="'.$est_adj.' box-shadow:none;"><i class="icon-arrow-right-2" title="'.$adj_codigo.'" style="color:#7fa7c9;"></i></td>';
                // Celda CONSOLIDADO: "COD - FECHAVUELO" si la factura tiene consolidado asignado, vacia si no.
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_consolidado.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_finca.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_cons.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_fulles.'</td>';
                $html .= '<td style="'.$est_adj.'">'.$adj_nombre_link.'</td>';
                // FH REC y E ya no van con colspan: FH REC muestra GUIA/tamano, E muestra ESTADO de factura_finca.
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_tam.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.'">'.$celda_estado_adj.'</td>';
                $html .= '<td class="td_centro" style="'.$est_adj.' text-align:right;">';
                // Excluir packing lists y statements: no son facturas a procesar.
                $nombre_lower = strtolower((string)$adj_nombre);
                $es_excluido = (strpos($nombre_lower, 'packing') !== false
                             || strpos($nombre_lower, 'statement') !== false);
  
                if($proc !== null)
                    {
                    $codigo_ff = (int)$proc["CODIGO"];
                    $cc_actual = isset($proc["CODIGOCONSOLIDADO"]) ? (int)$proc["CODIGOCONSOLIDADO"] : 0;
                    // Si esta asignada a un consolidado, mostrar icono reply (quitar) a la izquierda.
                    if($cc_actual > 0)
                        $html .= '<a onclick="desasignar_consolidado('.$codigo_ff.');" title="Quitar del consolidado" style="cursor:pointer; color:#2e7d32; margin-right:4px;"><i class="icon-reply"></i></a>';
                    // Icono forward (asignar al consolidado seleccionado en la barra).
                    $html .= '<a onclick="asignar_consolidado('.$codigo_ff.');" title="Asignar a consolidado" style="cursor:pointer; color:#88010e; margin-right:4px;"><i class="icon-forward"></i></a>';
                    // Icono target verde pastel: ver la factura procesada.
                    $html .= '<a onclick="window.open(\'ver_factura_finca.php?codigo='.$codigo_ff.'\', \'_blank\');" title="Procesada - factura_finca CODIGO: '.$codigo_ff.'" style="cursor:pointer; color:#8fbc8f; margin-right:6px;"><i class="icon-target"></i></a>';
                    }
                else if($es_excluido)
                    {
                    // Packing/statement: no se procesa como factura, no se muestra icono de procesar.
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
    $html .= '<div style="text-align:right; font-size:11px; color:#666; padding:5px;">Total: '.$numero_correos.' correos ('.htmlspecialchars((string)$texto_rango, ENT_QUOTES, 'UTF-8').')</div>';
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
    $html .= '<th style="width: 22%; cursor: pointer;" onclick="ordenar_por(\'GUIA\')">GUIA'.$ind_guia.'</th>';
    $html .= '<th style="width: 13%; cursor: pointer;" onclick="ordenar_por(\'NOMBREMARCACION\')">MARCA'.$ind_marca.'</th>';
    $html .= '<th style="width: 17%; cursor: pointer;" onclick="ordenar_por(\'NOMBRECLIENTE\')">CLIENTE'.$ind_cliente.'</th>';
    $html .= '<th style="width: 5%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 28%;">OPC</th>';
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
        $html .= '<a onclick="dialog_crear_factura_manual('.$codigo.');" style="cursor:pointer; color:#2e7d32; margin-right:4px;" title="Crear factura manual"><i class="icon-clipboard-2"></i></a>';
        $html .= '<a onclick="dialog_subir_archivo('.$codigo.');" style="cursor:pointer; color:#88010e; margin-right:4px;" title="Subir archivo y procesar con IA"><i class="icon-upload"></i></a>';
        $html .= '<a onclick="toggle_menu_formato(this, '.$codigo.');" style="cursor:pointer; color:#2e7d32; margin-right:4px; position:relative;" title="Generar consolidado"><i class="icon-puzzle"></i></a>';
        $html .= '<a onclick="messageBox(\'Factura cliente - proximamente\');" style="cursor:pointer; color:#2196F3; margin-right:4px;" title="Factura cliente"><i class="icon-dollar"></i></a>';
        $html .= '<a onclick="messageBox(\'Packing - proximamente\');" style="cursor:pointer; color:#d4890e; margin-right:4px;" title="Packing"><i class="icon-bus"></i></a>';
        $html .= '<a href="javascript: muestra_trazabilidad_consolidado('.$codigo.');" title="Trazabilidad"><i class="icon-accessibility fg-teal"></i></a>';
        $html .= '<a href="javascript: devuelve_consolidado('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
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


// Devuelve el HTML completo del detalle del consolidado: una "tarjeta" por
// cada factura asignada (factura_finca.CODIGOCONSOLIDADO = $codigo_consolidado).
// Cada tarjeta tiene: cabecera, metadata, grid posicional 60% + area PDF 40%,
// y totales con check de cuadre subtotal-detalle vs total-cabecera.
function detalle_consolidado_dsft($codigo_consolidado)
    {   
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    if($codigo_consolidado <= 0)
        return "Consolidado invalido";

    $sql_ff = "SELECT ff.CODIGO, ff.FINCA, ff.CLIENTEMARCACION,
        ff.NUMEROFACTURA, ff.FECHAFACTURACION, ff.PAISDESTINO,
        ff.GUIA, ff.SUBTOTAL, ff.TOTAL, ff.CODIGOADJUNTO, ff.ESTADO,
        ff.CODIGOFINCA, ff.CODIGOTIPOPRODUCTO,
        ac.NOMBREARCHIVO, ac.MIMETYPE
        FROM factura_finca ff
        LEFT JOIN archivo_correo ac ON ff.CODIGOADJUNTO = ac.CODIGO
        WHERE ff.CODIGOCONSOLIDADO = ".$codigo_consolidado."
        ORDER BY ff.FINCA, ff.NUMEROFACTURA";
    $res_ff = mysqli_query($link, $sql_ff);
    if(!$res_ff || mysqli_num_rows($res_ff) == 0)
        return "<p style='color:#888; text-align:center;'>No hay facturas asignadas a este consolidado.</p>";

    // Opciones de finca (proveedor con codigo_tipo_proveedor = 1) reutilizadas
    // en el Select2 de "Confirmar finca" del header de cada tarjeta.
    $opciones_fincas = "";
    $sql_fincas      = "SELECT p.codigo_proveedor AS CODIGO, p.nombre_proveedor AS NOMBRE
        FROM proveedor p
        WHERE p.codigo_tipo_proveedor = 1
        ORDER BY p.nombre_proveedor";
    $res_fincas      = mysqli_query($link, $sql_fincas);
    if($res_fincas)
        {
        $total_fincas = mysqli_num_rows($res_fincas);
        for($fi=1; $fi<=$total_fincas; $fi++)
            {
            $prov             = mysqli_fetch_assoc($res_fincas);
            $opciones_fincas .= '<option value="'.(int)$prov["CODIGO"].'">'
                .htmlspecialchars((string)$prov["NOMBRE"], ENT_QUOTES, "UTF-8")
                .'</option>';
            }
        }

    // Opciones de tipo de producto (activos) reutilizadas en el Select2 de
    // "tipo" del header de cada tarjeta.
    $sql_tipos = "SELECT CODIGO, NOMBRE
        FROM tipo_producto
        WHERE ESTADO = 1
        ORDER BY NOMBRE";
    $res_tipos      = mysqli_query($link, $sql_tipos);
    $opciones_tipos = "";
    if($res_tipos)
        {
        $total_tipos = mysqli_num_rows($res_tipos);
        for($ti=1; $ti<=$total_tipos; $ti++)
            {
            $tipo            = mysqli_fetch_assoc($res_tipos);
            $opciones_tipos .= '<option value="'.(int)$tipo["CODIGO"].'">'
                .htmlspecialchars((string)$tipo["NOMBRE"], ENT_QUOTES, "UTF-8")
                .'</option>';
            }
        }

    $total_ff = mysqli_num_rows($res_ff);
    $html     = "";

    for($f=1; $f<=$total_ff; $f++)
        {
        $ff         = mysqli_fetch_assoc($res_ff);
        $codigo_ff  = (int)$ff["CODIGO"];
        $finca      = htmlspecialchars((string)$ff["FINCA"], ENT_QUOTES, "UTF-8");
        $marca      = htmlspecialchars((string)$ff["CLIENTEMARCACION"], ENT_QUOTES, "UTF-8");
        $nfac       = htmlspecialchars((string)$ff["NUMEROFACTURA"], ENT_QUOTES, "UTF-8");
        $fecha      = htmlspecialchars((string)$ff["FECHAFACTURACION"], ENT_QUOTES, "UTF-8");
        $pais       = htmlspecialchars((string)$ff["PAISDESTINO"], ENT_QUOTES, "UTF-8");
        $guia       = htmlspecialchars((string)$ff["GUIA"], ENT_QUOTES, "UTF-8");
        $total_cab  = (float)$ff["TOTAL"];
        $codigo_adj   = (int)$ff["CODIGOADJUNTO"];
        $nombre_adj   = htmlspecialchars((string)$ff["NOMBREARCHIVO"], ENT_QUOTES, "UTF-8");
        $es_pdf       = (stripos((string)$ff["MIMETYPE"], "pdf") !== false)
            || (strtolower(substr((string)$ff["NOMBREARCHIVO"], -4)) == ".pdf");
        $codigo_finca = (int)$ff["CODIGOFINCA"];

        // Auto-match: si la factura aun no tiene CODIGOFINCA pero la IA
        // extrajo un texto en FINCA, buscar un proveedor cuyo nombre coincida
        // exacto (case/spaces-insensitive) y pre-seleccionarlo.
        if($codigo_finca == 0 && trim((string)$ff["FINCA"]) != "")
            {
            $finca_ia     = strtoupper(trim((string)$ff["FINCA"]));
            $finca_ia_esc = mysqli_real_escape_string($link, $finca_ia);
            $sql_match    = "SELECT codigo_proveedor AS CODIGO
                FROM proveedor
                WHERE codigo_tipo_proveedor = 1
                  AND UPPER(TRIM(nombre_proveedor)) = '".$finca_ia_esc."'
                LIMIT 1";
            $res_match = mysqli_query($link, $sql_match);
            if($res_match && mysqli_num_rows($res_match) > 0)
                {
                $fila_match   = mysqli_fetch_assoc($res_match);
                $codigo_finca = (int)$fila_match["CODIGO"];
                }
            }

        // Pre-seleccionar la finca actual en las opciones del select.
        if($codigo_finca > 0)
            $opciones_seleccionadas = str_replace(
                'value="'.$codigo_finca.'"',
                'value="'.$codigo_finca.'" selected',
                $opciones_fincas
                );
        else
            $opciones_seleccionadas = $opciones_fincas;

        // Boton confirmar/cambiar segun si ya tiene CODIGOFINCA persistida.
        // CODIGOFINCA > 0  -> "Cambiar" verde
        // CODIGOFINCA == 0 -> "Confirmar" rojo (incluye el caso de auto-match que aun no se persistio)
        $codigo_finca_persistido = (int)$ff["CODIGOFINCA"];
        if($codigo_finca_persistido > 0)
            {
            $btn_texto = "Cambiar";
            $btn_color = "#2e7d32";
            }
        else
            {
            $btn_texto = "Confirmar";
            $btn_color = "#88010e";
            }

        // ---- TIPO DE PRODUCTO: auto-deteccion + preseleccion del header ----
        $codigo_tipo     = (int)$ff["CODIGOTIPOPRODUCTO"];
        $tipo_persistido = $codigo_tipo; // > 0 si ya esta confirmado en la BD.

        if($codigo_tipo == 0)
            {
            // Buscar el producto dominante (mas frecuente) en el detalle.
            $sql_dom = "SELECT PRODUCTO, COUNT(*) AS TOTAL
                FROM detalle_factura_finca
                WHERE CODIGOFACTURAFINCA = ".$codigo_ff."
                  AND PRODUCTO IS NOT NULL AND PRODUCTO != ''
                GROUP BY PRODUCTO
                ORDER BY TOTAL DESC
                LIMIT 1";
            $res_dom = mysqli_query($link, $sql_dom);
            if($res_dom && mysqli_num_rows($res_dom) > 0)
                {
                $fila_dom         = mysqli_fetch_assoc($res_dom);
                $producto_dom     = strtoupper(trim((string)$fila_dom["PRODUCTO"]));
                $producto_dom_esc = mysqli_real_escape_string($link, $producto_dom);
                // Match flexible: exacto por NOMBRE/NOMBREINGLES, o por prefijo
                // (LIKE 'X%') para que GYPSO matchee GYPSOPHILA y CARNATION
                // matchee CLAVEL via NOMBREINGLES. SPRAY sin match queda en ROSA.
                $sql_match_tipo   = "SELECT CODIGO 
                    FROM tipo_producto
                    WHERE ESTADO = 1
                      AND (UPPER(TRIM(NOMBRE)) = '".$producto_dom_esc."'
                           OR UPPER(TRIM(NOMBREINGLES)) = '".$producto_dom_esc."'
                           OR UPPER(TRIM(NOMBRE)) LIKE '".$producto_dom_esc."%'
                           OR UPPER(TRIM(NOMBREINGLES)) LIKE '".$producto_dom_esc."%')
                    LIMIT 1";
                $res_match_tipo = mysqli_query($link, $sql_match_tipo);
                if($res_match_tipo && mysqli_num_rows($res_match_tipo) > 0)
                    {
                    $fila_match_tipo = mysqli_fetch_assoc($res_match_tipo);
                    $codigo_tipo     = (int)$fila_match_tipo["CODIGO"];
                    }
                }
            // Si sigue en 0, default a ROSA (CODIGO = 1).
            if($codigo_tipo == 0)
                $codigo_tipo = 1;
            }

        // Pre-seleccionar el tipo en las opciones del select (igual que fincas).
        if($codigo_tipo > 0)
            $opciones_tipos_sel = str_replace(
                'value="'.$codigo_tipo.'"',
                'value="'.$codigo_tipo.'" selected',
                $opciones_tipos
                );
        else
            $opciones_tipos_sel = $opciones_tipos;

        // Boton confirmar/cambiar segun si el tipo ya esta persistido en la BD.
        // tipo_persistido > 0 -> "Cambiar" verde ; == 0 (sugerido) -> "Confirmar" rojo.
        if($tipo_persistido > 0)
            {
            $btn_tipo_texto = "Cambiar";
            $btn_tipo_color = "#2e7d32";
            }
        else
            {
            $btn_tipo_texto = "Confirmar";
            $btn_tipo_color = "#88010e";
            }

        // LINEA 1: titulo factura + iconos PDF/regenerar + select de finca a la derecha.
        // Descripcion compacta para el dialog de "Quitar factura".
        $descripcion_factura = addslashes($nfac.' - '.$finca);

        $html .= '<div style="background:#f2f2f2; padding:8px 12px; margin-top:10px; border:1px solid #ccc; border-radius:4px 4px 0 0; font-weight:bold; font-size:13px; color:#88010e; overflow:hidden;">';
        $html .= '<div style="float:right; display:flex; align-items:center; gap:4px;">';
        $html .= '<select id="id_select_tipo_'.$codigo_ff.'" style="width:150px; font-size:11px;">';
        $html .= '<option value="0">-- TIPO --</option>';
        $html .= $opciones_tipos_sel;
        $html .= '</select>';
        $html .= '<button type="button" id="id_btn_tipo_'.$codigo_ff.'" onclick="confirmar_tipo_producto('.$codigo_ff.');" style="font-size:10px; padding:2px 6px; background:'.$btn_tipo_color.'; color:#fff; border:none; border-radius:3px; cursor:pointer;">'.$btn_tipo_texto.'</button>';
        $html .= '<span style="color:#ccc; margin:0 4px;">|</span>';
        $html .= '<select id="id_select_finca_'.$codigo_ff.'" style="width:220px; font-size:11px;">';
        $html .= '<option value="0">-- CONFIRMAR FINCA --</option>';
        $html .= $opciones_seleccionadas;
        $html .= '</select>';
        $html .= '<button type="button" id="id_btn_finca_'.$codigo_ff.'" onclick="confirmar_finca('.$codigo_ff.');" style="font-size:11px; padding:2px 8px; background:'.$btn_color.'; color:#fff; border:none; border-radius:3px; cursor:pointer;">'.$btn_texto.'</button>';
        $html .= '</div>';
        // Icono toggle al inicio: minimiza/expande el contenido de la tarjeta.
        $html .= '<a onclick="toggle_tarjeta_factura('.$codigo_ff.');" style="cursor:pointer; color:#88010e; margin-right:6px;" title="Minimizar/Expandir"><i id="id_toggle_icon_'.$codigo_ff.'" class="icon-arrow-up"></i></a>';
        $html .= '<span style="color:#333;">'.$codigo_ff.'</span> - FACTURA <strong>'.$nfac.' - '.$finca.'</strong>';
        if($es_pdf && $codigo_adj > 0)
            $html .= ' <a onclick="ver_pdf_consolidado('.$codigo_adj.', \''.$nombre_adj.'\', '.$codigo_ff.');" style="cursor:pointer; margin-left:10px;" title="Ver PDF original"><i class="icon-file-pdf" style="color:#88010e;"></i></a>';
        // Icono regenerar: solo si hay adjunto asociado.
        if($codigo_adj > 0)
            $html .= ' <a onclick="regenerar_factura('.$codigo_ff.', '.$codigo_adj.', \''.$nombre_adj.'\');" style="cursor:pointer; color:#d4890e; margin-left:8px;" title="Regenerar factura (volver a procesar con IA)"><i class="icon-loop"></i></a>';
        // Icono quitar: desasocia la factura del consolidado (no la borra).
        $html .= ' <a onclick="quitar_factura_consolidado('.$codigo_ff.', \''.$descripcion_factura.'\');" style="cursor:pointer; color:#88010e; margin-left:8px;" title="Quitar factura de este consolidado"><i class="icon-remove"></i></a>';
        $html .= '</div>';

        // Contenido colapsable: metadata + flex grid/PDF. Los totales quedan FUERA.
        $html .= '<div id="id_contenido_factura_'.$codigo_ff.'">';


        // LINEA 2: metadata extraida.
        $html .= '<div style="background:#fafafa; padding:4px 12px; border-left:1px solid #ccc; border-right:1px solid #ccc; font-size:12px; color:#555;">';
        $html .= 'No.FAC: <strong>'.$nfac.'</strong>';
        $html .= ' | SHIP: <strong>'.$fecha.'</strong>';
        $html .= ' | MARCA: <strong>'.$marca.'</strong>';
        $html .= ' | '.$pais;
        $html .= ' | AWB: <strong>'.$guia.'</strong>'; 
        $html .= '</div>';   
    
        // CONTENEDOR 65/35: grid posicional (envuelto en id_grid_factura_N
        // para refresco granular) + area PDF.
        $html .= '<div style="display:flex; border:1px solid #ccc; border-top:none;">';
        $html .= '<div style="width:65%; max-height:500px; overflow-y:auto; overflow-x:auto; padding:4px;">';
        $html .= '<div id="id_grid_factura_'.$codigo_ff.'">';
        $html .= render_grid_factura_dsft($codigo_ff);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div id="id_pdf_area_'.$codigo_ff.'" style="width:35%; min-height:300px; background:#f9f9f9; display:flex; align-items:center; justify-content:center; color:#aaa; font-size:13px; flex-direction:column;">';
        $html .= '<i class="icon-file-pdf" style="font-size:40px;"></i><br>Click en el icono PDF para ver';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>'; // fin id_contenido_factura_N

        // Linea de totales FUERA del colapsable (siempre visible).
        $html .= '<div id="id_totales_factura_'.$codigo_ff.'" style="padding:6px 12px; font-size:12px; border:1px solid #ccc; border-top:none; border-radius:0 0 4px 4px; background:#fafafa;">';
        $html .= render_totales_factura_dsft($codigo_ff);
        $html .= '</div>';

        $html .= '<div style="margin-bottom:15px;"></div>';
        }

    return $html;
    }

// Renderiza el grid posicional + boton "agregar caja" de una factura.
// NO incluye los totales (esos van por render_totales_factura_dsft para
// quedar fuera del area colapsable de la tarjeta). Se llama desde
// detalle_consolidado_dsft (carga inicial) y desde el AJAX cuando se
// modifica una celda / linea (refresco granular).
function render_grid_factura_dsft($codigo_ff)
    {
    global $link;
    $codigo_ff = (int)$codigo_ff;
    if($codigo_ff <= 0)
        return "Factura invalida";

    // FINCA de la cabecera (para la columna FARM del grid).
    $sql_cab = "SELECT FINCA FROM factura_finca WHERE CODIGO = ".$codigo_ff;
    $res_cab = mysqli_query($link, $sql_cab);
    if(!$res_cab || mysqli_num_rows($res_cab) == 0)
        return "Factura no encontrada";
    $fila_cab = mysqli_fetch_assoc($res_cab);
    $finca    = (string)$fila_cab["FINCA"];

    return _render_grid_factura($link, $codigo_ff, $finca);
    }

// Renderiza SOLO el contenido (innerHTML) del bloque de totales de una
// factura: SUBTOTAL DETALLE vs TOTAL CABECERA + check de cuadre. El div
// envoltura "id_totales_factura_N" lo pone detalle_consolidado_dsft.
// Esta funcion se llama tambien por AJAX para refrescar los totales sin
// recargar el grid completo.
function render_totales_factura_dsft($codigo_ff)
    {
    global $link;
    $codigo_ff = (int)$codigo_ff;
    if($codigo_ff <= 0)
        return "Factura invalida";

    $sql_cab = "SELECT TOTAL FROM factura_finca WHERE CODIGO = ".$codigo_ff;
    $res_cab = mysqli_query($link, $sql_cab);
    if(!$res_cab || mysqli_num_rows($res_cab) == 0)
        return "Factura no encontrada";
    $fila_cab  = mysqli_fetch_assoc($res_cab);
    $total_cab = (float)$fila_cab["TOTAL"];

    $sql_sum  = "SELECT SUM(PRECIOTOTAL) AS SUMA FROM detalle_factura_finca WHERE CODIGOFACTURAFINCA = ".$codigo_ff;
    $res_sum  = mysqli_query($link, $sql_sum);
    $suma_det = 0;
    if($res_sum)
        {
        $fila_sum = mysqli_fetch_assoc($res_sum);
        if($fila_sum)
            $suma_det = (float)$fila_sum["SUMA"];
        }
    $cuadra = (abs($suma_det - $total_cab) < 0.01) ? ' style="color:green;"' : ' style="color:#88010e;"';

    $html  = 'SUBTOTAL DETALLE: <strong>$'.number_format($suma_det, 2).'</strong>';
    $html .= ' | TOTAL CABECERA: <strong'.$cuadra.'>$'.number_format($total_cab, 2).'</strong>';
    if(abs($suma_det - $total_cab) < 0.01)
        $html .= ' <span style="color:green;">&#10003;</span>';
    else
        $html .= ' <span style="color:#88010e;">&#10007; DIFERENCIA: $'.number_format(abs($suma_det - $total_cab), 2).'</span>';
    return $html;
    }

// Helper interno de detalle_consolidado_dsft. Renderiza el grid posicional
// de detalle_factura_finca con columnas cm (40 a 150) y FB equivalente
// (FB=1, HB=0.5, QB=0.25, OB/EB=0.125) en la primera linea de cada caja.
function _render_grid_factura($link, $codigo_ff, $finca)
    {
    $sql = "SELECT * FROM detalle_factura_finca
        WHERE CODIGOFACTURAFINCA = ".(int)$codigo_ff."
        ORDER BY NUMEROCAJA, INDICELINEA";
    $res = mysqli_query($link, $sql);
    if(!$res || mysqli_num_rows($res) == 0)
        {
        // Sin lineas: mostrar mensaje + boton "Agregar caja" para que la
        // usuaria pueda empezar a cargar (caso tipico de factura creada a mano).
        $html  = "<p style='color:#888; font-size:11px;'>Sin lineas de detalle.</p>";
        $html .= '<div style="margin-top:6px;">';
        $html .= '<a onclick="agregar_caja_detalle('.(int)$codigo_ff.');"';
        $html .= ' style="cursor:pointer; color:#88010e; font-size:12px;">';
        $html .= '<i class="icon-plus"></i> Agregar caja</a>';
        $html .= '</div>';
        return $html;
        }

    $total = mysqli_num_rows($res);
    $cms   = array(40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150);
    $total_cms = count($cms);

    $html  = '<table class="grid_factura_detalle" style="width:100%; border-collapse:collapse; font-size:11px;">';
    $html .= '<tr style="background:#88010e; color:#fff;">';
    $html .= '<th style="padding:2px 4px;">FB</th>';
    $html .= '<th style="padding:2px 4px;">PROD</th>';
    $html .= '<th style="padding:2px 4px;">VARIETY</th>';
    for($c=0; $c<$total_cms; $c++)
        $html .= '<th style="padding:2px 3px; width:35px; text-align:center;">'.$cms[$c].'</th>';
    $html .= '<th style="padding:2px 4px; text-align:right;">ST PR</th>';
    $html .= '<th style="padding:2px 4px; text-align:right;">TOT</th>';
    $html .= '<th style="padding:2px 4px; width:25px;">A</th>';
    $html .= '<th style="padding:2px 4px; width:40px;">OP</th>';
    $html .= '</tr>';

    $caja_anterior = -1;
    for($i=1; $i<=$total; $i++)
        {
        $d            = mysqli_fetch_assoc($res);
        $codigo_linea = (int)$d["CODIGO"];
        $num_caja     = (int)$d["NUMEROCAJA"];
        $tipo_caja    = strtoupper(trim((string)$d["TIPOCAJA"]));
        $largo        = ($d["LARGO"] !== null) ? (int)$d["LARGO"] : null;
        $tallos       = ($d["TALLOSTOTAL"] !== null) ? (int)$d["TALLOSTOTAL"] : null;

        // FB solo en primera linea de cada caja. es_primera_caja se usa para
        // decidir si pintamos el boton "+ agregar linea a esta caja".
        $fb              = "";
        $es_primera_caja = ($num_caja != $caja_anterior);
        if($es_primera_caja)
            {
            if($tipo_caja == "FB")
                $fb = "1";
            else if($tipo_caja == "HB")
                $fb = "0.5";
            else if($tipo_caja == "QB")
                $fb = "0.25";
            else if($tipo_caja == "OB" || $tipo_caja == "EB")
                $fb = "0.125";
            $caja_anterior = $num_caja;
            }

        $bg = ($i % 2 == 0) ? "#f9f9f9" : "#fff";
        $html .= '<tr data-codigo="'.$codigo_linea.'" style="background:'.$bg.';">';
        // FB no editable.
        $html .= '<td style="padding:2px 4px; text-align:center; border:1px solid #ddd;">'.$fb.'</td>';
        // PROD: producto de la linea (truncado a 8 con title completo). Editable
        // con DOBLE-click solo en la primera linea de cada caja (celda_prod); el
        // cambio aplica a TODAS las lineas de esa caja. Las demas filas lo muestran
        // sin la clase (no clickeables).
        $prod_raw     = (string)$d["PRODUCTO"];
        $prod_display = htmlspecialchars(strtoupper(substr(trim($prod_raw), 0, 8)), ENT_QUOTES, "UTF-8");
        $prod_title   = htmlspecialchars(strtoupper($prod_raw), ENT_QUOTES, "UTF-8");
        if($es_primera_caja)
            $html .= '<td class="celda_prod" data-codigo="'.$codigo_linea.'" data-caja="'.$num_caja.'" data-ff="'.(int)$codigo_ff.'" style="padding:2px 4px; border:1px solid #ddd; cursor:pointer;" title="'.$prod_title.'">'.$prod_display.'</td>';
        else
            $html .= '<td style="padding:2px 4px; border:1px solid #ddd;" title="'.$prod_title.'">'.$prod_display.'</td>';
        // VARIEDAD editable, siempre en mayuscula.
        $html .= '<td class="celda_editable" data-field="VARIEDAD" style="padding:2px 4px; border:1px solid #ddd;">'.htmlspecialchars(strtoupper((string)$d["VARIEDAD"]), ENT_QUOTES, "UTF-8").'</td>';

        // Columnas cm: TODAS interactivas (vacias y con valor). Doble-click para
        // mover/setear el valor en la columna cm correspondiente al LARGO.
        for($c=0; $c<$total_cms; $c++)
            {
            $val = "";
            if($largo !== null && $largo == $cms[$c] && $tallos !== null)
                $val = (string)$tallos;
            $html .= '<td class="celda_editable celda_cm" data-field="CM" data-cm="'.$cms[$c].'" style="padding:2px 3px; text-align:center; border:1px solid #ddd;">'.$val.'</td>';
            }

        $precio_u     = ($d["PRECIOUNITARIO"] !== null) ? number_format((float)$d["PRECIOUNITARIO"], 2) : "";
        $precio_t     = ($d["PRECIOTOTAL"] !== null) ? number_format((float)$d["PRECIOTOTAL"], 2) : "";
        $alerta_raw = (string)$d["ALERTA"];

        // ST PRICE editable (PRECIOUNITARIO). TOTAL y ALERTA no editables.
        $html .= '<td class="celda_editable" data-field="PRECIOUNITARIO" style="padding:2px 4px; text-align:right; border:1px solid #ddd;">'.$precio_u.'</td>';
        $html .= '<td style="padding:2px 4px; text-align:right; border:1px solid #ddd;">'.$precio_t.'</td>';
        // Columna ALERTA: icono warning naranja clickeable si hay alerta, icono check verde si no.
        if(trim($alerta_raw) != "")
            {
            // Escape para uso dentro de onclick="messageBox('...')".
            $alerta_js = str_replace(array("\\", "'", "\r\n", "\n", "\r"),
                                     array("\\\\", "\\'", "\\n", "\\n", "\\n"),
                                     $alerta_raw);
            $alerta_js = htmlspecialchars($alerta_js, ENT_QUOTES, "UTF-8");
            $html .= '<td style="padding:2px 4px; text-align:center; border:1px solid #ddd;"><a onclick="messageBox(\''.$alerta_js.'\');" style="cursor:pointer; color:#cc7700;" title="Ver alerta"><i class="icon-warning"></i></a></td>';
            }
        else
            {
            $html .= '<td style="padding:2px 4px; text-align:center; border:1px solid #ddd;"><span style="color:#2e7d32;"><i class="icon-checkmark"></i></span></td>';
            }
        // Columna de opciones: en la primera linea de cada caja, mostrar tambien
        // un boton "+" que agrega una linea adicional a la MISMA caja (mismo
        // NUMEROCAJA y TIPOCAJA). Siempre se muestra el "x" para eliminar.
        $html .= '<td style="padding:2px 4px; text-align:right; border:1px solid #ddd;">';
        if($es_primera_caja)
            {
            $tipo_caja_js = htmlspecialchars(addslashes($tipo_caja), ENT_QUOTES, "UTF-8");
            $html .= '<a onclick="agregar_linea_a_caja('.(int)$codigo_ff.', '.$num_caja.', \''.$tipo_caja_js.'\');" style="cursor:pointer; color:#2e7d32; margin-right:4px;" title="Agregar linea a esta caja"><i class="icon-plus" style="font-size:10px;"></i></a>';
            }
        $html .= '<a onclick="eliminar_linea_detalle('.$codigo_linea.', '.(int)$codigo_ff.');" style="cursor:pointer; color:#88010e;" title="Eliminar linea"><i class="icon-cancel" style="font-size:10px;"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</table>';
    // Boton agregar caja debajo de la tabla (abre dialog para elegir tipo).
    $html .= '<div style="margin-top:6px;"><a onclick="agregar_caja_detalle('.(int)$codigo_ff.');" style="cursor:pointer; color:#88010e; font-size:12px;"><i class="icon-plus"></i> Agregar caja</a></div>';
    return $html;
    }


// Actualiza un campo editable de una linea de detalle_factura_finca.
// Lista blanca de campos: VARIEDAD, LARGO, TALLOSTOTAL, PRECIOUNITARIO.
// Si el campo afecta el total (PRECIOUNITARIO o TALLOSTOTAL), recalcula
// PRECIOTOTAL = TALLOSTOTAL * PRECIOUNITARIO en una segunda query.
function actualizar_celda_detalle_dsft($codigo, $campo, $valor)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo <= 0)
        return "Codigo invalido";

    // Lista blanca de campos editables.
    $editables = array("VARIEDAD", "LARGO", "TALLOSTOTAL", "PRECIOUNITARIO");
    $campo     = strtoupper(trim((string)$campo));
    $valido    = 0;
    $total_ed  = count($editables);
    for($k=0; $k<$total_ed; $k++)
        {
        if($editables[$k] == $campo)
            {
            $valido = 1;
            break;
            }
        }
    if($valido == 0)
        return "Campo no editable";

    // Construir el valor sanitizado segun el tipo de campo.
    if($campo == "VARIEDAD")
        {
        $valor_sql = "'".mysqli_real_escape_string($link, strtoupper(trim((string)$valor)))."'";
        }
    else if($campo == "LARGO" || $campo == "TALLOSTOTAL")
        {
        $valor_int = (int)$valor;
        if($valor_int < 0)
            return "Valor invalido para ".$campo;
        $valor_sql = (string)$valor_int;
        }
    else
        {
        // PRECIOUNITARIO: aceptar decimales.
        $valor_float = (float)str_replace(",", ".", (string)$valor);
        if($valor_float < 0)
            return "Precio invalido";
        $valor_sql = number_format($valor_float, 4, '.', '');
        }

    $sql = "UPDATE detalle_factura_finca
        SET ".$campo." = ".$valor_sql.",
            FECHAMODIFICACION     = NOW(),
            CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);

    // Si cambio el precio o los tallos, recalcular PRECIOTOTAL.
    if($campo == "PRECIOUNITARIO" || $campo == "TALLOSTOTAL")
        {
        $sql_rec = "UPDATE detalle_factura_finca
            SET PRECIOTOTAL = COALESCE(TALLOSTOTAL,0) * COALESCE(PRECIOUNITARIO,0),
                FECHAMODIFICACION = NOW()
            WHERE CODIGO = ".$codigo;
        mysqli_query($link, $sql_rec);
        }

    return "OK";
    }

// Elimina (DELETE fisico) una linea de detalle_factura_finca por CODIGO.
function eliminar_linea_detalle_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo <= 0)
        return "Codigo invalido";
    $sql = "DELETE FROM detalle_factura_finca WHERE CODIGO = ".$codigo;
    $r   = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Restaura el detalle de una factura usando el JSON original guardado en
// factura_finca.RESPUESTACLAUDE (la respuesta cruda de la IA). NO vuelve a
// llamar a Claude/Haiku: solo reusa lo que la IA dijo la primera vez.
// Borra detalle_factura_finca actual y vuelve a insertar desde el JSON.
// Retorna "OK|N" donde N es el numero de lineas reinsertadas, o un mensaje
// de error.
function regenerar_detalle_factura_dsft($codigo_ff)
    {
    global $link;
    $codigo_ff = (int)$codigo_ff;
    if($codigo_ff <= 0)
        return "Codigo invalido";

    // 1) Leer el JSON definitivo (ya limpio y estructurado, guardado al
    //    finalizar el procesamiento de Haiku/OCR/formateador).
    $sql = "SELECT RESPUESTACLAUDE2 FROM factura_finca WHERE CODIGO = ".$codigo_ff;
    $res = mysqli_query($link, $sql);
    if(!$res || mysqli_num_rows($res) == 0)
        return "Factura no encontrada";
    $fila     = mysqli_fetch_assoc($res);
    $json_raw = (string)$fila["RESPUESTACLAUDE2"];
    if(trim($json_raw) == "")
        return "No hay JSON definitivo. Reprocese la factura.";

    // 2) Decodificar directamente (es el JSON ya estructurado).
    $arreglo = json_decode($json_raw, true);
    if(!$arreglo || !isset($arreglo["CAJAS"]))
        return "JSON definitivo invalido o sin CAJAS";

    // 3) Eliminar el detalle actual.
    $sql_del = "DELETE FROM detalle_factura_finca WHERE CODIGOFACTURAFINCA = ".$codigo_ff;
    mysqli_query($link, $sql_del);

    // 4) Re-insertar lineas desde el JSON (misma logica que el flujo
    //    original de inserta_detalle_factura_finca).
    $cajas        = $arreglo["CAJAS"];
    $total_cajas  = count($cajas);
    $indice_linea = 0;
    $lineas_ok    = 0;

    for($i=0; $i<$total_cajas; $i++)
        {
        $numero_caja = isset($cajas[$i]["NUMERO_CAJA"]) ? $cajas[$i]["NUMERO_CAJA"] : null;
        $tipo_caja   = isset($cajas[$i]["TIPO_CAJA"])   ? $cajas[$i]["TIPO_CAJA"]   : null;
        $contenido   = isset($cajas[$i]["CONTENIDO"])   ? $cajas[$i]["CONTENIDO"]   : array();
        $total_lin   = count($contenido);

        for($j=0; $j<$total_lin; $j++)
            {
            $indice_linea++;
            $L = $contenido[$j];
 
            $producto     = isset($L["PRODUCTO"]) ? mysqli_real_escape_string($link, (string)$L["PRODUCTO"]) : "";
            $variedad     = isset($L["VARIEDAD"]) ? mysqli_real_escape_string($link, (string)$L["VARIEDAD"]) : "";
            $largo        = (isset($L["LARGO"]) && $L["LARGO"] !== null) ? (int)$L["LARGO"] : "NULL";
            $grado        = (isset($L["GRADO"]) && $L["GRADO"] !== null) ? "'".mysqli_real_escape_string($link, (string)$L["GRADO"])."'" : "NULL";
            $tallos_ramo  = (isset($L["TALLOS_POR_RAMO"]) && $L["TALLOS_POR_RAMO"] !== null) ? (int)$L["TALLOS_POR_RAMO"] : "NULL";
            $ramos        = (isset($L["RAMOS"]) && $L["RAMOS"] !== null) ? (int)$L["RAMOS"] : "NULL";
            $tallos_total = (isset($L["TALLOS_TOTAL"]) && $L["TALLOS_TOTAL"] !== null) ? (int)$L["TALLOS_TOTAL"] : "NULL";
            $precio_u     = (isset($L["PRECIO_UNITARIO"]) && $L["PRECIO_UNITARIO"] !== null) ? (float)$L["PRECIO_UNITARIO"] : "NULL";
            $precio_t     = (isset($L["PRECIO_TOTAL"]) && $L["PRECIO_TOTAL"] !== null) ? (float)$L["PRECIO_TOTAL"] : "NULL";
            $alerta       = (isset($L["ALERTA"]) && $L["ALERTA"] !== null) ? "'".mysqli_real_escape_string($link, (string)$L["ALERTA"])."'" : "NULL";

            $sql_ins = "INSERT INTO detalle_factura_finca (
                CODIGO, ESTADO, CODIGOFACTURAFINCA, NUMEROCAJA, TIPOCAJA,
                INDICELINEA, PRODUCTO, VARIEDAD, LARGO, GRADO,
                TALLOSPORRAMO, RAMOS, TALLOSTOTAL,
                PRECIOUNITARIO, PRECIOTOTAL, ALERTA,
                CODIGOUSUARIOREGISTRA, FECHAREGISTRO
            ) VALUES (
                0, 1, ".$codigo_ff.",
                ".($numero_caja !== null ? (int)$numero_caja : "NULL").",
                ".($tipo_caja !== null ? "'".mysqli_real_escape_string($link, (string)$tipo_caja)."'" : "NULL").",
                ".$indice_linea.",
                '".$producto."', '".$variedad."',
                ".$largo.", ".$grado.",
                ".$tallos_ramo.", ".$ramos.", ".$tallos_total.",
                ".$precio_u.", ".$precio_t.", ".$alerta.",
                0, NOW()
            )";
  
            $r = mysqli_query($link, $sql_ins);
            if($r)
                $lineas_ok++;
            }
        }

    return "OK|".$lineas_ok;
    }

// Helper interno: siguiente INDICELINEA para una factura.
function _siguiente_indice_linea($codigo_ff)
    {
    global $link;
    $sql = "SELECT COALESCE(MAX(INDICELINEA),0) AS MAXI FROM detalle_factura_finca WHERE CODIGOFACTURAFINCA = ".(int)$codigo_ff;
    $res = mysqli_query($link, $sql);
    if(!$res)
        return 1;
    $fila = mysqli_fetch_assoc($res);
    if(!$fila)
        return 1;
    return ((int)$fila["MAXI"]) + 1;
    }

// Helper interno: valida el TIPOCAJA contra la lista cerrada y lo retorna
// en mayusculas, o "" si es invalido.
function _valida_tipo_caja($tipo_caja)
    {
    $tipo_caja = strtoupper(trim((string)$tipo_caja));
    if($tipo_caja == "FB" || $tipo_caja == "HB" || $tipo_caja == "QB"
       || $tipo_caja == "OB" || $tipo_caja == "EB")
        return $tipo_caja;
    return "";
    }

// Agrega una linea adicional a una caja existente: misma NUMEROCAJA y
// mismo TIPOCAJA. Usada por el icono "+" en la primera linea de cada caja.
function agregar_linea_a_caja_dsft($codigo_ff, $numero_caja, $tipo_caja)
    {
    global $link;
    $codigo_ff   = (int)$codigo_ff;
    $numero_caja = (int)$numero_caja;
    if($codigo_ff <= 0 || $numero_caja <= 0)
        return "Codigos invalidos";

    $tipo_caja = _valida_tipo_caja($tipo_caja);
    if($tipo_caja == "")
        return "Tipo de caja invalido";

    $nuevo_ind     = _siguiente_indice_linea($codigo_ff);
    $tipo_caja_sql = mysqli_real_escape_string($link, $tipo_caja);

    $sql = "INSERT INTO detalle_factura_finca
        (CODIGOFACTURAFINCA, NUMEROCAJA, TIPOCAJA, INDICELINEA, ESTADO, FECHAREGISTRO)
        VALUES (".$codigo_ff.", ".$numero_caja.", '".$tipo_caja_sql."', ".$nuevo_ind.", 1, NOW())";
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Agrega una caja nueva al detalle: NUMEROCAJA = max+1 y TIPOCAJA elegido
// por la usuaria (FB/HB/QB/OB).
function agregar_caja_detalle_dsft($codigo_ff, $tipo_caja)
    {
    global $link;
    $codigo_ff = (int)$codigo_ff;
    if($codigo_ff <= 0)
        return "Factura invalida";

    $tipo_caja = _valida_tipo_caja($tipo_caja);
    if($tipo_caja == "")
        return "Tipo de caja invalido";

    // Siguiente NUMEROCAJA.
    $sql_max_caja = "SELECT COALESCE(MAX(NUMEROCAJA),0) AS MAXI FROM detalle_factura_finca WHERE CODIGOFACTURAFINCA = ".$codigo_ff;
    $res_max_caja = mysqli_query($link, $sql_max_caja);
    $max_caja     = 0;
    if($res_max_caja)
        {
        $fila_mc = mysqli_fetch_assoc($res_max_caja);
        if($fila_mc)
            $max_caja = (int)$fila_mc["MAXI"];
        }
    $nuevo_caja = $max_caja + 1;
    $nuevo_ind  = _siguiente_indice_linea($codigo_ff);

    $tipo_caja_sql = mysqli_real_escape_string($link, $tipo_caja);

    $sql = "INSERT INTO detalle_factura_finca
        (CODIGOFACTURAFINCA, NUMEROCAJA, TIPOCAJA, INDICELINEA, ESTADO, FECHAREGISTRO)
        VALUES (".$codigo_ff.", ".$nuevo_caja.", '".$tipo_caja_sql."', ".$nuevo_ind.", 1, NOW())";
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Asigna la FINCA (proveedor codigo_tipo_proveedor=1) a una factura_finca.
// Usado por el select + boton "Confirmar" del header de cada tarjeta de
// factura en detalle_consolidado_dsft.
function confirmar_finca_factura_dsft($codigo_ff, $codigo_finca)
    {
    global $link;
    $codigo_ff    = (int)$codigo_ff;
    $codigo_finca = (int)$codigo_finca;
    if($codigo_ff <= 0 || $codigo_finca <= 0)
        return "Parametros invalidos";

    $sql = "UPDATE factura_finca
        SET CODIGOFINCA = ".$codigo_finca.",
            FECHAMODIFICACION = NOW(),
            CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo_ff;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Asigna el TIPO DE PRODUCTO a una factura_finca. Usado por el select + boton
// "Confirmar/Cambiar" de tipo del header de cada tarjeta en detalle_consolidado_dsft.
function confirmar_tipo_producto_dsft($codigo_ff, $codigo_tipo)
    {
    global $link;
    $codigo_ff   = (int)$codigo_ff;
    $codigo_tipo = (int)$codigo_tipo;
    if($codigo_ff <= 0 || $codigo_tipo <= 0)
        return "Parametros invalidos";

    // Nombre del tipo, para escribirlo tambien en el detalle.
    $sql_nombre  = "SELECT NOMBRE FROM tipo_producto WHERE CODIGO = ".$codigo_tipo;
    $res_nombre  = mysqli_query($link, $sql_nombre);
    $nombre_tipo = "";
    if($res_nombre && mysqli_num_rows($res_nombre) > 0)
        {
        $fila_nombre = mysqli_fetch_assoc($res_nombre);
        $nombre_tipo = (string)$fila_nombre["NOMBRE"];
        }

    // Cabecera.
    $sql1 = "UPDATE factura_finca
        SET CODIGOTIPOPRODUCTO = ".$codigo_tipo.",
            FECHAMODIFICACION = NOW(),
            CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo_ff;
    $r1 = mysqli_query($link, $sql1);
    if(!$r1)
        return mysqli_error($link);

    // TODAS las lineas del detalle de esa factura.
    $nombre_tipo = mysqli_real_escape_string($link, $nombre_tipo);
    $sql2 = "UPDATE detalle_factura_finca
        SET PRODUCTO = '".$nombre_tipo."',
            CODIGOTIPOPRODUCTO = ".$codigo_tipo.",
            FECHAMODIFICACION = NOW()
        WHERE CODIGOFACTURAFINCA = ".$codigo_ff;
    $r2 = mysqli_query($link, $sql2);
    if(!$r2)
        return mysqli_error($link);

    return "OK";
    }

// Cambia el PRODUCTO (y CODIGOTIPOPRODUCTO) de TODAS las lineas de una caja. Usado
// por el doble-click en la celda PROD del grid (select inline de tipo_producto).
function cambiar_producto_caja_dsft($codigo_ff, $numero_caja, $codigo_tipo)
    {
    global $link;
    $codigo_ff   = (int)$codigo_ff;
    $numero_caja = (int)$numero_caja;
    $codigo_tipo = (int)$codigo_tipo;
    if($codigo_ff <= 0 || $codigo_tipo <= 0)
        return "Parametros invalidos";

    // Nombre del tipo.
    $sql_n = "SELECT NOMBRE FROM tipo_producto WHERE CODIGO = ".$codigo_tipo;
    $res_n = mysqli_query($link, $sql_n);
    if(!$res_n || mysqli_num_rows($res_n) == 0)
        return "Tipo no encontrado";
    $fila_n = mysqli_fetch_assoc($res_n);
    $nombre = mysqli_real_escape_string($link, (string)$fila_n["NOMBRE"]);

    // Todas las lineas de esa caja.
    $sql = "UPDATE detalle_factura_finca
        SET PRODUCTO = '".$nombre."',
            CODIGOTIPOPRODUCTO = ".$codigo_tipo.",
            FECHAMODIFICACION = NOW()
        WHERE CODIGOFACTURAFINCA = ".$codigo_ff."
          AND NUMEROCAJA = ".$numero_caja;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return mysqli_error($link);
    return "OK";
    }

// Desasocia una factura del consolidado al que esta asignada (no la borra).
// Usada por el icono icon-remove del header de cada tarjeta.
function quitar_factura_consolidado_dsft($codigo_ff)
    {
    global $link;
    $codigo_ff = (int)$codigo_ff;
    if($codigo_ff <= 0)
        return "Codigo invalido";

    $sql = "UPDATE factura_finca
        SET CODIGOCONSOLIDADO = NULL,
            FECHAMODIFICACION = NOW(),
            CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo_ff;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }

// Crea una factura "a mano" (sin pasar por la IA): cabecera con
// CODIGOFINCA + FINCA (nombre) + NUMEROFACTURA, asociada al consolidado
// indicado. El detalle queda vacio para que la usuaria lo cargue luego
// con "+ Agregar caja" / dblclick en cm.
// Retorna "OK|CODIGO_NUEVO" o un mensaje de error.
function crear_factura_manual_dsft($codigo_consolidado, $codigo_finca, $nombre_finca, $nfac)
    {
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    $codigo_finca       = (int)$codigo_finca;
    if($codigo_consolidado <= 0)
        return "Consolidado invalido";

    $nombre_finca = mysqli_real_escape_string($link, strtoupper(trim((string)$nombre_finca)));
    $nfac         = mysqli_real_escape_string($link, strtoupper(trim((string)$nfac)));

    $sql = "INSERT INTO factura_finca (
        CODIGOCONSOLIDADO, CODIGOFINCA, FINCA, NUMEROFACTURA,
        ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
    ) VALUES (
        ".$codigo_consolidado.",
        ".$codigo_finca.",
        '".$nombre_finca."',
        '".$nfac."',
        1, 0, NOW()
    )";
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK|".(int)mysqli_insert_id($link);
    }

// Recibe un archivo PDF/Excel del POST y lo guarda como archivo_correo con
// IDCORREO = "UPLOAD_MANUAL". NO crea factura_finca: eso lo hara el CLI de
// procesa_factura_final_cli_haiku cuando se ejecute. Despues del CLI hay
// que llamar a asignar_consolidado_factura_dsft para vincular el
// factura_finca recien creado con el consolidado/finca elegidos.
// Retorna "OK|CODIGO_ADJ" o un mensaje de error.
function subir_archivo_factura_dsft()
    {
    global $link;
    $codigo_consolidado = (int)$_POST["codigo_consolidado"];

    if(!isset($_FILES["archivo"]) || $_FILES["archivo"]["error"] != 0)
        return "Error al recibir el archivo";

    $nombre_archivo = $_FILES["archivo"]["name"];
    $tmp_path       = $_FILES["archivo"]["tmp_name"];
    $tamano         = (int)$_FILES["archivo"]["size"];
    $mimetype       = $_FILES["archivo"]["type"];

    $binario = file_get_contents($tmp_path);
    if($binario === false)
        return "Error al leer el archivo temporal";

    $hash = md5($binario);

    // INSERT en archivo_correo. ARCHIVO es BLOB -> usar prepared statement
    // con send_long_data para soportar archivos grandes (>1 MB). El
    // CODIGOCONSOLIDADO se guarda aqui para tener trazabilidad del destino.
    $sql_adj = "INSERT INTO archivo_correo (
        IDCORREO, CODIGOFINCA, CODIGOCONSOLIDADO,
        NOMBREARCHIVO, MIMETYPE, TAMANOBYTES,
        HASHARCHIVO, ARCHIVO, RUTA, FECHAGUARDADO
    ) VALUES (
        'UPLOAD_MANUAL', NULL, ".$codigo_consolidado.",
        ?, ?, ?, ?, ?, NULL, NOW()
    )";

    $stmt = mysqli_prepare($link, $sql_adj);
    if(!$stmt)
        return "Error preparando INSERT archivo: ".mysqli_error($link);

    $null_blob = NULL;
    mysqli_stmt_bind_param($stmt, "ssisb", $nombre_archivo, $mimetype, $tamano, $hash, $null_blob);
    mysqli_stmt_send_long_data($stmt, 4, $binario);
    $r = mysqli_stmt_execute($stmt);
    if(!$r)
        {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return "Error al insertar archivo: ".$err;
        }
    $codigo_adj = (int)mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);

    if($codigo_adj <= 0)
        return "Error al obtener CODIGO de archivo";

    return "OK|".$codigo_adj;
    }

// Llamada DESPUES de que procesar_factura_web termina: vincula el
// factura_finca recien creado (identificado por CODIGOADJUNTO) con el
// consolidado y la finca que la usuaria eligio en el dialog de upload.
function asignar_consolidado_factura_dsft($codigo_adj, $codigo_consolidado, $codigo_finca, $nombre_finca)
    {
    global $link;
    $codigo_adj         = (int)$codigo_adj;
    $codigo_consolidado = (int)$codigo_consolidado;
    $codigo_finca       = (int)$codigo_finca;
    if($codigo_adj <= 0 || $codigo_consolidado <= 0)
        return "Parametros invalidos";

    // Buscar el factura_finca que el CLI creo para este adjunto.
    $sql_busca = "SELECT CODIGO FROM factura_finca WHERE CODIGOADJUNTO = ".$codigo_adj." LIMIT 1";
    $res_busca = mysqli_query($link, $sql_busca);
    if(!$res_busca || mysqli_num_rows($res_busca) == 0)
        return "No se encontro la factura procesada";
    $fila_busca = mysqli_fetch_assoc($res_busca);
    $codigo_ff  = (int)$fila_busca["CODIGO"];

    $nombre_finca_sql = mysqli_real_escape_string($link, strtoupper(trim((string)$nombre_finca)));

    // Si codigo_finca > 0 lo seteamos, sino dejamos lo que haya puesto la IA.
    $set_finca = ($codigo_finca > 0) ? "CODIGOFINCA = ".$codigo_finca."," : "";
    $set_nombre = ($nombre_finca_sql !== "") ? "FINCA = '".$nombre_finca_sql."'," : "";

    $sql_upd = "UPDATE factura_finca SET
        CODIGOCONSOLIDADO = ".$codigo_consolidado.",
        ".$set_finca."
        ".$set_nombre."
        FECHAMODIFICACION = NOW(),
        CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo_ff;
    $r = mysqli_query($link, $sql_upd);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK|".$codigo_ff;
    }

// Llamada DESPUES de procesar_factura_web cuando la usuaria subio el
// archivo SIN preseleccionar finca: la IA ya extrajo todo (incluyendo
// FINCA y CODIGOFINCA si pudo matchear). Aqui solo le asignamos el
// CODIGOCONSOLIDADO al factura_finca recien creado.
function asignar_consolidado_post_ia_dsft($codigo_adj, $codigo_consolidado)
    {
    global $link;
    $codigo_adj         = (int)$codigo_adj;
    $codigo_consolidado = (int)$codigo_consolidado;
    if($codigo_adj <= 0 || $codigo_consolidado <= 0)
        return "Parametros invalidos";

    $sql = "SELECT CODIGO FROM factura_finca
        WHERE CODIGOADJUNTO = ".$codigo_adj."
        ORDER BY CODIGO DESC LIMIT 1";
    $res = mysqli_query($link, $sql);
    if(!$res || mysqli_num_rows($res) == 0)
        return "No se encontro factura para adjunto ".$codigo_adj;
    $fila      = mysqli_fetch_assoc($res);
    $codigo_ff = (int)$fila["CODIGO"];

    $sql2 = "UPDATE factura_finca
        SET CODIGOCONSOLIDADO = ".$codigo_consolidado.",
            FECHAMODIFICACION = NOW(),
            CODIGOUSUARIOMODIFICA = 0
        WHERE CODIGO = ".$codigo_ff;
    $r = mysqli_query($link, $sql2);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK|".$codigo_ff;
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
  
    // Tabla de 3 columnas: cada celda contiene un badge a ancho completo.
    $html = '<table style="width:100%; border-collapse:collapse;">';
    $col  = 0;
    for($i=1; $i<=$numero; $i++)
        {
        $f       = mysqli_fetch_assoc($resultado);
        $cg      = (int)$f["CODIGO"];
        $numguia = htmlspecialchars((string)$f["NUMEROGUIA"], ENT_QUOTES, 'UTF-8');

        if($col == 0)
            $html .= '<tr>';
 
        $html .= '<td style="padding:2px 3px;">';
        $html .= '<span class="badge_guia" style="display:inline-block; background:#f2f2f2; border:1px solid #ccc; border-radius:4px; padding:1px 4px; font-size:10px; width:100%; box-sizing:border-box; text-align:center;">';
        $html .= $numguia;
        $html .= ' <a onclick="quitar_guia_consolidado('.$cg.');" style="cursor:pointer; color:#88010e; margin-left:4px; font-weight:bold;" title="Quitar guia">&times;</a>';
        $html .= '</span>';
        $html .= '</td>';

        $col++;
        if($col >= 3)
            {
            $html .= '</tr>';
            $col = 0;
            }
        }
    // Si la ultima fila quedo incompleta, rellenar con celdas vacias.
    if($col > 0)
        {
        for($j=$col; $j<3; $j++)
            $html .= '<td></td>';
        $html .= '</tr>';
        }
    $html .= '</table>';
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


// ============================================================================
// TIPO DE PRODUCTO - consola nueva (_dsft). CRUD con borrado logico (ESTADO=-1).
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_tipo_producto($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de tipos de producto (HTML completo: thead + tbody + total).
function lista_tipo_producto_dsft($campo_orden = "NOMBRE", $direccion_orden = "ASC")
    {
    global $link;

    // Validar campo y direccion contra lista blanca.
    $campos_permitidos = array(1=>"CODIGO", 2=>"NOMBRE", 3=>"NOMBREINGLES", 4=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "NOMBRE";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "DESC") ? "DESC" : "ASC";

    $sql = "SELECT CODIGO, NOMBRE, NOMBREINGLES, NOMBRERUSO, ESTADO
        FROM tipo_producto
        WHERE ESTADO >= 0
        ORDER BY ".$orden_valido." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=1; $i<=$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']       = $fila['CODIGO'];
        $arreglo[$i]['NOMBRE']       = $fila['NOMBRE'];
        $arreglo[$i]['NOMBREINGLES'] = $fila['NOMBREINGLES'];
        $arreglo[$i]['NOMBRERUSO']   = $fila['NOMBRERUSO'];
        $arreglo[$i]['ESTADO']       = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo = _ind_orden_tipo_producto("CODIGO",       $orden_valido, $direccion_valida);
    $ind_nombre = _ind_orden_tipo_producto("NOMBRE",       $orden_valido, $direccion_valida);
    $ind_ingles = _ind_orden_tipo_producto("NOMBREINGLES", $orden_valido, $direccion_valida);
    $ind_estado = _ind_orden_tipo_producto("ESTADO",       $orden_valido, $direccion_valida);

    $html  = '<table class="grid_tipos">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 7%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 30%; cursor: pointer;" onclick="ordenar_por(\'NOMBRE\')">NOMBRE'.$ind_nombre.'</th>';
    $html .= '<th style="width: 26%; cursor: pointer;" onclick="ordenar_por(\'NOMBREINGLES\')">INGLES'.$ind_ingles.'</th>';
    $html .= '<th style="width: 20%;">RUSO</th>';
    $html .= '<th style="width: 8%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 9%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=1; $i<=$numero; $i++)
        {
        $codigo = (int)$arreglo[$i]['CODIGO'];
        $nombre = htmlspecialchars((string)$arreglo[$i]['NOMBRE'], ENT_QUOTES, 'UTF-8');
        $ingles = htmlspecialchars((string)$arreglo[$i]['NOMBREINGLES'], ENT_QUOTES, 'UTF-8');
        $ruso   = htmlspecialchars((string)$arreglo[$i]['NOMBRERUSO'], ENT_QUOTES, 'UTF-8');
        $estado_n = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_tipo" id="id_grupo_tipo_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$nombre.'" onclick="devuelve_tipo_producto('.$codigo.');"><strong>'.$nombre.'</strong></td>';
        $html .= '<td title="'.$ingles.'" onclick="devuelve_tipo_producto('.$codigo.');">'.$ingles.'</td>';
        $html .= '<td title="'.$ruso.'" onclick="devuelve_tipo_producto('.$codigo.');">'.$ruso.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: devuelve_tipo_producto('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_tipo_producto('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve un tipo de producto como JSON para llenar el formulario.
function devuelve_tipo_producto_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, NOMBRE, NOMBREINGLES, NOMBRERUSO, OBSERVACIONES, ESTADO
        FROM tipo_producto
        WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Tipo de producto no encontrado"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']        = $fila['CODIGO'];
    $respuesta['NOMBRE']        = $fila['NOMBRE'];
    $respuesta['NOMBREINGLES']  = $fila['NOMBREINGLES'];
    $respuesta['NOMBRERUSO']    = $fila['NOMBRERUSO'];
    $respuesta['OBSERVACIONES'] = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']        = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0. Valida NOMBRE obligatorio y unico.
// Retorna "OK|CODIGO" en exito, o mensaje de error.
function grabar_tipo_producto_dsft($codigo, $nombre, $nombre_ingles, $nombre_ruso, $observaciones, $estado, $codigo_usuario)
    {
    global $link;

    // Validacion: NOMBRE obligatorio (identica al JS).
    $nombre = strtoupper(trim((string)$nombre));
    if($nombre == "")
        return "Por favor ingrese el NOMBRE del tipo de producto";

    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    $estado         = (int)$estado;

    // Escape de strings (sobrescribir misma variable).
    $nombre        = mysqli_real_escape_string($link, $nombre);
    $nombre_ingles = mysqli_real_escape_string($link, strtoupper(trim((string)$nombre_ingles)));
    $nombre_ruso   = mysqli_real_escape_string($link, trim((string)$nombre_ruso));
    $observaciones = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    // Validacion: NOMBRE unico (la columna es UNIQUE; chequear todos los registros
    // excluyendo el actual si es edicion).
    $sql_dup = "SELECT CODIGO
        FROM tipo_producto
        WHERE NOMBRE = '".$nombre."'";
    if($codigo > 0)
        $sql_dup .= " AND CODIGO <> ".$codigo;
    $res_dup = mysqli_query($link, $sql_dup);
    if($res_dup && mysqli_num_rows($res_dup) > 0)
        return "Ya existe un tipo de producto con el NOMBRE '".$nombre."'";

    if($codigo == 0)
        {
        $sql = "INSERT INTO tipo_producto (
            NOMBRE, NOMBREINGLES, NOMBRERUSO, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            '".$nombre."', '".$nombre_ingles."', '".$nombre_ruso."', '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        $r = mysqli_query($link, $sql);
        if(!$r)
            return "Error SQL: ".mysqli_error($link);
        $codigo_final = mysqli_insert_id($link);
        }
    else
        {
        $sql = "UPDATE tipo_producto SET
            NOMBRE                = '".$nombre."',
            NOMBREINGLES          = '".$nombre_ingles."',
            NOMBRERUSO            = '".$nombre_ruso."',
            OBSERVACIONES         = '".$observaciones."',
            ESTADO                = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION     = NOW()
            WHERE CODIGO = ".$codigo;
        $r = mysqli_query($link, $sql);
        if(!$r)
            return "Error SQL: ".mysqli_error($link);
        $codigo_final = $codigo;
        }

    return "OK|".$codigo_final;
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_tipo_producto_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE tipo_producto SET
        ESTADO                = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION     = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }


// ============================================================================
// TIPO DE COBRO - consola nueva (_dsft). CRUD con borrado logico (ESTADO=-1).
// ============================================================================

// Helper interno para indicador de ordenamiento (triangulo ASC/DESC).
function _ind_orden_tipo_cobro($campo, $orden_valido, $direccion_valida)
    {
    if($orden_valido != $campo)
        return "";
    return ($direccion_valida == "ASC") ? " &#9650;" : " &#9660;";
    }

// Lista el grid de tipos de cobro (HTML completo: thead + tbody + total).
function lista_tipo_cobro_dsft($campo_orden = "NOMBRE", $direccion_orden = "ASC")
    {
    global $link;

    // Validar campo y direccion contra lista blanca.
    $campos_permitidos = array(1=>"CODIGO", 2=>"NOMBRE", 3=>"NOMBREINGLES", 4=>"ESTADO");
    $total_campos = count($campos_permitidos);
    $orden_valido = "NOMBRE";
    for($c=1; $c<=$total_campos; $c++)
        {
        if($campos_permitidos[$c] == $campo_orden)
            {
            $orden_valido = $campo_orden;
            break;
            }
        }
    $direccion_valida = ($direccion_orden == "DESC") ? "DESC" : "ASC";

    $sql = "SELECT CODIGO, NOMBRE, NOMBREINGLES, ESTADO
        FROM tipo_cobro
        WHERE ESTADO >= 0
        ORDER BY ".$orden_valido." ".$direccion_valida;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado)
        return '<div style="padding: 10px; color: #88010e;">Error SQL: '.htmlspecialchars(mysqli_error($link)).'</div>';
    $numero = mysqli_num_rows($resultado);

    $arreglo = array();
    for($i=1; $i<=$numero; $i++)
        {
        $fila = mysqli_fetch_array($resultado);
        $arreglo[$i]['CODIGO']       = $fila['CODIGO'];
        $arreglo[$i]['NOMBRE']       = $fila['NOMBRE'];
        $arreglo[$i]['NOMBREINGLES'] = $fila['NOMBREINGLES'];
        $arreglo[$i]['ESTADO']       = $fila['ESTADO'];
        }

    // Indicadores de ordenamiento por columna.
    $ind_codigo = _ind_orden_tipo_cobro("CODIGO",       $orden_valido, $direccion_valida);
    $ind_nombre = _ind_orden_tipo_cobro("NOMBRE",       $orden_valido, $direccion_valida);
    $ind_ingles = _ind_orden_tipo_cobro("NOMBREINGLES", $orden_valido, $direccion_valida);
    $ind_estado = _ind_orden_tipo_cobro("ESTADO",       $orden_valido, $direccion_valida);

    $html  = '<table class="grid_tipos">';
    $html .= '<thead><tr>';
    $html .= '<th style="width: 8%; cursor: pointer;" onclick="ordenar_por(\'CODIGO\')">COD'.$ind_codigo.'</th>';
    $html .= '<th style="width: 42%; cursor: pointer;" onclick="ordenar_por(\'NOMBRE\')">NOMBRE'.$ind_nombre.'</th>';
    $html .= '<th style="width: 30%; cursor: pointer;" onclick="ordenar_por(\'NOMBREINGLES\')">INGLES'.$ind_ingles.'</th>';
    $html .= '<th style="width: 8%; cursor: pointer;" onclick="ordenar_por(\'ESTADO\')">EST'.$ind_estado.'</th>';
    $html .= '<th style="width: 12%;">OPC</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    for($i=1; $i<=$numero; $i++)
        {
        $codigo = (int)$arreglo[$i]['CODIGO'];
        $nombre = htmlspecialchars((string)$arreglo[$i]['NOMBRE'], ENT_QUOTES, 'UTF-8');
        $ingles = htmlspecialchars((string)$arreglo[$i]['NOMBREINGLES'], ENT_QUOTES, 'UTF-8');
        $estado_n = (int)$arreglo[$i]['ESTADO'];
        if($estado_n == 1)
            $estado_label = '<span style="color: #2e7d32; font-weight: bold;">ACT</span>';
        else
            $estado_label = '<span style="color: #888;">INA</span>';

        $html .= '<tr class="grupo_tipo" id="id_grupo_tipo_'.$codigo.'">';
        $html .= '<td class="td_centro">'.$codigo.'</td>';
        $html .= '<td title="'.$nombre.'" onclick="devuelve_tipo_cobro('.$codigo.');"><strong>'.$nombre.'</strong></td>';
        $html .= '<td title="'.$ingles.'" onclick="devuelve_tipo_cobro('.$codigo.');">'.$ingles.'</td>';
        $html .= '<td class="td_centro">'.$estado_label.'</td>';
        $html .= '<td class="td_opc">';
        $html .= '<a href="javascript: devuelve_tipo_cobro('.$codigo.');" title="Editar"><i class="icon-pencil fg-brown"></i></a>';
        $html .= '<a href="javascript: elimina_tipo_cobro('.$codigo.');" title="Eliminar"><i class="icon-cancel fg-darkRed"></i></a>';
        $html .= '</td>';
        $html .= '</tr>';
        }

    $html .= '</tbody></table>';
    $html .= '<div style="text-align: right; font-size: 11px; color: #666; padding: 5px;">Total: '.$numero.' registros</div>';
    return $html;
    }

// Devuelve un tipo de cobro como JSON para llenar el formulario.
function devuelve_tipo_cobro_dsft($codigo)
    {
    global $link;
    $codigo = (int)$codigo;
    if($codigo == 0)
        return json_encode(array("ERROR" => "Codigo invalido"));

    $sql = "SELECT CODIGO, NOMBRE, NOMBREINGLES, NOMBRERUSO, OBSERVACIONES, ESTADO
        FROM tipo_cobro
        WHERE CODIGO = ".$codigo;
    $resultado = mysqli_query($link, $sql);
    if(!$resultado || mysqli_num_rows($resultado) == 0)
        return json_encode(array("ERROR" => "Tipo de cobro no encontrado"));

    $fila = mysqli_fetch_array($resultado);
    $respuesta = array();
    $respuesta['CODIGO']        = $fila['CODIGO'];
    $respuesta['NOMBRE']        = $fila['NOMBRE'];
    $respuesta['NOMBREINGLES']  = $fila['NOMBREINGLES'];
    $respuesta['NOMBRERUSO']    = $fila['NOMBRERUSO'];
    $respuesta['OBSERVACIONES'] = $fila['OBSERVACIONES'];
    $respuesta['ESTADO']        = $fila['ESTADO'];

    return json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }

// INSERT si $codigo == 0, UPDATE si > 0. Valida NOMBRE obligatorio y unico.
// Retorna "OK|CODIGO" en exito, o mensaje de error.
function grabar_tipo_cobro_dsft($codigo, $nombre, $nombre_ingles, $nombre_ruso, $observaciones, $estado, $codigo_usuario)
    {
    global $link;

    // Validacion: NOMBRE obligatorio (identica al JS).
    $nombre = strtoupper(trim((string)$nombre));
    if($nombre == "")
        return "Por favor ingrese el NOMBRE del tipo de cobro";

    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    $estado         = (int)$estado;

    // Escape de strings (sobrescribir misma variable).
    $nombre        = mysqli_real_escape_string($link, $nombre);
    $nombre_ingles = mysqli_real_escape_string($link, strtoupper(trim((string)$nombre_ingles)));
    $nombre_ruso   = mysqli_real_escape_string($link, trim((string)$nombre_ruso));
    $observaciones = mysqli_real_escape_string($link, strtoupper(trim((string)$observaciones)));

    // Validacion: NOMBRE unico (la columna es UNIQUE; chequear todos los registros
    // excluyendo el actual si es edicion).
    $sql_dup = "SELECT CODIGO
        FROM tipo_cobro
        WHERE NOMBRE = '".$nombre."'";
    if($codigo > 0)
        $sql_dup .= " AND CODIGO <> ".$codigo;
    $res_dup = mysqli_query($link, $sql_dup);
    if($res_dup && mysqli_num_rows($res_dup) > 0)
        return "Ya existe un tipo de cobro con el NOMBRE '".$nombre."'";

    if($codigo == 0)
        {
        $sql = "INSERT INTO tipo_cobro (
            NOMBRE, NOMBREINGLES, NOMBRERUSO, OBSERVACIONES,
            ESTADO, CODIGOUSUARIOREGISTRA, FECHAREGISTRO
        ) VALUES (
            '".$nombre."', '".$nombre_ingles."', '".$nombre_ruso."', '".$observaciones."',
            ".$estado.", ".$codigo_usuario.", NOW()
        )";
        $r = mysqli_query($link, $sql);
        if(!$r)
            return "Error SQL: ".mysqli_error($link);
        $codigo_final = mysqli_insert_id($link);
        }
    else
        {
        $sql = "UPDATE tipo_cobro SET
            NOMBRE                = '".$nombre."',
            NOMBREINGLES          = '".$nombre_ingles."',
            NOMBRERUSO            = '".$nombre_ruso."',
            OBSERVACIONES         = '".$observaciones."',
            ESTADO                = ".$estado.",
            CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
            FECHAMODIFICACION     = NOW()
            WHERE CODIGO = ".$codigo;
        $r = mysqli_query($link, $sql);
        if(!$r)
            return "Error SQL: ".mysqli_error($link);
        $codigo_final = $codigo;
        }

    return "OK|".$codigo_final;
    }

// Eliminacion logica: ESTADO = -1. NO hace DELETE fisico.
function elimina_tipo_cobro_dsft($codigo, $codigo_usuario)
    {
    global $link;
    $codigo         = (int)$codigo;
    $codigo_usuario = (int)$codigo_usuario;
    if($codigo == 0)
        return "Codigo invalido";

    $sql = "UPDATE tipo_cobro SET
        ESTADO                = -1,
        CODIGOUSUARIOMODIFICA = ".$codigo_usuario.",
        FECHAMODIFICACION     = NOW()
        WHERE CODIGO = ".$codigo;
    $r = mysqli_query($link, $sql);
    if(!$r)
        return "Error SQL: ".mysqli_error($link);
    return "OK";
    }


// ============================================================================
// EXCEL DEL CONSOLIDADO (PhpSpreadsheet). Endpoint de DESCARGA: no hace echo en
// el camino feliz, manda headers de xlsx y escribe a php://output. Se llama
// desde el dispatch con exit() inmediato.
// NOTA: PhpSpreadsheet vive en el server (SiteGround); en local no esta. Los
// "use" no van dentro de funcion: se usan nombres de clase completos.
// ============================================================================
function generar_consolidado_dsft($codigo_consolidado, $formato = "xlsx")
    { 
    global $link;
    $codigo_consolidado = (int)$codigo_consolidado;
    $formato            = strtolower(trim((string)$formato));

    require_once __DIR__ . '/vendor/autoload.php';

    // 1) Cabecera del consolidado. Nombres de columna REALES: MAYUSCULAS en
    // consolidado/marcacion; pais es tabla legacy con minusculas.
    $sql_cons = "SELECT c.CODIGO AS CODIGO,
        c.FECHAVUELO AS FECHAVUELO,
        c.CODIGOCLIENTE AS CODIGOCLIENTE,
        m.NOMBREMARCACION AS MARCACION,
        p.nombre_pais AS PAIS
        FROM consolidado c
        LEFT JOIN marcacion m ON c.CODIGOMARCACION = m.CODIGO
        LEFT JOIN pais p ON c.CODIGOPAIS = p.codigo_pais
        WHERE c.CODIGO = ".$codigo_consolidado;
    $res_cons = mysqli_query($link, $sql_cons);
    if(!$res_cons || mysqli_num_rows($res_cons) == 0)
        {
        echo "Consolidado no encontrado";
        return;
        }
    $cons = mysqli_fetch_assoc($res_cons);

    // Guias (AWBs) del consolidado.
    $sql_guias = "SELECT g.NUMEROGUIA AS NUMEROGUIA
        FROM guia_consolidado gc
        INNER JOIN guia g ON gc.CODIGOGUIA = g.CODIGO
        WHERE gc.CODIGOCONSOLIDADO = ".$codigo_consolidado;
    $res_guias   = mysqli_query($link, $sql_guias);
    $awbs        = array();
    $total_guias = ($res_guias) ? mysqli_num_rows($res_guias) : 0;
    for($gi=1; $gi<=$total_guias; $gi++)
        {
        $fg     = mysqli_fetch_assoc($res_guias);
        $awbs[] = $fg["NUMEROGUIA"];
        }
    $awb_str = implode(", ", $awbs);

    // 2) Detalle de todas las facturas del consolidado, agrupado por PRODUCTO.
    // ORDENGRUPO viene de tipo_producto.CAMPOE1 (ROSAS=1, SPRAY=2, CLAVEL=3...),
    // 99 si el PRODUCTO no matchea ningun tipo. Se usa un SUBQUERY con MIN (no un
    // JOIN) para NO multiplicar lineas cuando un PRODUCTO matchea varios tipos.
    $sql_det = "SELECT
        d.CODIGO, d.CODIGOFACTURAFINCA, d.NUMEROCAJA, d.TIPOCAJA,
        d.PRODUCTO, d.VARIEDAD, d.LARGO, d.TALLOSTOTAL,
        d.PRECIOUNITARIO, d.PRECIOTOTAL,
        ff.FINCA,
        COALESCE((
            SELECT MIN(tp.CAMPOE1)
            FROM tipo_producto tp
            WHERE UPPER(TRIM(tp.NOMBRE)) = UPPER(TRIM(d.PRODUCTO))
               OR UPPER(TRIM(tp.NOMBREINGLES)) = UPPER(TRIM(d.PRODUCTO))
               OR UPPER(TRIM(tp.NOMBRE)) LIKE CONCAT(UPPER(TRIM(d.PRODUCTO)), '%')
               OR UPPER(TRIM(tp.NOMBREINGLES)) LIKE CONCAT(UPPER(TRIM(d.PRODUCTO)), '%')
        ), 99) AS ORDENGRUPO
        FROM detalle_factura_finca d
        INNER JOIN factura_finca ff ON d.CODIGOFACTURAFINCA = ff.CODIGO
        WHERE ff.CODIGOCONSOLIDADO = ".$codigo_consolidado."
          AND d.ESTADO >= 0
        ORDER BY ORDENGRUPO, d.PRODUCTO, ff.FINCA, d.NUMEROCAJA, d.INDICELINEA";
    $res_det   = mysqli_query($link, $sql_det);
    $total_det = ($res_det) ? mysqli_num_rows($res_det) : 0;

    // Agrupar por PRODUCTO (sin foreach: por clave indexada).
    $grupos = array();
    for($di=1; $di<=$total_det; $di++)
        {
        $fila = mysqli_fetch_assoc($res_det);
        $prod = strtoupper(trim((string)$fila["PRODUCTO"]));
        if($prod == "")
            $prod = "OTRO";
        if(!isset($grupos[$prod]))
            $grupos[$prod] = array();
        $grupos[$prod][] = $fila;
        }

    // 3) Construir el Excel.
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    // Fondo blanco por defecto en toda la hoja. 
    $spreadsheet->getDefaultStyle()->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('FFFFFF');

    $sheet->setTitle('Consolidado');

    $cms = array(40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150);

    // Anchos de columna: A=FB, B=FARM, C=VARIETY, D..O=cm, P=ST PRICE, Q=TOTAL.
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(22);
    $sheet->getColumnDimension('C')->setWidth(22);
    $col_letra = 'D';
    $total_cms = count($cms);
    for($c=0; $c<$total_cms; $c++)
        {
        $sheet->getColumnDimension($col_letra)->setWidth(7);
        $col_letra++;
        }
    $sheet->getColumnDimension('P')->setWidth(9);
    $sheet->getColumnDimension('Q')->setWidth(10);

    // --- HEADER (filas 1-7) ---
    $sheet->setCellValue('D1', 'INVOICE NUM:');
    $sheet->setCellValue('G1', $codigo_consolidado);
    $sheet->getStyle('D1')->getFont()->setBold(true);

    $sheet->setCellValue('D2', 'SHIP DATE:');
    $sheet->setCellValue('G2', $cons["FECHAVUELO"]);
    $sheet->getStyle('D2')->getFont()->setBold(true);

    $sheet->setCellValue('D3', 'CUSTOMER NAME:');
    $sheet->setCellValue('G3', strtoupper((string)$cons["MARCACION"]));
    $sheet->getStyle('D3')->getFont()->setBold(true);

    $sheet->setCellValue('D4', 'ADRESS:');
    $sheet->setCellValue('G4', strtoupper((string)$cons["PAIS"]));
    $sheet->getStyle('D4')->getFont()->setBold(true);

    $sheet->setCellValue('A5', 'DIVA FLOREX S.A.S.  RUC 1793189840001');
    $sheet->setCellValue('D5', 'LABEL:');
    $sheet->setCellValue('G5', strtoupper((string)$cons["MARCACION"]));
    $sheet->getStyle('D5')->getFont()->setBold(true);

    $sheet->setCellValue('A6', 'Av. 6 de Diciembre N34-155 Dpt 84, Quito, Ecuador');
    $sheet->setCellValue('D6', 'AWB NUMBER:');
    $sheet->setCellValue('G6', $awb_str);
    $sheet->getStyle('D6')->getFont()->setBold(true);

    $sheet->setCellValue('A7', 'Phone number: +593999135857, +59326010256');

    // Fuente general del documento.
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    $fila_actual = 9;

    // Mapeo de PRODUCTO (BD) -> titulo en ingles para la seccion.
    $titulos_producto = array(
        'ROSA' => 'ROSES', 'CLAVEL' => 'CARNATIONS',
        'GYPSOPHILA' => 'GYPSOPHILA', 'GYPSO' => 'GYPSOPHILA',
        'HIDRANGEA' => 'HYDRANGEAS', 'ALSTROEMERIA' => 'ALSTROEMERIA',
        'CARNATION' => 'CARNATIONS', 'SPRAY' => 'SPRAY ROSES',
        'ROSA SPRAY' => 'SPRAY ROSES', 'CLAVEL SPRAY' => 'SPRAY CARNATIONS'
        );

    // --- SECCIONES POR PRODUCTO ---
    $keys_grupos    = array_keys($grupos);
    $total_grupos   = count($keys_grupos);
    $resumen_grupos = array();
    for($g=0; $g<$total_grupos; $g++)
        {
        $nombre_grupo = $keys_grupos[$g];
        $lineas       = $grupos[$nombre_grupo];
        $total_lineas = count($lineas);

        // Titulo del grupo.
        $titulo = isset($titulos_producto[$nombre_grupo]) ? $titulos_producto[$nombre_grupo] : strtoupper($nombre_grupo);
        $sheet->setCellValue('A'.$fila_actual, $titulo);
        $sheet->getStyle('A'.$fila_actual)->getFont()->setBold(true)->setSize(11);
        $fila_actual++;

        // Header de columnas.
        $headers = array('FB', 'FARM', 'VARIETY');
        for($c=0; $c<$total_cms; $c++)
            $headers[] = $cms[$c].'cm';
        $headers[] = 'ST PRICE';
        $headers[] = 'TOTAL';

        $col           = 'A';
        $total_headers = count($headers);
        for($h=0; $h<$total_headers; $h++)
            {
            $sheet->setCellValue($col.$fila_actual, $headers[$h]);
            $col++;
            }

        // Estilo header: fondo crimson, texto blanco, centrado, bold.
        $sheet->getStyle('A'.$fila_actual.':Q'.$fila_actual)->applyFromArray(
            array(
            'font'      => array('bold' => true, 'color' => array('rgb' => 'FFFFFF'), 'size' => 9),
            'fill'      => array('fillType' => 'solid', 'startColor' => array('rgb' => '88010E')),
            'alignment' => array('horizontal' => 'center')
            ));
        $fila_actual++;

        // Acumuladores de la seccion (para la fila TOTAL).
        $total_fb           = 0;
        $total_cms_grupo    = array();
        for($tc=0; $tc<$total_cms; $tc++)
            $total_cms_grupo[$tc] = 0;
        $total_costo_grupo  = 0;
        $total_tallos_grupo = 0;
        $total_piezas_grupo = 0;

        // Datos.
        $caja_anterior = -1;
        for($l=0; $l<$total_lineas; $l++)
            {
            $lin       = $lineas[$l];
            $num_caja  = (int)$lin["NUMEROCAJA"];
            $tipo_caja = strtoupper(trim((string)$lin["TIPOCAJA"]));
            $largo     = ($lin["LARGO"] !== null) ? (int)$lin["LARGO"] : null;
            $tallos    = ($lin["TALLOSTOTAL"] !== null) ? (int)$lin["TALLOSTOTAL"] : null;

            // FB: solo en la primera linea de cada caja (fraccion segun tipo).
            $fb = "";
            if($num_caja != $caja_anterior)
                {
                if($tipo_caja == "FB")
                    $fb = 1;
                else if($tipo_caja == "HB")
                    $fb = 0.5;
                else if($tipo_caja == "QB")
                    $fb = 0.25;
                else if($tipo_caja == "OB" || $tipo_caja == "EB")
                    $fb = 0.125;
                $caja_anterior = $num_caja;
                $total_piezas_grupo++;
                }

            $sheet->setCellValue('A'.$fila_actual, $fb);
            $sheet->setCellValue('B'.$fila_actual, strtoupper((string)$lin["FINCA"]));
            $sheet->setCellValue('C'.$fila_actual, strtoupper((string)$lin["VARIEDAD"]));

            // Columnas cm (D=40cm .. O=150cm) segun el LARGO de la linea.
            if($largo !== null && $tallos !== null)
                {
                $idx_cm = array_search($largo, $cms);
                if($idx_cm !== false)
                    {
                    $col_cm = chr(ord('D') + $idx_cm);
                    $sheet->setCellValue($col_cm.$fila_actual, $tallos);
                    }
                }

            if($lin["PRECIOUNITARIO"] !== null)
                $sheet->setCellValue('P'.$fila_actual, (float)$lin["PRECIOUNITARIO"]);
            if($lin["PRECIOTOTAL"] !== null)
                $sheet->setCellValue('Q'.$fila_actual, (float)$lin["PRECIOTOTAL"]);

            // Formato numerico en P y Q (numeros crudos para que Excel sume).
            $sheet->getStyle('P'.$fila_actual.':Q'.$fila_actual)->getNumberFormat()->setFormatCode('#,##0.00');

            // Acumular totales de la seccion.
            if($fb !== "")
                $total_fb += (float)$fb;
            if($largo !== null && $tallos !== null)
                {
                $idx_cm = array_search($largo, $cms);
                if($idx_cm !== false)
                    $total_cms_grupo[$idx_cm] += $tallos;
                $total_tallos_grupo += $tallos;
                }
            if($lin["PRECIOTOTAL"] !== null)
                $total_costo_grupo += (float)$lin["PRECIOTOTAL"];

            // Bordes finos + alineacion + tamano.
            $sheet->getStyle('A'.$fila_actual.':Q'.$fila_actual)->applyFromArray(
                array('borders' => array('allBorders' => array(
                    'borderStyle' => 'thin', 'color' => array('rgb' => 'CCCCCC')
                    ))));  
            $sheet->getStyle('A'.$fila_actual)->getAlignment()->setHorizontal('center');
            $sheet->getStyle('D'.$fila_actual.':Q'.$fila_actual)->getAlignment()->setHorizontal('right');
            $sheet->getStyle('A'.$fila_actual.':Q'.$fila_actual)->getFont()->setSize(9);

            $fila_actual++;
            }

        // Fila TOTAL de la seccion.
        $sheet->setCellValue('A'.$fila_actual, 'TOTAL');
        $sheet->setCellValue('B'.$fila_actual, number_format($total_fb, 1));
        for($tc=0; $tc<$total_cms; $tc++)
            {
            $col_cm = chr(ord('D') + $tc);
            if($total_cms_grupo[$tc] > 0)
                $sheet->setCellValue($col_cm.$fila_actual, $total_cms_grupo[$tc]);
            }
        $sheet->setCellValue('Q'.$fila_actual, $total_costo_grupo);
        $sheet->getStyle('Q'.$fila_actual)->getNumberFormat()->setFormatCode('#,##0.00');

        // Estilo: bold, borde superior medio, fondo gris claro.
        $sheet->getStyle('A'.$fila_actual.':Q'.$fila_actual)->applyFromArray(
            array(
            'font'    => array('bold' => true, 'size' => 9),
            'borders' => array('top' => array(
                'borderStyle' => 'medium',
                'color'       => array('rgb' => '333333')
                )),
            'fill'    => array('fillType' => 'solid',
                'startColor' => array('rgb' => 'F0F0F0'))
            ));

        // Guardar totales de la seccion para la tabla resumen.
        $resumen_grupos[] = array(
            'PRODUCTO' => $titulo,
            'FULLES'   => $total_fb,
            'PIEZAS'   => $total_piezas_grupo,
            'TALLOS'   => $total_tallos_grupo,
            'COSTO'    => $total_costo_grupo
            );

        $fila_actual++;

        // Espacio entre secciones.
        $fila_actual += 2;
        }

    // --- TABLA RESUMEN (al final, tras 2 filas en blanco) ---
    $fila_actual += 3;

    // Header con merge: PRODUCTO(A:C) #FULLES(D:E) #PIEZAS(F:G) #TALLOS(H:I) COSTO(J:K).
    $sheet->mergeCells('A'.$fila_actual.':C'.$fila_actual);
    $sheet->setCellValue('A'.$fila_actual, 'PRODUCTO');
    $sheet->mergeCells('D'.$fila_actual.':E'.$fila_actual);
    $sheet->setCellValue('D'.$fila_actual, '# FULLES');
    $sheet->mergeCells('F'.$fila_actual.':G'.$fila_actual);
    $sheet->setCellValue('F'.$fila_actual, '# PIEZAS');
    $sheet->mergeCells('H'.$fila_actual.':I'.$fila_actual);
    $sheet->setCellValue('H'.$fila_actual, '# TALLOS');
    $sheet->mergeCells('J'.$fila_actual.':K'.$fila_actual);
    $sheet->setCellValue('J'.$fila_actual, 'COSTO');

    $sheet->getStyle('A'.$fila_actual.':K'.$fila_actual)->applyFromArray(
        array(
        'font'      => array('bold' => true, 'color' => array('rgb' => 'FFFFFF'), 'size' => 9),
        'fill'      => array('fillType' => 'solid', 'startColor' => array('rgb' => '88010E')),
        'alignment' => array('horizontal' => 'center')
        ));
    $fila_actual++;

    // Una fila por grupo + acumulado general.
    $super_fulles = 0;
    $super_piezas = 0;
    $super_tallos = 0;
    $super_costo  = 0;

    $total_resumen = count($resumen_grupos);
    for($r=0; $r<$total_resumen; $r++)
        {
        $rg = $resumen_grupos[$r];
        $sheet->mergeCells('A'.$fila_actual.':C'.$fila_actual);
        $sheet->setCellValue('A'.$fila_actual, $rg['PRODUCTO']);
        $sheet->mergeCells('D'.$fila_actual.':E'.$fila_actual);
        $sheet->setCellValue('D'.$fila_actual, number_format($rg['FULLES'], 1));
        $sheet->mergeCells('F'.$fila_actual.':G'.$fila_actual);
        $sheet->setCellValue('F'.$fila_actual, $rg['PIEZAS']);
        $sheet->mergeCells('H'.$fila_actual.':I'.$fila_actual);
        $sheet->setCellValue('H'.$fila_actual, $rg['TALLOS']);
        $sheet->mergeCells('J'.$fila_actual.':K'.$fila_actual);
        $sheet->setCellValue('J'.$fila_actual, number_format($rg['COSTO'], 2));

        $sheet->getStyle('A'.$fila_actual.':K'.$fila_actual)->applyFromArray(
            array(
            'borders' => array('allBorders' => array(
                'borderStyle' => 'thin',
                'color'       => array('rgb' => 'CCCCCC')
                )),
            'fill'    => array('fillType' => 'solid',
                'startColor' => array('rgb' => 'FFFFFF'))
            ));
        $sheet->getStyle('D'.$fila_actual.':K'.$fila_actual)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('A'.$fila_actual.':K'.$fila_actual)->getFont()->setSize(9);

        $super_fulles += $rg['FULLES'];
        $super_piezas += $rg['PIEZAS'];
        $super_tallos += $rg['TALLOS'];
        $super_costo  += $rg['COSTO'];

        $fila_actual++;
        }

    // Fila SUPER TOTAL.
    $sheet->mergeCells('A'.$fila_actual.':C'.$fila_actual);
    $sheet->setCellValue('A'.$fila_actual, 'TOTAL');
    $sheet->mergeCells('D'.$fila_actual.':E'.$fila_actual);
    $sheet->setCellValue('D'.$fila_actual, number_format($super_fulles, 1));
    $sheet->mergeCells('F'.$fila_actual.':G'.$fila_actual);
    $sheet->setCellValue('F'.$fila_actual, $super_piezas);
    $sheet->mergeCells('H'.$fila_actual.':I'.$fila_actual);
    $sheet->setCellValue('H'.$fila_actual, $super_tallos);
    $sheet->mergeCells('J'.$fila_actual.':K'.$fila_actual);
    $sheet->setCellValue('J'.$fila_actual, number_format($super_costo, 2));

    $sheet->getStyle('A'.$fila_actual.':K'.$fila_actual)->applyFromArray(
        array(
        'font'    => array('bold' => true, 'size' => 10),
        'borders' => array('top' => array(
            'borderStyle' => 'medium',
            'color'       => array('rgb' => '333333')
            )),
        'fill'    => array('fillType' => 'solid',
            'startColor' => array('rgb' => 'F0F0F0'))
        ));
    $sheet->getStyle('D'.$fila_actual.':K'.$fila_actual)->getAlignment()->setHorizontal('right');

    // 4) Salida segun formato. Limpiar TODO buffer previo para no corromper el
    // binario (xlsx/pdf) con whitespace/warnings de los includes.
    $nombre_archivo = 'CONSOLIDADO_'.$codigo_consolidado.'_'
        .strtoupper((string)$cons["MARCACION"])
        .'_'.date('Ymd_His').'.xlsx';

    while(ob_get_level() > 0)
        ob_end_clean();

    if($formato == "pdf")
        {
        try
            {
            // 1) Guardar xlsx temporal.
            $tmp_xlsx = '/tmp/consolidado_'.$codigo_consolidado.'_'.time().'.xlsx';
            $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmp_xlsx);

            // 2) Cliente Google con el token existente.
            $client = new \Google\Client();
            $client->setAuthConfig('/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json');
            $token_json = file_get_contents('/home/u154-6g3keph3vtcn/credenciales_correos/token.json');
            $token_data = json_decode($token_json, true);
            $client->setAccessToken($token_data);

            // Refrescar el token si expiro (preservando el refresh_token).
            if($client->isAccessTokenExpired())
                {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                $new_token = $client->getAccessToken();
                $new_token['refresh_token'] = $token_data['refresh_token'];
                file_put_contents('/home/u154-6g3keph3vtcn/credenciales_correos/token.json',
                    json_encode($new_token));
                }

            $drive = new \Google\Service\Drive($client);

            // 3) Subir el xlsx a Drive convirtiendolo a Google Sheet.
            $file_metadata = new \Google\Service\Drive\DriveFile();
            $file_metadata->setName('consolidado_temp_'.$codigo_consolidado.'.xlsx');
            $file_metadata->setMimeType('application/vnd.google-apps.spreadsheet');

            $contenido_xlsx = file_get_contents($tmp_xlsx);
            $uploaded = $drive->files->create($file_metadata, array(
                'data'       => $contenido_xlsx,
                'mimeType'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'uploadType' => 'multipart',
                'fields'     => 'id'
                ));

            $file_id = $uploaded->id;

            // 4) Exportar como PDF.
            $pdf_content = $drive->files->export($file_id, 'application/pdf', array(
                'alt' => 'media'
                ));
            $pdf_body = $pdf_content->getBody()->getContents();

            // 5) Borrar el archivo temporal de Drive.
            $drive->files->delete($file_id);

            // 6) Borrar el xlsx temporal local.
            unlink($tmp_xlsx);

            // 7) Servir el PDF.
            while(ob_get_level() > 0)
                ob_end_clean();

            $nombre_pdf = str_replace('.xlsx', '.pdf', $nombre_archivo);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$nombre_pdf.'"');
            header('Content-Length: '.strlen($pdf_body));
            header('Cache-Control: max-age=0');
            echo $pdf_body;
            exit;
            }
        catch(\Exception $e)
            {
            while(ob_get_level() > 0)
                ob_end_clean();
            echo "Error generando PDF: ".$e->getMessage();
            if(isset($tmp_xlsx) && file_exists($tmp_xlsx))
                unlink($tmp_xlsx);
            // Intentar borrar de Drive si alcanzo a subirse.
            if(isset($drive) && isset($file_id))
                {
                try
                    {
                    $drive->files->delete($file_id);
                    }
                catch(\Exception $e2)
                    {
                    }
                }
            exit;
            }
        }
    else
        {
        // Excel directo a la salida.
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$nombre_archivo.'"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        }

    exit;
    }