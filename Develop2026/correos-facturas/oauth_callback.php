<?php

// Callback OAuth para autorizacion de Gmail (solo lectura).
// Recibe el "code" de Google y lo intercambia por un access/refresh token.

require_once __DIR__ . '/../vendor/autoload.php';

// Configuracion del cliente OAuth de Google.
$client = new Google\Client();
$client->setAuthConfig('/home/u154-6g3keph3vtcn/credenciales_correos/client_secret.json');
$client->addScope(Google\Service\Gmail::GMAIL_READONLY);
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessType('offline'); // necesario para obtener refresh token
$client->setPrompt('consent');      // fuerza devolver refresh token aunque ya haya autorizado

// Sin "code": redirigir al usuario al consentimiento de Google.
if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . $auth_url);
    exit;
}

// Con "code": intercambiar por token.
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    // Mostrar solo el detalle del error, nunca el code ni el token.
    http_response_code(400);
    echo 'Error al obtener el token: ' . htmlspecialchars($token['error'], ENT_QUOTES, 'UTF-8');
    if (isset($token['error_description'])) {
        echo ' - ' . htmlspecialchars($token['error_description'], ENT_QUOTES, 'UTF-8');
    }
    exit;
}

// Guardar el token en el directorio de credenciales (fuera del webroot).
file_put_contents(
    '/home/u154-6g3keph3vtcn/credenciales_correos/token.json',
    json_encode($token)
);

echo 'Autorización completada, ya puede cerrar esta ventana';
