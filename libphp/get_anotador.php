<?php
/**
 * get_horas.php
 * 
 * Obtiene los datos de la hoja de cálculo de Google Sheets.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

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

function normalizarTexto($texto) {
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'à', 'è', 'ì', 'ò', 'ù', 'â', 'ê', 'î', 'ô', 'û'],
        ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
        $texto
    );
    $texto = preg_replace('/[^a-z0-9]/', '', $texto);
    return $texto;
}

try {
    // Leer datos del payload JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg() . ' | input: ' . $input);
    }

    $spreadsheetId  = $data['spreadsheetId']  ?? '1Q6EcSvccB7BoJiw9PD2s5J4PB8AJmr-6v-yKhiE4E8k';
    $worksheetTitle = $data['worksheetTitle'] ?? 'Datos';
    $filterGrado    = isset($data['filterGrado'])   ? strtolower(trim($data['filterGrado']))   : null;
    $filterMateria  = isset($data['filterMateria']) ? strtolower(trim($data['filterMateria'])) : null;
    $filterDocente  = isset($data['filterDocente']) ? strtolower(trim($data['filterDocente'])) : null;
    $returnActividades = isset($data['returnActividades']) && $data['returnActividades'];
    $maxRegistros   = isset($data['maxRegistros']) ? intval($data['maxRegistros']) : 10;
    $debug          = isset($data['debug']) && $data['debug'];
    $range          = $worksheetTitle . '!A:AI';

    $client = new Client();
    $client->setApplicationName('Anotador');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado en el servidor.');
    }

    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);

    $service = new Sheets($client);

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = [];
    if ($response !== null) {
        $values = $response->getValues();
    }

    if ($debug) {
        $sampleRows = [];
        for ($i = 1; $i < min(10, count($values)); $i++) {
            $row = $values[$i];
            $sampleRows[] = [
                'rowIndex'     => $i + 1,
                'docente_raw'  => isset($row[2]) ? $row[2] : null,
                'docente_norm' => isset($row[2]) ? normalizarTexto($row[2]) : '',
                'materia_raw'  => isset($row[3]) ? $row[3] : null,
                'materia_norm' => isset($row[3]) ? normalizarTexto($row[3]) : '',
                'grado_raw'    => isset($row[4]) ? $row[4] : null,
                'grado_norm'   => isset($row[4]) ? normalizarTexto($row[4]) : '',
                'anotacion_raw'=> isset($row[6]) ? $row[6] : null,
            ];
        }
        echo json_encode([
            'debug'            => true,
            'filterDocente'    => $filterDocente,
            'filterGrado'      => $filterGrado,
            'filterMateria'    => $filterMateria,
            'filterDocenteNorm'=> $filterDocente !== null ? normalizarTexto($filterDocente) : null,
            'filterGradoNorm'  => $filterGrado   !== null ? normalizarTexto($filterGrado)   : null,
            'filterMateriaNorm'=> $filterMateria  !== null ? normalizarTexto($filterMateria)  : null,
            'sampleRows'       => $sampleRows
        ]);
        exit;
    }

    if ($returnActividades && $filterGrado !== null) {
        $filterDocenteNorm = $filterDocente !== null ? normalizarTexto($filterDocente) : null;
        $filterGradoNorm   = normalizarTexto($filterGrado);
        $filterMateriaNorm = $filterMateria !== null ? normalizarTexto($filterMateria) : null;

        $registrosFiltrados = [];

        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i];

            $rowDocente = isset($row[2]) ? normalizarTexto($row[2]) : '';
            $rowMateria = isset($row[3]) ? normalizarTexto($row[3]) : '';
            $rowGrado   = isset($row[4]) ? normalizarTexto($row[4]) : '';

            $matchDocente = $filterDocenteNorm === null
                || strpos($rowDocente, $filterDocenteNorm) !== false
                || strpos($filterDocenteNorm, $rowDocente) !== false;

            $matchGrado = strpos($rowGrado, $filterGradoNorm) !== false
                || strpos($filterGradoNorm, $rowGrado) !== false;

            $matchMateria = $filterMateriaNorm === null
                || strpos($rowMateria, $filterMateriaNorm) !== false;

            if ($matchDocente && $matchGrado && $matchMateria) {
                $registrosFiltrados[] = ['rowIndex' => $i + 1, 'values' => $row];
            }
        }

        $total  = count($registrosFiltrados);
        $inicio = $total > $maxRegistros ? $total - $maxRegistros : 0;

        $actividades = [];
        for ($j = $inicio; $j < $total; $j++) {
            $row          = $registrosFiltrados[$j]['values'];
            $rowAnotacion = isset($row[6]) ? trim($row[6]) : '';
            if ($rowAnotacion && !in_array($rowAnotacion, $actividades)) {
                $actividades[] = $rowAnotacion;
            }
        }

        sort($actividades);

        echo json_encode([
            'success'       => true,
            'actividades'   => $actividades,
            'totalRegistros'=> $total
        ]);
        exit;
    }

    // Retorno general de todos los registros con índice
    $dataWithIndex = [];
    if ($values) {
        foreach ($values as $idx => $row) {
            $dataWithIndex[] = [
                'rowIndex' => $idx + 1,
                'values'   => $row
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'records' => $dataWithIndex
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
?>