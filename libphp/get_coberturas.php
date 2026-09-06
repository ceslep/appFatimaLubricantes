<?php
/**
 * get_coberturas.php - Lectura de coberturas desde Google Sheets
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido. Use GET.');
    }

    $spreadsheetId = $_GET['spreadsheetId'] ?? '1N-94FYW5kvGmOcJ4CCqQRWC71guFLxlXltlM7GvDQDw';
    $worksheetTitle = $_GET['worksheetTitle'] ?? 'historial';
    $range = $worksheetTitle . '!A1:Z5000';

    $client = new Client();
    $client->setApplicationName('Coberturas');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues() ?: [];

    echo json_encode([
        'success' => true,
        'values' => $values
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}