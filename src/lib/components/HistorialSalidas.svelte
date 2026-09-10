<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP, descargarCSV } from '../utils';
  import Table from './ui/Table.svelte';
  import Icon from './ui/Icon.svelte';
  import type { Salida } from '../types';

  interface GrupoSalida {
    etiqueta: string;
    registros: number;
    unidades: number;
    valor: number;
  }

  let salidas = $state<Salida[]>([]);
  let loading = $state(true);
  let error = $state('');
  // Por defecto se muestra todo el historial hasta la fecha actual.
  let desde = $state('');
  let hasta = $state(hoyISO());
  let filtroProducto = $state('');
  let filtroTipo = $state('');

  let salidasFiltradas = $derived(
    salidas.filter((s) => {
      if (desde || hasta) {
        const k = fechaISO(s.fecha);
        if (!k) return false;
        if (desde && k < desde) return false;
        if (hasta && k > hasta) return false;
      }
      if (filtroProducto && !s.producto.toLowerCase().includes(filtroProducto.toLowerCase())) return false;
      if (filtroTipo && (s.tipo || '').trim() !== filtroTipo) return false;
      return true;
    })
  );

  // "dd/mm/aaaa hh:mm" -> "aaaa-mm-dd" (comparable con los inputs date)
  function fechaISO(fecha: string): string | null {
    const parte = (fecha || '').trim().split(' ')[0];
    const m = parte.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!m) return null;
    return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
  }

  function compararFecha(a: string, b: string): number {
    return (fechaISO(a) ?? a).localeCompare(fechaISO(b) ?? b);
  }

  function formatoISO(d: Date): string {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function hoyISO(): string {
    return formatoISO(new Date());
  }

  function isoHaceDias(dias: number): string {
    const d = new Date();
    d.setDate(d.getDate() - dias);
    return formatoISO(d);
  }

  function fechaBonita(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function pct(parte: number, total: number): number {
    return total > 0 ? Math.round((parte / total) * 100) : 0;
  }

  function agrupar(clave: (s: Salida) => string): GrupoSalida[] {
    // Agregador local y síncrono: un objeto plano evita reactividad innecesaria.
    const mapa: Record<string, GrupoSalida> = {};
    for (const s of salidasFiltradas) {
      const k = clave(s).trim() || 'Sin dato';
      const g = mapa[k] ?? { etiqueta: k, registros: 0, unidades: 0, valor: 0 };
      g.registros += 1;
      g.unidades += Number(s.cantidad || 0);
      g.valor += Number(s.cantidad || 0) * Number(s.precio_venta || 0);
      mapa[k] = g;
    }
    return Object.values(mapa);
  }

  const PALETA = ['bg-rose-500', 'bg-amber-500', 'bg-sky-500', 'bg-violet-500', 'bg-emerald-500', 'bg-slate-400'];
  function colorBarra(_etiqueta: string, i: number): string {
    return PALETA[i % PALETA.length];
  }

  let tipos = $derived([...new Set(salidas.map((s) => (s.tipo || '').trim()).filter(Boolean))].sort());
  let hayFiltro = $derived(Boolean(desde || hasta || filtroProducto || filtroTipo));

  let rangoTexto = $derived((() => {
    if (!desde && !hasta) return 'Todo el historial de salidas';
    if (desde && hasta && desde === hasta) return 'Salidas del ' + fechaBonita(desde);
    if (desde && !hasta) return 'Desde el ' + fechaBonita(desde);
    if (!desde && hasta) return 'Hasta el ' + fechaBonita(hasta);
    return 'Del ' + fechaBonita(desde) + ' al ' + fechaBonita(hasta);
  })());

  let totalRegistros = $derived(salidasFiltradas.length);
  let totalUnidades = $derived(salidasFiltradas.reduce((sum, s) => sum + Number(s.cantidad || 0), 0));
  let totalValor = $derived(
    salidasFiltradas.reduce((sum, s) => sum + Number(s.cantidad || 0) * Number(s.precio_venta || 0), 0)
  );
  let totalProductos = $derived(new Set(salidasFiltradas.map((s) => s.producto)).size);
  let totalTipos = $derived(new Set(salidasFiltradas.map((s) => (s.tipo || 'Sin tipo').trim() || 'Sin tipo')).size);

  let porTipo = $derived(agrupar((s) => s.tipo || 'Sin tipo').sort((a, b) => b.unidades - a.unidades));
  let porProducto = $derived(
    agrupar((s) => s.producto).sort((a, b) => b.unidades - a.unidades).slice(0, 8)
  );
  let porFecha = $derived(
    agrupar((s) => (s.fecha || '').trim().split(' ')[0]).sort((a, b) => compararFecha(a.etiqueta, b.etiqueta)).slice(-12)
  );

  let maxTipo = $derived(Math.max(1, ...porTipo.map((g) => g.unidades)));
  let maxProducto = $derived(Math.max(1, ...porProducto.map((g) => g.unidades)));
  let maxFecha = $derived(Math.max(1, ...porFecha.map((g) => g.unidades)));

  const kpis = $derived([
    { label: 'Salidas', valor: String(totalRegistros), detalle: 'Movimientos de inventario', icon: 'arrow-up-right', tile: 'bg-rose-50 text-rose-600' },
    { label: 'Unidades', valor: String(totalUnidades), detalle: 'Total descontado del stock', icon: 'package', tile: 'bg-amber-50 text-amber-600' },
    { label: 'Valor estimado', valor: formatCOP(totalValor), detalle: 'Valorizado a precio de venta', icon: 'banknote', tile: 'bg-sky-50 text-sky-600' },
    { label: 'Productos', valor: String(totalProductos), detalle: totalTipos + ' tipo' + (totalTipos === 1 ? '' : 's') + ' de salida', icon: 'droplet', tile: 'bg-violet-50 text-violet-600' },
  ]);

  onMount(async () => {
    try {
      salidas = await api.listarSalidas();
    } catch (e: any) {
      error = e?.message || 'No se pudieron cargar las salidas de inventario';
      console.error(e);
    }
    loading = false;
  });

  function setRangoHoy() {
    desde = hoyISO();
    hasta = hoyISO();
  }

  function setUltimos30() {
    desde = isoHaceDias(29);
    hasta = hoyISO();
  }

  function setTodo() {
    desde = '';
    hasta = '';
  }

  function limpiarFiltros() {
    desde = '';
    hasta = '';
    filtroProducto = '';
    filtroTipo = '';
  }

  function tipoChip(tipo: string) {
    switch (tipo) {
      case 'Venta': return 'bg-rose-50 text-rose-700 ring-rose-200';
      case 'Traslado': return 'bg-sky-50 text-sky-700 ring-sky-200';
      case 'Consumo': return 'bg-amber-50 text-amber-700 ring-amber-200';
      default: return 'bg-slate-100 text-slate-600 ring-slate-200';
    }
  }

  function exportarCSV() {
    if (salidasFiltradas.length === 0) return;
    const filas = [...salidasFiltradas].reverse().map((s) => [
      s.fecha || '',
      s.producto || '',
      s.cantidad || 0,
      s.precio_venta || 0,
      Number(s.cantidad || 0) * Number(s.precio_venta || 0),
      s.tipo || '',
      s.cajero || '',
    ]);
    descargarCSV('salidas_inventario', ['Fecha', 'Producto', 'Cantidad', 'Precio', 'Total', 'Tipo', 'Cajero'], filas);
  }
</script>

<div class="space-y-5">
  <!-- Cabecera + filtros -->
  <div class="panel p-4">
    <div class="flex items-center justify-between gap-3 mb-3">
      <p class="text-[13px] font-semibold text-slate-700 flex items-center gap-2">
        <Icon name="arrow-up-right" class="w-4 h-4 text-rose-500" />
        Salidas de inventario
        <span class="chip bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-100">{salidas.length} movimiento{salidas.length === 1 ? '' : 's'}</span>
      </p>
      <div class="flex items-center gap-2">
        {#if salidasFiltradas.length > 0}
          <button onclick={exportarCSV} title="Descargar CSV de las salidas filtradas"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12.5px] font-semibold text-slate-600 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer">
            <Icon name="download" class="w-3.5 h-3.5" />
            <span class="hidden sm:inline">Exportar CSV</span>
          </button>
        {/if}
        {#if hayFiltro}
          <button onclick={limpiarFiltros} class="inline-flex items-center gap-1 text-[12.5px] font-medium text-blue-600 hover:text-blue-700 hover:underline cursor-pointer">
            Limpiar filtros
          </button>
        {/if}
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="field-label" for="sal-desde">Desde</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
              <Icon name="calendar" class="w-[17px] h-[17px]" />
            </span>
            <input id="sal-desde" type="date" bind:value={desde} max={hasta || undefined} class="input-base pl-9" />
          </div>
        </div>
        <div>
          <label class="field-label" for="sal-hasta">Hasta</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
              <Icon name="calendar" class="w-[17px] h-[17px]" />
            </span>
            <input id="sal-hasta" type="date" bind:value={hasta} min={desde || undefined} class="input-base pl-9" />
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="field-label" for="sal-producto">Producto</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
              <Icon name="droplet" class="w-[17px] h-[17px]" />
            </span>
            <input id="sal-producto" type="text" bind:value={filtroProducto} placeholder="Buscar producto" class="input-base pl-9.5" />
          </div>
        </div>
        <div>
          <label class="field-label" for="sal-tipo">Tipo</label>
          <div class="relative">
            <select id="sal-tipo" bind:value={filtroTipo} class="input-base appearance-none pr-9 cursor-pointer">
              <option value="">Todos los tipos</option>
              {#each tipos as t (t)}
                <option value={t}>{t}</option>
              {/each}
            </select>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
              <Icon name="chevron-down" class="w-4 h-4" />
            </span>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
      <button type="button" onclick={setRangoHoy}
        class="inline-flex items-center gap-1.5 rounded-[10px] bg-blue-50 text-blue-700 text-[12.5px] font-semibold px-3 py-2 ring-1 ring-inset ring-blue-100 hover:bg-blue-100 transition-colors cursor-pointer">
        <Icon name="check" class="w-3.5 h-3.5" />
        Hoy
      </button>
      <button type="button" onclick={setUltimos30}
        class="inline-flex items-center gap-1.5 rounded-[10px] bg-slate-100 text-slate-600 text-[12.5px] font-semibold px-3 py-2 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer">
        <Icon name="history" class="w-3.5 h-3.5" />
        Últimos 30 días
      </button>
      <button type="button" onclick={setTodo}
        class="inline-flex items-center gap-1.5 rounded-[10px] bg-slate-100 text-slate-600 text-[12.5px] font-semibold px-3 py-2 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer">
        Todo el historial
      </button>
    </div>
    <p class="mt-2.5 text-[12px] text-slate-500 flex items-center gap-1.5">
      <Icon name="calendar" class="w-3.5 h-3.5 text-slate-400" />
      {rangoTexto} &middot; {salidasFiltradas.length} movimiento{salidasFiltradas.length === 1 ? '' : 's'}
    </p>
  </div>

  {#if loading}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      {#each Array(4) as _, i (i)}
        <div class="panel rounded-2xl p-4 animate-pulse"><div class="h-10 w-10 rounded-xl bg-slate-200/70 mb-3"></div><div class="h-3 w-16 rounded bg-slate-200/60"></div></div>
      {/each}
    </div>
    <div class="panel h-64 animate-pulse"></div>
  {:else if error}
    <div class="panel px-5 py-12 text-center">
      <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-3">
        <Icon name="alert" class="w-6 h-6" strokeWidth={1.6} />
      </div>
      <p class="text-[13.5px] font-semibold text-slate-700">No se pudieron cargar las salidas</p>
      <p class="text-[12.5px] text-slate-500 mt-1">{error}</p>
    </div>
  {:else if salidas.length === 0}
    <div class="panel px-6 py-14 text-center">
      <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
        <Icon name="arrow-up-right" class="w-7 h-7" strokeWidth={1.5} />
      </div>
      <p class="text-[14px] font-semibold text-slate-700">Aún no hay salidas registradas</p>
      <p class="text-[12.5px] text-slate-500 mt-1.5 max-w-md mx-auto">
        Los movimientos de salida aparecerán aquí a medida que se registren. Prueba ampliar el rango de fechas o usar "Todo el historial".
      </p>
    </div>
  {:else}
    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      {#each kpis as k (k.label)}
        <div class="panel rounded-2xl p-4">
          <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl {k.tile} flex items-center justify-center shrink-0">
              <Icon name={k.icon} class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <p class="text-[10.5px] font-bold uppercase tracking-[0.08em] text-slate-400 truncate">{k.label}</p>
              <p class="text-[19px] font-extrabold text-slate-900 tracking-tight leading-tight truncate">{k.valor}</p>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 mt-2 truncate">{k.detalle}</p>
        </div>
      {/each}
    </div>

    {#if salidasFiltradas.length === 0}
      <div class="panel px-5 py-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
          <Icon name="search" class="w-6 h-6" strokeWidth={1.5} />
        </div>
        <p class="text-[13px] text-slate-500">No se encontraron movimientos con esos filtros</p>
        <button onclick={limpiarFiltros} class="mt-2 text-[12px] font-semibold text-blue-600 hover:underline cursor-pointer">Quitar filtros</button>
      </div>
    {:else}
      <!-- Desglose por tipo y por producto -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="panel rounded-2xl p-5">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-8 h-8 rounded-[10px] bg-rose-50 text-rose-600 flex items-center justify-center">
              <Icon name="tag" class="w-4 h-4" />
            </span>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Por tipo de salida</h3>
          </div>
          <div class="space-y-3.5">
            {#each porTipo as g, i (g.etiqueta)}
              <div>
                <div class="flex items-baseline justify-between gap-2">
                  <span class="text-[12.5px] font-semibold text-slate-700 truncate">{g.etiqueta}</span>
                  <span class="text-[11.5px] text-slate-500 shrink-0">{g.unidades} und &middot; {pct(g.unidades, totalUnidades)}%</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full {colorBarra(g.etiqueta, i)}" style="width: {Math.max(pct(g.unidades, maxTipo), 2)}%"></div>
                </div>
              </div>
            {/each}
          </div>
        </div>

        <div class="panel rounded-2xl p-5">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-8 h-8 rounded-[10px] bg-blue-50 text-blue-600 flex items-center justify-center">
              <Icon name="droplet" class="w-4 h-4" />
            </span>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Productos con más salidas</h3>
          </div>
          <div class="space-y-3.5">
            {#each porProducto as g (g.etiqueta)}
              <div>
                <div class="flex items-baseline justify-between gap-2">
                  <span class="text-[12.5px] font-semibold text-slate-700 truncate">{g.etiqueta}</span>
                  <span class="text-[11.5px] text-slate-500 shrink-0">{g.unidades} und &middot; {formatCOP(g.valor)}</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-sky-400" style="width: {Math.max(pct(g.unidades, maxProducto), 2)}%"></div>
                </div>
              </div>
            {/each}
          </div>
        </div>
      </div>

      <!-- Actividad por fecha -->
      <div class="panel rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-5">
          <span class="w-8 h-8 rounded-[10px] bg-slate-100 text-slate-500 flex items-center justify-center">
            <Icon name="calendar" class="w-4 h-4" />
          </span>
          <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Actividad por fecha</h3>
          <span class="text-[11.5px] text-slate-400 ml-auto">Unidades por día</span>
        </div>
        <div class="flex items-end gap-1.5 sm:gap-2 overflow-x-auto pb-1">
          {#each porFecha as g (g.etiqueta)}
            <div class="flex-1 min-w-[34px] flex flex-col items-center gap-1">
              <span class="text-[10px] font-semibold text-slate-500">{g.unidades}</span>
              <div class="w-full max-w-[42px] rounded-t-md bg-gradient-to-t from-rose-500 to-rose-300" style="height: {Math.round((g.unidades / maxFecha) * 88) + 8}px"></div>
              <span class="text-[9.5px] text-slate-400 whitespace-nowrap">{g.etiqueta.slice(0, 5)}</span>
            </div>
          {/each}
        </div>
      </div>

      <!-- Detalle -->
      <div>
        <p class="field-label uppercase text-[10.5px] tracking-[0.12em] !mb-2.5">Detalle de movimientos</p>

        <!-- Escritorio: tabla -->
        <div class="hidden md:block panel overflow-hidden">
          <Table headers={['Fecha', 'Producto', 'Cant.', 'Precio', 'Total', 'Tipo', 'Cajero']}>
            {#each [...salidasFiltradas].reverse() as s (s.row)}
              <tr class="hover:bg-rose-50/30 transition-colors">
                <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">{s.fecha}</td>
                <td class="px-4 py-3.5 font-semibold text-[13.5px] text-slate-900">{s.producto}</td>
                <td class="px-4 py-3.5 text-right">
                  <span class="inline-flex items-center gap-1 font-bold text-rose-600"><Icon name="minus" class="w-3 h-3" strokeWidth={3} />{s.cantidad}</span>
                </td>
                <td class="px-4 py-3.5 text-right text-slate-500">{formatCOP(Number(s.precio_venta) || 0)}</td>
                <td class="px-4 py-3.5 text-right font-bold text-slate-900">{formatCOP(Number(s.cantidad || 0) * Number(s.precio_venta || 0))}</td>
                <td class="px-4 py-3.5">
                  <span class="chip ring-1 ring-inset {tipoChip(s.tipo)}">{s.tipo || '—'}</span>
                </td>
                <td class="px-4 py-3.5 text-[12.5px] text-slate-400">{s.cajero}</td>
              </tr>
            {/each}
            <tr class="bg-rose-50/60 border-t-2 border-rose-100">
              <td class="px-4 py-3.5 font-bold text-slate-800" colspan="2">TOTAL &middot; {totalRegistros} movimiento{totalRegistros === 1 ? '' : 's'}</td>
              <td class="px-4 py-3.5 text-right font-bold text-slate-800">{totalUnidades}</td>
              <td class="px-4 py-3.5"></td>
              <td class="px-4 py-3.5 text-right font-extrabold text-[15px] text-rose-700">{formatCOP(totalValor)}</td>
              <td class="px-4 py-3.5" colspan="2"></td>
            </tr>
          </Table>
        </div>

        <!-- Móvil: tarjetas -->
        <div class="md:hidden space-y-3">
          {#each [...salidasFiltradas].reverse() as s (s.row)}
            <div class="panel p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <Icon name="arrow-up-right" class="w-4 h-4 text-rose-500 shrink-0" />
                    <p class="text-[14px] font-bold text-slate-900 leading-snug">{s.producto}</p>
                  </div>
                  <p class="text-[11px] text-slate-400 mt-1">{s.fecha}</p>
                </div>
                <div class="text-right shrink-0">
                  <p class="inline-flex items-center gap-0.5 text-[16px] font-extrabold text-rose-600 leading-tight">
                    <Icon name="minus" class="w-3.5 h-3.5" strokeWidth={3} />
                    {s.cantidad}
                  </p>
                  <p class="text-[10.5px] text-slate-400 mt-0.5">unidades</p>
                </div>
              </div>
              <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2 text-[12px]">
                <span class="text-slate-500">Precio &middot; Total</span>
                <span class="font-bold text-slate-800">{formatCOP(Number(s.precio_venta) || 0)} &middot; {formatCOP(Number(s.cantidad || 0) * Number(s.precio_venta || 0))}</span>
              </div>
              <div class="mt-2 flex items-center justify-between gap-2 text-[12px]">
                <span class="chip ring-1 ring-inset {tipoChip(s.tipo)}">{s.tipo || '—'}</span>
                <span class="flex items-center gap-1.5 text-slate-500"><Icon name="user" class="w-3.5 h-3.5 text-slate-400 shrink-0" />{s.cajero}</span>
              </div>
            </div>
          {/each}
        </div>
      </div>
    {/if}
  {/if}
</div>
