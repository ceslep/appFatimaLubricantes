export function formatCOP(valor: number): string {
  return '$ ' + valor.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export function formatFecha(fecha: string): string {
  return fecha;
}

export function getFechaActual(): string {
  const d = new Date();
  const dia = String(d.getDate()).padStart(2, '0');
  const mes = String(d.getMonth() + 1).padStart(2, '0');
  const anio = d.getFullYear();
  const hora = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${dia}/${mes}/${anio} ${hora}:${min}`;
}

export function esHoy(fecha: string): boolean {
  const hoy = getFechaActual().substring(0, 10);
  return fecha.substring(0, 10) === hoy;
}
