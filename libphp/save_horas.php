<?php
/**
 * save_horas.php - Versión final corregida
 * 
 * Recibe los datos de horas laborables y los guarda en Google Sheets.
 * Espera exactamente 35 valores: [timestamp, email, nombre, mes, d1, d2, ..., d31]
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

    if (!isset($data['values']) || !is_array($data['values'])) {
        throw new Exception('Datos incompletos. Se espera el campo "values".');
    }

    // Parámetros dinámicos
    $spreadsheetId = $data['spreadsheetId'] ?? '1UW_dbtJEFJeOjCg323HJPaacqPIztw_9bGI5Rw6HRxQ';
    $worksheetTitle = $data['worksheetTitle'] ?? date('Y');
    $range = $worksheetTitle . '!A1:AI1000'; // Rango amplio

    // Inicializar cliente de Google
    $client = new Client();
    $client->setApplicationName('Horas laborables');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $newData = $data['values'];

    // ✅ VALIDACIÓN ESTRUCTURAL: deben ser exactamente 35 elementos
    if (count($newData) !== 35) {
        throw new Exception('El payload debe contener exactamente 35 valores: timestamp, email, nombre, mes y 31 días.');
    }

    // Validar campos clave (índices 0 a 3)
    foreach ([0, 1, 2, 3] as $index) {
        if (!isset($newData[$index]) || trim((string)$newData[$index]) === '') {
            throw new Exception("Campo requerido en posición $index está vacío o no definido.");
        }
    }

    error_log("DEBUG: Payload válido recibido con 35 elementos.");

    // Extraer campos de identificación única: Nombre (C = índice 2), Mes (D = índice 3)
    $searchName = trim((string)$newData[2]);
    $searchMonth = trim((string)$newData[3]);

    if ($searchName === '' || $searchMonth === '') {
        throw new Exception('Nombre y Mes no pueden estar vacíos.');
    }

    // Leer toda la hoja
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    // Depuración: mostrar estructura de la hoja
    error_log("DEBUG: Total filas leídas: " . count($allValues));
    foreach ($allValues as $idx => $row) {
        $c = isset($row[2]) ? $row[2] : '[vacio]';
        $d = isset($row[3]) ? $row[3] : '[vacio]';
        error_log("Fila " . ($idx + 1) . " → C: '$c', D: '$d'");
    }

    // Buscar coincidencia (ignorar fila 1 = encabezado)
    $foundRowIndex = -1;
    if (count($allValues) > 1) {
        for ($i = 1; $i < count($allValues); $i++) {
            $row = $allValues[$i];
            if (isset($row[2]) && isset($row[3])) {
                $existingName = trim((string)$row[2]);
                $existingMonth = trim((string)$row[3]);
                if ($existingName !== '' && $existingMonth !== '') {
                    if ($existingName === $searchName && $existingMonth === $searchMonth) {
                        $foundRowIndex = $i + 1; // Fila real en Sheets (1-based)
                        break;
                    }
                }
            }
        }
    }

    error_log("DEBUG: Búsqueda → Nombre='$searchName', Mes='$searchMonth' → foundRowIndex=$foundRowIndex");

    // Preparar datos para escribir
    $body = new ValueRange(['values' => [$newData]]);
    $params = ['valueInputOption' => 'RAW'];
    $returnedRowIndex = -1;

    if ($foundRowIndex !== -1) {
        // Actualizar fila existente
        $updateRange = $worksheetTitle . "!A$foundRowIndex:AI$foundRowIndex";
        $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        $message = 'Registro actualizado exitosamente.';
        $returnedRowIndex = $foundRowIndex;
        error_log("DEBUG: Actualizado en fila $foundRowIndex");
    } else {
        // Insertar en nueva fila (después de todos los datos reales)
        $nextRow = count($allValues) + 1;
        if ($nextRow < 2) $nextRow = 2; // Nunca escribir en fila 1 (encabezado)

        $updateRange = $worksheetTitle . "!A{$nextRow}:AI{$nextRow}";
        $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        $message = 'Nuevo registro creado exitosamente.';
        $returnedRowIndex = $nextRow;
        error_log("DEBUG: Nuevo registro en fila $nextRow");
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'updated' => $foundRowIndex !== -1,
        'rowIndex' => $returnedRowIndex
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>