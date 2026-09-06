<?php
/**
 * Google Sheets Uploader - Backend
 * Recibe apuestas por POST y las guarda en Google Sheets
 * 
 * Usage: POST a este archivo con JSON:
 * {
 *   "bets": [
 *     {"timestamp": "...", "participant": "...", "bet_text": "...", ...}
 *   ],
 *   "mode": "write" | "append" | "clear"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

$CONFIG = [
    'spreadsheet_id' => '1PIo_oLVjQubdbLodigV3cwOfwQ29k-SGsRmbeorI3nM',
    'sheet_name' => 'datos'
];

function createSheetsService(): Sheets
{
    $client = new Client();
    $client->setAuthConfig(__DIR__ . '/assets/serviceaccount.json');
    $client->addScope(Sheets::SPREADSHEETS);
    return new Sheets($client);
}

function writeBets(array $bets, Sheets $service, string $spreadsheetId, string $sheetName): array
{
    $header = [['Fecha/Hora', 'Participante', 'Apuesta', 'Mensaje Original', 'Estado', 'Resultado Real', 'Puntos']];

    $rows = array_map(function ($bet) {
        return [
            $bet['timestamp'] ?? '',
            $bet['participant'] ?? '',
            $bet['bet_text'] ?? '',
            $bet['original_message'] ?? '',
            $bet['status'] ?? 'pending',
            $bet['real_result'] ?? '',
            $bet['points'] ?? '',
        ];
    }, $bets);

    $allRows = array_merge($header, $rows);

    $body = new Sheets\ValueRange(['values' => $allRows]);
    $params = ['valueInputOption' => 'RAW'];
    $range = $sheetName . '!A1';

    try {
        $result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
        return [
            'success' => true,
            'updated_cells' => $result->getUpdatedCells(),
            'total_rows' => count($allRows)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function appendBets(array $bets, Sheets $service, string $spreadsheetId, string $sheetName): array
{
    $rows = array_map(function ($bet) {
        return [
            $bet['timestamp'] ?? '',
            $bet['participant'] ?? '',
            $bet['bet_text'] ?? '',
            $bet['original_message'] ?? '',
            $bet['status'] ?? 'pending',
            $bet['real_result'] ?? '',
            $bet['points'] ?? '',
        ];
    }, $bets);

    $body = new Sheets\ValueRange(['values' => $rows]);
    $params = ['valueInputOption' => 'RAW'];

    try {
        $result = $service->spreadsheets_values->append($spreadsheetId, $sheetName, $body, $params);
        return [
            'success' => true,
            'updated_range' => $result->getUpdatesRange(),
            'total_bets' => count($bets)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function clearSheet(Sheets $service, string $spreadsheetId, string $sheetName): array
{
    try {
        $service->spreadsheets_values->clear($spreadsheetId, $sheetName, new Sheets\ClearValuesRequest());
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getBets(Sheets $service, string $spreadsheetId, string $sheetName): array
{
    try {
        $result = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $result->getValues();

        if (empty($values)) {
            return [];
        }

        $header = array_shift($values);

        $bets = [];
        foreach ($values as $row) {
            $bets[] = [
                'timestamp' => $row[0] ?? '',
                'participant' => $row[1] ?? '',
                'bet_text' => $row[2] ?? '',
                'original_message' => $row[3] ?? '',
                'status' => $row[4] ?? 'pending',
                'real_result' => $row[5] ?? '',
                'points' => $row[6] ?? 0,
            ];
        }

        return $bets;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$mode = $data['mode'] ?? 'write';
$bets = $data['bets'] ?? [];

$service = createSheetsService();

switch ($mode) {
    case 'clear':
        $result = clearSheet($service, $CONFIG['spreadsheet_id'], $CONFIG['sheet_name']);
        break;

    case 'append':
        if (empty($bets)) {
            $result = ['success' => false, 'error' => 'No bets provided'];
        } else {
            $result = appendBets($bets, $service, $CONFIG['spreadsheet_id'], $CONFIG['sheet_name']);
        }
        break;

    case 'write':
        if (empty($bets)) {
            $result = ['success' => false, 'error' => 'No bets provided'];
        } else {
            $result = writeBets($bets, $service, $CONFIG['spreadsheet_id'], $CONFIG['sheet_name']);
        }
        break;

    case 'read':
        $result = getBets($service, $CONFIG['spreadsheet_id'], $CONFIG['sheet_name']);
        break;

    default:
        $result = ['success' => false, 'error' => 'Unknown mode'];
}

echo json_encode($result);