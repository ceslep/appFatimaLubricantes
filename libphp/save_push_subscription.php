<?php
/**
 * save_push_subscription.php - Almacena una suscripción push en la hoja
 * `push_subscriptions` de Google Sheets.
 *
 * Destino en producción: https://app.iedeoccidente.com/gs/save_push_subscription.php
 *
 * La hoja `push_subscriptions` tiene las siguientes columnas (A:H):
 *   A: participantId    - phone del participante (últimos 10 dígitos)
 *   B: participantName  - nombre del participante
 *   C: endpoint         - URL del endpoint de push del navegador
 *   D: p256dh           - clave pública de encriptación (base64url)
 *   E: auth             - secreto de autenticación (base64url)
 *   F: userAgent        - string del navegador/dispositivo
 *   G: subscribedAt     - ISO 8601 de cuándo se suscribió
 *   H: active           - TRUE/FALSE (para desuscripciones suaves)
 *
 * La suscripción es upsert por endpoint: si ya existe una fila con el mismo
 * endpoint, se actualiza en lugar de duplicar.
 *
 * Petición (POST, JSON):
 *   {
 *     "participantId": "3218552353",
 *     "participantName": "Juan Pérez",
 *     "subscription": {
 *       "endpoint": "https://fcm.googleapis.com/...",
 *       "keys": { "p256dh": "...", "auth": "..." }
 *     },
 *     "userAgent": "Mozilla/5.0...",
 *     "subscribedAt": "2026-07-02T12:00:00.000Z"
 *   }
 *
 * Respuestas:
 *   200 { success: true, action: "created"|"updated" }
 *   400 { success: false, error: "..." }
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateValuesRequest;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';
const SPREADSHEET_ID = '1PIo_oLVjQubdbLodigV3cwOfwQ29k-SGsRmbeorI3nM';
const WORKSHEET = 'push_subscriptions';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    // Validar campos requeridos
    foreach (['participantId', 'participantName', 'subscription'] as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Falta el campo requerido: $field");
        }
    }

    $participantId = trim($data['participantId']);
    $participantName = trim($data['participantName']);
    $subscription = $data['subscription'];
    $userAgent = trim($data['userAgent'] ?? '');
    $subscribedAt = trim($data['subscribedAt'] ?? date('c'));

    // Validar subscription
    if (!isset($subscription['endpoint']) || $subscription['endpoint'] === '') {
        throw new Exception('La suscripción debe tener un endpoint válido.');
    }
    if (!isset($subscription['keys']['p256dh']) || !isset($subscription['keys']['auth'])) {
        throw new Exception('La suscripción debe tener keys.p256dh y keys.auth.');
    }

    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['keys']['p256dh'];
    $auth = $subscription['keys']['auth'];

    // Conectar a Google Sheets
    $client = new Client();
    $client->setApplicationName('Polla Mundialista Push Notifications');
    $client->setScopes([Sheets::SPREADSHEETS]);
    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    // Buscar si ya existe una suscripción con este endpoint (upsert)
    $range = WORKSHEET . '!A2:H1000';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $rows = $response->getValues() ?: [];

    $existingRowIndex = null;
    foreach ($rows as $i => $row) {
        $rowEndpoint = trim((string)($row[2] ?? ''));
        if ($rowEndpoint === $endpoint) {
            $existingRowIndex = $i;
            break;
        }
    }

    $newRow = [
        $participantId,
        $participantName,
        $endpoint,
        $p256dh,
        $auth,
        $userAgent,
        $subscribedAt,
        'TRUE'
    ];

    if ($existingRowIndex !== null) {
        // Actualizar fila existente
        $sheetRow = $existingRowIndex + 2;
        $body = new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => [
                [
                    'range' => WORKSHEET . "!A{$sheetRow}:H{$sheetRow}",
                    'values' => [$newRow]
                ]
            ]
        ]);
        $service->spreadsheets_values->batchUpdate($spreadsheetId, $body);
        $action = 'updated';
    } else {
        // Insertar nueva fila
        $body = new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => [
                [
                    'range' => WORKSHEET . '!A' . (count($rows) + 2),
                    'values' => [$newRow]
                ]
            ]
        ]);
        $service->spreadsheets_values->batchUpdate($spreadsheetId, $body);
        $action = 'created';
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'participantId' => $participantId
    ]);

} catch (Exception $e) {
    $code = ($e->getMessage() === 'Método no permitido. Use POST.') ? 405 : 500;
    if (http_response_code() === 200 || !http_response_code()) {
        http_response_code($code);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
