<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario } from '../stores';
  import { formatCOP, descargarCSV } from '../utils';
  import type { IslaLectura } from '../types';
  import Table from './ui/Table.svelte';
  import Icon from './ui/Icon.svelte';

  let esAdmin = $derived($usuario?.rol === 'admin');

  let lecturas = $state<IslaLectura[]>([]);
  let loading = $state(true);
  let error = $state('');

  // Por defecto: todo el historial hasta hoy.
  let desde = $state('');
  let hasta = $state(hoyISO());
  let filtroIsla = $state('');
  let filtroCombustible = $state('');
  let filtroUsuario = $state('');

  function formatoISO(d: Date): string {
    return (
      d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
    );
  }

  function hoyISO(): string {
    return formatoISO(new Date());
  }

  // "dd/mm/aaaa hh:mm" -> "aaaa-mm-dd" (comparable con los inputs date)
  function fechaISO(fecha: string): string | null {
    const m = /(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(fecha || '');
    return m ? m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0') : null;
  }

  function compararFecha(a: string, b: string): number {
    return (fechaISO(a) ?? a).localeCompare(fechaISO(b) ?? b);
  }

  function fechaBonita(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatearGalones(v: number): string {
    return Number(v || 0).toLocaleString('es-CO', { maximumFractionDigits: 3 });
  }

  onMount(cargar);

  async function cargar() {
    loading = true;
    error = '';
    try {
      lecturas = await api.listarIslas();
    } catch (e: any) {
      error = e?.message || 'No se pudo cargar el historial de islas';
      lecturas = [];
    }
    loading = false;
  }

  let lecturasFiltradas = $derived(
    lecturas
      .filter((l) => {
        if (desde || hasta) {
          const k = fechaISO(l.fecha);
          if (!k) return false;
          if (desde && k < desde) return false;
          if (hasta && k > hasta) return false;
        }
        if (filtroIsla && l.isla !== filtroIsla) return false;
        if (filtroCombustible && l.combustible !== filtroCombustible) return false;
        if (filtroUsuario && (l.usuario || '') !== filtroUsuario) return false;
        return true;
      })
      .sort((a, b) => compararFecha(b.fecha, a.fecha))
  );

  let islasDisponibles = $derived([...new Set(lecturas.map((l) => l.isla).filter(Boolean))].sort());
  let combustiblesDisponibles = $derived([...new Set(lecturas.map((l) => l.combustible).filter(Boolean))].sort());
  // Usuarios que han registrado lecturas, con su nombre visible para el selector.
  let usuariosDisponibles = $derived((() => {
    const mapa: Record<string, string> = {};
    for (const l of lecturas) {
      const u = (l.usuario || '').trim();
      if (!u) continue;
      if (!mapa[u]) mapa[u] = l.cajero || '';
    }
    return Object.entries(mapa).sort((a, b) => a[0].localeCompare(b[0], 'es'));
  })());
  let hayFiltro = $derived(Boolean(desde || hasta || filtroIsla || filtroCombustible || filtroUsuario));

  let totalRegistros = $derived(lecturasFiltradas.length);
  let totalGalones = $derived(lecturasFiltradas.reduce((s, l) => s + Number(l.galones || 0), 0));
  let totalValor = $derived(lecturasFiltradas.reduce((s, l) => s + Number(l.total || 0), 0));

  // Totales por combustible dentro del filtro actual.
  let porCombustible = $derived((() => {
    const mapa: Record<string, { galones: number; total: number; registros: number }> = {};
    for (const l of lecturasFiltradas) {
      const k = (l.combustible || '').trim() || 'Sin especificar';
      const g = mapa[k] ?? { galones: 0, total: 0, registros: 0 };
      g.galones += Number(l.galones || 0);
      g.total += Number(l.total || 0);
      g.registros += 1;
      mapa[k] = g;
    }
    return Object.entries(mapa).sort((a, b) => b[1].total - a[1].total);
  })());

  let rangoTexto = $derived((() => {
    if (!desde && !hasta) return 'Todo el historial de islas';
    if (desde && hasta && desde === hasta) return 'Islas del ' + fechaBonita(desde);
    if (desde && !hasta) return 'Desde el ' + fechaBonita(desde);
    if (!desde && hasta) return 'Hasta el ' + fechaBonita(hasta);
    return 'Del ' + fechaBonita(desde) + ' al ' + fechaBonita(hasta);
  })());

  function limpiarFiltros() {
    desde = '';
    hasta = hoyISO();
    filtroIsla = '';
    filtroCombustible = '';
    filtroUsuario = '';
  }

  function exportar() {
    const filas = lecturasFiltradas.map((l) => [
      l.fecha,
      l.isla,
      l.combustible,
      l.lectura_inicial,
      l.lectura_final,
      l.galones,
      l.precio,
      l.total,
      l.cajero,
      l.usuario,
    ]);
    descargarCSV(
      'historial-islas-' + hoyISO(),
      ['Fecha', 'Isla', 'Combustible', 'Lectura Inicial', 'Lectura Final', 'Galones', 'Precio', 'Total', 'Cajero', 'Usuario'],
      filas
    );
  }

  async function eliminar(l: IslaLectura) {
    if (!confirm(`¿Eliminar la lectura de ${l.isla} · ${l.combustible} del ${l.fecha}?`)) return;
    error = '';
    try {
      await api.eliminarIsla(l.row);
      await cargar();
    } catch (e: any) {
      error = e?.message || 'No se pudo eliminar la lectura';
    }
  }
</script>

<div class="space-y-5">
  <!-- Filtros -->
  <div class="panel p-5 sm:p-6">
    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
      <div class="flex items-start gap-3 min-w-0">
        <div class="w-10 h-10 rounded-[12px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
          <Icon name="history" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Historial de islas</h3>
          <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">{rangoTexto}</p>
        </div>
      </div>

      <div class="lg:ml-auto grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 shrink-0">
        <div>
          <label class="field-label" for="hi-desde">Desde</label>
          <input id="hi-desde" type="date" bind:value={desde} class="input-base" />
        </div>
        <div>
          <label class="field-label" for="hi-hasta">Hasta</label>
          <input id="hi-hasta" type="date" bind:value={hasta} class="input-base" />
        </div>
        <div>
          <label class="field-label" for="hi-isla">Isla</label>
          <select id="hi-isla" bind:value={filtroIsla} class="input-base appearance-none cursor-pointer pr-9">
            <option value="">Todas</option>
            {#each islasDisponibles as isla}
              <option value={isla}>{isla}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="field-label" for="hi-comb">Combustible</label>
          <select id="hi-comb" bind:value={filtroCombustible} class="input-base appearance-none cursor-pointer pr-9">
            <option value="">Todos</option>
            {#each combustiblesDisponibles as comb}
              <option value={comb}>{comb}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="field-label" for="hi-usuario">Usuario</label>
          <select id="hi-usuario" bind:value={filtroUsuario} class="input-base appearance-none cursor-pointer pr-9">
            <option value="">Todos</option>
            {#each usuariosDisponibles as [usuario, nombre] (usuario)}
              <option value={usuario}>{usuario}{nombre ? ' — ' + nombre : ''}</option>
            {/each}
          </select>
        </div>
      </div>
    </div>

    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
      <span class="chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
        {totalRegistros} registro{totalRegistros === 1 ? '' : 's'}
      </span>
      <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">
        {formatearGalones(totalGalones)} galones
      </span>
      <span class="chip bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">
        {formatCOP(totalValor)}
      </span>
      {#if hayFiltro}
        <button
          type="button"
          onclick={limpiarFiltros}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 transition-colors cursor-pointer"
        >
          Limpiar filtros
        </button>
      {/if}
      <button
        type="button"
        onclick={exportar}
        disabled={totalRegistros === 0}
        class="toque ml-auto inline-flex items-center gap-2 rounded-[10px] bg-slate-100 text-slate-700 text-[12.5px] font-semibold px-3.5 py-2.5
          ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer
          disabled:opacity-50 disabled:pointer-events-none"
      >
        <Icon name="download" class="w-4 h-4" />
        Exportar CSV
      </button>
    </div>
  </div>

  {#if error}
    <p class="text-[13px] text-rose-600 flex items-start gap-2 px-1">
      <Icon name="alert" class="w-4 h-4 mt-[2px] shrink-0" />
      {error}
    </p>
  {/if}

  <!-- Totales por combustible -->
  {#if !loading && porCombustible.length > 0}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      {#each porCombustible as [comb, d] (comb)}
        <div class="panel p-5">
          <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-[12px] {comb === 'Gasolina' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'} flex items-center justify-center shrink-0">
              <Icon name="droplet" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{comb}</p>
              <p class="text-[20px] font-extrabold text-slate-900 leading-tight">{formatCOP(d.total)}</p>
            </div>
          </div>
          <p class="text-[12px] text-slate-500 mt-3">
            {formatearGalones(d.galones)} galones · {d.registros} registro{d.registros === 1 ? '' : 's'}
          </p>
        </div>
      {/each}
    </div>
  {/if}

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(5) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="h-3 w-32 rounded bg-slate-200/70"></div>
            <div class="h-3 w-24 rounded bg-slate-200/50 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Fecha', 'Isla', 'Combustible', 'Lecturas', 'Galones', 'Precio', 'Total', 'Cajero', '']}>
        {#each lecturasFiltradas as l (l.row)}
          <tr class="hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3.5 text-[12.5px] text-slate-500 whitespace-nowrap">{l.fecha}</td>
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-[8px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                  <Icon name="fuel" class="w-3.5 h-3.5" />
                </span>
                <span class="font-semibold text-[13.5px] text-slate-900">{l.isla}</span>
              </div>
            </td>
            <td class="px-4 py-3.5">
              <span class="inline-flex items-center gap-1.5 text-[12.5px] text-slate-600">
                <span class="w-2 h-2 rounded-full {l.combustible === 'Gasolina' ? 'bg-emerald-500' : 'bg-slate-700'}"></span>
                {l.combustible}
              </span>
            </td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-500 whitespace-nowrap">
              {formatearGalones(l.lectura_inicial)} → {formatearGalones(l.lectura_final)}
            </td>
            <td class="px-4 py-3.5 text-[13px] font-semibold text-slate-700">{formatearGalones(l.galones)}</td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-500 whitespace-nowrap">{formatCOP(l.precio)}</td>
            <td class="px-4 py-3.5 text-[13.5px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(l.total)}</td>
            <td class="px-4 py-3.5">
              {#if l.cajero || l.usuario}
                <p class="text-[12.5px] text-slate-600 leading-tight">{l.cajero || '—'}</p>
                {#if l.usuario}
                  <p class="text-[11px] text-slate-400 leading-tight">@{l.usuario}</p>
                {/if}
              {:else}
                <span class="text-[12.5px] text-slate-400">—</span>
              {/if}
            </td>
            <td class="px-4 py-3.5 text-right">
              {#if esAdmin}
                <button
                  type="button"
                  onclick={() => eliminar(l)}
                  aria-label={'Eliminar lectura de ' + l.isla + ' ' + l.combustible}
                  class="p-2 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                >
                  <Icon name="trash" class="w-4 h-4" />
                </button>
              {/if}
            </td>
          </tr>
        {/each}
        {#if lecturasFiltradas.length === 0}
          <tr>
            <td colspan="9" class="px-4 py-12 text-center text-slate-400">
              {hayFiltro ? 'No hay lecturas en el rango seleccionado' : 'Aún no hay lecturas de islas registradas'}
            </td>
          </tr>
        {/if}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each lecturasFiltradas as l (l.row)}
        <div class="panel p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="w-9 h-9 rounded-[11px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <Icon name="fuel" class="w-[18px] h-[18px]" />
              </span>
              <div class="min-w-0">
                <p class="text-[14px] font-bold text-slate-900 leading-snug truncate">{l.isla}</p>
                <p class="text-[11px] text-slate-400 truncate">{l.fecha} · {l.combustible}</p>
              </div>
            </div>
            <p class="text-[15px] font-extrabold text-slate-900 shrink-0">{formatCOP(l.total)}</p>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-2.5">
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Lecturas</p>
              <p class="text-[12.5px] font-semibold text-slate-700">
                {formatearGalones(l.lectura_inicial)} → {formatearGalones(l.lectura_final)}
              </p>
            </div>
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Galones</p>
              <p class="text-[13px] font-bold text-slate-800">{formatearGalones(l.galones)}</p>
            </div>
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Precio</p>
              <p class="text-[12.5px] font-semibold text-slate-700">{formatCOP(l.precio)}</p>
            </div>
          </div>

          {#if l.cajero || l.usuario || esAdmin}
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2">
              <span class="text-[11.5px] text-slate-400 truncate">
                {l.cajero || 'Sin cajero'}{l.usuario ? ' · @' + l.usuario : ''}
              </span>
              {#if esAdmin}
                <button
                  type="button"
                  onclick={() => eliminar(l)}
                  class="ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                >
                  <Icon name="trash" class="w-3.5 h-3.5" />
                  Eliminar
                </button>
              {/if}
            </div>
          {/if}
        </div>
      {/each}

      {#if lecturasFiltradas.length === 0}
        <div class="panel px-5 py-10 text-center">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <Icon name="history" class="w-6 h-6" strokeWidth={1.5} />
          </div>
          <p class="text-[13px] text-slate-500">
            {hayFiltro ? 'No hay lecturas en el rango seleccionado' : 'Aún no hay lecturas de islas registradas'}
          </p>
        </div>
      {/if}
    </div>
  {/if}
</div>
