<?php
/**
 * migrate_to_mongo.php
 * Migra datos de Google Sheets → MongoDB Atlas
 * VERSIÓN CON DEPURACIÓN EXTREMA
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
echo " Paso 0: Inicio del script\n";
flush();
// === Verificar archivos necesarios ===
echo " Paso 1: Verificando archivos\n";
$serviceAccountFile = __DIR__ . '/assets/serviceaccount.json';
if (!file_exists($serviceAccountFile)) {
    echo "ERROR: Service account no encontrado: $serviceAccountFile\n";
    file_put_contents('/tmp/migrate_error.log', "Service account no existe\n");
    exit;
}
echo "  ✓ Service account existe\n";
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "ERROR: autoload.php no encontrado\n";
    exit;
}
echo "  ✓ autoload.php existe\n";
flush();
// === Cargar Google API ===
echo " Paso 2: Cargando Google API\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "  ✓ autoload cargado\n";
    flush();
    
    // Intentar con el namespace correcto
    if (class_exists('Google\Client')) {
        echo "  ✓ Google\Client existe\n";
    } elseif (class_exists('Google_Client')) {
        echo "  ✓ Google_Client existe (legacy)\n";
    } else {
        echo "ERROR: Google\Client no disponible\n";
        exit;
    }
    flush();
} catch (Throwable $e) {
    echo "ERROR cargando Google API: " . $e->getMessage() . "\n";
    exit;
}
// === Autenticar ===
echo " Paso 3: Autenticando con Google\n";
try {
    $client = new Google\Client();
    $client->setApplicationName('Migration');
    $client->setScopes(['https://www.googleapis.com/auth/spreadsheets.readonly']);
    $client->setAuthConfig($serviceAccountFile);
    echo "  ✓ Cliente creado\n";
    flush();
    
    $service = new Google\Service\Sheets($client);
    echo "  ✓ Service Sheets creado\n";
    flush();
} catch (Throwable $e) {
    echo "ERROR autenticando: " . $e->getMessage() . "\n";
    exit;
}
// === Leer Google Sheets ===
echo " Paso 4: Leyendo Google Sheets\n";
$spreadsheetId = '1wN7lp7lOGyxKYIUJ9TU89N9knnJjX2Z_TfsOUg48QpQ';
$worksheetTitle = 'Inasistencias';
try {
    $range = $worksheetTitle . '!A1:Z10000';
    echo "  Consultando: $spreadsheetId / $range\n";
    flush();
    
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "ADVERTENCIA: Hoja vacía\n";
        echo json_encode(['success' => false, 'message' => 'No hay datos']);
        exit;
    }
    
    echo "  ✓ Leídas " . count($values) . " filas\n";
    flush();
} catch (Throwable $e) {
    echo "ERROR leyendo Sheets: " . $e->getMessage() . "\n";
    exit;
}
// === Agrupar datos ===
echo " Paso 5: Agrupando datos\n";
$headers = $values[0];
$dataRows = array_slice($values, 1);
echo "  Filas de datos: " . count($dataRows) . "\n";
flush();
$grouped = [];
foreach ($dataRows as $row) {
    $fecha = $row[0] ?? '';
    $docente = $row[1] ?? '';
    $materia = $row[2] ?? '';
    $grado = $row[3] ?? '';
    $estudiante = $row[4] ?? '';
    $presente = strtolower($row[5] ?? '') === 'presente';
    $justificacion = $row[6] ?? '';
    $observaciones = $row[7] ?? '';
    
    if (empty($fecha) || empty($docente)) continue;
    
    $key = "{$fecha}|{$docente}|{$materia}|{$grado}";
    
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'fecha' => $fecha,
            'docente' => $docente,
            'materia' => $materia,
            'grado' => $grado,
            'estudiantes' => [],
            'observaciones' => ''
        ];
    }
    
    $grouped[$key]['estudiantes'][] = [
        'nombre' => $estudiante,
        'presente' => $presente,
        'justificacion' => $justificacion
    ];
    
    if (!empty($observaciones) && empty($grouped[$key]['observaciones'])) {
        $grouped[$key]['observaciones'] = $observaciones;
    }
}
$totalDocs = count($grouped);
echo "  Documentos a crear: {$totalDocs}\n";
flush();
// === Insertar en MongoDB ===
echo " Paso 6: Insertando en MongoDB\n";
$inserted = 0;
$errors = 0;
$batch = array_values($grouped);
$batchSize = 50;
for ($i = 0; $i < count($batch); $i += $batchSize) {
    $documents = [];
    $currentBatch = array_slice($batch, $i, $batchSize);
    
    foreach ($currentBatch as $doc) {
        $totalEst = count($doc['estudiantes']);
        $totalInas = count(array_filter($doc['estudiantes'], function($e) { return !$e['presente']; }));
        
        $documents[] = [
            'fecha' => $doc['fecha'],
            'docente' => $doc['docente'],
            'materia' => $doc['materia'],
            'grado' => $doc['grado'],
            'estudiantes' => $doc['estudiantes'],
            'totalEstudiantes' => $totalEst,
            'totalInasistencias' => $totalInas,
            'observaciones' => $doc['observaciones'],
            'migratedAt' => date('c')
        ];
    }
    
    $ch = curl_init("https://data.mongodb-api.com/app/Cluster0/endpoint/data/v1/action/insertMany");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['documents' => $documents]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: 9ceae089-8ed5-495a-950a-c18e3cae3103'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "  Error curl: {$curlError}\n";
        $errors += count($documents);
    } elseif ($httpCode == 201 || $httpCode == 200) {
        $inserted += count($documents);
        echo "  Progreso: {$inserted}/{$totalDocs}\n";
    } else {
        echo "  Error HTTP {$httpCode}: " . substr($result, 0, 200) . "\n";
        $errors += count($documents);
    }
    flush();
    
    usleep(200000);
}
echo "\n=== RESULTADO ===\n";
$result = [
    'success' => ($errors == 0),
    'totalDocuments' => $totalDocs,
    'inserted' => $inserted,
    'errors' => $errors
];
echo json_encode($result, JSON_PRETTY_PRINT);
?>