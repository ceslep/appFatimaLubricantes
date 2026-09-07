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
  import Reportes from './lib/components/Reportes.svelte';
  import Configuracion from './lib/components/Configuracion.svelte';
  import Clientes from './lib/components/Clientes.svelte';
  import Icon from './lib/components/ui/Icon.svelte';

  let menuOpen = $state(false);

  const vistas: Record<string, string> = {
    'dashboard': 'Dashboard',
    'registrar-venta': 'Registrar Venta',
    'registrar-entrada': 'Registrar Entrada',
    'inventario': 'Inventario',
    'historial': 'Historial Ventas',
    'historial-entradas': 'Historial Entradas',
    'reportes': 'Reportes y estadísticas',
    'clientes': 'Clientes',
    'admin-usuarios': 'Usuarios',
    'configuracion': 'Configuración',
  };

  const iconosVista: Record<string, string> = {
    'dashboard': 'dashboard',
    'registrar-venta': 'cart',
    'registrar-entrada': 'package',
    'inventario': 'droplet',
    'historial': 'receipt',
    'historial-entradas': 'history',
    'reportes': 'chart',
    'clientes': 'users',
    'admin-usuarios': 'users',
    'configuracion': 'cog',
  };

  let titulo = $derived(
    $vistaActual === 'dashboard' && $usuario?.rol !== 'admin'
      ? 'Mi resumen'
      : $vistaActual === 'reportes' && $usuario?.rol !== 'admin'
        ? 'Mis estadísticas'
        : (vistas[$vistaActual] || 'Dashboard')
  );

  const hoyRaw = new Date().toLocaleDateString('es-CO', { weekday: 'short', day: 'numeric', month: 'short' });
  const hoy = hoyRaw.charAt(0).toUpperCase() + hoyRaw.slice(1);

  let nombre = $derived($usuario?.nombre || $usuario?.usuario || 'Usuario');
  let rol = $derived($usuario?.rol === 'admin' ? 'Administrador' : $usuario?.rol === 'ventas' ? 'Ventas' : 'Usuario');
  let iniciales = $derived(
    nombre
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((p) => p[0] || '')
      .join('')
      .toUpperCase() || 'U'
  );

  onMount(() => {
    restoreSession();
    setTimeout(() => {
      const sub = isVentas.subscribe((v) => {
        if (v) vistaActual.set('registrar-venta');
      });
      return sub;
    }, 0);
  });
</script>

{#if !$isLoggedIn}
  <Login />
{:else}
  <div class="flex h-dvh overflow-hidden">
    <Sidebar open={menuOpen} onclose={() => (menuOpen = false)} />

    <div class="flex-1 min-w-0 flex flex-col">
      <!-- Barra superior -->
      <header class="h-16 shrink-0 flex items-center gap-2 sm:gap-4 px-4 sm:px-6 lg:px-8 bg-white/70 backdrop-blur-xl border-b border-slate-200/80 z-30">
        <button
          onclick={() => (menuOpen = true)}
          class="lg:hidden -ml-1 p-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
          aria-label="Abrir menú"
        >
          <Icon name="menu" class="w-6 h-6" />
        </button>

        <div class="flex items-center gap-2.5 min-w-0">
          <span class="hidden xs:flex w-8 h-8 rounded-[10px] bg-blue-50 text-blue-600 items-center justify-center">
            <Icon name={iconosVista[$vistaActual] || 'dashboard'} class="w-[18px] h-[18px]" />
          </span>
          <div class="min-w-0 leading-tight">
            <h1 class="font-bold text-[17px] tracking-tight text-slate-900 truncate">{titulo}</h1>
            <p class="hidden sm:block text-[11.5px] text-slate-400 truncate -mt-0.5">Estación Fátima &middot; Lubricantes</p>
          </div>
        </div>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
          <div class="hidden md:flex items-center gap-2 text-[12.5px] font-medium text-slate-500 bg-white/80 ring-1 ring-inset ring-slate-200 rounded-full pl-3 pr-3.5 py-1.5">
            <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
            {hoy}
          </div>
          <div class="hidden sm:flex flex-col items-end leading-tight">
            <span class="text-[13px] font-semibold text-slate-800">{nombre}</span>
            <span class="text-[11px] text-slate-400">{rol}</span>
          </div>
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-sky-500 text-white text-[12.5px] font-bold flex items-center justify-center select-none shadow-[0_3px_10px_-3px_rgba(37,99,235,0.5)]">
            {iniciales}
          </div>
        </div>
      </header>

      <!-- Contenido -->
      <main class="flex-1 overflow-y-auto scrollbar-thin px-4 sm:px-6 lg:px-8 py-5 sm:py-7">
        <div class="mx-auto w-full max-w-7xl">
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
          {:else if $vistaActual === 'reportes'}
            <Reportes />
          {:else if $vistaActual === 'admin-usuarios'}
            <AdminUsuarios />
          {:else if $vistaActual === 'clientes'}
            <Clientes />
          {:else if $vistaActual === 'configuracion'}
            <Configuracion />
          {/if}
        </div>
      </main>
    </div>
  </div>
{/if}
