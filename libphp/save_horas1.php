<?php
/**
 * save_horas.php
 * 
 * Recibe los datos de horas laborables y los guarda en Google Sheets.
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

    // El frontend enviará un objeto con spreadsheetId, worksheetTitle y values (34 valores: email, nombre, mes, y 31 días)
    if (!isset($data['values']) || !is_array($data['values'])) {
        throw new Exception('Datos incompletos. Se espera el campo "values".');
    }

    // Obtener parámetros dinámicos del payload
    $spreadsheetId = $data['spreadsheetId'] ?? '1UW_dbtJEFJeOjCg323HJPaacqPIztw_9bGI5Rw6HRxQ';
    $worksheetTitle = $data['worksheetTitle'] ?? date('Y');
    $range = $worksheetTitle . '!A:AI';

    // Inicializar Google Client
    $client = new Client();
    $client->setApplicationName('Horas laborables');
    $client->setScopes([Sheets::SPREADSHEETS]);
    
    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado en el servidor.');
    }
    
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);

    $service = new Sheets($client);

    // Preparar los datos finales con Marca Temporal al inicio
    $timestamp = date('d/m/Y H:i:s');
    $newData = array_merge([$timestamp], $data['values']); // [timestamp, email, nombre, mes, d1, d2, ..., d31]

    // Obtener valores actuales para buscar duplicados
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues();
    
    $foundRowIndex = -1;

    if ($allValues) {
        foreach ($allValues as $idx => $row) {
            // Verificar si coinciden: Email(1), Docente(2), Mes(3)
            if (isset($row[1], $row[2], $row[3]) &&
                $row[1] == $newData[1] &&
                $row[2] == $newData[2] &&
                $row[3] == $newData[3]) {
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
        // Calculamos el rango exacto para la fila encontrada: A{index}:AI{index}
        $updateRange = $worksheetTitle . "!A$foundRowIndex:AI$foundRowIndex";
        $result = $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        $message = 'Registro actualizado exitosamente.';
    } else {
        // Crear nueva fila (Append)
        $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        $message = 'Registro guardado exitosamente.';
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
