<?php
/**
 * save_acta_area.php - Guardado de Acta de Área en Google Sheets
 *
 * Recibe los datos del formulario Acta de Área y los guarda en Google Sheets.
 * Columnas: Timestamp, ID, DocenteCreador, Institucion, AreaAcademica, Asignaturas,
 *           Grados, Fecha, HoraInicio, HoraFin, Lugar, Participantes(JSON),
 *           OrdenDia(JSON), Desarrollo(JSON), Acuerdos(JSON), Proxima(JSON),
 *           ActaLeidaAprobada, FirmaCoordinador, FirmaSecretario
 * Duplicado: coincidencia por Area+DocenteCreador+Grado+Fecha (update si existe, append si no)
 * Si la hoja no existe, la crea automáticamente con encabezados.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\SheetProperties;

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

    if (!isset($data['datos']) || !is_array($data['datos'])) {
        throw new Exception('Datos incompletos. Se espera el campo "datos" como arreglo.');
    }

    if (!isset($data['spreadsheetId'])) {
        throw new Exception('Se requiere el spreadsheetId.');
    }

    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? 'ActaArea';

    $client = new Client();
    $client->setApplicationName('Plan de Aula Backend');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    // Verificar si la hoja existe, si no, crearla con encabezados
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();
    $sheetExists = false;

    foreach ($sheets as $sheet) {
        if ($sheet->getProperties()->getTitle() === $worksheetTitle) {
            $sheetExists = true;
            break;
        }
    }

    if (!$sheetExists) {
        $headers = [
            'Timestamp', 'ID', 'DocenteCreador', 'Institucion', 'AreaAcademica',
            'Asignaturas', 'Grados', 'Fecha', 'HoraInicio', 'HoraFin', 'Lugar',
            'Participantes', 'OrdenDia', 'Desarrollo', 'Acuerdos', 'Proxima',
            'ActaLeidaAprobada', 'FirmaCoordinador', 'FirmaSecretario'
        ];

        $addSheet = new AddSheetRequest();
        $properties = new SheetProperties();
        $properties->setTitle($worksheetTitle);
        $addSheet->setProperties($properties);

        $batchUpdate = new BatchUpdateSpreadsheetRequest();
        $batchUpdate->setRequests([['addSheet' => $addSheet]]);
        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdate);

        $headerBody = new ValueRange(['values' => [$headers]]);
        $headerRange = $worksheetTitle . '!A1:S1';
        $service->spreadsheets_values->update($spreadsheetId, $headerRange, $headerBody, ['valueInputOption' => 'RAW']);
    } else {
        // Verificar si la fila 1 tiene encabezados, si no, escribirlos
        $checkResponse = $service->spreadsheets_values->get($spreadsheetId, $worksheetTitle . '!A1:S1');
        $firstRow = $checkResponse->getValues()[0] ?? [];
        $expectedHeaders = ['Timestamp', 'ID', 'DocenteCreador', 'Institucion', 'AreaAcademica',
            'Asignaturas', 'Grados', 'Fecha', 'HoraInicio', 'HoraFin', 'Lugar',
            'Participantes', 'OrdenDia', 'Desarrollo', 'Acuerdos', 'Proxima',
            'ActaLeidaAprobada', 'FirmaCoordinador', 'FirmaSecretario'];
        if (count($firstRow) < 19 || $firstRow[0] !== 'Timestamp') {
            $headerBody = new ValueRange(['values' => [$expectedHeaders]]);
            $service->spreadsheets_values->update($spreadsheetId, $worksheetTitle . '!A1:S1', $headerBody, ['valueInputOption' => 'RAW']);
        }
    }

    $range = $worksheetTitle . '!A2:S';

    $allData = $data['datos'];
    $totalRegistros = count($allData);

    if ($totalRegistros === 0) {
        throw new Exception('No hay datos para registrar.');
    }

    $expectedFields = 19;

    foreach ($allData as $index => $registro) {
        if (!is_array($registro)) {
            throw new Exception("Registro en índice $index no es un arreglo válido.");
        }
        while (count($registro) < $expectedFields) {
            $registro[] = '';
        }
        $allData[$index] = $registro;
    }

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    // Si la primera fila tiene encabezados, saltarla
    $headerOffset = 0;
    if (!empty($allValues) && isset($allValues[0][0]) && $allValues[0][0] === 'Timestamp') {
        $headerOffset = 1;
    }

    $inserted = 0;
    $updated = 0;

    foreach ($allData as $registro) {
        $newArea = $registro[4] ?? '';
        $newDocente = $registro[2] ?? '';
        $newGrado = $registro[6] ?? '';
        $newFecha = $registro[7] ?? '';

        $foundRowIndex = -1;
        if ($allValues) {
            foreach ($allValues as $idx => $row) {
                if ($idx < $headerOffset) continue;
                if (isset($row[4], $row[2], $row[6], $row[7]) &&
                    $row[4] == $newArea &&
                    $row[2] == $newDocente &&
                    $row[6] == $newGrado &&
                    $row[7] == $newFecha) {
                    $foundRowIndex = $idx + 1;
                    break;
                }
            }
        }

        $body = new ValueRange(['values' => [$registro]]);
        $params = ['valueInputOption' => 'RAW'];

        if ($foundRowIndex !== -1) {
            $updateRange = $worksheetTitle . "!A$foundRowIndex:S$foundRowIndex";
            $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
            $updated++;
        } else {
            $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            $inserted++;
            $allValues[] = $registro;
        }
    }

    $message = "Registros guardados: $inserted nuevos, $updated actualizados.";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'inserted' => $inserted,
        'updated' => $updated,
        'total' => $totalRegistros
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>