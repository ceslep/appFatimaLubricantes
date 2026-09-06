<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario, vistaActual } from '../stores';
  import Button from './ui/Button.svelte';
  import Input from './ui/Input.svelte';
  import Select from './ui/Select.svelte';

  const ICONS = {
    truck: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-31GYuaDQ6tzlmK2MsYTpfwzmJwf9Kr.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    coins: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
    building: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-mlhQNhgVqu1ZxZAGedPgDJKneIlg7o.png',
    clipboard: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
  };

  let producto = $state('');
  let cantidad = $state('');
  let precioCompra = $state('');
  let proveedor = $state('');
  let observaciones = $state('');
  let productos = $state<string[]>([]);
  let error = $state('');
  let success = $state('');
  let loading = $state(false);

  onMount(async () => {
    try {
      const cat = await api.listarProductosCatalogo();
      productos = cat.map(p => p.producto);
    } catch (e) { console.error(e); }
  });

  async function registrar() {
    if (!producto || !cantidad) { error = 'Complete producto y cantidad'; return; }
    loading = true; error = ''; success = '';
    try {
      await api.registrarEntrada({
        producto, cantidad: Number(cantidad), precio_compra: Number(precioCompra || 0),
        proveedor, observaciones, cajero: $usuario?.nombre || '',
      });
      success = 'Entrada registrada exitosamente';
      producto = ''; cantidad = ''; precioCompra = ''; proveedor = ''; observaciones = '';
      setTimeout(() => { success = ''; }, 3000);
    } catch (e: any) { error = e.message; }
    loading = false;
  }
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src={ICONS.truck} alt="Entrada" class="icon-thumb" />
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Registrar Entrada</h1>
        <p class="text-gray-500 text-sm">Ingreso de inventario de lubricantes</p>
      </div>
    </div>
    <Button variant="secondary" onclick={() => vistaActual.set('historial-entradas')}>Ver Historial</Button>
  </div>

  <div class="glass-strong rounded-2xl p-6 max-w-xl">
    <form onsubmit={(e) => { e.preventDefault(); registrar(); }}>
      <div class="space-y-4">
        <div class="flex items-center gap-2">
          <img src={ICONS.oilCan} alt="Producto" class="icon-thumb-sm" />
          <div class="flex-1">
            <Select label="Producto" bind:value={producto} options={productos} placeholder="Seleccione un producto" required />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <Input label="Cantidad" type="number" bind:value={cantidad} placeholder="0" required />
          <div class="flex items-center gap-2">
            <img src={ICONS.coins} alt="Precio" class="icon-thumb-sm" />
            <div class="flex-1">
              <Input label="Precio de Compra" type="number" bind:value={precioCompra} placeholder="0" />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <img src={ICONS.building} alt="Proveedor" class="icon-thumb-sm" />
          <div class="flex-1">
            <Input label="Proveedor" bind:value={proveedor} placeholder="Nombre del proveedor" />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <img src={ICONS.clipboard} alt="Observaciones" class="icon-thumb-sm" />
          <div class="flex-1">
            <Input label="Observaciones" bind:value={observaciones} placeholder="Notas adicionales" />
          </div>
        </div>
      </div>

      {#if error}
        <div class="mt-3 p-3 rounded-xl bg-red-50/80 border border-red-100 text-red-600 text-sm">{error}</div>
      {/if}
      {#if success}
        <div class="mt-3 p-3 rounded-xl bg-emerald-50/80 border border-emerald-100 text-emerald-600 text-sm">{success}</div>
      {/if}

      <div class="mt-6">
        <Button variant="success" disabled={loading}>
          {loading ? 'Registrando...' : 'Registrar Entrada'}
        </Button>
      </div>
    </form>
  </div>
</div>
