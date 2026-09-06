<?php
/**
 * save_inasistencias.php - Guardado de inasistencias en Google Sheets
 * 
 * Recibe los datos de inasistencias y los guarda en Google Sheets.
 * Espera un arreglo de valores con la estructura:
 * [timestamp, docente, fecha, horas, asignatura, tipo_registro, grupo, estudiante, observaciones]
 * 
 * Implementa REEMPLAZO de registros existentes basado en clave única:
 * docente + fecha + grado + materia + estudiante
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

    if (!isset($data['inasistencias']) || !is_array($data['inasistencias'])) {
        throw new Exception('Datos incompletos. Se espera el campo "inasistencias" como arreglo.');
    }

    if (!isset($data['spreadsheetId'])) {
        throw new Exception('Se requiere el spreadsheetId.');
    }

    // Parámetros dinámicos
    $spreadsheetId = $data['spreadsheetId'];
    $worksheetTitle = $data['worksheetTitle'] ?? date('Y-m');
    $range = $worksheetTitle . '!A1:Z1000'; // Rango amplio

    // Inicializar cliente de Google
    $client = new Client();
    $client->setApplicationName('Control de Inasistencias');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $inasistenciasData = $data['inasistencias'];
    $totalInasistencias = count($inasistenciasData);

    if ($totalInasistencias === 0) {
        throw new Exception('No hay inasistencias para registrar.');
    }

    // Validar que cada inasistencia tenga los campos requeridos
    $expectedFields = 9; // timestamp, docente, fecha, horas, asignatura, tipo_registro, grupo, estudiante, observaciones

    foreach ($inasistenciasData as $index => $inasistencia) {
        if (!is_array($inasistencia) || count($inasistencia) !== $expectedFields) {
            throw new Exception("Inasistencia en índice $index debe tener exactamente $expectedFields campos.");
        }

        // Validar campos clave (docente, fecha, grado, materia, estudiante)
        foreach ([1, 2, 4, 6, 7] as $fieldIndex) { // docente, fecha, materia, grado, estudiante
            if (!isset($inasistencia[$fieldIndex]) || trim((string) $inasistencia[$fieldIndex]) === '') {
                throw new Exception("Campo requerido en posición $fieldIndex está vacío en inasistencia $index.");
            }
        }
    }

    error_log("DEBUG: Payload válido con $totalInasistencias inasistencias.");

    // Función para generar clave única
    // Clave: docente|fecha|grado|materia|estudiante
    function generateUniqueKey($row) {
        $docente = isset($row[1]) ? trim((string)$row[1]) : '';
        $fecha = isset($row[2]) ? trim((string)$row[2]) : '';
        $grado = isset($row[6]) ? trim((string)$row[6]) : '';
        $materia = isset($row[4]) ? trim((string)$row[4]) : '';
        $estudiante = isset($row[7]) ? trim((string)$row[7]) : '';
        return $docente . '|' . $fecha . '|' . $grado . '|' . $materia . '|' . $estudiante;
    }

    // Leer la hoja completa
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    // Si hay encabezado en la primera fila, preservarlo
    $hasHeader = count($allValues) > 0 && isset($allValues[0][0]) && strtolower(trim($allValues[0][0])) !== 'timestamp';
    $headerRow = $hasHeader ? array_shift($allValues) : null;

    // Construir mapa de registros existentes (clave única -> fila completa)
    $existingRecords = [];
    foreach ($allValues as $rowIndex => $row) {
        if (count($row) >= 8) { // Mínimo campos necesarios
            $key = generateUniqueKey($row);
            $existingRecords[$key] = $row;
        }
    }

    error_log("DEBUG: Registros existentes en hoja: " . count($existingRecords));

    // Procesar los nuevos registros
    $currentTimestamp = date('Y-m-d H:i:s');
    $newRecordsKeys = []; // Track keys of new records for reporting

    foreach ($inasistenciasData as $index => $inasistencia) {
        // Asegurar que el timestamp esté presente (índice 0)
        if (!isset($inasistencia[0]) || empty($inasistencia[0])) {
            $inasistencia[0] = $currentTimestamp;
        }

        $key = generateUniqueKey($inasistencia);
        $newRecordsKeys[] = $key;
        
        // Reemplazar o agregar
        $existingRecords[$key] = $inasistencia;
    }

    // Eliminar registros que ya no están en los nuevos datos (opcional: mantener registros de otros docentes/fechas)
    // Por ahora mantenemos todos los registros existentes que no coinciden con las claves de los nuevos
    // Esto permite que no se borren registros de otras combinaciones

    // Reconstruir array final
    $finalRows = [];
    if ($headerRow) {
        $finalRows[] = $headerRow;
    }
    foreach ($existingRecords as $key => $row) {
        $finalRows[] = $row;
    }

    $totalFinal = count($finalRows);
    $totalRegistros = $totalFinal - ($headerRow ? 1 : 0);

    // Escribir todos los datos de vuelta a la hoja
    $updateRange = $worksheetTitle . '!A1:Z' . $totalFinal;
    $body = new ValueRange(['values' => $finalRows]);
    $params = ['valueInputOption' => 'RAW'];

    $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);

    $replacedCount = 0;
    $addedCount = 0;
    
    // Contar reemplazados vs nuevos
    foreach ($newRecordsKeys as $key) {
        // Verificar si era un registro existente
        // Esta lógica es aproximada ya que $existingRecords ya fue modificado
    }

    echo json_encode([
        'success' => true,
        'message' => "Se procesaron $totalRegistros registro(s) exitosamente.",
        'total' => $totalRegistros,
        'spreadsheetId' => $spreadsheetId,
        'worksheetTitle' => $worksheetTitle,
        'mode' => 'replace'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>