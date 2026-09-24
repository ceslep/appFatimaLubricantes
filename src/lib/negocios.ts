// Negocios disponibles (cada uno apunta a su propio spreadsheet en el backend)
export interface Negocio {
  id: string;
  nombre: string;
}

export const NEGOCIOS: Negocio[] = [
  { id: 'estacion', nombre: 'Estación Fátima' },
  { id: 'otro', nombre: 'Tienda Fátima' },
  { id: 'islas', nombre: 'Islas Fátima' },
];

// Negocios que no son las islas de combustible (venta de lubricantes).
export const NEGOCIOS_TIENDA = ['estacion', 'otro'];
export const TODOS_LOS_NEGOCIOS = ['estacion', 'otro', 'islas'];

export function nombreNegocio(id: string): string {
  const n = NEGOCIOS.find((x) => x.id === id);
  return n ? n.nombre : 'Estación Fátima';
}
