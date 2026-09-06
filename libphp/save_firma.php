<?php
/**
 * save_firma.php - Guardar archivo de firma PNG en /ig/firmas/
 *
 * Recibe: { docente: string, firmaBase64: string }
 * Devuelve: { success: true, url: string } o { success: false, error: string }
 */

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
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    if (!isset($data['docente']) || !isset($data['firmaBase64'])) {
        echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
        exit;
    }

    $docente = $data['docente'];
    $firmaBase64 = $data['firmaBase64'];

    $docenteLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(trim($docente)));
    $timestamp = time();
    $filename = $docenteLimpio . '_' . $timestamp . '.png';

    $uploadDir = __DIR__ . '/firmas/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filepath = $uploadDir . $filename;

    // Remover prefijo data:image/png;base64, si existe
    $base64Data = $firmaBase64;
    if (strpos($base64Data, 'base64,') !== false) {
        $base64Data = explode('base64,', $base64Data)[1];
    }

    // Decodificar base64
    $decoded = base64_decode($base64Data, true);
    if ($decoded === false) {
        echo json_encode(['success' => false, 'error' => 'Base64 inválido']);
        exit;
    }

    // Verificar que sea una imagen PNG válida (magic bytes)
    $pngSignature = "\x89PNG\r\n\x1a\n";
    if (substr($decoded, 0, 8) !== $pngSignature) {
        echo json_encode(['success' => false, 'error' => 'El archivo no es un PNG válido']);
        exit;
    }

    // Guardar archivo
    if (file_put_contents($filepath, $decoded) === false) {
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo']);
        exit;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $url = $protocol . $host . '/ig/firmas/' . $filename;

    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>