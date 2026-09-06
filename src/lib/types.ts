export interface Venta {
  row: number;
  fecha: string;
  producto: string;
  cantidad: number;
  precio_unitario: number;
  total: number;
  cliente: string;
  placa: string;
  cajero: string;
  forma_pago: string;
}

export interface Entrada {
  row: number;
  fecha: string;
  producto: string;
  cantidad: number;
  precio_compra: number;
  proveedor: string;
  observaciones: string;
  cajero: string;
}

export interface Salida {
  row: number;
  fecha: string;
  producto: string;
  cantidad: number;
  destino: string;
  observaciones: string;
  cajero: string;
}

export interface ProductoCatalogo {
  producto: string;
  presentacion: string;
  precio_venta: number;
  stock: number;
}

export interface InventarioItem {
  producto: string;
  presentacion: string;
  stock: number;
  precio_venta: number;
}

export interface ResumenDia {
  total_ventas: number;
  total_items: number;
  num_ventas: number;
  total_entradas: number;
}

export interface Usuario {
  row: number;
  usuario: string;
  rol: string;
  nombre: string;
  activo: string;
}

export interface UsuarioLogueado {
  usuario: string;
  rol: string;
  nombre: string;
}

export type VistaActual = 'dashboard' | 'registrar-venta' | 'registrar-entrada' | 'inventario' | 'historial' | 'historial-entradas' | 'admin-usuarios';
