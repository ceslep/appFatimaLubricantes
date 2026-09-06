<?php
/**
 * delete_cobertura.php - Eliminar una cobertura específica
 *
 * Elimina UNA fila de Google Sheets basándose en fecha + hora + docente_cubre.
 * Opcionalmente afina el match con docente_ausente, grupo_ausente y grupo_a_cubrir
 * para desambiguar cuando un mismo docente cubre varios grupos en la misma hora/fecha.
 *
 * Implementación: clear de la hoja + reescritura de las filas restantes (excluyendo
 * solo la primera coincidencia). El método update con valores vacíos NO limpia celdas
 * en la API de Sheets, por eso se reconstruye la hoja.
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

    $spreadsheetId = $data['spreadsheetId'] ?? '1N-94FYW5kvGmOcJ4CCqQRWC71guFLxlXltlM7GvDQDw';
    $worksheetTitle = $data['worksheetTitle'] ?? 'historial';
    $fecha = $data['fecha'] ?? '';
    $hora = $data['hora'] ?? '';
    $docenteCubre = $data['docente_cubre'] ?? '';

    // Campos opcionales para desambiguar (si el frontend los envía)
    $docenteAusente = $data['docente_ausente'] ?? null;
    $grupoAusente = $data['grupo_ausente'] ?? null;
    $grupoACubrir = $data['grupo_a_cubrir'] ?? null;

    if (!$fecha || $hora === '' || !$docenteCubre) {
        throw new Exception('Se requiere fecha, hora y docente_cubre.');
    }

    $client = new Client();
    $client->setApplicationName('Coberturas');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $range = $worksheetTitle . '!A1:Z5000';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    if (count($allValues) === 0) {
        throw new Exception('Hoja vacía.');
    }

    $dataRows = array_slice($allValues, 1);

    // Reconstruir manteniendo todas las filas excepto la PRIMERA coincidencia.
    $filteredDataRows = [];
    $eliminado = false;
    foreach ($dataRows as $row) {
        $coincide = !$eliminado &&
            count($row) >= 6 &&
            trim((string) ($row[0] ?? '')) === trim((string) $fecha) &&
            trim((string) ($row[2] ?? '')) === trim((string) $hora) &&
            trim((string) ($row[5] ?? '')) === trim((string) $docenteCubre);

        // Afinar con campos opcionales cuando vengan en la petición
        if ($coincide && $docenteAusente !== null) {
            $coincide = trim((string) ($row[3] ?? '')) === trim((string) $docenteAusente);
        }
        if ($coincide && $grupoAusente !== null) {
            $coincide = trim((string) ($row[4] ?? '')) === trim((string) $grupoAusente);
        }
        if ($coincide && $grupoACubrir !== null) {
            $coincide = trim((string) ($row[6] ?? '')) === trim((string) $grupoACubrir);
        }

        if ($coincide) {
            $eliminado = true; // saltar esta fila (no la conservamos)
            continue;
        }
        $filteredDataRows[] = $row;
    }

    if (!$eliminado) {
        throw new Exception('Registro no encontrado.');
    }

    // Limpiar datos (desde A2) y reescribir las filas restantes.
    $clearRange = $worksheetTitle . '!A2:Z5000';
    $service->spreadsheets_values->clear($spreadsheetId, $clearRange, new Sheets\ClearValuesRequest());

    if (count($filteredDataRows) > 0) {
        $maxCols = 0;
        foreach ($filteredDataRows as $row) {
            if (count($row) > $maxCols) $maxCols = count($row);
        }
        if ($maxCols < 1) $maxCols = 1;

        $colLetter = function ($n) {
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
        'message' => 'Cobertura eliminada.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
