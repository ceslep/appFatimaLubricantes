<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Table from './ui/Table.svelte';
  import Badge from './ui/Badge.svelte';
  import Button from './ui/Button.svelte';
  import Modal from './ui/Modal.svelte';

  const ICONS = {
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    plus: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-aVMcBoKPxWSQV3iw3xPRFYGVBDZwzN.png',
  };

  interface ProductoInventario {
    producto: string;
    presentacion: string;
    stock: number;
    precio_venta: number;
  }

  let inventario = $state<ProductoInventario[]>([]);
  let loading = $state(true);
  let filtro = $state('');
  let editando = $state('');
  let nuevoPrecio = $state(0);
  let guardando = $state(false);

  let showModal = $state(false);
  let nuevoProducto = $state('');
  let nuevaPresentacion = $state('');
  let nuevoPrecioVenta = $state(0);
  let error = $state('');
  let guardandoNuevo = $state(false);

  let inventarioFiltrado = $derived(
    filtro
      ? inventario.filter(p =>
          p.producto.toLowerCase().includes(filtro.toLowerCase()) ||
          p.presentacion.toLowerCase().includes(filtro.toLowerCase())
        )
      : inventario
  );

  onMount(async () => {
    try { inventario = await api.inventario(); } catch (e) { console.error(e); }
    loading = false;
  });

  function iniciarEdicion(item: ProductoInventario) {
    editando = item.producto + '|' + item.presentacion;
    nuevoPrecio = item.precio_venta;
  }

  function cancelarEdicion() {
    editando = '';
    nuevoPrecio = 0;
  }

  function getClave(item: ProductoInventario) {
    return item.producto + '|' + item.presentacion;
  }

  async function guardarPrecio(item: ProductoInventario) {
    guardando = true;
    try {
      await api.actualizarPrecio(item.producto, item.presentacion, nuevoPrecio);
      const idx = inventario.findIndex(p => p.producto === item.producto && p.presentacion === item.presentacion);
      if (idx !== -1) inventario[idx].precio_venta = nuevoPrecio;
      editando = '';
    } catch (e: any) {
      alert(e.message);
    }
    guardando = false;
  }

  function abrirModal() {
    nuevoProducto = '';
    nuevaPresentacion = '';
    nuevoPrecioVenta = 0;
    error = '';
    showModal = true;
  }

  async function crearProducto() {
    if (!nuevoProducto || !nuevaPresentacion) {
      error = 'Complete producto y presentacion';
      return;
    }
    guardandoNuevo = true;
    error = '';
    try {
      await api.registrarProducto({
        producto: nuevoProducto,
        presentacion: nuevaPresentacion,
        precio_venta: Number(nuevoPrecioVenta),
      });
      inventario = await api.inventario();
      showModal = false;
    } catch (e: any) {
      error = e.message;
    }
    guardandoNuevo = false;
  }
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src={ICONS.oilCan} alt="Inventario" class="icon-thumb" />
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Inventario</h1>
        <p class="text-gray-500 text-sm">{inventario.length} productos</p>
      </div>
    </div>
    <Button variant="primary" onclick={abrirModal}>+ Nuevo Producto</Button>
  </div>

  <div class="relative">
    <input
      type="text"
      bind:value={filtro}
      placeholder="Buscar producto..."
      class="w-full px-4 py-3 rounded-xl glass-input text-gray-900 text-base
        focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
        placeholder:text-gray-400 transition-all duration-200"
    />
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
  {:else}
    <div class="glass-strong rounded-2xl overflow-hidden">
      <Table headers={['Producto', 'Presentacion', 'Stock', 'Precio Venta', 'Estado']}>
        {#each inventarioFiltrado as item}
          <tr class="hover:bg-white/50 transition-colors">
            <td class="px-4 py-3 font-medium text-gray-900">{item.producto}</td>
            <td class="px-4 py-3 text-gray-600">{item.presentacion}</td>
            <td class="px-4 py-3 font-bold text-gray-900 text-right">{item.stock}</td>
            <td class="px-4 py-3 text-right">
              {#if editando === getClave(item)}
                <div class="flex items-center justify-end gap-1">
                  <input
                    type="number"
                    bind:value={nuevoPrecio}
                    class="w-28 px-2 py-1 text-right rounded-lg glass-input text-gray-900 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500"
                    onkeydown={(e) => { if (e.key === 'Enter') guardarPrecio(item); if (e.key === 'Escape') cancelarEdicion(); }}
                  />
                  <button
                    onclick={() => guardarPrecio(item)}
                    disabled={guardando}
                    class="text-emerald-600 hover:text-emerald-700 p-1"
                    title="Guardar"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </button>
                  <button
                    onclick={cancelarEdicion}
                    class="text-red-400 hover:text-red-600 p-1"
                    title="Cancelar"
                  >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              {:else}
                <button
                  onclick={() => iniciarEdicion(item)}
                  class="text-gray-600 hover:text-blue-600 hover:underline cursor-pointer transition-colors"
                  title="Clic para editar precio"
                >
                  $ {item.precio_venta.toLocaleString('es-CO')}
                </button>
              {/if}
            </td>
            <td class="px-4 py-3">
              {#if item.stock <= 0}
                <Badge variant="danger">Agotado</Badge>
              {:else if item.stock <= 5}
                <Badge variant="warning">Bajo</Badge>
              {:else}
                <Badge variant="success">Disponible</Badge>
              {/if}
            </td>
          </tr>
        {/each}
        {#if inventarioFiltrado.length === 0}
          <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">
            {filtro ? 'No se encontraron productos' : 'No hay productos en inventario'}
          </td></tr>
        {/if}
      </Table>
    </div>
  {/if}
</div>

<Modal show={showModal} title="Nuevo Producto" onclose={() => showModal = false}>
  <div class="space-y-4">
    <div class="flex items-center gap-2">
      <img src={ICONS.oilCan} alt="" class="icon-thumb-sm" />
      <div class="flex-1">
        <label class="block text-sm font-medium text-gray-700 mb-1">Producto *</label>
        <input
          type="text"
          bind:value={nuevoProducto}
          placeholder="Nombre del producto"
          class="w-full px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
        />
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Presentacion *</label>
      <input
        type="text"
        bind:value={nuevaPresentacion}
        placeholder="Ej: 1 LITRO, 4 LITROS, QT, PT"
        class="w-full px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
      />
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Precio de Venta</label>
      <input
        type="number"
        bind:value={nuevoPrecioVenta}
        placeholder="0"
        class="w-full px-4 py-2.5 rounded-xl glass-input text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
      />
    </div>
    {#if error}
      <p class="text-red-500 text-sm">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => showModal = false}>Cancelar</Button>
      <Button variant="primary" disabled={guardandoNuevo} onclick={crearProducto}>
        {guardandoNuevo ? 'Creando...' : 'Crear Producto'}
      </Button>
    </div>
  </div>
</Modal>
