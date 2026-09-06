<?php
/**
 * get_planeador.php - Obtención de planeaciones desde Google Sheets
 * 
 * Permite obtener las planeaciones guardadas con filtros opcionales.
 * Filtros: docente, grado, materia, periodo, fechaDesde, fechaHasta
 */

include_once("cors.php");

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.');
    }

    $input = file_get_contents('php://input');
    $filtros = json_decode($input, true) ?: [];

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido.');
    }

    // Spreadsheet ID correcto
    $spreadsheetId = '1nXqDNW_KLlDoXKUENQ-Yg50dHfNRI_vL5r4boYOfbOo';
    $worksheetTitle = 'Planeaciones';
    $range = $worksheetTitle . '!A1:AX1000';

    $client = new Client();
    $client->setApplicationName('Planeador de Clases');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $allValues = $response->getValues() ?: [];

    if (count($allValues) === 0) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit();
    }

    // Verificar encabezado (busca 'id' en minúsculas)
    $hasHeader = isset($allValues[0][0]) && strtolower(trim($allValues[0][0])) === 'id';
    $startRow = $hasHeader ? 1 : 0;

    if ($startRow >= count($allValues)) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit();
    }

    // Mapear encabezados (49 columnas)
    $headers = [
        'id', 'fecha_creacion', 'docente', 'institution', 'campus', 'grade', 'subject', 'period',
        'dba', 'standard', 'dba_manual', 'competency', 'has_piar', 'piar_description', 'learning_objectives',
        'competencias', 'indicadores_logro', 'exploration', 'exploration_activities', 'tiempo_exploracion',
        'structuring', 'structuring_activities', 'tiempo_estructuracion', 'practice', 'practice_activities',
        'tiempo_practica', 'transfer', 'transfer_activities', 'tiempo_transferencia', 'assessment_moment',
        'assessment_activities', 'tiempo_valoracion', 'eval_type', 'eval_modalidades', 'eval_instrumentos',
        'eval_criterios', 'eval_evidencias', 'eval_criteria', 'eval_evidence', 'eval_ponderacion_conceptos',
        'eval_ponderacion_procedimientos', 'eval_ponderacion_actitudes', 'eval_descripcion_auto', 'resources',
        'planeacion_tipo', 'periodo_academico', 'fecha_inicio', 'fecha_fin', 'firma_docente', 'fecha_firma'
    ];

    // Convertir filas a objetos
    $planeaciones = [];
    for ($i = $startRow; $i < count($allValues); $i++) {
        $row = $allValues[$i];
        
        $planeacion = [];
        for ($j = 0; $j < count($headers); $j++) {
            $value = isset($row[$j]) ? $row[$j] : '';
            
            // Parsear arrays JSON
            if (in_array($headers[$j], ['dba', 'standard', 'exploration_activities', 'structuring_activities', 
                'practice_activities', 'transfer_activities', 'assessment_activities', 'eval_modalidades', 
                'eval_instrumentos', 'eval_criterios', 'eval_evidencias'])) {
                $decoded = json_decode($value, true);
                $planeacion[$headers[$j]] = is_array($decoded) ? $decoded : [];
            } else {
                $planeacion[$headers[$j]] = $value;
            }
        }
        
        $planeaciones[] = $planeacion;
    }

    // Aplicar filtros
    if (!empty($filtros['docente'])) {
        $planeaciones = array_filter($planeaciones, fn($p) => 
            stripos($p['docente'], $filtros['docente']) !== false
        );
    }

    if (!empty($filtros['grado'])) {
        $planeaciones = array_filter($planeaciones, fn($p) => 
            stripos($p['grade'], $filtros['grado']) !== false
        );
    }

    if (!empty($filtros['materia'])) {
        $planeaciones = array_filter($planeaciones, fn($p) => 
            stripos($p['subject'], $filtros['materia']) !== false
        );
    }

    if (!empty($filtros['periodo'])) {
        $planeaciones = array_filter($planeaciones, fn($p) => 
            stripos($p['period'], $filtros['periodo']) !== false ||
            stripos($p['periodo_academico'], $filtros['periodo']) !== false
        );
    }

    // Filtro por fecha desde
    if (!empty($filtros['fechaDesde'])) {
        $fechaDesde = $filtros['fechaDesde'];
        $planeaciones = array_filter($planeaciones, fn($p) => 
            !empty($p['fecha_creacion']) && $p['fecha_creacion'] >= $fechaDesde
        );
    }

    // Filtro por fecha hasta
    if (!empty($filtros['fechaHasta'])) {
        $fechaHasta = $filtros['fechaHasta'];
        $planeaciones = array_filter($planeaciones, fn($p) => 
            !empty($p['fecha_creacion']) && $p['fecha_creacion'] <= $fechaHasta
        );
    }

    // Reindexar array
    $planeaciones = array_values($planeaciones);

    echo json_encode([
        'success' => true,
        'data' => $planeaciones,
        'total' => count($planeaciones)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
