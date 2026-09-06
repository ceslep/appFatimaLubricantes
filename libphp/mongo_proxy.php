<?php
/**
 * mongo_proxy.php
 * Proxy para MongoDB Atlas Data API
 * Ubicación: app.iedeoccidente.com/gs/mongo_proxy.php
 */
include_once("cors.php");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// === CONFIGURAR ESTA KEY EN EL SERVIDOR ===
// NO dejar esta key en el código fuente que se sube a git
define('MONGODB_DATA_API_KEY', '9ceae089-8ed5-495a-950a-c18e3cae3103');
define('MONGODB_CLUSTER_NAME', 'Cluster0');
// ============================================
$input = file_get_contents('php://input');
$request = json_decode($input, true);
$action = $request['action'] ?? '';
$collection = $request['collection'] ?? 'inasistencias';
$payload = $request['payload'] ?? [];
$url = "https://data.mongodb-api.com/app/" . MONGODB_CLUSTER_NAME . "/endpoint/data/v1/action/" . $action;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'api-key: ' . MONGODB_DATA_API_KEY
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => curl_error($ch)]);
} else {
    echo $response;
}
curl_close($ch);
?>