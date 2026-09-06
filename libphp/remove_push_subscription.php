<?php
/**
 * remove_push_subscription.php - Marca una suscripción push como inactiva
 * en la hoja `push_subscriptions` de Google Sheets.
 *
 * Destino en producción: https://app.iedeoccidente.com/gs/remove_push_subscription.php
 *
 * No borra la fila físicamente (para mantener historial); simplemente cambia
 * la columna H a "FALSE".
 *
 * Petición (POST, JSON):
 *   {
 *     "endpoint": "https://fcm.googleapis.com/...",
 *     "participantId": "3218552353"
 *   }
 *
 * Respuestas:
 *   200 { success: true }
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

    if (!isset($data['endpoint']) || $data['endpoint'] === '') {
        throw new Exception('Falta el campo requerido: endpoint');
    }

    $endpoint = trim($data['endpoint']);

    // Conectar a Google Sheets
    $client = new Client();
    $client->setApplicationName('Polla Mundialista Push Notifications');
    $client->setScopes([Sheets::SPREADSHEETS]);
    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    // Buscar la suscripción por endpoint
    $range = WORKSHEET . '!A2:H1000';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $rows = $response->getValues() ?: [];

    $found = false;
    foreach ($rows as $i => $row) {
        $rowEndpoint = trim((string)($row[2] ?? ''));
        if ($rowEndpoint === $endpoint) {
            $sheetRow = $i + 2;
            $body = new BatchUpdateValuesRequest([
                'valueInputOption' => 'RAW',
                'data' => [
                    [
                        'range' => WORKSHEET . "!H{$sheetRow}:H{$sheetRow}",
                        'values' => [['FALSE']]
                    ]
                ]
            ]);
            $service->spreadsheets_values->batchUpdate($spreadsheetId, $body);
            $found = true;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'found' => $found,
        'message' => $found ? 'Suscripción desactivada.' : 'Suscripción no encontrada (ya podría estar inactiva).'
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
