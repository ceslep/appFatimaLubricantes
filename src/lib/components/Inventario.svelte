<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import Table from './ui/Table.svelte';
  import Badge from './ui/Badge.svelte';
  import Button from './ui/Button.svelte';
  import Modal from './ui/Modal.svelte';
  import Icon from './ui/Icon.svelte';

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
      ? inventario.filter((p) =>
          p.producto.toLowerCase().includes(filtro.toLowerCase()) ||
          p.presentacion.toLowerCase().includes(filtro.toLowerCase())
        )
      : inventario
  );

  let conteoBajo = $derived(inventario.filter((i) => i.stock <= 5).length);

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
      const idx = inventario.findIndex((p) => p.producto === item.producto && p.presentacion === item.presentacion);
      if (idx !== -1) inventario[idx].precio_venta = nuevoPrecio;
      editando = '';
    } catch (e: any) {
      alert(e.message);
    }
    guardando = false;
  }

  // ---- Edición de stock (igual que el precio) ----
  let editandoStock = $state('');
  let nuevoStock = $state(0);
  let guardandoStock = $state(false);

  function iniciarEdicionStock(item: ProductoInventario) {
    editandoStock = getClave(item);
    nuevoStock = item.stock;
  }

  function cancelarEdicionStock() {
    editandoStock = '';
    nuevoStock = 0;
  }

  async function guardarStock(item: ProductoInventario) {
    guardandoStock = true;
    try {
      await api.actualizarStock(item.producto, item.presentacion, nuevoStock);
      const idx = inventario.findIndex((p) => p.producto === item.producto && p.presentacion === item.presentacion);
      if (idx !== -1) inventario[idx].stock = nuevoStock;
      editandoStock = '';
    } catch (e: any) {
      alert(e.message);
    }
    guardandoStock = false;
  }

  function colorStock(stock: number): string {
    if (stock <= 0) return 'text-rose-600';
    if (stock <= 5) return 'text-amber-600';
    return 'text-slate-800';
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
      error = 'Complete producto y presentación';
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

  function chipEstado(stock: number) {
    if (stock <= 0) return { variant: 'danger' as const, texto: 'Agotado' };
    if (stock <= 5) return { variant: 'warning' as const, texto: 'Stock bajo' };
    return { variant: 'success' as const, texto: 'Disponible' };
  }
</script>

<div class="space-y-5">
  <!-- Barra de herramientas -->
  <div class="flex flex-col sm:flex-row sm:items-center gap-3">
    <div class="relative flex-1 sm:max-w-md">
      <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
        <Icon name="search" class="w-[18px] h-[18px]" />
      </span>
      <input
        type="text"
        bind:value={filtro}
        placeholder="Buscar producto o presentación…"
        class="input-base pl-10"
      />
    </div>
    <div class="flex items-center gap-2 ml-auto">
      <span class="hidden md:inline-flex chip bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200 normal-case">
        {inventario.length} producto{inventario.length === 1 ? '' : 's'}
        {#if conteoBajo > 0}
          <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
        {/if}
      </span>
      {#if conteoBajo > 0}
        <span class="chip bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">{conteoBajo} con stock bajo</span>
      {/if}
      <button
        onclick={abrirModal}
        class="inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-3.5 py-2.5
          shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
          hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer
          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
      >
        <Icon name="plus" class="w-4 h-4" strokeWidth={2.4} />
        <span class="hidden xs:inline">Nuevo producto</span>
      </button>
    </div>
  </div>

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(5) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="h-3 w-40 rounded bg-slate-200/70"></div>
            <div class="h-3 w-24 rounded bg-slate-200/50 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Producto', 'Presentación', 'Stock', 'Precio de venta', 'Estado']}>
        {#each inventarioFiltrado as item}
          <tr class="hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-[9px] bg-slate-100 text-slate-500 flex items-center justify-center"><Icon name="droplet" class="w-4 h-4" /></span>
                <span class="font-semibold text-[13.5px] text-slate-900">{item.producto}</span>
              </div>
            </td>
            <td class="px-4 py-3.5 text-[13px] text-slate-500">{item.presentacion}</td>
            <td class="px-4 py-3.5">
              {#if editandoStock === getClave(item)}
                <div class="flex items-center gap-1.5">
                  <input type="number" bind:value={nuevoStock} min="0" class="input-base w-24 py-1.5 text-right"
                    onkeydown={(e) => { if (e.key === 'Enter') guardarStock(item); if (e.key === 'Escape') cancelarEdicionStock(); }} />
                  <button onclick={() => guardarStock(item)} disabled={guardandoStock} title="Guardar" class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer"><Icon name="check" class="w-[18px] h-[18px]" strokeWidth={2.2} /></button>
                  <button onclick={cancelarEdicionStock} title="Cancelar" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 transition-colors cursor-pointer"><Icon name="x" class="w-[18px] h-[18px]" strokeWidth={2.2} /></button>
                </div>
              {:else}
                <button onclick={() => iniciarEdicionStock(item)} title="Clic para editar stock"
                  class="group/stock inline-flex items-center gap-1.5 rounded-lg px-2 py-1 -my-1 font-bold text-[13.5px] {colorStock(item.stock)} hover:bg-amber-50 transition-colors cursor-pointer">
                  {item.stock}
                  <Icon name="pencil" class="w-3.5 h-3.5 text-slate-300 group-hover/stock:text-amber-500" />
                </button>
              {/if}
            </td>
            <td class="px-4 py-3.5">
              {#if editando === getClave(item)}
                <div class="flex items-center justify-end gap-1.5">
                  <input type="number" bind:value={nuevoPrecio} min="0" class="input-base w-32 py-1.5 text-right"
                    onkeydown={(e) => { if (e.key === 'Enter') guardarPrecio(item); if (e.key === 'Escape') cancelarEdicion(); }} />
                  <button onclick={() => guardarPrecio(item)} disabled={guardando} title="Guardar" class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer"><Icon name="check" class="w-[18px] h-[18px]" strokeWidth={2.2} /></button>
                  <button onclick={cancelarEdicion} title="Cancelar" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 transition-colors cursor-pointer"><Icon name="x" class="w-[18px] h-[18px]" strokeWidth={2.2} /></button>
                </div>
              {:else}
                <button onclick={() => iniciarEdicion(item)} title="Clic para editar precio"
                  class="group/price flex items-center justify-end gap-1.5 ml-auto rounded-lg px-2 py-1 -my-1 text-[13.5px] font-semibold text-slate-700 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer">
                  {formatCOP(item.precio_venta)}
                  <Icon name="pencil" class="w-3.5 h-3.5 text-slate-300 group-hover/price:text-blue-500" />
                </button>
              {/if}
            </td>
            <td class="px-4 py-3.5"><Badge variant={chipEstado(item.stock).variant} dot>{chipEstado(item.stock).texto}</Badge></td>
          </tr>
        {/each}
        {#if inventarioFiltrado.length === 0}
          <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">{filtro ? 'No se encontraron productos' : 'No hay productos en inventario'}</td></tr>
        {/if}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each inventarioFiltrado as item}
        <div class="panel p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="w-9 h-9 rounded-[11px] bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"><Icon name="droplet" class="w-[18px] h-[18px]" /></span>
              <div class="min-w-0">
                <p class="text-[14px] font-bold text-slate-900 leading-snug">{item.producto}</p>
                <p class="text-[11px] text-slate-400 truncate">{item.presentacion || 'Sin presentación'}</p>
              </div>
            </div>
            <Badge variant={chipEstado(item.stock).variant} dot className="shrink-0">{chipEstado(item.stock).texto}</Badge>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2.5">
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Stock</p>
              {#if editandoStock === getClave(item)}
                <input type="number" bind:value={nuevoStock} min="0" class="input-base w-full py-1 text-right"
                  onkeydown={(e) => { if (e.key === 'Enter') guardarStock(item); if (e.key === 'Escape') cancelarEdicionStock(); }} />
                <div class="flex gap-1 justify-end mt-1">
                  <button onclick={() => guardarStock(item)} disabled={guardandoStock} title="Guardar" class="p-1 rounded-md text-emerald-600 hover:bg-emerald-50 cursor-pointer"><Icon name="check" class="w-4 h-4" strokeWidth={2.4} /></button>
                  <button onclick={cancelarEdicionStock} title="Cancelar" class="p-1 rounded-md text-slate-400 hover:bg-slate-100 cursor-pointer"><Icon name="x" class="w-4 h-4" strokeWidth={2.4} /></button>
                </div>
              {:else}
                <div class="flex items-center justify-between gap-1 mt-0.5">
                  <p class={'text-[17px] font-extrabold leading-tight truncate ' + colorStock(item.stock)}>{item.stock} <span class="text-[10px] font-medium text-slate-400">und</span></p>
                  <button onclick={() => iniciarEdicionStock(item)} aria-label="Editar stock" class="p-1.5 -m-1 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer">
                    <Icon name="pencil" class="w-4 h-4" />
                  </button>
                </div>
              {/if}
            </div>
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Precio venta</p>
              {#if editando === getClave(item)}
                <input type="number" bind:value={nuevoPrecio} min="0" class="input-base w-full py-1 text-right"
                  onkeydown={(e) => { if (e.key === 'Enter') guardarPrecio(item); if (e.key === 'Escape') cancelarEdicion(); }} />
                <div class="flex gap-1 justify-end mt-1">
                  <button onclick={() => guardarPrecio(item)} disabled={guardando} title="Guardar" class="p-1 rounded-md text-emerald-600 hover:bg-emerald-50 cursor-pointer"><Icon name="check" class="w-4 h-4" strokeWidth={2.4} /></button>
                  <button onclick={cancelarEdicion} title="Cancelar" class="p-1 rounded-md text-slate-400 hover:bg-slate-100 cursor-pointer"><Icon name="x" class="w-4 h-4" strokeWidth={2.4} /></button>
                </div>
              {:else}
                <div class="flex items-center justify-between gap-1 mt-0.5">
                  <p class="text-[15px] font-extrabold text-slate-900 leading-tight truncate">{formatCOP(item.precio_venta)}</p>
                  <button onclick={() => iniciarEdicion(item)} aria-label="Editar precio" class="p-1.5 -m-1 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer">
                    <Icon name="pencil" class="w-4 h-4" />
                  </button>
                </div>
              {/if}
            </div>
          </div>
        </div>
      {/each}
      {#if inventarioFiltrado.length === 0}
        <div class="panel px-5 py-10 text-center">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3"><Icon name="search" class="w-6 h-6" strokeWidth={1.5} /></div>
          <p class="text-[13px] text-slate-500">{filtro ? 'No se encontraron productos' : 'No hay productos en inventario'}</p>
        </div>
      {/if}
    </div>
  {/if}
</div>

<Modal show={showModal} title="Nuevo producto" subtitle="Agrega un lubricante al catálogo" onclose={() => (showModal = false)}>
  <div class="space-y-4">
    <div>
      <label class="field-label" for="np-producto">Producto *</label>
      <input id="np-producto" type="text" bind:value={nuevoProducto} placeholder="Nombre del producto" class="input-base" autocomplete="off" />
    </div>
    <div>
      <label class="field-label" for="np-presentacion">Presentación *</label>
      <input id="np-presentacion" type="text" bind:value={nuevaPresentacion} placeholder="Ej: 1 LITRO, 4 LITROS, QT, PT" class="input-base" autocomplete="off" />
    </div>
    <div>
      <label class="field-label" for="np-precio">Precio de venta</label>
      <input id="np-precio" type="number" bind:value={nuevoPrecioVenta} min="0" placeholder="0" class="input-base" />
    </div>
    {#if error}
      <p class="text-[13px] text-rose-600">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => (showModal = false)}>Cancelar</Button>
      <Button variant="primary" disabled={guardandoNuevo} onclick={crearProducto}>
        {guardandoNuevo ? 'Creando…' : 'Crear producto'}
      </Button>
    </div>
  </div>
</Modal>
