<?php
// v3.3 - Config Lubricantes - Islas: columna Usuario (login) para filtrar por cajero
define('LUB_SPREADSHEET_ID', '1-1JngYw36_IiaphiLynIWJ5pcGwfv0cM9WmEDJYIoh8');
define('LUB_SA_PATH', __DIR__ . '/assets/serviceaccount.json');
define('LUB_SCOPES', 'https://www.googleapis.com/auth/spreadsheets');
define('LUB_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('LUB_SHEETS_API', 'https://sheets.googleapis.com/v4/spreadsheets/' . LUB_SPREADSHEET_ID . '/values/');

define('LUB_HOJA_DATOS', 'datos');
define('LUB_HOJA_VENTAS', 'ventas');
define('LUB_HOJA_ENTRADAS', 'entradas');
define('LUB_HOJA_SALIDAS', 'salidas');
define('LUB_HOJA_USUARIOS', 'usuarios');

define('LUB_HEADERS_DATOS', ['Producto', 'Presentacion', 'Precio Venta', 'Stock', 'Activo']);
define('LUB_HEADERS_VENTAS', ['Fecha', 'Producto', 'Presentación', 'Cantidad', 'Precio Unitario', 'Total', 'Forma de pago', 'Cliente', 'Placa Vehiculo', 'Cajero', 'Cliente Doc']);
define('LUB_HEADERS_ENTRADAS', ['Fecha', 'Producto', 'Cantidad', 'Precio Compra', 'Proveedor', 'Observaciones', 'Cajero']);
define('LUB_HEADERS_SALIDAS', ['Fecha', 'Producto', 'Cantidad', 'Precio Venta', 'Tipo', 'Cajero']);
define('LUB_HEADERS_USUARIOS', ['Usuario', 'Password', 'Rol', 'Nombre Completo', 'Activo']);
define('LUB_HOJA_CONFIG', 'config');
define('LUB_HEADERS_CONFIG', ['Clave', 'Valor']);
define('LUB_HOJA_CLIENTES', 'clientes');
define('LUB_HEADERS_CLIENTES', ['Identificacion', 'Nombres', 'Telefono', 'Correo', 'Direccion', 'Placa 1', 'Placa 2', 'Placa 3', 'Placa 4', 'Observaciones']);

// Islas de combustible: cada isla tiene 2 mangueras (Gasolina y ACPM).
// Una fila por manguera y cierre del dia.
// 'Cajero' guarda el nombre visible y 'Usuario' el usuario de login (para filtrar).
// 10 columnas (dentro del rango A:K que lee lub_get_all).
define('LUB_HOJA_ISLAS', 'islas');
define('LUB_HEADERS_ISLAS', ['Fecha', 'Isla', 'Combustible', 'Lectura Inicial', 'Lectura Final', 'Galones', 'Precio', 'Total', 'Cajero', 'Usuario']);
define('LUB_COMBUSTIBLES', ['Gasolina', 'ACPM']);

// Claves de la hoja config para islas de combustible.
define('LUB_CFG_PRECIO_GASOLINA', 'precio_gasolina');
define('LUB_CFG_PRECIO_ACPM', 'precio_acpm');
define('LUB_CFG_ISLAS', 'islas');

