<?php
require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

// ID de tu Google Sheet y rango
const SPREADSHEET_ID = '1IJGQWq9c6RCvrhSGZSdSACm2sQ_GhcnrchlJX0XRHOE';
const RANGE = 'Datos!A:Z'; // Se usará la hoja "Datos"

// Ruta a tu archivo de credenciales
const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/assets/serviceaccount.json';

// Configuración de cabeceras
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejo de preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $client = new Client();
    $client->setApplicationName('Google Sheets PHP API');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAuthConfig(SERVICE_ACCOUNT_KEY_FILE);

    $service = new Sheets($client);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leer datos enviados
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido.');
        }

        // Preparar valores
        $values = [];
        foreach ($data as $item) {
            $values[] = [
                $item['card'] ?? '',
                $item['teachers'] ?? ''
            ];
        }

        $body = new ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'RAW'];

        // Guardar (añadir filas nuevas)
        $result = $service->spreadsheets_values->append(
            SPREADSHEET_ID,
            RANGE,
            $body,
            $params
        );

        echo json_encode([
            'message' => 'Datos guardados exitosamente',
            'updates' => $result->getUpdates()
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener datos
        $response = $service->spreadsheets_values->get(SPREADSHEET_ID, RANGE);
        $values = $response->getValues();

        $formattedData = [];
        if (!empty($values)) {
            foreach ($values as $row) {
                if (isset($row[0]) && isset($row[1])) {
                    $formattedData[] = [
                        'card' => $row[0],
                        'teachers' => $row[1]
                    ];
                }
            }
        }

        echo json_encode($formattedData);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
