<?php
/**
 * getPlanAula.php
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
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Obtener parámetros dinámicos de la query string o del cuerpo si es POST
    $spreadsheetId = $_GET['spreadsheetId'] ?? '1pkFF954kWh1aCAlyMlIjk7eQL1Povn3vO_5aJFxkM4c';
    $worksheetTitle = $_GET['worksheetTitle'] ?? 'plan';
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

    // Obtener valores
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
