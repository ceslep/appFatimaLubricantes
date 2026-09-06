<?php
/**
 * send_push_notification.php - Envía notificaciones push a suscriptores activos.
 *
 * Usa REST API de Sheets + JWT auth (sin google/apiclient).
 * Usa minishlink/web-push para enviar las notificaciones push.
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/gsheets.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

const SERVICE_ACCOUNT_KEY_FILE = __DIR__ . '/../assets/serviceaccount.json';
const SPREADSHEET_ID = '1PIo_oLVjQubdbLodigV3cwOfwQ29k-SGsRmbeorI3nM';
const WORKSHEET = 'push_subscriptions';
const VAPID_KEYS_FILE = __DIR__ . '/vapid-keys.json';

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

    if (!class_exists('Minishlink\\WebPush\\WebPush')) {
        throw new Exception('minishlink/web-push no está instalado.');
    }

    if (!file_exists(VAPID_KEYS_FILE)) {
        throw new Exception('Archivo vapid-keys.json no encontrado.');
    }
    $vapidKeys = json_decode(file_get_contents(VAPID_KEYS_FILE), true);
    if (empty($vapidKeys['publicKey']) || empty($vapidKeys['privateKey'])) {
        throw new Exception('vapid-keys.json debe contener publicKey y privateKey.');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    $title = trim($data['title'] ?? 'Polla Mundial 2026');
    $body = trim($data['body'] ?? '');
    $url = $data['url'] ?? '/polla/#/apostar';
    $tag = $data['tag'] ?? 'polla-notification';
    $icon = $data['icon'] ?? '/polla/icon-192.png';
    $image = $data['image'] ?? null;
    $actions = $data['actions'] ?? [];
    $notificationData = $data['data'] ?? [];
    $targetParticipant = $data['targetParticipant'] ?? null;
    $targetParticipants = $data['targetParticipants'] ?? [];

    if ($body === '') {
        throw new Exception('El campo body es requerido.');
    }

    // Leer suscripciones desde Sheets
    $token = gsheets_get_access_token(SERVICE_ACCOUNT_KEY_FILE);
    $rows = gsheets_get_values($token, SPREADSHEET_ID, WORKSHEET . '!A2:H1000');

    $subscriptions = [];
    $subscriptionRows = [];
    foreach ($rows as $i => $row) {
        $active = trim((string)($row[7] ?? 'TRUE'));
        if ($active !== 'TRUE') continue;

        $participantId = trim((string)($row[0] ?? ''));
        $endpoint = trim((string)($row[2] ?? ''));
        $p256dh = trim((string)($row[3] ?? ''));
        $authKey = trim((string)($row[4] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $authKey === '') continue;

        if ($targetParticipant !== null && $participantId !== $targetParticipant) continue;
        if (!empty($targetParticipants) && !in_array($participantId, $targetParticipants)) continue;

        $sub = Subscription::create([
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $authKey
            ]
        ]);

        $subscriptions[] = $sub;
        $subscriptionRows[$endpoint] = $i + 2;
    }

    if (empty($subscriptions)) {
        echo json_encode([
            'success' => true,
            'sent' => 0,
            'failed' => 0,
            'invalid' => 0,
            'message' => 'No hay suscriptores activos.'
        ]);
        exit;
    }

    // Configurar WebPush con VAPID
    $webPush = new WebPush([
        'VAPID' => [
            'subject' => 'mailto:ceslep@gmail.com',
            'publicKey' => $vapidKeys['publicKey'],
            'privateKey' => $vapidKeys['privateKey']
        ]
    ]);

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'tag' => $tag,
        'icon' => $icon,
        'image' => $image,
        'data' => $notificationData,
        'actions' => $actions
    ]);

    $sent = 0;
    $failed = 0;
    $invalidEndpoints = [];
    $debug = [];

    foreach ($subscriptions as $sub) {
        $report = $webPush->sendOneNotification($sub, $payload);

        // Diagnóstico por suscripción: guardamos el status HTTP real de FCM y
        // el motivo exacto del rechazo. Sin esto, un fallo se ve idéntico ya
        // sea por VAPID mismatch (403), suscripción muerta (404/410) o payload
        // inválido (400), y no hay forma de saber cuál es.
        $endpoint = $sub->getEndpoint();
        $entry = [
            'endpoint' => substr($endpoint, 0, 60) . '...',
            'success' => $report->isSuccess(),
            'expired' => $report->isSubscriptionExpired(),
            'reason' => $report->getReason(),
        ];
        $resp = $report->getResponse();
        if ($resp !== null) {
            $entry['statusCode'] = $resp->getStatusCode();
        }
        $debug[] = $entry;

        if ($report->isSuccess()) {
            $sent++;
        } else {
            $failed++;
            if ($report->isSubscriptionExpired()) {
                $invalidEndpoints[] = $endpoint;
            }
        }
    }

    // Desactivar suscripciones inválidas
    $invalid = 0;
    if (!empty($invalidEndpoints)) {
        foreach ($invalidEndpoints as $endpoint) {
            if (isset($subscriptionRows[$endpoint])) {
                $sheetRow = $subscriptionRows[$endpoint];
                try {
                    gsheets_batch_update($token, SPREADSHEET_ID, WORKSHEET . "!H{$sheetRow}:H{$sheetRow}", [['FALSE']]);
                    $invalid++;
                } catch (Exception $e) {
                    error_log("[push] Error desactivando suscripción inválida: " . $e->getMessage());
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'invalid' => $invalid,
        'totalSubscribers' => count($subscriptions),
        'debug' => $debug
    ]);

} catch (Exception $e) {
    $code = ($e->getMessage() === 'Método no permitido. Use POST.') ? 405 : 500;
    if (http_response_code() === 200 || !http_response_code()) {
        http_response_code($code);
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
