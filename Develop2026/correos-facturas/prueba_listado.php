<?php

// Prueba de listado: correos recibidos "ayer" en zona horaria America/Guayaquil.
// Solo lectura. Detecta adjuntos PDF / XLSX / XLS.

require_once __DIR__ . '/../vendor/autoload.php';

// Rutas de credenciales (fuera del webroot).
$ruta_client_secret = '/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json';
$ruta_token        = '/home/u154-6g3keph3vtcn/credenciales_correos/token.json';
 
try {
    // Cliente OAuth.
    $client = new Google\Client();
    $client->setAuthConfig($ruta_client_secret);
    $client->addScope(Google\Service\Gmail::GMAIL_READONLY);
    $client->setAccessType('offline');

    // Cargar token guardado.
    if (!file_exists($ruta_token)) {
        throw new Exception('No existe token.json. Ejecute primero oauth_callback.php para autorizar.');
    }
    $token = json_decode(file_get_contents($ruta_token), true);
    $client->setAccessToken($token);

    // Refrescar si esta expirado.
    if ($client->isAccessTokenExpired()) {
        $refresh_token = $client->getRefreshToken();
        if (empty($refresh_token)) {
            throw new Exception('Token expirado y no hay refresh token. Re-autorice con oauth_callback.php.');
        }
        $nuevo_token = $client->fetchAccessTokenWithRefreshToken($refresh_token);
        if (isset($nuevo_token['error'])) {
            throw new Exception('No se pudo refrescar el token: ' . $nuevo_token['error']);
        }
        // Conservar el refresh_token si Google no lo reenvia.
        if (!isset($nuevo_token['refresh_token']) && !empty($refresh_token)) {
            $nuevo_token['refresh_token'] = $refresh_token;
        }
        file_put_contents($ruta_token, json_encode($nuevo_token));
    }

    // Servicio Gmail.
    $service = new Google\Service\Gmail($client);

    // Rango "desde ayer 00:00 hasta ahora" en America/Guayaquil -> timestamps unix.
    $tz          = new DateTimeZone('America/Guayaquil');
    $inicio_ayer = new DateTime('yesterday 00:00:00', $tz);
    $fin_rango   = new DateTime('now', $tz);
    $ts_inicio   = $inicio_ayer->getTimestamp();
    $ts_fin      = $fin_rango->getTimestamp();

    // Query Gmail: in:anywhere cubre "Todos" excluyendo Spam y Papelera.
    // Se excluye al remitente compras2@divaflor.com desde el propio query.
    $query = 'in:anywhere -from:compras2@divaflor.com has:attachment after:' . $ts_inicio . ' before:' . $ts_fin;

    // Listado de mensajes.
    $lista = $service->users_messages->listUsersMessages('me', [
        'q'          => $query,
        'maxResults' => 100,
    ]);

    $mensajes = $lista->getMessages();

    // Normaliza texto: minusculas y quita tildes/dieresis/enie.
    $normalizar_texto = function ($texto) {
        $texto = mb_strtolower((string)$texto, 'UTF-8');
        $reemplazos = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ];
        return strtr($texto, $reemplazos);
    };

    // Palabras y combinaciones que, si aparecen en asunto o nombre de adjunto, descartan el correo.
    $palabras_sueltas = ['credito', 'disponible', 'disponibilidad', 'availability', 'statement'];
    $combinaciones   = [
        ['estado', 'cuenta'],
    ];

    // Retorna true si el texto contiene alguna palabra prohibida o combinacion completa.
    $texto_es_prohibido = function ($texto, $palabras_sueltas, $combinaciones) use ($normalizar_texto) {
        $norm = $normalizar_texto($texto);
        if ($norm === '') {
            return false;
        }
        foreach ($palabras_sueltas as $palabra) {
            if (strpos($norm, $palabra) !== false) {
                return true;
            }
        }
        foreach ($combinaciones as $combo) {
            $todas = true;
            foreach ($combo as $palabra) {
                if (strpos($norm, $palabra) === false) {
                    $todas = false;
                    break;
                }
            }
            if ($todas) {
                return true;
            }
        }
        return false;
    };

    // Palabras que marcan al correo como "factura/invoice" (override de las prohibidas).
    $palabras_factura = ['factura', 'facturas', 'invoice', 'invoices'];

    // Retorna true si el texto contiene alguna palabra de factura/invoice.
    $texto_es_factura = function ($texto, $palabras_factura) use ($normalizar_texto) {
        $norm = $normalizar_texto($texto);
        if ($norm === '') {
            return false;
        }
        foreach ($palabras_factura as $palabra) {
            if (strpos($norm, $palabra) !== false) {
                return true;
            }
        }
        return false;
    };

    // Extrae texto plano del cuerpo recorriendo recursivamente parts text/plain y text/html.
    $extraer_cuerpo_texto = function ($payload) use (&$extraer_cuerpo_texto) {
        $acumulado = '';
        if (!$payload) {
            return $acumulado;
        }
        $mime = method_exists($payload, 'getMimeType') ? $payload->getMimeType() : '';
        if ($mime === 'text/plain' || $mime === 'text/html') {
            $body = $payload->getBody();
            if ($body) {
                $data = $body->getData();
                if (!empty($data)) {
                    $acumulado .= base64_decode(strtr($data, '-_', '+/')) . ' ';
                }
            }
        }
        $sub = method_exists($payload, 'getParts') ? $payload->getParts() : null;
        if (!empty($sub)) {
            foreach ($sub as $parte) {
                $acumulado .= $extraer_cuerpo_texto($parte);
            }
        }
        return $acumulado;
    };

    // Funcion recursiva para recolectar nombres de adjuntos PDF/XLSX/XLS.
    $extensiones_validas = ['pdf', 'xlsx', 'xls'];
    $buscar_adjuntos = function ($parts) use (&$buscar_adjuntos, $extensiones_validas) {
        $encontrados = [];
        if (!is_array($parts)) {
            return $encontrados;
        }
        foreach ($parts as $part) {
            $filename = $part->getFilename();
            if (!empty($filename)) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $extensiones_validas, true)) {
                    $encontrados[] = $filename;
                }
            }
            // Subpartes anidadas (multipart).
            $sub = $part->getParts();
            if (!empty($sub)) {
                $encontrados = array_merge($encontrados, $buscar_adjuntos($sub));
            }
        }
        return $encontrados;
    };
 
    // Render HTML.
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<title>Correos de ayer</title>';
    echo '<style>';
    echo 'body { font-family: -apple-system, "Segoe UI", Arial, sans-serif; font-size: 13px; padding: 15px; color: #222; }';
    echo 'h1 { font-size: 16px; margin: 0 0 10px 0; }';
    echo '.meta { color: #666; font-size: 12px; margin-bottom: 12px; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background: #2c2c2c; color: #fff; text-align: left; padding: 8px 10px; font-weight: 600; font-size: 12px; }';
    echo 'td { padding: 7px 10px; vertical-align: top; border-bottom: 1px solid #e5e5e5; font-size: 12px; }';
    echo 'tr:nth-child(even) td { background: #f7f7f7; }';
    echo '.id { color: #888; font-family: ui-monospace, Menlo, monospace; font-size: 11px; }';
    echo '.adjuntos { color: #88010e; }';
    echo '.empty { padding: 20px; color: #666; }';
    echo '</style></head><body>';

    echo '<h1>Correos recibidos desde ayer</h1>';
    echo '<div class="meta">Rango: '
        . htmlspecialchars($inicio_ayer->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8')
        . ' a '
        . htmlspecialchars($fin_rango->format('Y-m-d H:i'), ENT_QUOTES, 'UTF-8')
        . ' (America/Guayaquil)</div>';

    if (empty($mensajes)) {
        echo '<div class="empty">No se encontraron correos para ayer</div>';
        echo '</body></html>';
        exit;
    }

    echo '<table>';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Fecha</th><th>Remitente</th><th>Asunto</th><th>Adjuntos</th>';
    echo '</tr></thead><tbody>';

    foreach ($mensajes as $msg) {
        // format=full para poder inspeccionar parts y detectar adjuntos.
        $detalle = $service->users_messages->get('me', $msg->getId(), [
            'format' => 'full',
        ]);
 
        $payload  = $detalle->getPayload();
        $headers  = $payload ? $payload->getHeaders() : [];
        $fecha    = '';
        $remite   = '';
        $asunto   = '';
        foreach ($headers as $h) {
            $nombre = strtolower($h->getName());
            if ($nombre === 'date') {
                $fecha = $h->getValue();
            } elseif ($nombre === 'from') {
                $remite = $h->getValue();
            } elseif ($nombre === 'subject') {
                $asunto = $h->getValue();
            }
        }

        // 1) Regla absoluta: si viene de compras2@divaflor.com, descartar siempre.
        if (stripos($remite, 'compras2@divaflor.com') !== false) {
            continue;
        }

        // 2) Extraer adjuntos PDF/XLSX/XLS y cuerpo de texto del payload.
        $adjuntos = [];
        if ($payload) {
            $filename_raiz = $payload->getFilename();
            if (!empty($filename_raiz)) {
                $ext = strtolower(pathinfo($filename_raiz, PATHINFO_EXTENSION));
                if (in_array($ext, $extensiones_validas, true)) {
                    $adjuntos[] = $filename_raiz;
                }
            }
            $adjuntos = array_merge($adjuntos, $buscar_adjuntos($payload->getParts()));
        }
        $cuerpo              = $extraer_cuerpo_texto($payload);
        $nombres_adj_concat  = implode(' ', $adjuntos);

        // 3) Override "factura/invoice": si aparece en asunto, cuerpo o nombre de adjunto, mantener.
        $es_factura = (
            $texto_es_factura($asunto, $palabras_factura) ||
            $texto_es_factura($cuerpo, $palabras_factura) ||
            $texto_es_factura($nombres_adj_concat, $palabras_factura)
        );

        // 4) Filtros normales: solo se aplican si NO es factura.
        if (!$es_factura) {
            // Sin adjuntos PDF/XLSX/XLS -> descartar.
            if (empty($adjuntos)) {
                continue;
            }
            // Asunto con palabras prohibidas -> descartar.
            if ($texto_es_prohibido($asunto, $palabras_sueltas, $combinaciones)) {
                continue;
            }
            // Nombres de adjuntos con palabras prohibidas -> descartar.
            if ($texto_es_prohibido($nombres_adj_concat, $palabras_sueltas, $combinaciones)) {
                continue;
            }
        }

        $adj_render = empty($adjuntos)
            ? '&mdash;'
            : htmlspecialchars(implode(', ', $adjuntos), ENT_QUOTES, 'UTF-8');
 
        echo '<tr>';
        echo '<td class="id">' . htmlspecialchars($msg->getId(), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($remite, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td class="adjuntos">' . $adj_render . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';

} catch (Throwable $e) {
    // Mostrar solo el mensaje del error, sin datos sensibles del token.
    http_response_code(500);
    echo '<pre style="font-family: ui-monospace, Menlo, monospace; color: #88010e;">';
    echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
}
