<?php
/**
 * save_cobertura.php - Guardado de coberturas y liberados en Google Sheets
 *
 * Historial: [fecha, dia_semana, hora, docente_ausente, grupo_ausente, docente_cubre, grupo_a_cubrir, estado, motivo]
 * Liberados: [fecha, dia_semana, grupo, hora_liberada, motivo]
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

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
        throw new Exception('JSON inválido.');
    }

    if (!isset($data['values']) || !is_array($data['values'])) {
        throw new Exception('Se espera el campo "values" como arreglo.');
    }

    if (!isset($data['spreadsheetId'])) {
        throw new Exception('Se requiere el spreadsheetId.');
    }

    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? 'historial';
    $range = $worksheetTitle . '!A1:Z5000';

    $client = new Client();
    $client->setApplicationName('Coberturas');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $values = $data['values'];
    if (count($values) === 0 || !is_array($values[0])) {
        $values = [$values];
    }

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    $nextRow = count($allValues) + 1;
    if ($nextRow < 2) $nextRow = 2;

    $numCols = count($values[0]);
    $lastCol = chr(64 + $numCols);
    $insertRange = $worksheetTitle . '!A' . $nextRow . ':' . $lastCol . ($nextRow + count($values) - 1);
    $body = new ValueRange(['values' => $values]);
    $params = ['valueInputOption' => 'RAW'];

    $service->spreadsheets_values->update($spreadsheetId, $insertRange, $body, $params);

    echo json_encode([
        'success' => true,
        'message' => 'Registro guardado exitosamente.',
        'spreadsheetId' => $spreadsheetId,
        'worksheetTitle' => $worksheetTitle,
        'rowsInserted' => count($values)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}