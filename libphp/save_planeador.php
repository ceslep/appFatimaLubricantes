<?php
/**
 * save_planeador.php - Guardado de planeaciones en Google Sheets
 *
 * Recibe los datos de planeación de clases y los guarda en Google Sheets.
 * Estructura: 49 columnas con formato JSON para arrays.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

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
    $worksheetTitle = $data['worksheetTitle'] ?? 'Planeaciones';
    $range = $worksheetTitle . '!A1:AX5000';

    $client = new Client();
    $client->setApplicationName('Planeador de Clases');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $planeaciones = $data['datos'];
    $totalRegistros = count($planeaciones);

    if ($totalRegistros === 0) {
        throw new Exception('No hay planeaciones para registrar.');
    }

    // 49 Encabezados de columnas
    $headers = [
        'id', 'fecha_creacion', 'docente', 'institution', 'campus',
        'grade', 'subject', 'period', 'dba', 'standard',
        'dba_manual', 'competency', 'has_piar', 'piar_description',
        'learning_objectives', 'competencias', 'indicadores_logro',
        'exploration', 'exploration_activities', 'tiempo_exploracion',
        'structuring', 'structuring_activities', 'tiempo_estructuracion',
        'practice', 'practice_activities', 'tiempo_practica',
        'transfer', 'transfer_activities', 'tiempo_transferencia',
        'assessment_moment', 'assessment_activities', 'tiempo_valoracion',
        'eval_type', 'eval_modalidades', 'eval_instrumentos',
        'eval_criterios', 'eval_evidencias', 'eval_criteria', 'eval_evidence',
        'eval_ponderacion_conceptos', 'eval_ponderacion_procedimientos',
        'eval_ponderacion_actitudes', 'eval_descripcion_auto',
        'resources', 'planeacion_tipo', 'periodo_academico',
        'fecha_inicio', 'fecha_fin', 'firma_docente', 'fecha_firma',
    ];

    // Convertir planeaciones a filas
    $newRows = [];

    foreach ($planeaciones as $planeacion) {
        $row = [
            $planeacion['id'] ?? uniqid('PL_'),
            $planeacion['fecha_creacion'] ?? date('Y-m-d'),
            $planeacion['docente'] ?? '',
            $planeacion['institution'] ?? '',
            $planeacion['campus'] ?? '',
            $planeacion['grade'] ?? '',
            $planeacion['subject'] ?? '',
            $planeacion['period'] ?? '',
            isset($planeacion['dba']) ? json_encode($planeacion['dba'], JSON_UNESCAPED_UNICODE) : '[]',
            isset($planeacion['standard']) ? json_encode($planeacion['standard'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['dba_manual'] ?? '',
            $planeacion['competency'] ?? '',
            isset($planeacion['has_piar']) ? ($planeacion['has_piar'] ? 'true' : 'false') : 'false',
            $planeacion['piar_description'] ?? '',
            $planeacion['learning_objectives'] ?? '',
            $planeacion['competencias'] ?? '',
            $planeacion['indicadores_logro'] ?? '',
            $planeacion['exploration'] ?? '',
            isset($planeacion['exploration_activities']) ? json_encode($planeacion['exploration_activities'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['tiempo_exploracion'] ?? 10,
            $planeacion['structuring'] ?? '',
            isset($planeacion['structuring_activities']) ? json_encode($planeacion['structuring_activities'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['tiempo_estructuracion'] ?? 20,
            $planeacion['practice'] ?? '',
            isset($planeacion['practice_activities']) ? json_encode($planeacion['practice_activities'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['tiempo_practica'] ?? 25,
            $planeacion['transfer'] ?? '',
            isset($planeacion['transfer_activities']) ? json_encode($planeacion['transfer_activities'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['tiempo_transferencia'] ?? 15,
            $planeacion['assessment_moment'] ?? '',
            isset($planeacion['assessment_activities']) ? json_encode($planeacion['assessment_activities'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['tiempo_valoracion'] ?? 10,
            $planeacion['eval_type'] ?? 'Formativa',
            isset($planeacion['eval_modalidades']) ? json_encode($planeacion['eval_modalidades'], JSON_UNESCAPED_UNICODE) : '[]',
            isset($planeacion['eval_instrumentos']) ? json_encode($planeacion['eval_instrumentos'], JSON_UNESCAPED_UNICODE) : '[]',
            isset($planeacion['eval_criterios']) ? json_encode($planeacion['eval_criterios'], JSON_UNESCAPED_UNICODE) : '[]',
            isset($planeacion['eval_evidencias']) ? json_encode($planeacion['eval_evidencias'], JSON_UNESCAPED_UNICODE) : '[]',
            $planeacion['eval_criteria'] ?? '',
            $planeacion['eval_evidence'] ?? '',
            $planeacion['eval_ponderacion_conceptos'] ?? 30,
            $planeacion['eval_ponderacion_procedimientos'] ?? 40,
            $planeacion['eval_ponderacion_actitudes'] ?? 30,
            $planeacion['eval_descripcion_auto'] ?? '',
            $planeacion['resources'] ?? '',
            $planeacion['planeacion_tipo'] ?? '',
            $planeacion['periodo_academico'] ?? '',
            $planeacion['fecha_inicio'] ?? '',
            $planeacion['fecha_fin'] ?? '',
            $planeacion['firma_docente'] ?? '',
            $planeacion['fecha_firma'] ?? '',
        ];
        $newRows[] = $row;
    }

    // Leer la hoja para encontrar la siguiente fila vacía
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    $nextRow = count($allValues) + 1;

    // Si la hoja está vacía, escribir encabezados primero en fila 1
    if ($nextRow <= 1) {
        $headerRange = $worksheetTitle . '!A1:AX1';
        $headerBody = new ValueRange(['values' => [$headers]]);
        $service->spreadsheets_values->update($spreadsheetId, $headerRange, $headerBody, ['valueInputOption' => 'RAW']);
        $nextRow = 2;
    } elseif ($nextRow < 2) {
        $nextRow = 2; // Saltar encabezado
    }

    // Insertar nuevas filas a partir de la siguiente fila vacía
    $lastCol = 'AX'; // 50 columnas = A..AX
    $insertRange = $worksheetTitle . "!A{$nextRow}:{$lastCol}" . ($nextRow + $totalRegistros - 1);
    $body = new ValueRange(['values' => $newRows]);
    $params = ['valueInputOption' => 'RAW'];

    $service->spreadsheets_values->update($spreadsheetId, $insertRange, $body, $params);

    echo json_encode([
        'success' => true,
        'message' => "Se registraron exitosamente $totalRegistros planeación(es).",
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
