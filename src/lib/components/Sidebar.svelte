<script lang="ts">
  import { vistaActual, isAdmin, isVentas, usuario, logout } from '../stores';
  import type { VistaActual } from '../types';

  let isOpen = $state(false);

  const ICONS = {
    gasPump: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Y9XTBPYYZden371yTkDUk5RakCVmX9.png',
    dashboard: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
    shoppingCart: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-aVMcBoKPxWSQV3iw3xPRFYGVBDZwzN.png',
    truck: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-31GYuaDQ6tzlmK2MsYTpfwzmJwf9Kr.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    receipt: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
    user: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-dae1lHATNY8hkh3GxeAnScrtd34Ii5.png',
    logout: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-31GYuaDQ6tzlmK2MsYTpfwzmJwf9Kr.png',
    menu: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"%3E%3Cpath stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/%3E%3C/svg%3E',
  };

  const menuItems: { icon: string; label: string; vista: VistaActual; roles?: string[] }[] = [
    { icon: ICONS.dashboard, label: 'Dashboard', vista: 'dashboard', roles: ['admin'] },
    { icon: ICONS.shoppingCart, label: 'Registrar Venta', vista: 'registrar-venta', roles: ['admin', 'ventas'] },
    { icon: ICONS.truck, label: 'Registrar Entrada', vista: 'registrar-entrada', roles: ['admin'] },
    { icon: ICONS.oilCan, label: 'Inventario', vista: 'inventario', roles: ['admin'] },
    { icon: ICONS.receipt, label: 'Historial Ventas', vista: 'historial', roles: ['admin'] },
    { icon: ICONS.truck, label: 'Historial Entradas', vista: 'historial-entradas', roles: ['admin'] },
    { icon: ICONS.user, label: 'Usuarios', vista: 'admin-usuarios', roles: ['admin'] },
  ];

  function puedeVer(item: { roles?: string[] }) {
    if (!item.roles) return true;
    return item.roles.includes($usuario?.rol || '');
  }

  function nav(v: VistaActual) {
    vistaActual.set(v);
    isOpen = false;
  }

  function toggleSidebar() {
    isOpen = !isOpen;
  }

  function closeSidebar() {
    isOpen = false;
  }
</script>

<button
  onclick={toggleSidebar}
  class="lg:hidden fixed top-4 left-4 z-50 p-2 rounded-xl glass-btn shadow-lg"
  aria-label="Abrir menú"
>
  <img src={ICONS.menu} alt="" class="w-6 h-6" />
</button>

{#if isOpen}
  <div
    class="lg:hidden fixed inset-0 bg-black/30 z-40 backdrop-blur-sm"
    onclick={closeSidebar}
    role="button"
    tabindex="-1"
    onkeydown={(e) => e.key === 'Escape' && closeSidebar()}
  ></div>
{/if}

<aside class="glass-sidebar flex flex-col h-full fixed lg:static z-40 transition-transform duration-300 ease-in-out
  {isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}">
  <div class="p-5 border-b border-white/30">
    <div class="flex items-center gap-3">
      <img src={ICONS.gasPump} alt="Fatima LB" class="icon-thumb-lg" />
      <div class="flex-1 min-w-0">
        <h2 class="font-bold text-gray-900 text-sm truncate">Estación Fátima Lubricantes</h2>
        <p class="text-xs text-gray-500 truncate">{$usuario?.nombre}</p>
      </div>
    </div>
  </div>

  <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
    {#each menuItems as item}
      {#if puedeVer(item)}
        <button
          onclick={() => nav(item.vista)}
          class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
            {$vistaActual === item.vista
              ? 'glass-btn bg-blue-50/80 text-blue-700 shadow-sm'
              : 'text-gray-600 hover:bg-white/50 hover:text-gray-900'}"
        >
          <img src={item.icon} alt={item.label} class="icon-thumb-sm" />
          <span class="truncate">{item.label}</span>
        </button>
      {/if}
    {/each}
  </nav>

  <div class="p-3 border-t border-white/30">
    <button
      onclick={() => { logout(); vistaActual.set('dashboard'); isOpen = false; }}
      class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-red-50/80 hover:text-red-600 transition-all duration-200"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Cerrar Sesión
    </button>
  </div>
</aside>
