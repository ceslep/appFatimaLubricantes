export interface Venta {
  row: number;
  fecha: string;
  producto: string;
  presentacion?: string;
  cantidad: number;
  precio_unitario: number;
  total: number;
  cliente: string;
  cliente_doc?: string;
  placa: string;
  cajero: string;
  forma_pago: string;
}

export interface Cliente {
  row: number;
  identificacion: string;
  nombres: string;
  telefono: string;
  correo: string;
  direccion: string;
  notas: string;
  placa1?: string;
  placa2?: string;
  placa3?: string;
  placa4?: string;
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
  precio_venta: number;
  tipo: string;
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

// ---- Islas de combustible ----
// Cada isla tiene 2 mangueras: Gasolina y ACPM. Una fila = una manguera en el cierre del día.
export interface IslaLectura {
  row: number;
  fecha: string;
  isla: string;
  combustible: string;
  lectura_inicial: number;
  lectura_final: number;
  galones: number;
  precio: number;
  total: number;
  cajero: string;
  // Usuario de login (ej: 'yenifer'), con el que el admin filtra el historial.
  usuario: string;
}

export interface ResumenCombustible {
  galones: number;
  total: number;
  registros: number;
}

export interface ResumenNegocio {
  total_ventas: number;
  total_items: number;
  num_ventas: number;
}

// Fila del desglose por cajero: las tres fuentes sumadas en una sola identidad.
export interface ResumenCajeroFila {
  cajero: string;
  islas: number;
  islas_galones: number;
  estacion: number;
  tienda: number;
  total: number;
  num_ventas: number;
}

// Fila del desglose por forma de pago (solo ventas: las islas no tienen forma de pago).
export interface ResumenFormaPagoFila {
  forma: string;
  estacion: number;
  tienda: number;
  total: number;
  num_ventas: number;
}

export interface ResumenGeneral {
  desde: string;
  hasta: string;
  // Filtro de cajero aplicado ('' = todos).
  cajero: string;
  islas: { total: number; registros: number; combustibles: Record<string, ResumenCombustible> };
  por_cajero: ResumenCajeroFila[];
  por_forma_pago: ResumenFormaPagoFila[];
  estacion: ResumenNegocio;
  tienda: ResumenNegocio;
  total: number;
}

export type VistaActual = 'dashboard' | 'registrar-venta' | 'registrar-entrada' | 'inventario' | 'islas' | 'historial' | 'historial-entradas' | 'historial-salidas' | 'historial-islas' | 'reportes' | 'resumen' | 'admin-usuarios' | 'configuracion' | 'clientes';
