<?php
/**
 * delete_horas_extras.php - Eliminar registro de horas extras en Google Sheets
 *
 * Recibe: { spreadsheetId, worksheetTitle, rowIndex }
 * Elimina la fila especificada (vacía la fila o la elimina)
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\Request;

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

    if (!isset($data['spreadsheetId'])) {
        throw new Exception('Se requiere el spreadsheetId.');
    }

    if (!isset($data['rowIndex'])) {
        throw new Exception('Se requiere el rowIndex.');
    }

    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? 'extras';
    $rowIndex = intval($data['rowIndex']);

    if ($rowIndex < 2) {
        throw new Exception('No se puede eliminar la fila de encabezados.');
    }

    $client = new Client();
    $client->setApplicationName('Horas Extras');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $sheetId = null;
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    foreach ($spreadsheet->getSheets() as $sheet) {
        if ($sheet->getProperties()->getTitle() === $worksheetTitle) {
            $sheetId = $sheet->getProperties()->getSheetId();
            break;
        }
    }

    if ($sheetId === null) {
        throw new Exception("No se encontró la hoja: {$worksheetTitle}");
    }

    $deleteRange = new Sheets\Request();
    $deleteRange->setDeleteDimension(new \Google\Service\Sheets\DeleteDimensionRequest());
    $deleteRange->getDeleteDimension()->setRange(new \Google\Service\Sheets\DimensionRange([
        'sheetId' => $sheetId,
        'dimension' => 'ROWS',
        'startIndex' => $rowIndex - 1,
        'endIndex' => $rowIndex
    ]));

    $batchUpdateRequest = new Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => [$deleteRange]
    ]);

    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);

    echo json_encode([
        'success' => true,
        'message' => 'Registro eliminado exitosamente.',
        'rowIndex' => $rowIndex
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>