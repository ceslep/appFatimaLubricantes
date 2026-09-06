<?php
// api/save-to-sheets.php

require __DIR__ . '/vendor/autoload.php'; // Asegúrate de que esta ruta esté bien.

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

$allowed_origins = [
    'https://app.iedeoccidente.com',
    'http://localhost:5173',
    'https://ceslep.github.io'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Leer y decodificar el JSON del frontend
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['message' => 'Entrada JSON inválida.']);
    exit();
}

try {
    $client = new Client();
    $client->setApplicationName('Grupos Fiestas App');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $serviceAccountPath = __DIR__ . '/assets/serviceaccount.json';

    if (!file_exists($serviceAccountPath)) {
        http_response_code(500);
        echo json_encode(['message' => 'Archivo de credenciales no encontrado.']);
        exit();
    }
    $client->setAuthConfig($serviceAccountPath);

    $service = new Sheets($client);

    $spreadsheetId = '1IJGQWq9c6RCvrhSGZSdSACm2sQ_GhcnrchlJX0XRHOE'; // Cambia por tu ID real
    $range = 'Datos!A:B';                  // Adapta a tu hoja y rango

    // Preparar los valores a insertar
    $values = [];
    foreach ($data as $item) {
        $values[] = [
            $item['card'] ?? '',        // Evita errores si key no existe
            $item['teachers'] ?? ''
        ];
    }

    $body = new ValueRange(['values' => $values]);
    $params = ['valueInputOption' => 'RAW'];

    $result = $service->spreadsheets_values->update(
    $spreadsheetId,
    $range,   // ejemplo: 'Datos!A:B'
    $body,
    ['valueInputOption' => 'RAW']
);

    http_response_code(200);
    echo json_encode([
    'message'       => '¡Datos guardados exitosamente!',
    'spreadsheetId' => $result->getSpreadsheetId(),
    'updatedRange'  => $result->getUpdatedRange(),
    'updatedRows'   => $result->getUpdatedRows(),
    'updatedColumns'=> $result->getUpdatedColumns(),
    'updatedCells'  => $result->getUpdatedCells()
]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Error al guardar datos en Google Sheets.',
        'error' => $e->getMessage()
    ]);
}
