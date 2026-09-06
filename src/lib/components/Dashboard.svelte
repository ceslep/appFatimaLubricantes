<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import Card from './ui/Card.svelte';

  const ICONS = {
    coins: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
    check: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-aVMcBoKPxWSQV3iw3xPRFYGVBDZwzN.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    truck: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-31GYuaDQ6tzlmK2MsYTpfwzmJwf9Kr.png',
    alert: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
  };

  let resumen = $state({ total_ventas: 0, total_items: 0, num_ventas: 0, total_entradas: 0 });
  let stockBajo = $state<{ producto: string; stock: number }[]>([]);
  let loading = $state(true);

  onMount(async () => {
    try {
      const [r, inv] = await Promise.all([api.resumenDia(), api.inventario()]);
      resumen = r;
      stockBajo = inv.filter(i => i.stock <= 5).sort((a, b) => a.stock - b.stock);
    } catch (e) { console.error(e); }
    loading = false;
  });
</script>

<div class="space-y-6">
  <div class="hidden sm:block">
    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-500 text-sm">Resumen del dia - Estacion de Servicio Fatima</p>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
  {:else}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 gradient-primary rounded-xl flex items-center justify-center shadow-lg shadow-blue-200/50">
            <img src={ICONS.coins} alt="Ventas" class="icon-thumb-sm" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Ventas Hoy</p>
            <p class="text-2xl font-bold text-gray-900">{formatCOP(resumen.total_ventas)}</p>
          </div>
        </div>
      </div>

      <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 gradient-success rounded-xl flex items-center justify-center shadow-lg shadow-emerald-200/50">
            <img src={ICONS.check} alt="Transacciones" class="icon-thumb-sm" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Transacciones</p>
            <p class="text-2xl font-bold text-gray-900">{resumen.num_ventas}</p>
          </div>
        </div>
      </div>

      <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 gradient-warning rounded-xl flex items-center justify-center shadow-lg shadow-amber-200/50">
            <img src={ICONS.oilCan} alt="Items" class="icon-thumb-sm" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Items Vendidos</p>
            <p class="text-2xl font-bold text-gray-900">{resumen.total_items}</p>
          </div>
        </div>
      </div>

      <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 gradient-purple rounded-xl flex items-center justify-center shadow-lg shadow-purple-200/50">
            <img src={ICONS.truck} alt="Entradas" class="icon-thumb-sm" />
          </div>
          <div>
            <p class="text-sm text-gray-500">Entradas Hoy</p>
            <p class="text-2xl font-bold text-gray-900">{resumen.total_entradas}</p>
          </div>
        </div>
      </div>
    </div>

    {#if stockBajo.length > 0}
      <div class="glass-card rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-3">
          <img src={ICONS.alert} alt="Alerta" class="icon-thumb-sm" />
          <h3 class="font-bold text-gray-900">Alerta de Stock Bajo</h3>
        </div>
        <div class="space-y-2">
          {#each stockBajo as item}
            <div class="flex items-center justify-between py-2 px-3 bg-red-50/80 rounded-xl">
              <span class="text-sm font-medium text-gray-700">{item.producto}</span>
              <span class="text-sm font-bold text-red-600">{item.stock} unidades</span>
            </div>
          {/each}
        </div>
      </div>
    {/if}
  {/if}
</div>
