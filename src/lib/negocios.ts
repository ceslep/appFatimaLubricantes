// Negocios disponibles (cada uno apunta a su propio spreadsheet en el backend)
export interface Negocio {
  id: string;
  nombre: string;
}

export const NEGOCIOS: Negocio[] = [
  { id: 'estacion', nombre: 'Estación Fátima' },
  { id: 'otro', nombre: 'Tienda Fátima' },
];

export function nombreNegocio(id: string): string {
  const n = NEGOCIOS.find((x) => x.id === id);
  return n ? n.nombre : 'Estación Fátima';
}
