<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP, descargarCSV } from '../utils';
  import Table from './ui/Table.svelte';
  import Modal from './ui/Modal.svelte';
  import Button from './ui/Button.svelte';
  import Icon from './ui/Icon.svelte';
  import type { Venta } from '../types';

  let ventas = $state<Venta[]>([]);
  let loading = $state(true);
  let showDeleteModal = $state(false);
  let selectedVenta = $state<Venta | null>(null);
  let filtroFecha = $state('');
  let filtroCajero = $state('');
  let filtroProducto = $state('');

  let ventasFiltradas = $derived(
    ventas.filter((v) => {
      if (filtroFecha && !v.fecha.toLowerCase().includes(filtroFecha.toLowerCase())) return false;
      if (filtroCajero && !v.cajero.toLowerCase().includes(filtroCajero.toLowerCase())) return false;
      if (filtroProducto && !v.producto.toLowerCase().includes(filtroProducto.toLowerCase())) return false;
      return true;
    })
  );

  let cajeros = $derived([...new Set(ventas.map((v) => v.cajero).filter(Boolean))]);
  let totalFiltrado = $derived(ventasFiltradas.reduce((sum, v) => sum + Number(v.total || 0), 0));
  let cantidadTotal = $derived(ventasFiltradas.reduce((sum, v) => sum + Number(v.cantidad || 0), 0));
  let hayFiltro = $derived(Boolean(filtroFecha || filtroCajero || filtroProducto));

  onMount(async () => {
    try { ventas = await api.listarVentas(); } catch (e) { console.error(e); }
    loading = false;
  });

  function limpiarFiltros() {
    filtroFecha = '';
    filtroCajero = '';
    filtroProducto = '';
  }

  function chipPago(forma: string) {
    switch (forma) {
      case 'Tarjeta': return 'bg-sky-50 text-sky-700 ring-sky-200';
      case 'Transferencia': return 'bg-violet-50 text-violet-700 ring-violet-200';
      case 'Efectivo': return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
      default: return 'bg-slate-100 text-slate-600 ring-slate-200';
    }
  }

  function exportarCSV() {
    if (ventasFiltradas.length === 0) return;
    const filas = [...ventasFiltradas].reverse().map((v) => [
      v.fecha || '',
      v.producto || '',
      v.presentacion || '',
      v.cantidad || 0,
      v.precio_unitario || 0,
      v.total || 0,
      v.forma_pago || '',
      v.cliente || '',
      v.placa || '',
      v.cajero || '',
    ]);
    descargarCSV('historial_ventas', ['Fecha', 'Producto', 'Presentación', 'Cantidad', 'Precio unitario', 'Total', 'Forma de pago', 'Cliente', 'Placa', 'Cajero'], filas);
  }

  async function eliminar() {
    if (!selectedVenta) return;
    try {
      await api.eliminarVenta(selectedVenta.row);
      ventas = ventas.filter((v) => v.row !== selectedVenta!.row);
      showDeleteModal = false;
      selectedVenta = null;
    } catch (e: any) { alert(e.message); }
  }
</script>

<div class="space-y-5">
  <!-- Filtros -->
  <div class="panel p-4">
    <div class="flex items-center justify-between gap-3 mb-3">
      <p class="text-[13px] font-semibold text-slate-700 flex items-center gap-2">
        <Icon name="receipt" class="w-4 h-4 text-blue-600" />
        Historial de ventas
        <span class="chip bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">{ventas.length} registro{ventas.length === 1 ? '' : 's'}</span>
      </p>
      <div class="flex items-center gap-2">
        {#if ventasFiltradas.length > 0}
          <button onclick={exportarCSV} title="Descargar CSV de las ventas filtradas"
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
          <Icon name="calendar" class="w-[17px] h-[17px]" />
        </span>
        <input
          type="text"
          bind:value={filtroFecha}
          placeholder="Filtrar por fecha (dd/mm/aaaa)"
          aria-label="Filtrar por fecha"
          class="input-base pl-9.5"
        />
      </div>
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
          <Icon name="droplet" class="w-[17px] h-[17px]" />
        </span>
        <input
          type="text"
          bind:value={filtroProducto}
          placeholder="Filtrar por producto"
          aria-label="Filtrar por producto"
          class="input-base pl-9.5"
        />
      </div>
      <div class="relative">
        {#if filtroCajero}
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
            <Icon name="user" class="w-[17px] h-[17px]" />
          </span>
        {/if}
        <select
          bind:value={filtroCajero}
          aria-label="Filtrar por cajero"
          class="input-base appearance-none pr-9 {filtroCajero ? 'pl-9.5' : ''} cursor-pointer"
        >
          <option value="">Todos los cajeros</option>
          {#each cajeros as c}
            <option value={c}>{c}</option>
          {/each}
        </select>
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
          <Icon name="chevron-down" class="w-4 h-4" />
        </span>
      </div>
    </div>
  </div>

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(6) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="h-3 w-24 rounded bg-slate-200/70"></div>
            <div class="h-3 w-40 rounded bg-slate-200/50"></div>
            <div class="h-3 w-20 rounded bg-slate-200/60 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Fecha', 'Producto', 'Cant.', 'Precio', 'Total', 'Pago', 'Cliente', 'Cajero', '']}>
        {#each [...ventasFiltradas].reverse() as v}
          <tr class="hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">{v.fecha}</td>
            <td class="px-4 py-3.5">
              <span class="block font-semibold text-[13.5px] text-slate-900 max-w-[220px] truncate">{v.producto}</span>
              {#if v.presentacion}
                <span class="block text-[11px] text-slate-400 mt-0.5 max-w-[220px] truncate">{v.presentacion}</span>
              {/if}
            </td>
            <td class="px-4 py-3.5 text-right font-semibold text-slate-700">{v.cantidad}</td>
            <td class="px-4 py-3.5 text-right text-slate-500">{formatCOP(v.precio_unitario)}</td>
            <td class="px-4 py-3.5 text-right font-bold text-slate-900">{formatCOP(v.total)}</td>
            <td class="px-4 py-3.5">
              <span class="chip ring-1 ring-inset {chipPago(v.forma_pago)}">{v.forma_pago || '—'}</span>
            </td>
            <td class="px-4 py-3.5 text-[13px] text-slate-500 max-w-[140px] truncate">{v.cliente || '—'}</td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-400">{v.cajero}</td>
            <td class="px-4 py-3.5 text-right">
              <button
                onclick={() => { selectedVenta = v; showDeleteModal = true; }}
                aria-label={'Eliminar venta de ' + v.producto}
                class="p-2 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
              >
                <Icon name="trash" class="w-[17px] h-[17px]" />
              </button>
            </td>
          </tr>
        {/each}
        {#if ventasFiltradas.length > 0}
          <tr class="bg-blue-50/60 border-t-2 border-blue-100">
            <td class="px-4 py-3.5 font-bold text-slate-800" colspan="2">TOTAL &middot; {ventasFiltradas.length} registro{ventasFiltradas.length === 1 ? '' : 's'}</td>
            <td class="px-4 py-3.5 text-right font-bold text-slate-800">{cantidadTotal}</td>
            <td class="px-4 py-3.5"></td>
            <td class="px-4 py-3.5 text-right font-extrabold text-[15px] text-blue-700">{formatCOP(totalFiltrado)}</td>
            <td class="px-4 py-3.5" colspan="4"></td>
          </tr>
        {/if}
        {#if ventasFiltradas.length === 0}
          <tr><td colspan="9" class="px-4 py-14 text-center text-slate-400">
            {hayFiltro ? 'No se encontraron registros con esos filtros' : 'Aún no hay ventas registradas'}
          </td></tr>
        {/if}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each [...ventasFiltradas].reverse() as v}
        <div class="panel p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[14px] font-bold text-slate-900 leading-snug">{v.producto}</p>
              <p class="text-[11px] text-slate-400 mt-0.5">{v.presentacion || v.fecha}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-[16px] font-extrabold text-blue-700 leading-tight">{formatCOP(v.total)}</p>
              <p class="text-[10.5px] text-slate-400 mt-0.5">{v.cantidad} und &middot; {formatCOP(v.precio_unitario)} c/u</p>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <span class="chip ring-1 ring-inset {chipPago(v.forma_pago)}">{v.forma_pago || '—'}</span>
            <span class="text-[11.5px] text-slate-500">{v.presentacion ? v.fecha : ''}</span>
            {#if !v.presentacion}<span class="text-[11.5px] text-slate-500">{v.fecha}</span>{/if}
          </div>
          <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 min-w-0 text-[12px] text-slate-500">
              <Icon name="user" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span class="truncate">{v.cliente || 'Sin cliente'}</span>
            </div>
            <span class="text-[11.5px] text-slate-400 shrink-0">{v.cajero}</span>
            <button
              onclick={() => { selectedVenta = v; showDeleteModal = true; }}
              aria-label={'Eliminar venta de ' + v.producto}
              class="p-1.5 -m-1 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer shrink-0"
            >
              <Icon name="trash" class="w-4 h-4" />
            </button>
          </div>
        </div>
      {/each}

      {#if ventasFiltradas.length > 0}
        <div class="flex items-center justify-between gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-sky-500 text-white px-4 py-3">
          <div class="text-[12px] font-semibold leading-tight">
            <span class="block">TOTAL &middot; {ventasFiltradas.length} venta{ventasFiltradas.length === 1 ? '' : 's'}</span>
            <span class="block text-[11px] text-blue-100">{cantidadTotal} unidades</span>
          </div>
          <p class="text-[18px] font-extrabold tracking-tight">{formatCOP(totalFiltrado)}</p>
        </div>
      {:else}
        <div class="panel px-5 py-10 text-center">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <Icon name={hayFiltro ? 'search' : 'receipt'} class="w-6 h-6" strokeWidth={1.5} />
          </div>
          <p class="text-[13px] text-slate-500">{hayFiltro ? 'No se encontraron registros con esos filtros' : 'Aún no hay ventas registradas'}</p>
          {#if hayFiltro}
            <button onclick={limpiarFiltros} class="mt-2 text-[12px] font-semibold text-blue-600 hover:underline cursor-pointer">Quitar filtros</button>
          {/if}
        </div>
      {/if}
    </div>
  {/if}
</div>

<Modal show={showDeleteModal} title="Eliminar venta" subtitle="Esta acción no se puede deshacer" onclose={() => (showDeleteModal = false)}>
  <div class="flex items-start gap-3 rounded-2xl bg-rose-50/70 ring-1 ring-inset ring-rose-100 p-4">
    <span class="w-9 h-9 rounded-[10px] bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
      <Icon name="trash" class="w-[18px] h-[18px]" />
    </span>
    <div class="text-[13.5px] text-slate-700 leading-relaxed">
      ¿Seguro que deseas eliminar la venta de
      <strong class="text-slate-900">{selectedVenta?.producto}</strong>
      {#if selectedVenta}
        del <span class="text-slate-500">{selectedVenta.fecha}</span>?
      {/if}
    </div>
  </div>
  <div class="flex gap-3 justify-end pt-5">
    <Button variant="secondary" onclick={() => (showDeleteModal = false)}>Cancelar</Button>
    <Button variant="danger" onclick={eliminar}>Eliminar venta</Button>
  </div>
</Modal>
