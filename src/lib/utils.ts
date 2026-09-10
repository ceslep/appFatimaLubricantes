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

// Redimensiona y comprime una imagen para guardarla como data URL (logos pequeños).
// Devuelve PNG (conserva transparencia) o JPEG si el PNG pesa demasiado.
export async function comprimirImagen(file: File, maxLado = 256, maxCaracteres = 42000): Promise<string> {
  const original = await new Promise<string>((resolve, reject) => {
    const fr = new FileReader();
    fr.onload = () => resolve(String(fr.result));
    fr.onerror = () => reject(new Error('No se pudo leer la imagen'));
    fr.readAsDataURL(file);
  });

  const img = await new Promise<HTMLImageElement>((resolve, reject) => {
    const el = new Image();
    el.onload = () => resolve(el);
    el.onerror = () => reject(new Error('El archivo no es una imagen válida'));
    el.src = original;
  });

  const anchoBase = img.naturalWidth || img.width;
  const altoBase = img.naturalHeight || img.height;

  for (const lado of [maxLado, 192, 128, 96, 72]) {
    const escala = Math.min(1, lado / Math.max(anchoBase, altoBase));
    const w = Math.max(1, Math.round(anchoBase * escala));
    const h = Math.max(1, Math.round(altoBase * escala));
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('El navegador no permite procesar la imagen');

    ctx.clearRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);
    const png = canvas.toDataURL('image/png');
    if (png.length <= maxCaracteres) return png;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);
    const jpeg = canvas.toDataURL('image/jpeg', 0.85);
    if (jpeg.length <= maxCaracteres) return jpeg;
  }

  const mini = document.createElement('canvas');
  mini.width = 64;
  mini.height = 64;
  const ctxMini = mini.getContext('2d');
  if (!ctxMini) throw new Error('El navegador no permite procesar la imagen');
  ctxMini.fillStyle = '#ffffff';
  ctxMini.fillRect(0, 0, 64, 64);
  const escala = Math.min(64 / anchoBase, 64 / altoBase);
  const w = Math.max(1, Math.round(anchoBase * escala));
  const h = Math.max(1, Math.round(altoBase * escala));
  ctxMini.drawImage(img, (64 - w) / 2, (64 - h) / 2, w, h);
  return mini.toDataURL('image/jpeg', 0.7);
}

// Página pública de registro de clientes: se abre sin login con ?registro=1 o #registro
export function esRegistroPublico(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.has('registro') || params.get('modo') === 'registro') return true;
    const hash = (window.location.hash || '').toLowerCase().replace(/^#\/?/, '');
    return hash === 'registro';
  } catch {
    return false;
  }
}

// Enlace directo que se comparte con los clientes
export function enlaceRegistro(): string {
  if (typeof window === 'undefined') return '';
  return window.location.origin + window.location.pathname + '?registro=1';
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
