<?php
// v2.2 - Sheets Helper - fix lub_get_all range
require_once __DIR__ . '/lubricantes_config.php';

function lub_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function lub_get_token() {
    $sa = json_decode(file_get_contents(LUB_SA_PATH), true);
    $now = time();
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss'   => $sa['client_email'],
        'scope' => LUB_SCOPES,
        'aud'   => LUB_TOKEN_URL,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]);
    $signed = lub_base64url_encode($header) . '.' . lub_base64url_encode($payload);
    openssl_sign($signed, $signature, $sa['private_key'], 'SHA256');
    $jwt = $signed . '.' . lub_base64url_encode($signature);

    $ch = curl_init(LUB_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $resp['access_token'] ?? null;
}

function lub_active_sheet() {
    return (isset($GLOBALS['LUB_ACTIVE_SHEET']) && $GLOBALS['LUB_ACTIVE_SHEET'] !== '')
        ? $GLOBALS['LUB_ACTIVE_SHEET']
        : LUB_SPREADSHEET_ID;
}

function lub_values_api() {
    return 'https://sheets.googleapis.com/v4/spreadsheets/' . lub_active_sheet() . '/values/';
}

function lub_request($method, $range, $body = null) {
    $token = lub_get_token();
    if (!$token) return ['error' => 'No se pudo obtener token de acceso'];

    $url = lub_values_api() . urlencode($range);
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true)];
}

function lub_get($sheet, $range = 'A:H') {
    return lub_request('GET', $sheet . '!' . $range);
}

function lub_get_all($sheet) {
    // A:K (11 columnas): cubre todas las hojas del sistema
    // (ventas llegó a 11 columnas con Presentación y Cliente Doc)
    return lub_request('GET', $sheet . '!A:K');
}

function lub_ensure_sheet($title) {
    $check = lub_request('GET', $title . '!A1:A1');
    if (($check['code'] ?? 0) === 200) return true;
    $token = lub_get_token();
    if (!$token) return false;
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . lub_active_sheet() . '/:batchUpdate';
    $body = json_encode(['requests' => [['addSheet' => ['properties' => ['title' => $title]]]]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

function lub_append($sheet, $values) {
    $body = ['values' => [$values]];
    $range = $sheet . '!A:H';
    $url = lub_values_api() . urlencode($range) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
    $token = lub_get_token();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => $resp];
}

function lub_update($sheet, $row, $values) {
    $cols = chr(64 + count($values));
    $range = $sheet . '!A' . $row . ':' . $cols . $row;
    $body = ['values' => [$values]];
    $token = lub_get_token();
    $url = lub_values_api() . urlencode($range) . '?valueInputOption=USER_ENTERED';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => $resp];
}

function lub_delete_row($sheet, $row, $colCount = 8) {
    $cols = chr(64 + $colCount);
    $get = lub_get($sheet, 'A' . $row . ':' . $cols . $row);
    if (!isset($get['data']['values'])) {
        return ['code' => 404, 'data' => ['error' => 'Row not found']];
    }
    $empty = array_fill(0, $colCount, '');
    return lub_update($sheet, $row, $empty);
}
