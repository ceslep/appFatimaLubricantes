const API_BASE = 'https://app.iedeoccidente.com/appf/lubricantes_api.php';

async function apiCall(action: string, params: Record<string, unknown> = {}, method: string = 'GET'): Promise<unknown> {
  let url = `${API_BASE}?action=${action}`;
  let body: string | undefined;

  if (method === 'GET') {
    for (const [key, val] of Object.entries(params)) {
      url += `&${key}=${encodeURIComponent(String(val))}`;
    }
  } else {
    body = JSON.stringify(params);
  }

  const resp = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body,
  });

  const data = await resp.json();
  if (!data.success) throw new Error(data.error || 'Error desconocido');
  return data.data ?? data;
}

export const api = {
  listarProductosCatalogo: () => apiCall('listar_productos_catalogo') as Promise<{ producto: string; presentacion: string; precio_venta: number; stock: number }[]>,
  productosMasVendidos: () => apiCall('productos_mas_vendidos') as Promise<{ producto: string; presentacion: string; precio_venta: number; stock: number; total_vendido: number }[]>,
  registrarProducto: (p: { producto: string; presentacion: string; precio_venta: number }) => apiCall('registrar_producto', p, 'POST'),
  actualizarPrecio: (producto: string, presentacion: string, precio_venta: number) => apiCall('actualizar_precio', { producto, presentacion, precio_venta }, 'POST'),
  listarVentas: () => apiCall('listar_ventas') as Promise<{ row: number; fecha: string; producto: string; cantidad: number; precio_unitario: number; total: number; cliente: string; placa: string; cajero: string; forma_pago: string }[]>,
  registrarVenta: (venta: { producto: string; cantidad: number; precio_unitario: number; cliente: string; placa: string; cajero: string; forma_pago: string }) => apiCall('registrar_venta', venta, 'POST'),
  registrarVentaMultiple: (data: { items: { producto: string; cantidad: number; precio_unitario: number }[]; cliente: string; placa: string; cajero: string; forma_pago: string }) => apiCall('registrar_venta_multiple', data, 'POST'),
  eliminarVenta: (row: number) => apiCall('eliminar_venta', { row }, 'POST'),
  listarEntradas: () => apiCall('listar_entradas') as Promise<{ row: number; fecha: string; producto: string; cantidad: number; precio_compra: number; proveedor: string; observaciones: string; cajero: string }[]>,
  registrarEntrada: (entrada: { producto: string; presentacion: string; cantidad: number; precio_compra: number; proveedor: string; observaciones: string; cajero: string }) => apiCall('registrar_entrada', entrada, 'POST'),
  eliminarEntrada: (row: number) => apiCall('eliminar_entrada', { row }, 'POST'),
  inventario: () => apiCall('inventario') as Promise<{ producto: string; presentacion: string; stock: number; precio_venta: number }[]>,
  resumenDia: () => apiCall('resumen_dia') as Promise<{ total_ventas: number; total_items: number; num_ventas: number; total_entradas: number }>,
  login: (usuario: string, password: string) => apiCall('login', { usuario, password }, 'POST') as Promise<{ usuario: string; rol: string; nombre: string }>,
  listarUsuarios: () => apiCall('listar_usuarios') as Promise<{ row: number; usuario: string; rol: string; nombre: string; activo: string }[]>,
  crearUsuario: (user: { usuario: string; password: string; rol: string; nombre: string }) => apiCall('crear_usuario', user, 'POST'),
  actualizarUsuario: (user: { usuario: string; password: string }) => apiCall('update_usuario', user, 'POST'),
  eliminarUsuario: (row: number) => apiCall('eliminar_usuario', { row }, 'POST'),
};
