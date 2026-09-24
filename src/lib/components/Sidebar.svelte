<script lang="ts">
  import { vistaActual, usuario, logout, negocio, setNegocio, logo } from '../stores';
  import { NEGOCIOS, NEGOCIOS_TIENDA, TODOS_LOS_NEGOCIOS } from '../negocios';
  import type { VistaActual } from '../types';
  import Icon from './ui/Icon.svelte';

  let { open = false, onclose = () => {} }: { open?: boolean; onclose?: () => void } = $props();

  $effect(() => {
    if (open) {
      const prev = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = prev;
      };
    }
  });

  type MenuItem = { icon: string; label: string; vista: VistaActual; roles: string[]; negocios: string[] };
  // Roles: 'admin' (control total) y 'cajero' (operación, sin cambios de catálogo).
  const ADMIN = ['admin'];
  const OPERACION = ['admin', 'cajero'];
  // Negocios: 'estacion' y 'otro' (Tienda) venden lubricantes; 'islas' es el
  // negocio de combustible y solo muestra su propio menú.
  const grupos: { titulo: string; items: MenuItem[] }[] = [
    {
      titulo: 'Combustible',
      items: [
        { icon: 'fuel', label: 'Cierre de islas', vista: 'islas', roles: OPERACION, negocios: ['islas'] },
        { icon: 'history', label: 'Historial de islas', vista: 'historial-islas', roles: ADMIN, negocios: ['islas'] },
      ],
    },
    {
      titulo: 'Principal',
      items: [
        { icon: 'dashboard', label: 'Dashboard', vista: 'dashboard', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
        { icon: 'receipt', label: 'Mi resumen', vista: 'dashboard', roles: ['cajero'], negocios: NEGOCIOS_TIENDA },
      ],
    },
    {
      titulo: 'Operación',
      items: [
        { icon: 'cart', label: 'Registrar Venta', vista: 'registrar-venta', roles: OPERACION, negocios: NEGOCIOS_TIENDA },
        { icon: 'package', label: 'Registrar Entrada', vista: 'registrar-entrada', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
        { icon: 'droplet', label: 'Inventario', vista: 'inventario', roles: OPERACION, negocios: NEGOCIOS_TIENDA },
      ],
    },
    {
      titulo: 'Historial',
      items: [
        { icon: 'receipt', label: 'Historial Ventas', vista: 'historial', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
        { icon: 'history', label: 'Historial Entradas', vista: 'historial-entradas', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
        { icon: 'arrow-up-right', label: 'Salidas de inventario', vista: 'historial-salidas', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
      ],
    },
    {
      titulo: 'Clientes',
      items: [{ icon: 'users', label: 'Clientes', vista: 'clientes', roles: OPERACION, negocios: NEGOCIOS_TIENDA }],
    },
    {
      titulo: 'Reportes',
      items: [
        { icon: 'chart', label: 'Resumen general', vista: 'resumen', roles: ADMIN, negocios: TODOS_LOS_NEGOCIOS },
        { icon: 'chart', label: 'Mi resumen general', vista: 'resumen', roles: ['cajero'], negocios: TODOS_LOS_NEGOCIOS },
        { icon: 'chart', label: 'Estadísticas y reportes', vista: 'reportes', roles: ADMIN, negocios: NEGOCIOS_TIENDA },
        { icon: 'chart', label: 'Estadísticas', vista: 'reportes', roles: ['cajero'], negocios: NEGOCIOS_TIENDA },
      ],
    },
    {
      titulo: 'Administración',
      items: [
        { icon: 'users', label: 'Usuarios', vista: 'admin-usuarios', roles: ADMIN, negocios: TODOS_LOS_NEGOCIOS },
        { icon: 'cog', label: 'Configuración', vista: 'configuracion', roles: ADMIN, negocios: TODOS_LOS_NEGOCIOS },
      ],
    },
  ];

  function nav(v: VistaActual) {
    vistaActual.set(v);
    onclose();
  }

  function cambiarNegocio(e: Event) {
    const id = (e.target as HTMLSelectElement).value;
    setNegocio(id);
    // El negocio de islas no tiene ventas ni inventario de lubricantes: se
    // aterriza directo en el cierre de islas.
    vistaActual.set(id === 'islas' ? 'islas' : 'dashboard');
    onclose();
  }

  function cerrarSesion() {
    logout();
    vistaActual.set('dashboard');
    onclose();
  }

  let nombre = $derived($usuario?.nombre || $usuario?.usuario || 'Usuario');
  let rol = $derived($usuario?.rol === 'admin' ? 'Administrador' : $usuario?.rol === 'cajero' || $usuario?.rol === 'ventas' ? 'Cajero' : 'Usuario');
  let iniciales = $derived(
    nombre
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((p) => p[0] || '')
      .join('')
      .toUpperCase() || 'U'
  );
</script>

<svelte:window onkeydown={(e) => e.key === 'Escape' && open && onclose()} />

<!-- Overlay móvil -->
{#if open}
  <!-- svelte-ignore a11y_click_events_have_key_events -->
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-40 bg-slate-900/35 backdrop-blur-[2px] lg:hidden"
    role="presentation"
    onclick={onclose}
  ></div>
{/if}

<aside
  class="fixed lg:static inset-y-0 left-0 z-50 w-[280px] max-w-[85vw] flex flex-col bg-white border-r border-slate-200/80
    shadow-[8px_0_30px_-18px_rgba(15,23,42,0.18)] lg:shadow-none
    transition-transform duration-300 ease-out
    {open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}"
  aria-label="Menú principal"
>
  <!-- Marca -->
  <div class="px-5 pt-5 pb-4 flex items-center gap-3">
    <div class="relative w-11 h-11 rounded-2xl overflow-hidden flex items-center justify-center shrink-0
      {$logo ? 'bg-white ring-1 ring-inset ring-slate-200' : 'bg-gradient-to-br from-blue-600 to-sky-500 shadow-[0_6px_16px_-6px_rgba(37,99,235,0.6)]'}">
      {#if $logo}
        <img src={$logo} alt="Logo de la empresa" class="w-full h-full object-contain" />
      {:else}
        <Icon name="droplet" class="w-5 h-5 text-white" strokeWidth={2.1} />
        <span class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/25"></span>
      {/if}
    </div>
    <div class="min-w-0 leading-tight">
      <h2 class="font-bold text-[15px] text-slate-900 tracking-tight truncate">Estación Fátima</h2>
      <p class="text-[11px] font-medium text-slate-400 truncate">Lubricantes &middot; Punto de venta</p>
    </div>
  </div>

  <!-- Selector de negocio -->
  <div class="px-3 pb-3">
    <label class="field-label !mb-1.5" for="sel-negocio">Negocio</label>
    <div class="relative">
      <select
        id="sel-negocio"
        value={$negocio}
        onchange={cambiarNegocio}
        class="input-base appearance-none pr-9 cursor-pointer"
      >
        {#each NEGOCIOS as n}
          <option value={n.id}>{n.nombre}</option>
        {/each}
      </select>
      <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
        <Icon name="chevron-down" class="w-4 h-4" />
      </span>
    </div>
  </div>

  <!-- Navegación -->
  <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 pb-3 space-y-5">
    {#each grupos as grupo}
      {@const visibles = grupo.items.filter((i) => (i.roles ?? []).includes($usuario?.rol || '') && (i.negocios ?? []).includes($negocio))}
      {#if visibles.length > 0}
        <div>
          <p class="px-3 pb-1.5 text-[10.5px] font-bold uppercase tracking-[0.12em] text-slate-400">{grupo.titulo}</p>
          <div class="space-y-0.5">
            {#each visibles as item}
              <button
                onclick={() => nav(item.vista)}
                aria-current={$vistaActual === item.vista ? 'page' : undefined}
                class="toque w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-colors cursor-pointer
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50
                  { $vistaActual === item.vista
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }"
              >
                <span class="w-[22px] flex items-center justify-center">
                  <Icon name={item.icon} class="w-[19px] h-[19px] { $vistaActual === item.vista ? 'text-blue-600' : 'text-slate-400' }" />
                </span>
                <span class="truncate">{item.label}</span>
                {#if $vistaActual === item.vista}
                  <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                {/if}
              </button>
            {/each}
          </div>
        </div>
      {/if}
    {/each}
  </nav>

  <!-- Usuario + salir -->
  <div class="border-t border-slate-200/80 p-3">
    <div class="flex items-center gap-3 rounded-xl px-2 py-2">
      <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-600 to-slate-800 text-white text-[12px] font-bold flex items-center justify-center shrink-0 select-none">
        {iniciales}
      </div>
      <div class="min-w-0 flex-1 leading-tight">
        <p class="text-[13px] font-semibold text-slate-800 truncate">{nombre}</p>
        <p class="text-[11px] text-slate-400">{rol}</p>
      </div>
      <button
        onclick={cerrarSesion}
        title="Cerrar sesión"
        aria-label="Cerrar sesión"
        class="toque-icono p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
      >
        <Icon name="logout" class="w-[18px] h-[18px]" />
      </button>
    </div>
  </div>
</aside>
