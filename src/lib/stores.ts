import { writable, derived } from 'svelte/store';
import type { UsuarioLogueado, VistaActual, Venta, Entrada, ProductoCatalogo } from './types';

export const usuario = writable<UsuarioLogueado | null>(null);
export const vistaActual = writable<VistaActual>('dashboard');
export const negocio = writable<string>('estacion');
export const ventas = writable<Venta[]>([]);
export const entradas = writable<Entrada[]>([]);
export const catalogo = writable<ProductoCatalogo[]>([]);

export const isLoggedIn = derived(usuario, ($u) => $u !== null);
export const isAdmin = derived(usuario, ($u) => $u?.rol === 'admin');
export const isVentas = derived(usuario, ($u) => $u?.rol === 'ventas');

export function login(user: UsuarioLogueado) {
  usuario.set(user);
  localStorage.setItem('lub_user', JSON.stringify(user));
}

export function logout() {
  usuario.set(null);
  localStorage.removeItem('lub_user');
}

export function restoreSession() {
  const saved = localStorage.getItem('lub_user');
  if (saved) {
    try {
      usuario.set(JSON.parse(saved));
    } catch { /* ignore */ }
  }
}

export function restoreNegocio() {
  const n = localStorage.getItem('lub_negocio');
  if (n) negocio.set(n);
}

export function setNegocio(id: string) {
  negocio.set(id);
  localStorage.setItem('lub_negocio', id);
}

// Modo tablet (solo usuarios no-admin): controles más grandes para uso táctil.
export const modoTablet = writable<boolean>(false);

export function setModoTablet(activo: boolean) {
  modoTablet.set(activo);
  try { localStorage.setItem('lub_modo_tablet', activo ? '1' : '0'); } catch { /* ignore */ }
}

export function restoreModoTablet() {
  try { modoTablet.set(localStorage.getItem('lub_modo_tablet') === '1'); } catch { /* ignore */ }
}

// Logo de la app (compartido): data URL de imagen definida en Configuración.
export const logo = writable<string>('');

export function setLogo(valor: string) {
  const limpio = valor || '';
  logo.set(limpio);
  try { localStorage.setItem('lub_logo', limpio); } catch { /* ignore */ }
}

export function restoreLogo() {
  try { logo.set(localStorage.getItem('lub_logo') || ''); } catch { /* ignore */ }
}
