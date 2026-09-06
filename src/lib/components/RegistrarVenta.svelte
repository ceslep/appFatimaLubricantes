<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario } from '../stores';
  import { formatCOP } from '../utils';
  import type { ProductoCatalogo } from '../types';
  import Button from './ui/Button.svelte';

  const ICONS = {
    shoppingCart: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-aVMcBoKPxWSQV3iw3xPRFYGVBDZwzN.png',
    oilCan: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-HTkfaRMe4nifQMfQq7vViru0yei2Kh.png',
    user: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-dae1lHATNY8hkh3GxeAnScrtd34Ii5.png',
    car: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eI6n4x6dT5zthnTccmRnV0GX7PJxmW.png',
  };

  interface ProductoTop extends ProductoCatalogo {
    total_vendido: number;
  }

  interface CartItem {
    producto: ProductoCatalogo;
    cantidad: number;
  }

  let topProductos = $state<ProductoTop[]>([]);
  let catalogo = $state<ProductoCatalogo[]>([]);
  let carrito = $state<CartItem[]>([]);
  let cliente = $state('');
  let placa = $state('');
  let mostrarCliente = $state(false);
  let error = $state('');
  let success = $state('');
  let loading = $state(false);
  let loadingProductos = $state(true);
  let busqueda = $state('');
  let mostrarDropdown = $state(false);
  let mostrarExito = $state(false);
  let formaPago = $state('Efectivo');
  let ultimaVenta = $state({ formaPago: '', totalItems: 0, totalCarrito: 0 });

  let productosFiltrados = $derived(
    busqueda
      ? catalogo.filter(p => p.producto.toLowerCase().includes(busqueda.toLowerCase()))
      : catalogo
  );

  let totalCarrito = $derived(carrito.reduce((sum, item) => sum + item.producto.precio_venta * item.cantidad, 0));
  let totalItems = $derived(carrito.reduce((sum, item) => sum + item.cantidad, 0));

  let maxVendido = $derived(topProductos.length > 0 ? Math.max(...topProductos.map(p => p.total_vendido)) : 1);

  onMount(async () => {
    try {
      const [cat, top] = await Promise.all([
        api.listarProductosCatalogo(),
        api.productosMasVendidos(),
      ]);
      catalogo = cat;
      topProductos = top;
    } catch (e) { console.error(e); }
    loadingProductos = false;
  });

  function agregarAlCarrito(p: ProductoCatalogo) {
    const existente = carrito.find(item => item.producto.producto === p.producto);
    if (existente) {
      if (existente.cantidad < existente.producto.stock) {
        existente.cantidad++;
        carrito = [...carrito];
      }
    } else {
      carrito = [...carrito, { producto: p, cantidad: 1 }];
    }
    busqueda = '';
    mostrarDropdown = false;
  }

  function incrementar(index: number) {
    const item = carrito[index];
    if (item && item.cantidad < item.producto.stock) {
      item.cantidad++;
      carrito = [...carrito];
    }
  }

  function decrementar(index: number) {
    const item = carrito[index];
    if (item && item.cantidad > 1) {
      item.cantidad--;
      carrito = [...carrito];
    }
  }

  function eliminarDelCarrito(index: number) {
    carrito = carrito.filter((_, i) => i !== index);
  }

  function onBusquedaInput(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    busqueda = val;
    mostrarDropdown = val.length > 0;
  }

  function onBusquedaFocus() {
    if (busqueda.length > 0) mostrarDropdown = true;
  }

  async function registrar() {
    if (carrito.length === 0) { error = 'Agregue productos al carrito'; return; }
    for (const item of carrito) {
      if (item.cantidad > item.producto.stock) {
        error = `Stock insuficiente para ${item.producto.producto}`;
        return;
      }
    }
    loading = true; error = ''; success = '';
    try {
      const items = carrito.map(item => ({
        producto: item.producto.producto,
        cantidad: item.cantidad,
        precio_unitario: item.producto.precio_venta,
      }));
      await api.registrarVentaMultiple({
        items,
        cliente,
        placa,
        cajero: $usuario?.nombre || '',
        forma_pago: formaPago,
      });
      ultimaVenta = {
        formaPago,
        totalItems,
        totalCarrito,
      };
      mostrarExito = true;
      carrito = [];
      cliente = ''; placa = '';
      mostrarCliente = false;
      try {
        const [cat, top] = await Promise.all([
          api.listarProductosCatalogo(),
          api.productosMasVendidos(),
        ]);
        catalogo = cat;
        topProductos = top;
      } catch {}
      setTimeout(() => { mostrarExito = false; }, 3000);
    } catch (e: any) { error = e.message; }
    loading = false;
  }
</script>

<div class="min-h-[calc(100vh-3rem)] flex flex-col pb-safe">
  <!-- Header -->
  <div class="mb-3 md:mb-4 flex items-center gap-2 md:gap-3">
    <img src={ICONS.shoppingCart} alt="Venta" class="w-8 h-8 md:w-10 md:h-10" />
    <div>
      <h1 class="text-xl md:text-2xl font-bold text-gray-900">Registrar Venta</h1>
      <p class="text-gray-500 text-xs md:text-sm">Estacion de Servicio Fatima</p>
    </div>
  </div>

  <div class="flex-1 flex flex-col lg:grid lg:grid-cols-3 gap-4">
    <!-- Columna izquierda: Productos y búsqueda -->
    <div class="lg:col-span-2 flex flex-col gap-4">
      <!-- Buscador -->
      <div class="relative">
        <input
          type="text"
          value={busqueda}
          oninput={onBusquedaInput}
          onfocus={onBusquedaFocus}
          placeholder="🔍 Buscar producto..."
          class="w-full px-4 py-3 md:py-4 rounded-xl glass-input text-gray-900 text-base md:text-lg
            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
            placeholder:text-gray-400 transition-all"
        />
        {#if mostrarDropdown && productosFiltrados.length > 0}
          <div class="absolute z-30 w-full mt-1 glass-strong rounded-xl shadow-lg max-h-64 md:max-h-80 overflow-y-auto">
            {#each productosFiltrados as p}
              <button
                type="button"
                onclick={() => agregarAlCarrito(p)}
                class="w-full px-4 py-3 md:py-4 text-left hover:bg-blue-50/80 active:bg-blue-100 transition-colors flex items-center justify-between border-b border-gray-100 last:border-0"
              >
                <div class="min-w-0 flex-1">
                  <span class="font-medium text-gray-900 block truncate">{p.producto}</span>
                  <span class="text-xs md:text-sm text-gray-500">{p.presentacion} · Stock: {p.stock}</span>
                </div>
                <span class="text-sm md:text-base font-bold text-blue-700 ml-2 whitespace-nowrap">{formatCOP(p.precio_venta)}</span>
              </button>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Productos acceso rápido -->
      {#if loadingProductos}
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Cargando...</p>
          <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            {#each Array(6) as _}
              <div class="glass-card rounded-xl p-2.5 xs:p-3 md:p-4 animate-pulse h-20 md:h-24"></div>
            {/each}
          </div>
        </div>
      {:else if topProductos.length > 0}
        <div>
          <div class="flex items-center gap-2 mb-2">
            <img src={ICONS.oilCan} alt="" class="w-4 h-4" />
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Acceso rápido</p>
          </div>
          <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            {#each topProductos as p}
              <button
                type="button"
                onclick={() => agregarAlCarrito(p)}
                disabled={p.stock <= 0}
                class="group relative glass-card rounded-xl p-2.5 xs:p-3 md:p-4 text-left hover:border-blue-300 active:scale-[0.97] transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <div class="font-semibold text-xs md:text-sm text-gray-900 leading-tight group-hover:text-blue-700 line-clamp-2">{p.producto}</div>
                <div class="text-[10px] md:text-xs text-gray-500 mt-0.5 truncate">{p.presentacion}</div>
                <div class="text-xs md:text-sm font-bold text-blue-700 mt-1">{formatCOP(p.precio_venta)}</div>
                {#if p.stock <= 0}
                  <div class="absolute top-2 right-2 px-1.5 py-0.5 bg-red-100 text-red-600 text-[9px] rounded-full font-medium">Agotado</div>
                {:else if p.stock <= 5}
                  <div class="absolute top-2 right-2 px-1.5 py-0.5 bg-amber-100 text-amber-600 text-[9px] rounded-full font-medium">Stock: {p.stock}</div>
                {/if}
              </button>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Columna derecha: Carrito y pago -->
    <div class="lg:col-span-1 flex flex-col">
      <div class="glass-strong rounded-2xl p-4 md:p-6 flex-1 flex flex-col">
        {#if carrito.length > 0}
          <!-- Header carrito -->
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <img src={ICONS.shoppingCart} alt="" class="w-5 h-5" />
              <span class="font-medium text-gray-900 text-sm md:text-base">Carrito</span>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">{totalItems} items</span>
          </div>

          <!-- Lista carrito (scrollable en móvil) -->
          <div class="flex-1 overflow-y-auto max-h-48 md:max-h-64 mb-3 space-y-2">
            {#each carrito as item, i}
              <div class="glass-card rounded-xl p-3">
                <!-- Fila superior: nombre y eliminar -->
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div class="min-w-0 flex-1">
                    <div class="font-medium text-sm text-gray-900 truncate">{item.producto.producto}</div>
                    <div class="text-[10px] md:text-xs text-gray-500">{item.producto.presentacion} · {formatCOP(item.producto.precio_venta)}</div>
                  </div>
                  <button
                    type="button"
                    onclick={() => eliminarDelCarrito(i)}
                    class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors flex-shrink-0"
                    aria-label="Eliminar"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <!-- Fila inferior: controles cantidad y subtotal -->
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-1">
                    <button
                      type="button"
                      onclick={() => decrementar(i)}
                      disabled={item.cantidad <= 1}
                      class="w-9 h-9 md:w-10 md:h-10 rounded-lg glass-btn hover:bg-gray-200/80 active:bg-gray-300 disabled:opacity-30 flex items-center justify-center text-lg font-bold transition-all"
                    >-</button>
                    <span class="w-10 text-center text-base md:text-lg font-bold">{item.cantidad}</span>
                    <button
                      type="button"
                      onclick={() => incrementar(i)}
                      disabled={item.cantidad >= item.producto.stock}
                      class="w-9 h-9 md:w-10 md:h-10 rounded-lg glass-btn hover:bg-gray-200/80 active:bg-gray-300 disabled:opacity-30 flex items-center justify-center text-lg font-bold transition-all"
                    >+</button>
                  </div>
                  <div class="text-sm md:text-base font-bold text-blue-700">{formatCOP(item.producto.precio_venta * item.cantidad)}</div>
                </div>
              </div>
            {/each}
          </div>

          <!-- Total -->
          <div class="gradient-success rounded-xl p-3 md:p-4 mb-3">
            <p class="text-xs text-emerald-100 font-medium">Total</p>
            <p class="text-3xl md:text-4xl font-bold text-white">{formatCOP(totalCarrito)}</p>
          </div>

          <!-- Datos cliente (colapsable) -->
          <div class="mb-3">
            <button
              type="button"
              onclick={() => mostrarCliente = !mostrarCliente}
              class="w-full flex items-center justify-between py-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Cliente (opcional)
              </span>
              <svg class="w-4 h-4 {mostrarCliente ? 'rotate-180' : ''} transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            {#if mostrarCliente}
              <div class="space-y-2 mt-2">
                <input type="text" bind:value={cliente} placeholder="Nombre del cliente"
                  class="w-full px-3 py-2.5 rounded-xl glass-input text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all" />
                <input type="text" bind:value={placa} placeholder="Placa del vehículo"
                  class="w-full px-3 py-2.5 rounded-xl glass-input text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all" />
              </div>
            {/if}
          </div>

          <!-- Forma de pago -->
          <div class="mb-3">
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Forma de pago</label>
            <div class="grid grid-cols-3 gap-1.5">
              {#each ['Efectivo', 'Tarjeta', 'Transferencia'] as fp}
                <button
                  type="button"
                  onclick={() => formaPago = fp}
                  class="py-3 px-2 rounded-xl text-xs md:text-sm font-medium transition-all duration-150 active:scale-95 {formaPago === fp ? 'gradient-primary text-white shadow-lg' : 'glass-card text-gray-700 hover:border-blue-300'}"
                >
                  {#if fp === 'Efectivo'}
                    💵
                  {:else if fp === 'Tarjeta'}
                    💳
                  {:else}
                    🏦
                  {/if}
                  <span class="block mt-0.5">{fp}</span>
                </button>
              {/each}
            </div>
          </div>

          <!-- Mensajes -->
          {#if error}
            <div class="p-3 rounded-xl bg-red-50/80 border border-red-100 text-red-600 text-sm mb-3">{error}</div>
          {/if}

          <!-- Botón registrar -->
          <div class="mt-auto pt-2">
            <button
              type="button"
              onclick={registrar}
              disabled={loading}
              class="w-full py-4 md:py-5 rounded-xl gradient-success text-white text-lg font-bold
                hover:opacity-90 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed
                transition-all duration-150 shadow-lg"
            >
              {loading ? 'Registrando...' : `✓ Registrar Venta`}
            </button>
          </div>

        {:else}
          <!-- Estado vacío -->
          <div class="flex-1 flex flex-col items-center justify-center text-center py-8">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-3">
              <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
            <p class="text-gray-400 text-sm">Seleccione productos para comenzar</p>
          </div>
        {/if}
      </div>
    </div>
  </div>
</div>

<!-- Modal éxito -->
{#if mostrarExito}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="glass-strong rounded-3xl p-6 md:p-8 w-full max-w-sm text-center animate-in zoom-in duration-200">
      <div class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-4 rounded-full gradient-success flex items-center justify-center">
        <svg class="w-8 h-8 md:w-10 md:h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">¡Venta Registrada!</h2>
      <p class="text-gray-500 text-sm mb-4">La venta se procesó exitosamente</p>
      <div class="glass-card rounded-xl p-4 mb-6 text-left space-y-2">
        <div class="flex justify-between text-sm">
          <span class="text-gray-500">Forma de pago:</span>
          <span class="font-semibold text-gray-900">{ultimaVenta.formaPago}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span class="text-gray-500">Items:</span>
          <span class="font-semibold text-gray-900">{ultimaVenta.totalItems}</span>
        </div>
        <div class="flex justify-between text-sm">
          <span class="text-gray-500">Total:</span>
          <span class="font-bold text-blue-700">{formatCOP(ultimaVenta.totalCarrito)}</span>
        </div>
      </div>
      <button
        type="button"
        onclick={() => mostrarExito = false}
        class="w-full py-3 md:py-4 rounded-xl gradient-primary text-white font-semibold hover:opacity-90 active:scale-[0.98] transition-all"
      >
        Aceptar
      </button>
    </div>
  </div>
{/if}

<style>
  .pb-safe {
    padding-bottom: env(safe-area-inset-bottom, 1rem);
  }
</style>
