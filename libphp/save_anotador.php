<?php
/**
 * save_anotador.php - Guardado de anotaciones en Google Sheets
 * 
 * Recibe los datos de anotaciones de clase y los guarda en Google Sheets.
 * Estructura: [timestamp, fecha, docente, asignatura, grado, horas, anotacion, observacion]
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

// Configuración de archivos
const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

// CORS y Cabeceras
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

    // Leer datos del cuerpo de la solicitud
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

    // Parámetros dinámicos
    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? 'Datos';
    $range = $worksheetTitle . '!A1:Z5000';

    // Inicializar cliente de Google
    $client = new Client();
    $client->setApplicationName('Anotador de Clase');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $anotacionesData = $data['datos'];
    $totalRegistros = count($anotacionesData);

    if ($totalRegistros === 0) {
        throw new Exception('No hay datos para registrar.');
    }

    // Validar que cada registro tenga los campos requeridos
    $expectedFields = 8; // timestamp, fecha, docente, asignatura, grado, horas, anotacion, observacion

    foreach ($anotacionesData as $index => $registro) {
        if (!is_array($registro) || count($registro) !== $expectedFields) {
            throw new Exception("Registro en índice $index debe tener exactamente $expectedFields campos.");
        }

        // Validar campos clave (fecha, docente, asignatura)
        foreach ([1, 2, 3] as $fieldIndex) {
            if (!isset($registro[$fieldIndex]) || trim((string) $registro[$fieldIndex]) === '') {
                throw new Exception("Campo requerido en posición $fieldIndex está vacío en registro $index.");
            }
        }
    }

    // Preparar datos para insertar (asegurar timestamp)
    $allRowsToInsert = [];
    $currentTimestamp = date('Y-m-d H:i:s');

    foreach ($anotacionesData as $index => $registro) {
        if (!isset($registro[0]) || empty($registro[0])) {
            $registro[0] = $currentTimestamp;
        }
        $allRowsToInsert[] = $registro;
    }

    // Leer la hoja para encontrar la siguiente fila vacía
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    $nextRow = count($allValues) + 1;
    if ($nextRow < 2)
        $nextRow = 2; // Saltar encabezado si está vacío

    // Insertar en batch
    $insertRange = $worksheetTitle . "!A{$nextRow}:H" . ($nextRow + $totalRegistros - 1);
    $body = new ValueRange(['values' => $allRowsToInsert]);
    $params = ['valueInputOption' => 'RAW'];

    $service->spreadsheets_values->update($spreadsheetId, $insertRange, $body, $params);

    echo json_encode([
        'success' => true,
        'message' => "Se registraron exitosamente $totalRegistros anotación(es).",
        'total' => $totalRegistros,
        'spreadsheetId' => $spreadsheetId,
        'worksheetTitle' => $worksheetTitle
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>