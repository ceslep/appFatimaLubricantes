<?php
/**
 * delete_coberturas_fecha.php - Elimina coberturas de un día específico en Google Sheets
 *
 * Recibe: spreadsheetId, worksheetTitle, fecha
 * Elimina todas las filas donde columna A (fecha) coincida con la fecha proporcionada
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

    if (!isset($data['spreadsheetId'])) {
        throw new Exception('Se requiere el spreadsheetId.');
    }

    if (!isset($data['fecha'])) {
        throw new Exception('Se requiere la fecha.');
    }

    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? 'historial';
    $fecha = $data['fecha'];
    $range = $worksheetTitle . '!A1:Z5000';

    $client = new Client();
    $client->setApplicationName('Coberturas');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    // Obtener todas las filas existentes
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    // Separar header de datos
    $headerRow = isset($allValues[0]) ? $allValues[0] : [];
    $dataRows = array_slice($allValues, 1);

    // Contar filas de ese día antes de filtrar
    $filasDia = 0;
    foreach ($dataRows as $row) {
        if (isset($row[0]) && $row[0] === $fecha) {
            $filasDia++;
        }
    }

    // Filtrar: mantener filas donde columna A NO coincida con la fecha
    $filteredDataRows = [];
    foreach ($dataRows as $row) {
        if (!isset($row[0]) || $row[0] !== $fecha) {
            $filteredDataRows[] = $row;
        }
    }

    // Limpiar toda la hoja desde A2
    $clearRange = $worksheetTitle . '!A2:Z5000';
    $service->spreadsheets_values->clear($spreadsheetId, $clearRange, new Sheets\ClearValuesRequest());

    // Si hay filas filtradas, escribirlas de nuevo respetando todas las columnas
    if (count($filteredDataRows) > 0) {
        // Calcular cantidad máxima de columnas para construir rango correcto
        $maxCols = 0;
        foreach ($filteredDataRows as $row) {
            if (count($row) > $maxCols) $maxCols = count($row);
        }
        if ($maxCols < 1) $maxCols = 1;

        // Convertir índice columna a letra (A, B, ... Z, AA, ...)
        $colLetter = function($n) {
            $letter = '';
            while ($n > 0) {
                $n--;
                $letter = chr(65 + ($n % 26)) . $letter;
                $n = intdiv($n, 26);
            }
            return $letter;
        };

        $lastCol = $colLetter($maxCols);
        $lastRow = count($filteredDataRows) + 1;
        $writeRange = $worksheetTitle . '!A2:' . $lastCol . $lastRow;

        $body = new ValueRange(['values' => $filteredDataRows]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->update($spreadsheetId, $writeRange, $body, $params);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coberturas del día eliminadas.',
        'filasEliminadas' => $filasDia
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}