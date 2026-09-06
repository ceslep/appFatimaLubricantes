<?php
/**
 * get_acta_area.php - Lectura de Plan de Aula desde Google Sheets
 *
 * Obtiene los registros de la hoja de cálculo de Plan de Aula.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $spreadsheetId = $_GET['spreadsheetId'] ?? '1Tifllb53X8JIZjtv7l11GwRIVeGqqSx8tYCzObABtdQ';
    $worksheetTitle = $_GET['worksheetTitle'] ?? 'ActaArea';
    // A:S = 19 columnas (incluye Participantes, OrdenDia, Desarrollo, Acuerdos,
    // Proxima, firmas). Antes leía solo A:K (11) y truncaba el acta rica.
    $range = $worksheetTitle . '!A:S';

    $client = new Client();
    $client->setApplicationName('Plan de Aula Backend');
    $client->setScopes([Sheets::SPREADSHEETS]);

    if (!file_exists(SERVICE_ACCOUNT_KEY_FILE)) {
        throw new Exception('Archivo de credenciales no encontrado.');
    }

    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);
    $service = new Sheets($client);

    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();

    echo json_encode([
        'success' => true,
        'values' => $values ?? []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>