<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

const SPREADSHEET_ID = '1QrTeZH7VhvRFfWvr2OKti80ePAO2qMN2DDLI6Lcm5Kc';
const RANGE = 'Datos!A:H'; // Ajusta columnas si cambias el orden


include_once("cors.php");
// Función para inicializar cliente
function getClient()
{
    $client = new Client();
    $client->setApplicationName('Plan Mejoramiento');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAuthConfig($serviceAccountPath = __DIR__ . '/assets/serviceaccount.json');
    $client->setAccessType('offline');
    return $client;
}

// 🚀 Recibir datos del formulario
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["error" => "No se recibieron datos"]);
    exit;
}

try {
    $client = getClient();
    $service = new Sheets($client);

    date_default_timezone_set('America/Bogota');
    $fechaHora = date('Y-m-d H:i:s'); // Fecha/hora registro

    $grupo = $data['grupo'] ?? '';
    $asignatura = $data['asignatura'] ?? '';
    $docente = $data['docenteSeleccionado'] ?? '';
    $fechaLimite = $data['fechaLimite'] ?? '';
    $planGeneral = $data['planMejoramiento'] ?? '';
    $planesIndividuales = $data['planesIndividuales'] ?? [];
    $estudiantes = $data['nombresEstudiante'] ?? [];
    $periodo = $data['periodo'] ?? '';

    // 1️⃣ Leer todos los valores existentes en la hoja
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, RANGE);
    $rows = $response->getValues() ?? [];

    $valuesToUpdate = [];

    foreach ($estudiantes as $estudiante) {
        $planIndividual = $planesIndividuales[$estudiante] ?? '';
        $planFinal = !empty(trim($planIndividual)) ? $planIndividual : $planGeneral;
        $found = false;
        $rowIndex = 0;

        // 2️⃣ Buscar coincidencia en las filas
        foreach ($rows as $index => $row) {
            $rowGrupo = $row[0] ?? '';
            $rowAsignatura = $row[1] ?? '';
            $rowDocente = $row[2] ?? '';
            $rowEstudiante = $row[3] ?? '';

            // Validación robusta ignorando espacios y mayúsculas/minúsculas
            if (
                mb_strtolower(trim($rowGrupo)) === mb_strtolower(trim($grupo)) &&
                mb_strtolower(trim($rowAsignatura)) === mb_strtolower(trim($asignatura)) &&
                mb_strtolower(trim($rowDocente)) === mb_strtolower(trim($docente)) &&
                mb_strtolower(trim($rowEstudiante)) === mb_strtolower(trim($estudiante))
            ) {
                $found = true;
                $rowIndex = $index + 1; // +1 porque Google Sheets es 1-based
                break;
            }
        }

        if ($found) {
            // 3️⃣ Si existe → Actualizar plan, fecha límite y período
            $updateRange = "Datos!E{$rowIndex}:H{$rowIndex}";
            $updateBody = new Google\Service\Sheets\ValueRange([
                'values' => [[$planFinal, $fechaLimite, $fechaHora, $periodo]]
            ]);
            $service->spreadsheets_values->update(
                SPREADSHEET_ID,
                $updateRange,
                $updateBody,
                ['valueInputOption' => 'USER_ENTERED']
            );
        } else {
            // 4️⃣ Si no existe → Insertar nueva fila
            $valuesToUpdate[] = [
                $grupo,
                $asignatura,
                $docente,
                $estudiante,
                $planFinal,
                $fechaLimite,
                $fechaHora,
                $periodo
            ];
        }
    }

    if (!empty($valuesToUpdate)) {
        $nextRow = count($rows) + 1;
        $updateRange = "Datos!A{$nextRow}:H";
        
        $body = new Google\Service\Sheets\ValueRange(['values' => $valuesToUpdate]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->update(SPREADSHEET_ID, $updateRange, $body, $params);
    }

    echo json_encode(["success" => true, "message" => "Datos procesados correctamente"]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
