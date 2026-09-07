<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import Table from './ui/Table.svelte';
  import Icon from './ui/Icon.svelte';
  import type { Entrada } from '../types';

  let entradas = $state<Entrada[]>([]);
  let loading = $state(true);

  onMount(async () => {
    try { entradas = await api.listarEntradas(); } catch (e) { console.error(e); }
    loading = false;
  });

  let totalEntradas = $derived(entradas.reduce((s, e) => s + Number(e.cantidad || 0), 0));
</script>

<div class="space-y-5">
  <div class="flex items-center gap-3">
    <div class="w-11 h-11 rounded-[14px] bg-emerald-50 text-emerald-600 flex items-center justify-center">
      <Icon name="truck" class="w-[22px] h-[22px]" />
    </div>
    <div>
      <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">Historial de entradas</h2>
      <p class="text-[12.5px] text-slate-500">
        {entradas.length} registro{entradas.length === 1 ? '' : 's'} &middot; {totalEntradas} unidades ingresadas
      </p>
    </div>
  </div>

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(5) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="h-3 w-24 rounded bg-slate-200/70"></div>
            <div class="h-3 w-36 rounded bg-slate-200/50"></div>
            <div class="h-3 w-20 rounded bg-slate-200/60 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Fecha', 'Producto', 'Cant.', 'Precio compra', 'Proveedor', 'Observaciones', 'Cajero']}>
        {#each [...entradas].reverse() as e}
          <tr class="hover:bg-emerald-50/30 transition-colors">
            <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">{e.fecha}</td>
            <td class="px-4 py-3.5 font-semibold text-[13.5px] text-slate-900">{e.producto}</td>
            <td class="px-4 py-3.5 text-right">
              <span class="inline-flex items-center gap-1 font-bold text-emerald-600"><Icon name="plus" class="w-3 h-3" strokeWidth={3} />{e.cantidad}</span>
            </td>
            <td class="px-4 py-3.5 text-right text-slate-500">{formatCOP(Number(e.precio_compra) || 0)}</td>
            <td class="px-4 py-3.5 text-[13px] text-slate-600">{e.proveedor || '—'}</td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-400 max-w-[180px] truncate" title={e.observaciones || ''}>{e.observaciones || '—'}</td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-400">{e.cajero}</td>
          </tr>
        {/each}
        {#if entradas.length === 0}
          <tr><td colspan="7" class="px-4 py-14 text-center text-slate-400">Aún no hay entradas registradas</td></tr>
        {/if}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each [...entradas].reverse() as e}
        <div class="panel p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <Icon name="droplet" class="w-4 h-4 text-emerald-500 shrink-0" />
                <p class="text-[14px] font-bold text-slate-900 leading-snug">{e.producto}</p>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">{e.fecha}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="inline-flex items-center gap-0.5 text-[16px] font-extrabold text-emerald-600 leading-tight">
                <Icon name="plus" class="w-3.5 h-3.5" strokeWidth={3} />
                {e.cantidad}
              </p>
              <p class="text-[10.5px] text-slate-400 mt-0.5">unidades</p>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2 text-[12px]">
            <span class="text-slate-500">Precio de compra</span>
            <span class="font-bold text-slate-800">{formatCOP(Number(e.precio_compra) || 0)}</span>
          </div>
          <div class="mt-2 space-y-1 text-[12px]">
            <p class="flex items-center gap-1.5 text-slate-600"><Icon name="building" class="w-3.5 h-3.5 text-slate-400 shrink-0" />{e.proveedor || 'Sin proveedor'}</p>
            {#if e.observaciones}
              <p class="flex items-start gap-1.5 text-slate-500"><Icon name="note" class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-[1px]" />{e.observaciones}</p>
            {/if}
            <p class="flex items-center gap-1.5 text-slate-500"><Icon name="user" class="w-3.5 h-3.5 text-slate-400 shrink-0" />{e.cajero}</p>
          </div>
        </div>
      {/each}
      {#if entradas.length === 0}
        <div class="panel px-5 py-10 text-center">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <Icon name="package" class="w-6 h-6" strokeWidth={1.5} />
          </div>
          <p class="text-[13px] text-slate-500">Aún no hay entradas registradas</p>
        </div>
      {/if}
    </div>
  {/if}
</div>
