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
  return dia + '/' + mes + '/' + anio + ' ' + hora + ':' + min;
}

export function esHoy(fecha: string): boolean {
  const hoy = getFechaActual().substring(0, 10);
  return fecha.substring(0, 10) === hoy;
}

// Descarga un archivo CSV (compatible con Excel, BOM UTF-8)
export function descargarCSV(nombre: string, cabeceras: string[], filas: (string | number)[][]): void {
  const esc = (v: unknown) => {
    const s = String(v ?? '');
    return '"' + s.replace(/"/g, '""') + '"';
  };
  const CRLF = String.fromCharCode(13) + String.fromCharCode(10);
  const contenido = String.fromCharCode(0xfeff) + [cabeceras, ...filas].map((f) => f.map(esc).join(',')).join(CRLF);
  const blob = new Blob([contenido], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nombre.endsWith('.csv') ? nombre : nombre + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
