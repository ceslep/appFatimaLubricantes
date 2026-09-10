// Caché local ligera (stale-while-revalidate) para mostrar datos al instante.
// Los datos se guardan por negocio para no mezclar catálogos.

const PREFIX = 'lub_cache_v1:';

interface EntradaCache<T> {
  t: number;
  data: T;
}

export function cacheLeer<T>(clave: string): T | null {
  if (typeof localStorage === 'undefined') return null;
  try {
    const raw = localStorage.getItem(PREFIX + clave);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as EntradaCache<T>;
    return parsed && 'data' in parsed ? parsed.data : null;
  } catch {
    return null;
  }
}

export function cacheEscribir<T>(clave: string, data: T): void {
  if (typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(PREFIX + clave, JSON.stringify({ t: Date.now(), data } satisfies EntradaCache<T>));
  } catch {
    // localStorage lleno o bloqueado: la caché es opcional, no rompemos la app
  }
}
