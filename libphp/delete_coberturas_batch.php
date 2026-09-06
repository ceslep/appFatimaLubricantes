<?php
/**
 * delete_coberturas_batch.php - Eliminar varias coberturas en una sola operación
 *
 * Recibe: spreadsheetId, worksheetTitle, keys (arreglo de claves)
 * Cada clave: { fecha, hora, docente_cubre, docente_ausente?, grupo_ausente?, grupo_a_cubrir? }
 *
 * Hace UN get + UN clear + UN update reescribiendo la hoja sin las filas coincidentes.
 * Misma semántica de match que delete_cobertura.php: por cada clave se elimina la PRIMERA
 * fila coincidente (un arreglo $used evita que una clave borre duplicados o que una fila
 * satisfaga dos claves).
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
    $keys = $data['keys'] ?? null;

    if (!is_array($keys) || count($keys) === 0) {
        throw new Exception('Se requiere "keys" como arreglo no vacío.');
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

    // Estado por clave: cuáles ya fueron consumidas (primera coincidencia).
    $numKeys = count($keys);
    $used = array_fill(0, $numKeys, false);

    $filteredDataRows = [];
    foreach ($dataRows as $row) {
        $matchedKeyIdx = -1;
        for ($k = 0; $k < $numKeys; $k++) {
            if ($used[$k]) continue;
            $key = $keys[$k];

            $fecha = $key['fecha'] ?? '';
            $hora = $key['hora'] ?? '';
            $docenteCubre = $key['docente_cubre'] ?? '';
            $docenteAusente = $key['docente_ausente'] ?? null;
            $grupoAusente = $key['grupo_ausente'] ?? null;
            $grupoACubrir = $key['grupo_a_cubrir'] ?? null;

            $coincide =
                count($row) >= 6 &&
                trim((string) ($row[0] ?? '')) === trim((string) $fecha) &&
                trim((string) ($row[2] ?? '')) === trim((string) $hora) &&
                trim((string) ($row[5] ?? '')) === trim((string) $docenteCubre);

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
                $matchedKeyIdx = $k;
                break;
            }
        }

        if ($matchedKeyIdx >= 0) {
            $used[$matchedKeyIdx] = true; // consumir clave y saltar fila
            continue;
        }
        $filteredDataRows[] = $row;
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

    // Reportar claves no encontradas (sin lanzar excepción por parciales).
    $notFound = [];
    $deleted = 0;
    for ($k = 0; $k < $numKeys; $k++) {
        if ($used[$k]) {
            $deleted++;
        } else {
            $notFound[] = $keys[$k];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Coberturas eliminadas.',
        'deleted' => $deleted,
        'notFound' => $notFound,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
