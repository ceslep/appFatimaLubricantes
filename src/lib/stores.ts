import { writable, derived } from 'svelte/store';
import type { UsuarioLogueado, VistaActual, Venta, Entrada, ProductoCatalogo } from './types';

export const usuario = writable<UsuarioLogueado | null>(null);
export const vistaActual = writable<VistaActual>('dashboard');
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
