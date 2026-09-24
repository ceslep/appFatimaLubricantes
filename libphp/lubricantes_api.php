<?php
// v4.2 - API Lubricantes - Resumen general: desglose por cajero y por forma de pago
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/lubricantes_sheets.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// ---- Multi-negocio: cada negocio usa su propio spreadsheet ----
$lub_negocios = [
    'estacion' => LUB_SPREADSHEET_ID,
    'otro'     => '1f9EfI03bDXehvF9cGEdbU2KHsS3BHMDxGQU7wsf8BQA',
    // Las islas de combustible son un negocio aparte, pero sus datos viven en el
    // spreadsheet principal (pestaña 'islas' y su config de precios).
    'islas'    => LUB_SPREADSHEET_ID,
];
$negocioSolicitado = strtolower(trim($_GET['negocio'] ?? 'estacion'));
if (!array_key_exists($negocioSolicitado, $lub_negocios)) $negocioSolicitado = 'estacion';
$GLOBALS['LUB_ACTIVE_SHEET'] = $lub_negocios[$negocioSolicitado];

// Datos compartidos entre negocios: usuarios y clientes viven en el spreadsheet principal
$lub_shared = ['login', 'listar_usuarios', 'crear_usuario', 'update_usuario', 'eliminar_usuario', 'listar_clientes', 'cliente_existe', 'registrar_cliente', 'actualizar_cliente', 'eliminar_cliente'];
if (in_array($action, $lub_shared)) {
    $GLOBALS['LUB_ACTIVE_SHEET'] = LUB_SPREADSHEET_ID;
}

function ensure_headers($sheet, $headers) {
    // La verificación de encabezados se cachea: en el uso normal ya existen y
    // solo hacíamos una petición extra a Sheets en cada acción.
    $flag = lub_cache_path('headers_' . md5(lub_active_sheet() . '|' . $sheet));
    if ($flag !== '' && is_file($flag) && (time() - filemtime($flag)) < 43200) return;

    $check = lub_get($sheet, 'A1:H1');
    if (isset($check['data']['values'][0]) && !empty($check['data']['values'][0][0])) {
        if ($flag !== '') @touch($flag);
        return;
    }

    $token = lub_get_token();
    $cols = chr(64 + count($headers));
    // Usa el spreadsheet activo (coherente con multi-negocio).
    $url = lub_values_api() . urlencode($sheet . '!A1:' . $cols . '1') . '?valueInputOption=RAW';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => json_encode(['values' => [$headers]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
    if ($flag !== '') @touch($flag);
}

// Encabezados de la hoja 'islas'. A diferencia de ensure_headers(), compara la
// fila completa: si la hoja se creó con el layout anterior (9 columnas, sin
// 'Usuario') reescribe solo la fila 1 para agregar la columna nueva, sin tocar
// los datos ya registrados.
function ensure_headers_islas() {
    $flag = lub_cache_path('headers_' . md5(lub_active_sheet() . '|' . LUB_HOJA_ISLAS . '|v3'));
    if ($flag !== '' && is_file($flag) && (time() - filemtime($flag)) < 43200) return;

    $cols = chr(64 + count(LUB_HEADERS_ISLAS));
    $check = lub_get(LUB_HOJA_ISLAS, 'A1:' . $cols . '1');
    $actual = $check['data']['values'][0] ?? [];
    if ($actual === LUB_HEADERS_ISLAS) {
        if ($flag !== '') @touch($flag);
        return;
    }

    $token = lub_get_token();
    if (!$token) return;
    $url = lub_values_api() . urlencode(LUB_HOJA_ISLAS . '!A1:' . $cols . '1') . '?valueInputOption=RAW';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => json_encode(['values' => [LUB_HEADERS_ISLAS]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
    if ($flag !== '') @touch($flag);
}

// Normaliza un nombre de cajero para comparar (espacios y mayúsculas).
function lub_norm_cajero($valor) {
    $s = trim((string)$valor);
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

// ¿Alguno de los valores coincide con el filtro de cajero? (filtro vacío = todos).
// Acepta varios valores porque una lectura de isla guarda el nombre visible
// (Cajero) y el usuario de login (Usuario): cualquiera de los dos sirve.
function lub_coincide_cajero($filtro, ...$valores) {
    if (trim((string)$filtro) === '') return true;
    $f = lub_norm_cajero($filtro);
    foreach ($valores as $v) {
        if (lub_norm_cajero($v) === $f) return true;
    }
    return false;
}

// Columna 'Cajero' de una fila de ventas, tolerando los layouts antiguos.
function lub_cajero_de_venta($row) {
    $n = count($row);
    if ($n >= 10) return $row[9] ?? '';
    if ($n === 9) return $row[8] ?? '';
    return $row[7] ?? '';
}

// Columna 'Forma de pago' de una fila de ventas, tolerando los layouts antiguos
// (las filas sin forma de pago devuelven cadena vacía).
function lub_forma_pago_de_venta($row) {
    $n = count($row);
    if ($n >= 10) return trim((string)($row[6] ?? ''));
    if ($n === 9) return trim((string)($row[5] ?? ''));
    return '';
}

// Clave de una lectura de isla: día + isla + combustible + AUTOR.
// El autor es parte de la clave a propósito: volver a guardar el mismo día
// corrige la propia fila, pero nunca reemplaza la de otro cajero.
function lub_clave_lectura($dia, $isla, $comb, $autor) {
    return $dia . '|' . strtolower(trim((string)$isla)) . '|' . strtolower(trim((string)$comb)) . '|' . strtolower(trim((string)$autor));
}

// Mapa login (en minúsculas) -> nombre completo, desde la hoja de usuarios.
function lub_mapa_usuarios() {
    $mapa = [];
    $res = lub_get_all(LUB_HOJA_USUARIOS);
    if (isset($res['data']['values'])) {
        foreach ($res['data']['values'] as $i => $row) {
            if ($i === 0 && strtolower(trim($row[0] ?? '')) === 'usuario') continue;
            $login = strtolower(trim((string)($row[0] ?? '')));
            if ($login === '') continue;
            $mapa[$login] = trim((string)($row[3] ?? ''));
        }
    }
    return $mapa;
}

// Identidad canónica de un cajero, para poder sumar en una misma fila lo que
// viene de islas (que guarda login + nombre) y de ventas (que solo guarda nombre).
function lub_autor_canonico($login, $nombre, $mapa) {
    $loginKey = strtolower(trim((string)$login));
    $nombre = trim((string)$nombre);
    if ($loginKey !== '' && isset($mapa[$loginKey]) && trim((string)$mapa[$loginKey]) !== '') {
        return trim((string)$mapa[$loginKey]);
    }
    if ($nombre !== '') return $nombre;
    if ($loginKey !== '') return trim((string)$login);
    return 'Sin asignar';
}

// Acumula un monto en la fila del cajero dentro del desglose por cajero.
function lub_sumar_por_cajero(&$mapa, $autor, $campo, $monto) {
    if (!isset($mapa[$autor])) {
        $mapa[$autor] = [
            'cajero' => $autor,
            'islas' => 0.0,
            'islas_galones' => 0.0,
            'estacion' => 0.0,
            'tienda' => 0.0,
            'num_ventas' => 0,
        ];
    }
    $mapa[$autor][$campo] += $monto;
}

function lub_precios_combustible() {
    // Precios vigentes por combustible, definidos en Configuración (hoja config).
    $precios = ['Gasolina' => 0.0, 'ACPM' => 0.0];
    $claves = ['Gasolina' => LUB_CFG_PRECIO_GASOLINA, 'ACPM' => LUB_CFG_PRECIO_ACPM];
    $res = lub_get_all(LUB_HOJA_CONFIG);
    if (isset($res['data']['values'])) {
        foreach ($res['data']['values'] as $i => $row) {
            if ($i === 0) continue;
            $clave = strtolower(trim($row[0] ?? ''));
            if ($clave === '') continue;
            foreach ($claves as $comb => $cfgKey) {
                if ($clave === $cfgKey) $precios[$comb] = lub_money($row[1] ?? 0);
            }
        }
    }
    return $precios;
}

try {
    switch ($action) {

        case 'listar_productos_catalogo':
            ensure_headers(LUB_HOJA_DATOS, LUB_HEADERS_DATOS);
            $result = lub_get_all(LUB_HOJA_DATOS);
            $productos = [];
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $activo = $row[4] ?? 'TRUE';
                    if (strtoupper($activo) !== 'TRUE') continue;
                    $productos[] = [
                        'producto' => $row[0] ?? '',
                        'presentacion' => $row[1] ?? '',
                        'precio_venta' => floatval(str_replace(['$', '.'], '', $row[2] ?? '0')),
                        'stock' => floatval($row[3] ?? 0),
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $productos]);
            exit;

        case 'obtener_config':
            lub_ensure_sheet(LUB_HOJA_CONFIG);
            ensure_headers(LUB_HOJA_CONFIG, LUB_HEADERS_CONFIG);
            $res = lub_get_all(LUB_HOJA_CONFIG);
            $valores = [];
            if (isset($res['data']['values'])) {
                foreach ($res['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $clave = strtolower(trim($row[0] ?? ''));
                    if ($clave === '') continue;
                    // Valor tal cual: los mensajes conservan mayúsculas y saltos de línea.
                    $valores[$clave] = trim((string)($row[1] ?? ''));
                }
            }
            $booleanos = [
                'enviar_whatsapp' => 'FALSE',
                'cliente_obligatorio' => 'FALSE',
            ];
            $out = [];
            foreach ($booleanos as $k => $d) {
                $v = strtoupper($valores[$k] ?? '');
                $out[$k] = in_array($v, ['TRUE', 'FALSE']) ? $v : $d;
            }
            $out['mensaje_registro'] = $valores['mensaje_registro'] ?? '';
            $out['logo_data'] = $valores['logo_data'] ?? '';
            // Islas de combustible: precios vigentes y catálogo de islas.
            $out['precio_gasolina'] = $valores[LUB_CFG_PRECIO_GASOLINA] ?? '';
            $out['precio_acpm'] = $valores[LUB_CFG_PRECIO_ACPM] ?? '';
            $islas = [];
            $rawIslas = $valores[LUB_CFG_ISLAS] ?? '';
            if ($rawIslas !== '') {
                $decoded = json_decode($rawIslas, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $nombre) {
                        $nombre = trim((string)$nombre);
                        if ($nombre !== '') $islas[] = $nombre;
                    }
                }
            }
            $out['islas'] = $islas;
            echo json_encode(['success' => true, 'data' => $out]);
            exit;

        case 'guardar_config':
            lub_ensure_sheet(LUB_HOJA_CONFIG);
            ensure_headers(LUB_HOJA_CONFIG, LUB_HEADERS_CONFIG);
            $cambios = [];
            foreach (['enviar_whatsapp', 'cliente_obligatorio'] as $clave) {
                if (array_key_exists($clave, $input)) {
                    $valor = strtoupper(trim((string)$input[$clave]));
                    if (!in_array($valor, ['TRUE', 'FALSE'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Valor invalido para ' . $clave]);
                        exit;
                    }
                    $cambios[$clave] = $valor;
                }
            }
            // Mensaje libre que se muestra en la página pública de registro.
            if (array_key_exists('mensaje_registro', $input)) {
                $mensaje = trim((string)$input['mensaje_registro']);
                $mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 500) : substr($mensaje, 0, 500);
                $cambios['mensaje_registro'] = $mensaje;
            }
            // Logo de la app: se guarda como data URL de imagen en la hoja de configuración.
            if (array_key_exists('logo_data', $input)) {
                $logo = trim((string)$input['logo_data']);
                if ($logo !== '') {
                    if (strpos($logo, 'data:image/') !== 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'El logo debe ser una imagen valida']);
                        exit;
                    }
                    if (strlen($logo) > 49000) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'El logo es demasiado grande']);
                        exit;
                    }
                }
                $cambios['logo_data'] = $logo;
            }
            // Precios de combustible (por galón) usados para valorar las lecturas de las islas.
            foreach ([LUB_CFG_PRECIO_GASOLINA, LUB_CFG_PRECIO_ACPM] as $clave) {
                if (array_key_exists($clave, $input)) {
                    $valor = lub_money($input[$clave]);
                    if ($valor < 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'El precio de ' . $clave . ' no puede ser negativo']);
                        exit;
                    }
                    $cambios[$clave] = (string)$valor;
                }
            }
            // Catálogo de islas: lista de nombres guardada como JSON.
            if (array_key_exists(LUB_CFG_ISLAS, $input)) {
                $rawIslas = $input[LUB_CFG_ISLAS];
                if (is_string($rawIslas)) $rawIslas = json_decode($rawIslas, true);
                if (!is_array($rawIslas)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Formato de islas invalido']);
                    exit;
                }
                $limpias = [];
                foreach ($rawIslas as $nombre) {
                    $nombre = trim((string)$nombre);
                    if ($nombre === '') continue;
                    if (strlen($nombre) > 40) $nombre = substr($nombre, 0, 40);
                    if (!in_array($nombre, $limpias, true)) $limpias[] = $nombre;
                    if (count($limpias) >= 40) break;
                }
                $cambios[LUB_CFG_ISLAS] = json_encode($limpias);
            }
            if (empty($cambios)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Sin parametros de configuracion']);
                exit;
            }
            foreach ($cambios as $clave => $valor) {
                $res = lub_get_all(LUB_HOJA_CONFIG);
                $fila = 0;
                if (isset($res['data']['values'])) {
                    foreach ($res['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        if (strtolower(trim($row[0] ?? '')) === $clave) {
                            $fila = $i + 1;
                            break;
                        }
                    }
                }
                if ($fila > 0) {
                    lub_update(LUB_HOJA_CONFIG, $fila, [$clave, $valor]);
                } else {
                    lub_append(LUB_HOJA_CONFIG, [$clave, $valor]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Configuracion guardada']);
            exit;

        case 'productos_mas_vendidos':
            ensure_headers(LUB_HOJA_DATOS, LUB_HEADERS_DATOS);
            ensure_headers(LUB_HOJA_VENTAS, LUB_HEADERS_VENTAS);
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            $resultVentas = lub_get_all(LUB_HOJA_VENTAS);
            $catalogo = [];
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (strtoupper($row[4] ?? 'TRUE') !== 'TRUE') continue;
                    $catalogo[$row[0] ?? ''] = [
                        'producto' => $row[0] ?? '',
                        'presentacion' => $row[1] ?? '',
                        'precio_venta' => floatval(str_replace(['$', '.'], '', $row[2] ?? '0')),
                        'stock' => floatval($row[3] ?? 0),
                    ];
                }
            }
            $ventasCount = [];
            if (isset($resultVentas['data']['values'])) {
                foreach ($resultVentas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $prod = $row[1] ?? '';
                    if (!$prod) continue;
                    $cantIdx = count($row) >= 10 ? 3 : 2;
                    $cant = floatval($row[$cantIdx] ?? 0);
                    if (!isset($ventasCount[$prod])) $ventasCount[$prod] = 0;
                    $ventasCount[$prod] += $cant;
                }
            }
            arsort($ventasCount);
            $top = [];
            $count = 0;
            foreach ($ventasCount as $prod => $totalVendido) {
                if ($count >= 16) break;
                if (isset($catalogo[$prod])) {
                    $item = $catalogo[$prod];
                    $item['total_vendido'] = $totalVendido;
                    $top[] = $item;
                    $count++;
                }
            }
            if (count($top) < 16) {
                foreach ($catalogo as $prod => $item) {
                    if (count($top) >= 16) break;
                    $exists = false;
                    foreach ($top as $t) { if ($t['producto'] === $prod) { $exists = true; break; } }
                    if (!$exists) {
                        $item['total_vendido'] = 0;
                        $top[] = $item;
                    }
                }
            }
            echo json_encode(['success' => true, 'data' => array_slice($top, 0, 16)]);
            exit;

        case 'registrar_producto':
            $producto = $input['producto'] ?? '';
            $presentacion = $input['presentacion'] ?? '';
            $precio_venta = $input['precio_venta'] ?? 0;
            if (!$producto) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Producto requerido']);
                exit;
            }
            ensure_headers(LUB_HOJA_DATOS, LUB_HEADERS_DATOS);
            $values = [$producto, $presentacion, $precio_venta, 0, 'TRUE'];
            $result = lub_append(LUB_HOJA_DATOS, $values);
            if ($result['code'] === 200) {
                echo json_encode(['success' => true, 'message' => 'Producto registrado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al registrar producto']);
            }
            exit;

        case 'actualizar_precio':
            $producto = $input['producto'] ?? '';
            $presentacion = $input['presentacion'] ?? '';
            $precio_venta = $input['precio_venta'] ?? 0;
            if (!$producto) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Producto requerido']);
                exit;
            }
            ensure_headers(LUB_HOJA_DATOS, LUB_HEADERS_DATOS);
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (($row[0] ?? '') === $producto && ($row[1] ?? '') === $presentacion) {
                        $rowNum = $i + 1;
                        lub_update(LUB_HOJA_DATOS, $rowNum, [
                            $row[0] ?? '',
                            $row[1] ?? '',
                            $precio_venta,
                            $row[3] ?? 0,
                            $row[4] ?? 'TRUE',
                        ]);
                        echo json_encode(['success' => true, 'message' => 'Precio actualizado']);
                        exit;
                    }
                }
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            exit;

        case 'actualizar_stock':
            $producto = $input['producto'] ?? '';
            $presentacion = $input['presentacion'] ?? '';
            $stock = $input['stock'] ?? 0;
            if (!$producto) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Producto requerido']);
                exit;
            }
            ensure_headers(LUB_HOJA_DATOS, LUB_HEADERS_DATOS);
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (($row[0] ?? '') === $producto && ($row[1] ?? '') === $presentacion) {
                        $rowNum = $i + 1;
                        lub_update(LUB_HOJA_DATOS, $rowNum, [
                            $row[0] ?? '',
                            $row[1] ?? '',
                            $row[2] ?? 0,
                            $stock,
                            $row[4] ?? 'TRUE',
                        ]);
                        echo json_encode(['success' => true, 'message' => 'Stock actualizado']);
                        exit;
                    }
                }
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            exit;

        case 'registrar_venta':
            ensure_headers(LUB_HOJA_VENTAS, LUB_HEADERS_VENTAS);
            $now = date('d/m/Y H:i');
            $producto = trim($input['producto'] ?? '');
            $presentacion = trim($input['presentacion'] ?? '');
            $cantidad = floatval($input['cantidad'] ?? 0);
            $precio_unitario = 0;
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (trim($row[0] ?? '') !== $producto) continue;
                    if ($presentacion !== '' && trim($row[1] ?? '') !== $presentacion) continue;
                    $precio_unitario = floatval(str_replace(['$', '.'], '', $row[2] ?? '0'));
                    break;
                }
            }
            $total = $cantidad * $precio_unitario;
            $values = [
                $now,
                $producto,
                $presentacion,
                $cantidad,
                $precio_unitario,
                $total,
                $input['forma_pago'] ?? 'Efectivo',
                $input['cliente'] ?? '',
                $input['placa'] ?? '',
                $input['cajero'] ?? '',
                trim($input['cliente_doc'] ?? ''),
            ];
            $result = lub_append(LUB_HOJA_VENTAS, $values);
            if ($result['code'] === 200) {
                if (isset($resultDatos['data']['values'])) {
                    foreach ($resultDatos['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        if (trim($row[0] ?? '') !== $producto) continue;
                        if ($presentacion !== '' && trim($row[1] ?? '') !== $presentacion) continue;
                        $rowNum = $i + 1;
                        $stockActual = floatval($row[3] ?? 0);
                        $nuevoStock = $stockActual - $cantidad;
                        lub_update(LUB_HOJA_DATOS, $rowNum, [
                            $row[0] ?? '',
                            $row[1] ?? '',
                            $row[2] ?? '',
                            $nuevoStock,
                            $row[4] ?? 'TRUE',
                        ]);
                        break;
                    }
                }
                lub_append(LUB_HOJA_SALIDAS, [
                    $now,
                    $producto,
                    $cantidad,
                    $precio_unitario,
                    'Venta',
                    $input['cajero'] ?? '',
                ]);
                echo json_encode(['success' => true, 'message' => 'Venta registrada']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al registrar venta']);
            }
            exit;

        case 'registrar_venta_multiple':
            ensure_headers(LUB_HOJA_VENTAS, LUB_HEADERS_VENTAS);
            $items = $input['items'] ?? [];
            $cliente = $input['cliente'] ?? '';
            $clienteDoc = trim($input['cliente_doc'] ?? '');
            $placa = $input['placa'] ?? '';
            $cajero = $input['cajero'] ?? '';
            $formaPago = $input['forma_pago'] ?? 'Efectivo';
            if (empty($items)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No hay items para registrar']);
                exit;
            }
            $now = date('d/m/Y H:i');
            $ok = 0;
            $errors = [];
            $datosVentas = [];
            $preciosDatos = [];
            $resultDatosAll = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatosAll['data']['values'])) {
                foreach ($resultDatosAll['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $nombre = trim($row[0] ?? '');
                    $pres = trim($row[1] ?? '');
                    $clave = $nombre . '|' . $pres;
                    $datosVentas[] = ['nombre' => $nombre, 'pres' => $pres, 'fila' => $i + 1];
                    if (!isset($preciosDatos[$clave])) {
                        $preciosDatos[$clave] = floatval(str_replace(['$', '.'], '', $row[2] ?? '0'));
                    }
                }
            }
            foreach ($items as $item) {
                $producto = trim($item['producto'] ?? '');
                $presentacionItem = trim($item['presentacion'] ?? '');
                $cantidad = floatval($item['cantidad'] ?? 0);
                $claveItem = $producto . '|' . $presentacionItem;
                $precio_unitario = $preciosDatos[$claveItem] ?? 0;
                $total = $cantidad * $precio_unitario;
                $values = [$now, $producto, $presentacionItem, $cantidad, $precio_unitario, $total, $formaPago, $cliente, $placa, $cajero, $clienteDoc];
                $result = lub_append(LUB_HOJA_VENTAS, $values);
                if ($result['code'] === 200) {
                    $ok++;
                    // Descontar stock de la referencia exacta (nombre + presentación)
                    $filaAfectada = 0;
                    foreach ($datosVentas as $d) {
                        if ($d['nombre'] === $producto && $d['pres'] === $presentacionItem) {
                            $filaAfectada = $d['fila'];
                            break;
                        }
                    }
                    if ($filaAfectada > 0 && isset($resultDatosAll['data']['values'][$filaAfectada - 1])) {
                        $rrow = $resultDatosAll['data']['values'][$filaAfectada - 1];
                        $stockActual = floatval($rrow[3] ?? 0);
                        $nuevoStock = $stockActual - $cantidad;
                        lub_update(LUB_HOJA_DATOS, $filaAfectada, [
                            $rrow[0] ?? '', $rrow[1] ?? '', $rrow[2] ?? '', $nuevoStock, $rrow[4] ?? 'TRUE',
                        ]);
                    }
                    lub_append(LUB_HOJA_SALIDAS, [$now, $producto, $cantidad, $precio_unitario, 'Venta', $cajero]);
                } else {
                    $errors[] = $producto;
                }
            }
            echo json_encode(['success' => true, 'message' => "$ok ventas registradas", 'errors' => $errors]);
            exit;

        case 'listar_ventas':
            ensure_headers(LUB_HOJA_VENTAS, LUB_HEADERS_VENTAS);
            $result = lub_get_all(LUB_HOJA_VENTAS);
            $ventas = [];
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0 && strtolower($row[0] ?? '') === 'fecha') continue;
                    if (count($row) >= 11) {
                        // Layout con Presentación y Cliente Doc
                        $ventas[] = [
                            'row' => $i + 1,
                            'fecha' => $row[0] ?? '',
                            'producto' => $row[1] ?? '',
                            'presentacion' => $row[2] ?? '',
                            'cantidad' => floatval(str_replace(['$', '.'], '', $row[3] ?? '0')),
                            'precio_unitario' => floatval(str_replace(['$', '.'], '', $row[4] ?? '0')),
                            'total' => floatval(str_replace(['$', '.'], '', $row[5] ?? '0')),
                            'forma_pago' => $row[6] ?? '',
                            'cliente' => $row[7] ?? '',
                            'placa' => $row[8] ?? '',
                            'cajero' => $row[9] ?? '',
                            'cliente_doc' => $row[10] ?? '',
                        ];
                        continue;
                    }
                    if (count($row) === 10) {
                        // Presentación pero sin Cliente Doc (transición)
                        $ventas[] = [
                            'row' => $i + 1,
                            'fecha' => $row[0] ?? '',
                            'producto' => $row[1] ?? '',
                            'presentacion' => $row[2] ?? '',
                            'cantidad' => floatval(str_replace(['$', '.'], '', $row[3] ?? '0')),
                            'precio_unitario' => floatval(str_replace(['$', '.'], '', $row[4] ?? '0')),
                            'total' => floatval(str_replace(['$', '.'], '', $row[5] ?? '0')),
                            'forma_pago' => $row[6] ?? '',
                            'cliente' => $row[7] ?? '',
                            'cliente_doc' => '',
                            'placa' => $row[8] ?? '',
                            'cajero' => $row[9] ?? '',
                        ];
                        continue;
                    }
                    // Filas antiguas (sin Presentación)
                    $hasFormaPago = count($row) >= 9;
                    $ventas[] = [
                        'row' => $i + 1,
                        'fecha' => $row[0] ?? '',
                        'producto' => $row[1] ?? '',
                        'presentacion' => '',
                        'cantidad' => floatval(str_replace(['$', '.'], '', $row[2] ?? '0')),
                        'precio_unitario' => floatval(str_replace(['$', '.'], '', $row[3] ?? '0')),
                        'total' => floatval(str_replace(['$', '.'], '', $row[4] ?? '0')),
                        'forma_pago' => $hasFormaPago ? ($row[5] ?? '') : '',
                        'cliente' => $hasFormaPago ? ($row[6] ?? '') : ($row[5] ?? ''),
                        'cliente_doc' => '',
                        'placa' => $hasFormaPago ? ($row[7] ?? '') : ($row[6] ?? ''),
                        'cajero' => $hasFormaPago ? ($row[8] ?? '') : ($row[7] ?? ''),
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $ventas]);
            exit;

        case 'eliminar_venta':
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            lub_delete_row(LUB_HOJA_VENTAS, $row, 11);
            echo json_encode(['success' => true, 'message' => 'Venta eliminada']);
            exit;

        case 'registrar_entrada':
            ensure_headers(LUB_HOJA_ENTRADAS, LUB_HEADERS_ENTRADAS);
            $now = date('d/m/Y H:i');
            $producto = $input['producto'] ?? '';
            $presentacion = trim($input['presentacion'] ?? '');
            $cantidad = floatval($input['cantidad'] ?? 0);
            $precioVentaInput = floatval(str_replace(['$', '.'], '', trim($input['precio_venta'] ?? '')));
            $values = [
                $now,
                $producto,
                $cantidad,
                $input['precio_compra'] ?? 0,
                $input['proveedor'] ?? '',
                $input['observaciones'] ?? '',
                $input['cajero'] ?? '',
            ];
            $result = lub_append(LUB_HOJA_ENTRADAS, $values);
            if ($result['code'] === 200) {
                $resultDatos = lub_get_all(LUB_HOJA_DATOS);
                if (isset($resultDatos['data']['values'])) {
                    $actualizado = false;
                    foreach ($resultDatos['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        $coincideProducto = trim($row[0] ?? '') === $producto;
                        if (!$coincideProducto) continue;
                        // Si se envió presentación, solo actualizar la fila exacta (nombre + presentación)
                        if ($presentacion !== '' && trim($row[1] ?? '') !== $presentacion) continue;
                        $rowNum = $i + 1;
                        $stockActual = floatval($row[3] ?? 0);
                        $nuevoStock = $stockActual + $cantidad;
                        $nuevoPrecio = $precioVentaInput > 0 ? $precioVentaInput : floatval(str_replace(['$', '.'], '', $row[2] ?? '0'));
                        lub_update(LUB_HOJA_DATOS, $rowNum, [
                            $row[0] ?? '',
                            $row[1] ?? '',
                            $nuevoPrecio,
                            $nuevoStock,
                            $row[4] ?? 'TRUE',
                        ]);
                        $actualizado = true;
                        break;
                    }
                    if (!$actualizado && $presentacion !== '') {
                        // No existía la combinación exacta: registra igual el ingreso (se regulará luego)
                        error_log('Entrada sin fila exacta en datos: ' . $producto . ' [' . $presentacion . ']');
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Entrada registrada']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al registrar entrada']);
            }
            exit;

        case 'listar_entradas':
            ensure_headers(LUB_HOJA_ENTRADAS, LUB_HEADERS_ENTRADAS);
            $result = lub_get_all(LUB_HOJA_ENTRADAS);
            $entradas = [];
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0 && strtolower($row[0] ?? '') === 'fecha') continue;
                    $entradas[] = [
                        'row' => $i + 1,
                        'fecha' => $row[0] ?? '',
                        'producto' => $row[1] ?? '',
                        'cantidad' => $row[2] ?? 0,
                        'precio_compra' => $row[3] ?? 0,
                        'proveedor' => $row[4] ?? '',
                        'observaciones' => $row[5] ?? '',
                        'cajero' => $row[6] ?? '',
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $entradas]);
            exit;

        case 'listar_salidas':
            ensure_headers(LUB_HOJA_SALIDAS, LUB_HEADERS_SALIDAS);
            $result = lub_get_all(LUB_HOJA_SALIDAS);
            $salidas = [];
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0 && strtolower($row[0] ?? '') === 'fecha') continue;
                    if (trim($row[1] ?? '') === '') continue;
                    $salidas[] = [
                        'row' => $i + 1,
                        'fecha' => $row[0] ?? '',
                        'producto' => $row[1] ?? '',
                        'cantidad' => lub_num($row[2] ?? 0),
                        // Dinero: la columna puede venir con formato de moneda ("$ 25.000").
                        'precio_venta' => lub_money($row[3] ?? 0),
                        'tipo' => $row[4] ?? '',
                        'cajero' => $row[5] ?? '',
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $salidas]);
            exit;

        case 'eliminar_entrada':
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            lub_delete_row(LUB_HOJA_ENTRADAS, $row, 7);
            echo json_encode(['success' => true, 'message' => 'Entrada eliminada']);
            exit;

        case 'inventario':
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            $stock = [];
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $producto = $row[0] ?? '';
                    if (!$producto) continue;
                    $stock[] = [
                        'producto' => $producto,
                        'presentacion' => $row[1] ?? '',
                        'stock' => floatval($row[3] ?? 0),
                        'precio_venta' => floatval(str_replace(['$', '.'], '', $row[2] ?? '0')),
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $stock]);
            exit;

        case 'resumen_dia':
            $hoy = date('d/m/Y');
            $hoyShort = date('j/n/Y');
            $resultVentas = lub_get_all(LUB_HOJA_VENTAS);
            $resultEntradas = lub_get_all(LUB_HOJA_ENTRADAS);
            $totalVentas = 0;
            $totalItems = 0;
            $totalEntradas = 0;
            $numVentas = 0;

            if (isset($resultVentas['data']['values'])) {
                foreach ($resultVentas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $fecha = $row[0] ?? '';
                    if (strpos($fecha, $hoy) !== false || strpos($fecha, $hoyShort) !== false) {
                        $idxCant = count($row) >= 10 ? 3 : 2;
                        $idxTotal = count($row) >= 10 ? 5 : 4;
                        $totalVentas += floatval(str_replace(['$', '.'], '', $row[$idxTotal] ?? '0'));
                        $totalItems += floatval($row[$idxCant] ?? 0);
                        $numVentas++;
                    }
                }
            }
            if (isset($resultEntradas['data']['values'])) {
                foreach ($resultEntradas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $fecha = $row[0] ?? '';
                    if (strpos($fecha, $hoy) !== false || strpos($fecha, $hoyShort) !== false) {
                        $totalEntradas++;
                    }
                }
            }
            echo json_encode(['success' => true, 'data' => [
                'total_ventas' => $totalVentas,
                'total_items' => $totalItems,
                'num_ventas' => $numVentas,
                'total_entradas' => $totalEntradas,
            ]]);
            exit;

        // ---- Islas de combustible ----
        case 'listar_islas':
            lub_ensure_sheet(LUB_HOJA_ISLAS);
            ensure_headers_islas();
            // Filtro opcional por autor: el cajero pide solo lo suyo (usuario y/o
            // nombre) y el admin no manda nada, así que ve todo.
            $filtrosAutor = [];
            foreach ([trim($_GET['usuario'] ?? ''), trim($_GET['cajero'] ?? '')] as $f) {
                if ($f !== '') $filtrosAutor[] = $f;
            }
            $res = lub_get_all(LUB_HOJA_ISLAS);
            $lecturas = [];
            if (isset($res['data']['values'])) {
                foreach ($res['data']['values'] as $i => $row) {
                    if ($i === 0 && strtolower(trim($row[0] ?? '')) === 'fecha') continue;
                    $isla = trim($row[1] ?? '');
                    if ($isla === '') continue;
                    if (!empty($filtrosAutor)) {
                        $coincide = false;
                        foreach ($filtrosAutor as $f) {
                            if (lub_coincide_cajero($f, $row[9] ?? '', $row[8] ?? '')) { $coincide = true; break; }
                        }
                        if (!$coincide) continue;
                    }
                    $lecturas[] = [
                        'row' => $i + 1,
                        'fecha' => $row[0] ?? '',
                        'isla' => $isla,
                        'combustible' => trim($row[2] ?? ''),
                        'lectura_inicial' => lub_num($row[3] ?? 0),
                        'lectura_final' => lub_num($row[4] ?? 0),
                        'galones' => lub_num($row[5] ?? 0),
                        'precio' => lub_money($row[6] ?? 0),
                        'total' => lub_money($row[7] ?? 0),
                        'cajero' => $row[8] ?? '',
                        'usuario' => trim($row[9] ?? ''),
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $lecturas]);
            exit;

        case 'registrar_isla':
            lub_ensure_sheet(LUB_HOJA_ISLAS);
            ensure_headers_islas();
            $isla = trim($input['isla'] ?? '');
            if ($isla === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Isla requerida']);
                exit;
            }
            $dia = trim($input['fecha'] ?? '');
            if (lub_fecha_clave($dia) === 0) $dia = date('d/m/Y');
            $claveDiaNueva = lub_fecha_clave($dia);
            $now = $dia . ' ' . date('H:i');
            // 'cajero' = nombre visible; 'usuario' = login, con el que el admin filtra.
            $cajero = $input['cajero'] ?? '';
            $usuarioLogin = trim($input['usuario'] ?? '');
            // Autor de la lectura: se usa para no pisar el registro de otro cajero.
            $autor = $usuarioLogin !== '' ? $usuarioLogin : trim((string)$cajero);
            $lecturas = $input['lecturas'] ?? [];
            if (!is_array($lecturas)) $lecturas = [];
            $precios = lub_precios_combustible();

            $existentes = [];
            $resIslas = lub_get_all(LUB_HOJA_ISLAS);
            if (isset($resIslas['data']['values'])) {
                foreach ($resIslas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    // Mismo criterio de autor que al guardar: login si existe, si no el nombre.
                    $autorFila = trim((string)($row[9] ?? ''));
                    if ($autorFila === '') $autorFila = trim((string)($row[8] ?? ''));
                    $clave = lub_clave_lectura(lub_fecha_clave($row[0] ?? ''), $row[1] ?? '', $row[2] ?? '', $autorFila);
                    $existentes[$clave] = $i + 1;
                }
            }

            $guardados = [];
            foreach (LUB_COMBUSTIBLES as $comb) {
                $dato = $lecturas[$comb] ?? null;
                if (!is_array($dato)) continue;
                if (trim((string)($dato['inicial'] ?? '')) === '' || trim((string)($dato['final'] ?? '')) === '') continue;
                $ini = lub_num($dato['inicial']);
                $fin = lub_num($dato['final']);
                if ($fin < $ini) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'En ' . $comb . ' la lectura final no puede ser menor que la inicial']);
                    exit;
                }
                $galones = round($fin - $ini, 3);
                $precio = $precios[$comb] ?? 0.0;
                $total = round($galones * $precio, 2);
                $values = [$now, $isla, $comb, $ini, $fin, $galones, $precio, $total, $cajero, $usuarioLogin];
                $clave = lub_clave_lectura($claveDiaNueva, $isla, $comb, $autor);
                if (isset($existentes[$clave])) {
                    lub_update(LUB_HOJA_ISLAS, $existentes[$clave], $values);
                } else {
                    $r = lub_append(LUB_HOJA_ISLAS, $values);
                    if (($r['code'] ?? 0) !== 200) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'error' => 'Error al guardar la lectura de ' . $comb]);
                        exit;
                    }
                }
                $guardados[] = $comb;
            }
            if (empty($guardados)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ingrese al menos una lectura (inicial y final)']);
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Lecturas guardadas: ' . implode(' y ', $guardados), 'guardados' => $guardados]);
            exit;

        case 'eliminar_isla':
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            lub_delete_row(LUB_HOJA_ISLAS, $row, 10);
            echo json_encode(['success' => true, 'message' => 'Lectura eliminada']);
            exit;

        case 'resumen_general':
            // Resumen del administrador: islas (combustible) + tienda + estación.
            // Admite filtro por cajero (opcional) aplicado a las tres fuentes.
            $GLOBALS['LUB_ACTIVE_SHEET'] = LUB_SPREADSHEET_ID;
            $desde = trim($_GET['desde'] ?? '');
            $hasta = trim($_GET['hasta'] ?? '');
            $cajeroFiltro = trim($_GET['cajero'] ?? '');
            if (lub_fecha_clave($desde) === 0) $desde = date('d/m/Y');
            if (lub_fecha_clave($hasta) === 0) $hasta = date('d/m/Y');
            $desdeKey = lub_fecha_clave($desde);
            $hastaKey = lub_fecha_clave($hasta);
            if ($hastaKey < $desdeKey) {
                $tmp = $desdeKey; $desdeKey = $hastaKey; $hastaKey = $tmp;
                $tmpDia = $desde; $desde = $hasta; $hasta = $tmpDia;
            }

            // Identidad unificada de los cajeros (la hoja de usuarios vive en el
            // spreadsheet principal, así que se lee antes de cambiar de negocio).
            $mapaUsuarios = lub_mapa_usuarios();
            $porCajero = [];
            $porFormaPago = [];

            $sumarVentas = function ($destino) use ($desdeKey, $hastaKey, $cajeroFiltro, $mapaUsuarios, &$porCajero, &$porFormaPago) {
                $res = lub_get_all(LUB_HOJA_VENTAS);
                $total = 0.0;
                $items = 0.0;
                $num = 0;
                if (isset($res['data']['values'])) {
                    foreach ($res['data']['values'] as $i => $row) {
                        if ($i === 0 && strtolower(trim($row[0] ?? '')) === 'fecha') continue;
                        $k = lub_fecha_clave($row[0] ?? '');
                        if ($k === 0 || $k < $desdeKey || $k > $hastaKey) continue;
                        $cajeroVenta = lub_cajero_de_venta($row);
                        if (!lub_coincide_cajero($cajeroFiltro, $cajeroVenta)) continue;
                        $idxTotal = count($row) >= 10 ? 5 : 4;
                        $idxCant = count($row) >= 10 ? 3 : 2;
                        // Dinero con lub_money: los totales de ventas están con formato
                        // de moneda ("$ 795.000") y lub_num los leería 1000 veces menor.
                        $monto = lub_money($row[$idxTotal] ?? 0);
                        $total += $monto;
                        $items += lub_num($row[$idxCant] ?? 0);
                        $num++;
                        $autor = lub_autor_canonico('', $cajeroVenta, $mapaUsuarios);
                        lub_sumar_por_cajero($porCajero, $autor, $destino, $monto);
                        lub_sumar_por_cajero($porCajero, $autor, 'num_ventas', 1);

                        // Desglose por forma de pago (solo ventas: las islas no tienen).
                        $forma = lub_forma_pago_de_venta($row);
                        if ($forma === '') $forma = 'Sin especificar';
                        if (!isset($porFormaPago[$forma])) {
                            $porFormaPago[$forma] = ['forma' => $forma, 'estacion' => 0.0, 'tienda' => 0.0, 'num_ventas' => 0];
                        }
                        $porFormaPago[$forma][$destino] += $monto;
                        $porFormaPago[$forma]['num_ventas']++;
                    }
                }
                return ['total_ventas' => round($total, 2), 'total_items' => round($items, 3), 'num_ventas' => $num];
            };

            // Islas: totales por combustible dentro del rango.
            lub_ensure_sheet(LUB_HOJA_ISLAS);
            ensure_headers_islas();
            $resIslas = lub_get_all(LUB_HOJA_ISLAS);
            $combustibles = [];
            $totalIslas = 0.0;
            $registrosIslas = 0;
            if (isset($resIslas['data']['values'])) {
                foreach ($resIslas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (trim($row[1] ?? '') === '') continue;
                    $k = lub_fecha_clave($row[0] ?? '');
                    if ($k === 0 || $k < $desdeKey || $k > $hastaKey) continue;
                    // Coincide por nombre visible (Cajero) o por login (Usuario).
                    if (!lub_coincide_cajero($cajeroFiltro, $row[8] ?? '', $row[9] ?? '')) continue;
                    $comb = trim($row[2] ?? '');
                    if ($comb === '') $comb = 'Sin especificar';
                    if (!isset($combustibles[$comb])) $combustibles[$comb] = ['galones' => 0.0, 'total' => 0.0, 'registros' => 0];
                    $galones = lub_num($row[5] ?? 0);
                    $montoIsla = lub_money($row[7] ?? 0);
                    $combustibles[$comb]['galones'] += $galones;
                    $combustibles[$comb]['total'] += $montoIsla;
                    $combustibles[$comb]['registros']++;
                    $totalIslas += $montoIsla;
                    $registrosIslas++;
                    // Desglose por cajero: el autor sale del login (Usuario) o del nombre.
                    $autorIsla = lub_autor_canonico($row[9] ?? '', $row[8] ?? '', $mapaUsuarios);
                    lub_sumar_por_cajero($porCajero, $autorIsla, 'islas', $montoIsla);
                    lub_sumar_por_cajero($porCajero, $autorIsla, 'islas_galones', $galones);
                }
            }
            foreach ($combustibles as $comb => $d) {
                $combustibles[$comb]['galones'] = round($d['galones'], 3);
                $combustibles[$comb]['total'] = round($d['total'], 2);
            }

            // Estación y tienda usan spreadsheets distintos: se alterna el activo.
            $GLOBALS['LUB_ACTIVE_SHEET'] = LUB_SPREADSHEET_ID;
            $estacion = $sumarVentas('estacion');
            $GLOBALS['LUB_ACTIVE_SHEET'] = $lub_negocios['otro'];
            $tienda = $sumarVentas('tienda');
            $GLOBALS['LUB_ACTIVE_SHEET'] = LUB_SPREADSHEET_ID;

            // Desglose por cajero: una fila por cajero con las tres fuentes sumadas.
            $porCajeroLista = [];
            foreach ($porCajero as $fila) {
                $fila['islas'] = round($fila['islas'], 2);
                $fila['islas_galones'] = round($fila['islas_galones'], 3);
                $fila['estacion'] = round($fila['estacion'], 2);
                $fila['tienda'] = round($fila['tienda'], 2);
                $fila['total'] = round($fila['islas'] + $fila['estacion'] + $fila['tienda'], 2);
                $porCajeroLista[] = $fila;
            }
            usort($porCajeroLista, function ($a, $b) {
                if ($a['total'] === $b['total']) return strcmp($a['cajero'], $b['cajero']);
                return $b['total'] <=> $a['total'];
            });

            // Desglose por forma de pago (solo ventas de tienda y estación).
            $porFormaPagoLista = [];
            foreach ($porFormaPago as $fila) {
                $fila['estacion'] = round($fila['estacion'], 2);
                $fila['tienda'] = round($fila['tienda'], 2);
                $fila['total'] = round($fila['estacion'] + $fila['tienda'], 2);
                $porFormaPagoLista[] = $fila;
            }
            usort($porFormaPagoLista, function ($a, $b) {
                if ($a['total'] === $b['total']) return strcmp($a['forma'], $b['forma']);
                return $b['total'] <=> $a['total'];
            });

            echo json_encode(['success' => true, 'data' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'cajero' => $cajeroFiltro,
                'islas' => [
                    'total' => round($totalIslas, 2),
                    'registros' => $registrosIslas,
                    'combustibles' => $combustibles,
                ],
                'por_cajero' => $porCajeroLista,
                'por_forma_pago' => $porFormaPagoLista,
                'estacion' => $estacion,
                'tienda' => $tienda,
                'total' => round($totalIslas + $estacion['total_ventas'] + $tienda['total_ventas'], 2),
            ]]);
            exit;

        case 'listar_clientes':
            lub_ensure_sheet(LUB_HOJA_CLIENTES);
            ensure_headers(LUB_HOJA_CLIENTES, LUB_HEADERS_CLIENTES);
            $res = lub_get_all(LUB_HOJA_CLIENTES);
            $clientes = [];
            if (isset($res['data']['values'])) {
                foreach ($res['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (trim($row[0] ?? '') === '' && trim($row[1] ?? '') === '') continue;
                    $clientes[] = [
                        'row' => $i + 1,
                        'identificacion' => $row[0] ?? '',
                        'nombres' => $row[1] ?? '',
                        'telefono' => $row[2] ?? '',
                        'correo' => $row[3] ?? '',
                        'direccion' => $row[4] ?? '',
                        'placa1' => $row[5] ?? '',
                        'placa2' => $row[6] ?? '',
                        'placa3' => $row[7] ?? '',
                        'placa4' => $row[8] ?? '',
                        'notas' => $row[9] ?? '',
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $clientes]);
            exit;

        case 'cliente_existe':
            // Verifica si una identificación ya está registrada (sin exponer la lista de clientes).
            lub_ensure_sheet(LUB_HOJA_CLIENTES);
            ensure_headers(LUB_HOJA_CLIENTES, LUB_HEADERS_CLIENTES);
            $docBuscar = strtolower(trim((string)($_GET['identificacion'] ?? '')));
            $existeCliente = false;
            if ($docBuscar !== '') {
                $resExiste = lub_get_all(LUB_HOJA_CLIENTES);
                if (isset($resExiste['data']['values'])) {
                    foreach ($resExiste['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        if (strtolower(trim($row[0] ?? '')) === $docBuscar) { $existeCliente = true; break; }
                    }
                }
            }
            echo json_encode(['success' => true, 'data' => ['existe' => $existeCliente]]);
            exit;

        case 'registrar_cliente':
            lub_ensure_sheet(LUB_HOJA_CLIENTES);
            ensure_headers(LUB_HOJA_CLIENTES, LUB_HEADERS_CLIENTES);
            $identificacion = trim($input['identificacion'] ?? '');
            $nombres = trim($input['nombres'] ?? '');
            $telefono = trim($input['telefono'] ?? '');
            if ($identificacion === '' || $nombres === '' || $telefono === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Identificación, nombres y teléfono son requeridos']);
                exit;
            }
            $res = lub_get_all(LUB_HOJA_CLIENTES);
            if (isset($res['data']['values'])) {
                foreach ($res['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (strtolower(trim($row[0] ?? '')) === strtolower($identificacion)) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'error' => 'Ya existe un cliente con esa identificación']);
                        exit;
                    }
                }
            }
            $values = [
                $identificacion, $nombres, $telefono,
                $input['correo'] ?? '', $input['direccion'] ?? '',
                $input['placa1'] ?? '', $input['placa2'] ?? '', $input['placa3'] ?? '', $input['placa4'] ?? '',
                $input['notas'] ?? '',
            ];
            $result = lub_append(LUB_HOJA_CLIENTES, $values);
            if ($result['code'] === 200) {
                echo json_encode(['success' => true, 'message' => 'Cliente registrado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al registrar cliente']);
            }
            exit;

        case 'actualizar_cliente':
            lub_ensure_sheet(LUB_HOJA_CLIENTES);
            ensure_headers(LUB_HOJA_CLIENTES, LUB_HEADERS_CLIENTES);
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            $values = [
                $input['identificacion'] ?? '',
                $input['nombres'] ?? '',
                $input['telefono'] ?? '',
                $input['correo'] ?? '',
                $input['direccion'] ?? '',
                $input['placa1'] ?? '', $input['placa2'] ?? '', $input['placa3'] ?? '', $input['placa4'] ?? '',
                $input['notas'] ?? '',
            ];
            lub_update(LUB_HOJA_CLIENTES, $row, $values);
            echo json_encode(['success' => true, 'message' => 'Cliente actualizado']);
            exit;

        case 'eliminar_cliente':
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            lub_delete_row(LUB_HOJA_CLIENTES, $row, 10);
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado']);
            exit;

        case 'login':
            $usuario = $input['usuario'] ?? '';
            $password = $input['password'] ?? '';
            if (!$usuario || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Usuario y contrasena requeridos']);
                exit;
            }
            ensure_headers(LUB_HOJA_USUARIOS, LUB_HEADERS_USUARIOS);
            $result = lub_get_all(LUB_HOJA_USUARIOS);
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (($row[0] ?? '') === $usuario && password_verify($password, $row[1] ?? '') && ($row[4] ?? '') === 'TRUE') {
                        echo json_encode(['success' => true, 'data' => [
                            'usuario' => $row[0],
                            'rol' => $row[2] ?? 'cajero',
                            'nombre' => $row[3] ?? '',
                        ]]);
                        exit;
                    }
                }
            }
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Credenciales invalidas']);
            exit;

        case 'listar_usuarios':
            ensure_headers(LUB_HOJA_USUARIOS, LUB_HEADERS_USUARIOS);
            $result = lub_get_all(LUB_HOJA_USUARIOS);
            $usuarios = [];
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0 && strtolower($row[0] ?? '') === 'usuario') continue;
                    $usuarios[] = [
                        'row' => $i + 1,
                        'usuario' => $row[0] ?? '',
                        'rol' => $row[2] ?? '',
                        'nombre' => $row[3] ?? '',
                        'activo' => $row[4] ?? 'TRUE',
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $usuarios]);
            exit;

        case 'crear_usuario':
            $usuario = $input['usuario'] ?? '';
            $password = $input['password'] ?? '';
            $rol = $input['rol'] ?? 'cajero';
            $nombre = $input['nombre'] ?? '';
            if (!$usuario || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Usuario y contrasena requeridos']);
                exit;
            }
            ensure_headers(LUB_HOJA_USUARIOS, LUB_HEADERS_USUARIOS);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $values = [$usuario, $hash, $rol, $nombre, 'TRUE'];
            $result = lub_append(LUB_HOJA_USUARIOS, $values);
            if ($result['code'] === 200) {
                echo json_encode(['success' => true, 'message' => 'Usuario creado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al crear usuario']);
            }
            exit;

        case 'update_usuario':
            $usuario = $input['usuario'] ?? '';
            $password = $input['password'] ?? '';
            if (!$usuario || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Usuario y contrasena requeridos']);
                exit;
            }
            ensure_headers(LUB_HOJA_USUARIOS, LUB_HEADERS_USUARIOS);
            $result = lub_get_all(LUB_HOJA_USUARIOS);
            if (isset($result['data']['values'])) {
                foreach ($result['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (($row[0] ?? '') === $usuario) {
                        $rowNum = $i + 1;
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        lub_update(LUB_HOJA_USUARIOS, $rowNum, [
                            $row[0],
                            $hash,
                            $row[2] ?? '',
                            $row[3] ?? '',
                            $row[4] ?? 'TRUE',
                        ]);
                        echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
                        exit;
                    }
                }
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;

        case 'eliminar_usuario':
            $row = intval($input['row'] ?? 0);
            if ($row < 2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Row invalida']);
                exit;
            }
            lub_update(LUB_HOJA_USUARIOS, $row, ['', '', '', '', 'FALSE']);
            echo json_encode(['success' => true, 'message' => 'Usuario desactivado']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Accion no valida: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
