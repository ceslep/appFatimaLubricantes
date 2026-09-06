<?php
// v3.2 - API Lubricantes - Catalogo + Ventas + Roles - Fix precios $, dashboard, historial
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

function ensure_headers($sheet, $headers) {
    $check = lub_get($sheet, 'A1:H1');
    if (!isset($check['data']['values'][0]) || empty($check['data']['values'][0][0])) {
        $token = lub_get_token();
        $cols = chr(64 + count($headers));
        $url = LUB_SHEETS_API . urlencode($sheet . '!A1:' . $cols . '1') . '?valueInputOption=RAW';
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
    }
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
                    $cant = floatval($row[2] ?? 0);
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

        case 'registrar_venta':
            ensure_headers(LUB_HOJA_VENTAS, LUB_HEADERS_VENTAS);
            $now = date('d/m/Y H:i');
            $producto = $input['producto'] ?? '';
            $cantidad = floatval($input['cantidad'] ?? 0);
            $precio_unitario = 0;
            $resultDatos = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatos['data']['values'])) {
                foreach ($resultDatos['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    if (trim($row[0] ?? '') === trim($producto)) {
                        $precio_unitario = floatval(str_replace(['$', '.'], '', $row[2] ?? '0'));
                        break;
                    }
                }
            }
            $total = $cantidad * $precio_unitario;
            $values = [
                $now,
                $producto,
                $cantidad,
                $precio_unitario,
                $total,
                $input['forma_pago'] ?? 'Efectivo',
                $input['cliente'] ?? '',
                $input['placa'] ?? '',
                $input['cajero'] ?? '',
            ];
            $result = lub_append(LUB_HOJA_VENTAS, $values);
            if ($result['code'] === 200) {
                if (isset($resultDatos['data']['values'])) {
                    foreach ($resultDatos['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        if (trim($row[0] ?? '') === trim($producto)) {
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
            $preciosDatos = [];
            $resultDatosAll = lub_get_all(LUB_HOJA_DATOS);
            if (isset($resultDatosAll['data']['values'])) {
                foreach ($resultDatosAll['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $preciosDatos[trim($row[0] ?? '')] = floatval(str_replace(['$', '.'], '', $row[2] ?? '0'));
                }
            }
            foreach ($items as $item) {
                $producto = trim($item['producto'] ?? '');
                $cantidad = floatval($item['cantidad'] ?? 0);
                $precio_unitario = $preciosDatos[$producto] ?? 0;
                $total = $cantidad * $precio_unitario;
                $values = [$now, $producto, $cantidad, $precio_unitario, $total, $formaPago, $cliente, $placa, $cajero];
                $result = lub_append(LUB_HOJA_VENTAS, $values);
                if ($result['code'] === 200) {
                    $ok++;
                    if (isset($resultDatosAll['data']['values'])) {
                        foreach ($resultDatosAll['data']['values'] as $i => $row) {
                            if ($i === 0) continue;
                            if (trim($row[0] ?? '') === $producto) {
                                $rowNum = $i + 1;
                                $stockActual = floatval($row[3] ?? 0);
                                $nuevoStock = $stockActual - $cantidad;
                                lub_update(LUB_HOJA_DATOS, $rowNum, [
                                    $row[0] ?? '', $row[1] ?? '', $row[2] ?? '', $nuevoStock, $row[4] ?? 'TRUE',
                                ]);
                                break;
                            }
                        }
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
                    $ventas[] = [
                        'row' => $i + 1,
                        'fecha' => $row[0] ?? '',
                        'producto' => $row[1] ?? '',
                        'cantidad' => floatval(str_replace(['$', '.'], '', $row[2] ?? '0')),
                        'precio_unitario' => floatval(str_replace(['$', '.'], '', $row[3] ?? '0')),
                        'total' => floatval(str_replace(['$', '.'], '', $row[4] ?? '0')),
                        'forma_pago' => $row[5] ?? '',
                        'cliente' => $row[6] ?? '',
                        'placa' => $row[7] ?? '',
                        'cajero' => $row[8] ?? '',
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
            lub_delete_row(LUB_HOJA_VENTAS, $row, 9);
            echo json_encode(['success' => true, 'message' => 'Venta eliminada']);
            exit;

        case 'registrar_entrada':
            ensure_headers(LUB_HOJA_ENTRADAS, LUB_HEADERS_ENTRADAS);
            $now = date('d/m/Y H:i');
            $producto = $input['producto'] ?? '';
            $cantidad = floatval($input['cantidad'] ?? 0);
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
                    foreach ($resultDatos['data']['values'] as $i => $row) {
                        if ($i === 0) continue;
                        if (($row[0] ?? '') === $producto) {
                            $rowNum = $i + 1;
                            $stockActual = floatval($row[3] ?? 0);
                            $nuevoStock = $stockActual + $cantidad;
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
                    if (strpos($fecha, $hoy) !== false) {
                        $totalVentas += floatval(str_replace(['$', '.'], '', $row[4] ?? '0'));
                        $totalItems += floatval($row[2] ?? 0);
                        $numVentas++;
                    }
                }
            }
            if (isset($resultEntradas['data']['values'])) {
                foreach ($resultEntradas['data']['values'] as $i => $row) {
                    if ($i === 0) continue;
                    $fecha = $row[0] ?? '';
                    if (strpos($fecha, $hoy) !== false) {
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
