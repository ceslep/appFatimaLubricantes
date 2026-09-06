<?php
/**
 * savePlanAula.php
 * 
 * Recibe los datos del formulario Plan de Aula y los guarda en Google Sheets.
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

    // Leer datos
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido.');
    }

    // El frontend enviará un arreglo plano con los 10 valores
    if (!isset($data['values']) || !is_array($data['values'])) {
        throw new Exception('Datos incompletos. Se espera el campo "values".');
    }

    // Obtener parámetros dinámicos del payload
    $spreadsheetId = $data['spreadsheetId'] ?? '1pkFF954kWh1aCAlyMlIjk7eQL1Povn3vO_5aJFxkM4c';
    $worksheetTitle = $data['worksheetTitle'] ?? 'plan';
    $range = $worksheetTitle . '!A:J';

    // Inicializar Google Client
    $client = new Client();
    $client->setApplicationName('Plan de Aula Backend');
    $client->setScopes([Sheets::SPREADSHEETS]);
    
    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado en el servidor.');
    }
    
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);

    $service = new Sheets($client);

    // Obtener valores actuales para buscar duplicados
    // range es worksheetTitle!A:J
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues();
    
    $foundRowIndex = -1;
    $newData = $data['values'];

    if ($allValues) {
        foreach ($allValues as $idx => $row) {
            // Verificar si coinciden: Area(0), Docente(1), Grado(2), Periodo(4)
            // Se usa comparación flexible == por si vienen como strings/números
            if (isset($row[0], $row[1], $row[2], $row[4]) &&
                $row[0] == $newData[0] &&
                $row[1] == $newData[1] &&
                $row[2] == $newData[2] &&
                $row[4] == $newData[4]) {
                $foundRowIndex = $idx + 1; // Las filas en Sheets son base 1
                break;
            }
        }
    }

    // Preparar el cuerpo para la operación
    $body = new ValueRange([
        'values' => [$newData]
    ]);
    $params = ['valueInputOption' => 'RAW'];

    if ($foundRowIndex !== -1) {
        // Actualizar fila existente
        // Calculamos el rango exacto para la fila encontrada: A{index}:J{index}
        $updateRange = $worksheetTitle . "!A$foundRowIndex:J$foundRowIndex";
        $result = $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        $message = 'Plan de Aula actualizado exitosamente.';
    } else {
        // Crear nueva fila (Append)
        $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        $message = 'Plan de Aula guardado exitosamente.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'updated' => $foundRowIndex !== -1
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
