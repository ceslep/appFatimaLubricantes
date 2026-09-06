<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import Table from './ui/Table.svelte';
  import type { Entrada } from '../types';

  const ICONS = {
    truck: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-31GYuaDQ6tzlmK2MsYTpfwzmJwf9Kr.png',
    calendar: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    building: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-mlhQNhgVqu1ZxZAGedPgDJKneIlg7o.png',
    clipboard: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
  };

  let entradas = $state<Entrada[]>([]);
  let loading = $state(true);

  onMount(async () => {
    try { entradas = await api.listarEntradas(); } catch (e) { console.error(e); }
    loading = false;
  });
</script>

<div class="space-y-6">
  <div class="flex items-center gap-3">
    <img src={ICONS.truck} alt="Entradas" class="icon-thumb" />
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Historial de Entradas</h1>
      <p class="text-gray-500 text-sm">{entradas.length} registros de ingreso</p>
    </div>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
  {:else}
    <div class="glass-strong rounded-2xl overflow-hidden">
      <Table headers={['Fecha', 'Producto', 'Cant.', 'Precio Compra', 'Proveedor', 'Observaciones', 'Cajero']}>
        {#each [...entradas].reverse() as e}
          <tr class="hover:bg-white/50 transition-colors">
            <td class="px-4 py-3 text-gray-600 text-xs">{e.fecha}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{e.producto}</td>
            <td class="px-4 py-3 text-emerald-600 font-medium text-right">{e.cantidad}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{formatCOP(e.precio_compra)}</td>
            <td class="px-4 py-3 text-gray-600">{e.proveedor || '-'}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{e.observaciones || '-'}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{e.cajero}</td>
          </tr>
        {/each}
        {#if entradas.length === 0}
          <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay entradas registradas</td></tr>
        {/if}
      </Table>
    </div>
  {/if}
</div>
