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

function lub_request($method, $range, $body = null) {
    $token = lub_get_token();
    if (!$token) return ['error' => 'No se pudo obtener token de acceso'];

    $url = LUB_SHEETS_API . urlencode($range);
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
    return lub_request('GET', $sheet . '!A:H');
}

function lub_append($sheet, $values) {
    $body = ['values' => [$values]];
    $range = $sheet . '!A:H';
    $url = LUB_SHEETS_API . urlencode($range) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
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
    $url = LUB_SHEETS_API . urlencode($range) . '?valueInputOption=USER_ENTERED';
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
