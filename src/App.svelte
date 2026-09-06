<script lang="ts">
  import { onMount } from 'svelte';
  import { isLoggedIn, isVentas, vistaActual, usuario, restoreSession } from './lib/stores';
  import Login from './lib/components/Login.svelte';
  import Sidebar from './lib/components/Sidebar.svelte';
  import Dashboard from './lib/components/Dashboard.svelte';
  import RegistrarVenta from './lib/components/RegistrarVenta.svelte';
  import RegistrarEntrada from './lib/components/RegistrarEntrada.svelte';
  import Inventario from './lib/components/Inventario.svelte';
  import Historial from './lib/components/Historial.svelte';
  import HistorialEntradas from './lib/components/HistorialEntradas.svelte';
  import AdminUsuarios from './lib/components/AdminUsuarios.svelte';

  const ICONS = {
    gasPump: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Y9XTBPYYZden371yTkDUk5RakCVmX9.png',
  };

  const vistas: Record<string, string> = {
    'dashboard': 'Dashboard',
    'registrar-venta': 'Registrar Venta',
    'registrar-entrada': 'Registrar Entrada',
    'inventario': 'Inventario',
    'historial': 'Historial Ventas',
    'historial-entradas': 'Historial Entradas',
    'admin-usuarios': 'Usuarios',
  };

  onMount(() => {
    restoreSession();
    setTimeout(() => {
      const sub = isVentas.subscribe(v => {
        if (v) vistaActual.set('registrar-venta');
      });
      return sub;
    }, 0);
  });
</script>

{#if !$isLoggedIn}
  <Login />
{:else}
  <div class="flex h-screen bg-gray-50 overflow-hidden">
    <Sidebar />
    <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
      <!-- Header mobile -->
      <header class="lg:hidden flex items-center gap-3 p-4 pt-16 sm:p-6 glass-strong border-b border-white/30">
        <img src={ICONS.gasPump} alt="" class="w-8 h-8" />
        <div class="min-w-0">
          <h1 class="font-bold text-gray-900 text-sm truncate">{vistas[$vistaActual] || 'Dashboard'}</h1>
          <p class="text-xs text-gray-500 truncate">Estación Fátima</p>
        </div>
      </header>
      <!-- Header desktop -->
      <header class="hidden lg:flex items-center justify-between px-6 py-3 glass-strong border-b border-white/30">
        <div class="flex items-center gap-3">
          <img src={ICONS.gasPump} alt="" class="w-8 h-8" />
          <div>
            <h1 class="font-bold text-gray-900 text-base">Estación Fátima Lubricantes</h1>
            <p class="text-xs text-gray-500">{$usuario?.nombre}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600">
          <span class="font-medium">{vistas[$vistaActual] || 'Dashboard'}</span>
        </div>
      </header>
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto">
          {#if $vistaActual === 'dashboard'}
            <Dashboard />
          {:else if $vistaActual === 'registrar-venta'}
            <RegistrarVenta />
          {:else if $vistaActual === 'registrar-entrada'}
            <RegistrarEntrada />
          {:else if $vistaActual === 'inventario'}
            <Inventario />
          {:else if $vistaActual === 'historial'}
            <Historial />
          {:else if $vistaActual === 'historial-entradas'}
            <HistorialEntradas />
          {:else if $vistaActual === 'admin-usuarios'}
            <AdminUsuarios />
          {/if}
        </div>
      </main>
    </div>
  </div>
{/if}
