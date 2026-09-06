<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import Table from './ui/Table.svelte';
  import Modal from './ui/Modal.svelte';
  import Button from './ui/Button.svelte';
  import type { Venta } from '../types';

  const ICONS = {
    receipt: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
    calendar: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    user: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-dae1lHATNY8hkh3GxeAnScrtd34Ii5.png',
  };

  let ventas = $state<Venta[]>([]);
  let loading = $state(true);
  let showDeleteModal = $state(false);
  let selectedVenta = $state<Venta | null>(null);
  let filtroFecha = $state('');
  let filtroCajero = $state('');
  let filtroProducto = $state('');

  let ventasFiltradas = $derived(
    ventas.filter(v => {
      if (filtroFecha && !v.fecha.toLowerCase().includes(filtroFecha.toLowerCase())) return false;
      if (filtroCajero && !v.cajero.toLowerCase().includes(filtroCajero.toLowerCase())) return false;
      if (filtroProducto && !v.producto.toLowerCase().includes(filtroProducto.toLowerCase())) return false;
      return true;
    })
  );

  let cajeros = $derived([...new Set(ventas.map(v => v.cajero).filter(Boolean))]);
  let totalFiltrado = $derived(ventasFiltradas.reduce((sum, v) => sum + Number(v.total || 0), 0));
  let cantidadTotal = $derived(ventasFiltradas.reduce((sum, v) => sum + Number(v.cantidad || 0), 0));

  onMount(async () => {
    try { ventas = await api.listarVentas(); } catch (e) { console.error(e); }
    loading = false;
  });

  async function eliminar() {
    if (!selectedVenta) return;
    try {
      await api.eliminarVenta(selectedVenta.row);
      ventas = ventas.filter(v => v.row !== selectedVenta!.row);
      showDeleteModal = false;
      selectedVenta = null;
    } catch (e: any) { alert(e.message); }
  }
</script>

<div class="space-y-6">
  <div class="flex items-center gap-3">
    <img src={ICONS.receipt} alt="Historial" class="icon-thumb" />
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Historial de Ventas</h1>
      <p class="text-gray-500 text-sm">{ventas.length} registros</p>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="flex items-center gap-2">
      <img src={ICONS.calendar} alt="Fecha" class="icon-thumb-sm" />
      <input
        type="text"
        bind:value={filtroFecha}
        placeholder="Filtrar por fecha..."
        class="flex-1 px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm
          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
          placeholder:text-gray-400 transition-all"
      />
    </div>
    <div class="flex items-center gap-2">
      <img src={ICONS.oilCan} alt="Producto" class="icon-thumb-sm" />
      <input
        type="text"
        bind:value={filtroProducto}
        placeholder="Filtrar por producto..."
        class="flex-1 px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm
          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
          placeholder:text-gray-400 transition-all"
      />
    </div>
    <div class="flex items-center gap-2">
      <img src={ICONS.user} alt="Cajero" class="icon-thumb-sm" />
      <select
        bind:value={filtroCajero}
        class="flex-1 px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm
          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
      >
        <option value="">Todos los cajeros</option>
        {#each cajeros as c}
          <option value={c}>{c}</option>
        {/each}
      </select>
    </div>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
  {:else}
    <div class="glass-strong rounded-2xl overflow-hidden">
      <Table headers={['Fecha', 'Producto', 'Cant.', 'Precio', 'Total', 'Pago', 'Cliente', 'Cajero', '']}>
        {#each [...ventasFiltradas].reverse() as v}
          <tr class="hover:bg-white/50 transition-colors">
            <td class="px-4 py-3 text-gray-600 text-xs">{v.fecha}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{v.producto}</td>
            <td class="px-4 py-3 text-gray-700 text-right">{v.cantidad}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{formatCOP(v.precio_unitario)}</td>
            <td class="px-4 py-3 font-bold text-blue-700 text-right">{formatCOP(v.total)}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded-full text-xs font-medium {v.forma_pago === 'Efectivo' ? 'bg-green-100 text-green-700' : v.forma_pago === 'Tarjeta' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}">
                {v.forma_pago || 'Efectivo'}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">{v.cliente || '-'}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{v.cajero}</td>
            <td class="px-4 py-3">
              <!-- svelte-ignore a11y_consider_explicit_label -->
              <button onclick={() => { selectedVenta = v; showDeleteModal = true; }}
                class="text-red-400 hover:text-red-600 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </td>
          </tr>
        {/each}
        {#if ventasFiltradas.length > 0}
          <tr class="gradient-success font-bold">
            <td class="px-4 py-3 text-white text-xs" colspan="2">TOTAL ({ventasFiltradas.length} registros)</td>
            <td class="px-4 py-3 text-white text-right">{cantidadTotal}</td>
            <td class="px-4 py-3"></td>
            <td class="px-4 py-3 text-white text-right text-lg">{formatCOP(totalFiltrado)}</td>
            <td class="px-4 py-3" colspan="4"></td>
          </tr>
        {/if}
        {#if ventasFiltradas.length === 0}
          <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">
            {(filtroFecha || filtroCajero || filtroProducto) ? 'No se encontraron registros' : 'No hay ventas registradas'}
          </td></tr>
        {/if}
      </Table>
    </div>
  {/if}
</div>

<Modal show={showDeleteModal} title="Eliminar Venta" onclose={() => showDeleteModal = false}>
  <p class="text-gray-600 mb-4">Estas seguro de eliminar la venta de <strong>{selectedVenta?.producto}</strong>?</p>
  <div class="flex gap-3 justify-end">
    <Button variant="secondary" onclick={() => showDeleteModal = false}>Cancelar</Button>
    <Button variant="danger" onclick={eliminar}>Eliminar</Button>
  </div>
</Modal>
