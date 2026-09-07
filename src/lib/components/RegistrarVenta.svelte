<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario } from '../stores';
  import { formatCOP, getFechaActual } from '../utils';
  import type { Cliente, ProductoCatalogo } from '../types';
  import Icon from './ui/Icon.svelte';
  import CartResumen from './ui/CartResumen.svelte';

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
  let placa = $state('');
  let clienteDoc = $state('');
  let clienteNombre = $state('');
  let clienteTelefono = $state('');
  let clientes = $state<Cliente[]>([]);
  let clienteObligatorio = $state(false);
  let error = $state('');
  let loading = $state(false);
  let loadingProductos = $state(true);
  let busqueda = $state('');
  let mostrarDropdown = $state(false);
  let mostrarExito = $state(false);
  let formaPago = $state('Efectivo');
  let ultimaVenta = $state<{ formaPago: string; totalItems: number; totalCarrito: number; items: { producto: string; presentacion?: string; cantidad: number; subtotal: number }[] }>({ formaPago: '', totalItems: 0, totalCarrito: 0, items: [] });
  let hojaAbierta = $state(false);
  let confWhatsapp = $state(false);
  let telefono = $state('');
  let modalCliente = $state(false);
  let guardandoCliente = $state(false);
  let errorCliente = $state('');
  let formCliente = $state({ identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '' });

  let productosFiltrados = $derived(
    busqueda
      ? catalogo.filter((p) => p.producto.toLowerCase().includes(busqueda.toLowerCase()))
      : catalogo
  );

  let totalCarrito = $derived(carrito.reduce((sum, item) => sum + item.producto.precio_venta * item.cantidad, 0));
  let totalItems = $derived(carrito.reduce((sum, item) => sum + item.cantidad, 0));

  const metodosPago = [
    { id: 'Efectivo', icon: 'banknote' },
    { id: 'Tarjeta', icon: 'card' },
    { id: 'Transferencia', icon: 'landmark' },
  ];

  onMount(async () => {
    try {
      const [cat, top] = await Promise.all([
        api.listarProductosCatalogo(),
        api.productosMasVendidos(),
      ]);
      catalogo = cat;
      topProductos = top;
    } catch (e) { console.error(e); }
    try {
      const cfg = await api.obtenerConfig();
      confWhatsapp = cfg.enviar_whatsapp === 'TRUE';
      clienteObligatorio = cfg.cliente_obligatorio === 'TRUE';
    } catch (e) { console.error(e); }
    try {
      clientes = await api.listarClientes();
    } catch (e) { console.error(e); }
    loadingProductos = false;
  });

  function claveItem(p: ProductoCatalogo): string {
    return (p.producto || '').trim().toLowerCase() + '|' + (p.presentacion || '').trim().toLowerCase();
  }

  function agregarAlCarrito(p: ProductoCatalogo) {
    const existente = carrito.find((item) => claveItem(item.producto) === claveItem(p));
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

  function chipStock(stock: number): string {
    if (stock <= 0) return 'bg-rose-50 text-rose-700 ring-rose-200';
    if (stock <= 5) return 'bg-amber-50 text-amber-700 ring-amber-200';
    return 'bg-slate-50 text-slate-500 ring-slate-200';
  }

  function textoStock(stock: number): string {
    if (stock <= 0) return 'Agotado';
    return stock + ' disp.';
  }

  function cerrarExito() {
    mostrarExito = false;
    telefono = '';
  }

  function clienteCambio(c: { identificacion: string; nombres: string; telefono: string; placa1?: string }) {
    // Si el recibo por WhatsApp está activo, precargar el número registrado del cliente
    if (confWhatsapp && c.telefono) {
      telefono = c.telefono;
    }
    // Con "cliente obligatorio" activo, usar la Placa 1 del cliente por defecto (o vaciar si no tiene)
    if (clienteObligatorio) {
      placa = c.placa1 || '';
    }
  }

  function abrirModalCliente() {
    formCliente = { identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '' };
    errorCliente = '';
    modalCliente = true;
  }

  async function guardarClienteNuevo() {
    if (!formCliente.identificacion.trim() || !formCliente.nombres.trim() || !formCliente.telefono.trim()) {
      errorCliente = 'Identificación, nombres y teléfono son requeridos';
      return;
    }
    guardandoCliente = true;
    errorCliente = '';
    try {
      await api.registrarCliente(formCliente);
      clientes = await api.listarClientes();
      const nuevo = clientes.find((c) => c.identificacion.trim().toLowerCase() === formCliente.identificacion.trim().toLowerCase());
      if (nuevo) {
        clienteDoc = nuevo.identificacion;
        clienteNombre = nuevo.nombres;
        clienteTelefono = nuevo.telefono;
        clienteCambio(nuevo);
      }
      modalCliente = false;
    } catch (e: any) {
      errorCliente = e.message || 'Error al registrar el cliente';
    }
    guardandoCliente = false;
  }

  function normalizarTelefono(raw: string): string {
    let d = (raw || '').replace(/\D/g, '');
    if (d.startsWith('00')) d = d.slice(2);
    if (d.length === 10) d = '57' + d;
    if (d.length === 11 && d.startsWith('0')) d = '57' + d.slice(1);
    return d;
  }

  function telefonoValido(): boolean {
    const n = normalizarTelefono(telefono);
    return n.length >= 12 && n.length <= 13;
  }

  function textoRecibo(): string {
    const lineas: string[] = [];
    lineas.push('*ESTACIÓN FÁTIMA · LUBRICANTES*');
    lineas.push('Recibo de venta');
    lineas.push('Fecha: ' + getFechaActual());
    lineas.push('Cajero: ' + ($usuario?.nombre || ''));
    lineas.push('Pago: ' + ultimaVenta.formaPago);
    lineas.push('──────────────────');
    for (const it of ultimaVenta.items) {
      const detalle = it.producto + (it.presentacion ? ' · ' + it.presentacion : '');
      lineas.push('• ' + detalle + ' x' + it.cantidad + ' … ' + formatCOP(it.subtotal));
    }
    lineas.push('──────────────────');
    lineas.push('TOTAL: ' + formatCOP(ultimaVenta.totalCarrito));
    lineas.push('');
    lineas.push('¡Gracias por tu compra!');
    return lineas.join('\n');
  }

  function urlWhatsapp(): string {
    return 'https://wa.me/' + normalizarTelefono(telefono) + '?text=' + encodeURIComponent(textoRecibo());
  }

  function teclaGlobal(e: KeyboardEvent) {
    if (e.key !== 'Escape') return;
    if (modalCliente) { modalCliente = false; return; }
    if (mostrarExito) { cerrarExito(); return; }
    if (hojaAbierta) { hojaAbierta = false; }
  }

  async function registrar() {
    if (carrito.length === 0) { error = 'Agregue productos al carrito'; return; }
    if (clienteObligatorio && !clienteDoc) {
      error = 'Selecciona un cliente para registrar la venta';
      return;
    }
    for (const item of carrito) {
      if (item.cantidad > item.producto.stock) {
        error = 'Stock insuficiente para ' + item.producto.producto;
        return;
      }
    }
    loading = true; error = '';
    try {
      const items = carrito.map((item) => ({
        producto: item.producto.producto,
        presentacion: item.producto.presentacion || '',
        cantidad: item.cantidad,
        precio_unitario: item.producto.precio_venta,
      }));
      await api.registrarVentaMultiple({
        items,
        cliente: clienteNombre,
        cliente_doc: clienteDoc || undefined,
        placa,
        cajero: $usuario?.nombre || '',
        forma_pago: formaPago,
      });
      const filas = carrito.map((item) => ({
        producto: item.producto.producto,
        presentacion: item.producto.presentacion || '',
        cantidad: item.cantidad,
        subtotal: item.producto.precio_venta * item.cantidad,
      }));
      ultimaVenta = {
        formaPago,
        totalItems,
        totalCarrito,
        items: filas,
      };
      hojaAbierta = false;
      mostrarExito = true;
      carrito = [];
      clienteDoc = ''; clienteNombre = ''; clienteTelefono = '';
      placa = '';
      try {
        const [cat, top] = await Promise.all([
          api.listarProductosCatalogo(),
          api.productosMasVendidos(),
        ]);
        catalogo = cat;
        topProductos = top;
      } catch {}
      if (!confWhatsapp) {
        setTimeout(() => { mostrarExito = false; telefono = ''; }, 4000);
      }
    } catch (e: any) { error = e.message; }
    loading = false;
  }
</script>

<svelte:window onkeydown={teclaGlobal} />

<div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_400px] gap-5 items-start {carrito.length > 0 ? 'pb-28 lg:pb-0' : ''}">
  <!-- Columna izquierda: productos -->
  <div class="min-w-0 space-y-5">
    <!-- Buscador -->
    <div class="relative z-30">
      <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
        <Icon name="search" class="w-5 h-5" />
      </span>
      <input
        type="text"
        value={busqueda}
        oninput={onBusquedaInput}
        onfocus={onBusquedaFocus}
        onkeydown={(e) => { if (e.key === 'Escape') mostrarDropdown = false; }}
        placeholder="Buscar producto por nombre…"
        aria-label="Buscar producto"
        autocomplete="off"
        class="input-base h-[52px] pl-12 pr-4 text-[15px] rounded-2xl shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
      />
      {#if mostrarDropdown}
        <div class="absolute inset-x-0 mt-2 bg-white rounded-2xl shadow-[0_16px_48px_-16px_rgba(15,23,42,0.28)] ring-1 ring-slate-900/5 max-h-80 overflow-y-auto scrollbar-thin">
          {#if productosFiltrados.length > 0}
            {#each productosFiltrados as p}
              <button
                type="button"
                onclick={() => agregarAlCarrito(p)}
                disabled={p.stock <= 0}
                class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left hover:bg-blue-50/50 transition-colors border-b border-slate-50 last:border-0 disabled:opacity-45 disabled:cursor-not-allowed cursor-pointer"
              >
                <div class="min-w-0 flex-1">
                  <span class="font-semibold text-[13.5px] text-slate-900 block truncate">{p.producto}</span>
                  <span class="text-[12px] text-slate-500">{p.presentacion} &middot; {textoStock(p.stock)}</span>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                  <span class="font-bold text-[14px] text-slate-900">{formatCOP(p.precio_venta)}</span>
                  <span class="w-7 h-7 rounded-full {p.stock <= 0 ? 'bg-slate-100 text-slate-300' : 'bg-blue-600 text-white'} flex items-center justify-center shadow-sm">
                    <Icon name="plus" class="w-4 h-4" strokeWidth={2.4} />
                  </span>
                </div>
              </button>
            {/each}
          {:else}
            <p class="px-4 py-8 text-center text-[13px] text-slate-400">Sin resultados para &ldquo;{busqueda}&rdquo;</p>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Acceso rápido -->
    {#if loadingProductos}
      <div>
        <p class="field-label uppercase text-[10.5px] tracking-[0.12em] !mb-2">Cargando productos…</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
          {#each Array(8) as _}
            <div class="panel h-[104px] animate-pulse"></div>
          {/each}
        </div>
      </div>
    {:else if topProductos.length > 0}
      <div>
        <p class="field-label uppercase text-[10.5px] tracking-[0.12em] !mb-2.5">Acceso rápido</p>
        <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
          {#each topProductos as p}
            <button
              type="button"
              onclick={() => agregarAlCarrito(p)}
              disabled={p.stock <= 0}
              class="group relative panel panel-hover rounded-2xl p-3 sm:p-3.5 text-left flex flex-col gap-1.5
                disabled:opacity-55 disabled:pointer-events-none cursor-pointer
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
            >
              <div class="flex items-start justify-between gap-2">
                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-[10px] bg-slate-100 text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors flex items-center justify-center">
                  <Icon name="droplet" class="w-4 h-4" />
                </span>
                <span class="chip ring-1 ring-inset {chipStock(p.stock)}">{textoStock(p.stock)}</span>
              </div>
              <span class="font-semibold text-[12.5px] sm:text-[13px] leading-snug text-slate-900 line-clamp-2 mt-0.5">{p.producto}</span>
              <span class="text-[10.5px] sm:text-[11px] text-slate-400 truncate">{p.presentacion}</span>
              <span class="font-bold text-[14px] sm:text-[15px] text-blue-700 mt-auto pt-1">{formatCOP(p.precio_venta)}</span>
            </button>
          {/each}
        </div>
      </div>
    {:else}
      <div class="panel rounded-2xl border-dashed p-8 text-center">
        <p class="text-[13.5px] text-slate-500">Usa el buscador para agregar productos a la venta.</p>
      </div>
    {/if}
  </div>

  <!-- Columna derecha: carrito (solo escritorio) -->
  <div class="hidden lg:block">
    <div class="lg:sticky lg:top-0">
      <CartResumen
        variant="card"
        items={carrito}
        {clientes}
        clienteObligatorio={clienteObligatorio}
        bind:clienteDoc
        bind:clienteNombre
        bind:clienteTelefono
        bind:placa
        bind:formaPago
        preguntarWhatsapp={confWhatsapp}
        bind:telefono
        {error}
        {loading}
        onIncrement={incrementar}
        onDecrement={decrementar}
        onRemove={eliminarDelCarrito}
        onRegistrar={registrar}
        onNuevoCliente={abrirModalCliente}
        onClienteCambio={clienteCambio}
      />
    </div>
  </div>
</div>

<!-- Barra inferior (móvil/tablet): abre la hoja de pago -->
{#if carrito.length > 0}
  <div class="fixed bottom-0 inset-x-0 z-40 lg:hidden px-4 pt-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] bg-white/90 backdrop-blur-xl border-t border-slate-200/80 shadow-[0_-8px_30px_-12px_rgba(15,23,42,0.18)]">
    <div class="flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 text-white px-4 py-2.5 shadow-lg">
      <div class="min-w-0 leading-tight flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{totalItems} item{totalItems === 1 ? '' : 's'}</p>
        <p class="text-[19px] font-bold tracking-tight truncate">{formatCOP(totalCarrito)}</p>
      </div>
      <button
        type="button"
        onclick={() => (hojaAbierta = true)}
        class="shrink-0 inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white text-[14px] font-bold px-4 py-3
          hover:bg-blue-500 active:scale-[0.98] transition-all cursor-pointer
          shadow-[0_6px_16px_-8px_rgba(59,130,246,0.9)]"
      >
        <Icon name="receipt" class="w-[18px] h-[18px]" />
        Ir a pagar
      </button>
    </div>
  </div>
{/if}

<!-- Hoja inferior de resumen y pago (móvil/tablet) -->
{#if hojaAbierta && carrito.length > 0}
  <div class="fixed inset-0 z-50 flex flex-col justify-end lg:hidden">
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" role="presentation" onclick={() => (hojaAbierta = false)}></div>

    <div class="relative bg-white rounded-t-[24px] shadow-2xl max-h-[92dvh] flex flex-col overflow-hidden">
      <div class="shrink-0 flex justify-center pt-2.5 pb-1">
        <span class="w-10 h-1 rounded-full bg-slate-200"></span>
      </div>
      <div class="shrink-0 flex items-center justify-between px-5 pb-2">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-[10px] bg-blue-50 text-blue-600 flex items-center justify-center">
            <Icon name="cart" class="w-[17px] h-[17px]" />
          </div>
          <h2 class="text-[15px] font-bold text-slate-900 tracking-tight">Resumen y pago</h2>
        </div>
        <button
          type="button"
          onclick={() => (hojaAbierta = false)}
          aria-label="Cerrar resumen"
          class="p-1.5 -m-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <Icon name="x" class="w-5 h-5" />
        </button>
      </div>
      <div class="flex-1 overflow-y-auto scrollbar-thin px-5 pt-1 pb-[max(env(safe-area-inset-bottom),1.25rem)]">
        <CartResumen
          variant="sheet"
          items={carrito}
          {clientes}
          clienteObligatorio={clienteObligatorio}
          bind:clienteDoc
          bind:clienteNombre
          bind:clienteTelefono
          bind:placa
          bind:formaPago
          preguntarWhatsapp={confWhatsapp}
          bind:telefono
          {error}
          {loading}
          onIncrement={incrementar}
          onDecrement={decrementar}
          onRemove={eliminarDelCarrito}
          onRegistrar={registrar}
          onNuevoCliente={abrirModalCliente}
          onClienteCambio={clienteCambio}
        />
      </div>
    </div>
  </div>
{/if}

<!-- Modal nuevo cliente -->
{#if modalCliente}
  <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" role="presentation" onclick={() => (modalCliente = false)}></div>
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl ring-1 ring-slate-900/5 max-h-[90vh] overflow-y-auto scrollbar-thin p-6">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h3 class="text-[17px] font-bold text-slate-900 tracking-tight">Nuevo cliente</h3>
          <p class="text-[12px] text-slate-500 mt-0.5">Se guardará para futuras ventas y recibos.</p>
        </div>
        <button type="button" onclick={() => (modalCliente = false)} aria-label="Cerrar"
          class="p-1.5 -m-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">
          <Icon name="x" class="w-5 h-5" />
        </button>
      </div>
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="field-label" for="rv-cl-id">Identificación *</label>
            <input id="rv-cl-id" type="text" bind:value={formCliente.identificacion} placeholder="Cédula o NIT" class="input-base" autocomplete="off" />
          </div>
          <div>
            <label class="field-label" for="rv-cl-tel">Teléfono / WhatsApp *</label>
            <input id="rv-cl-tel" type="tel" inputmode="numeric" bind:value={formCliente.telefono} placeholder="Ej: 3001234567" class="input-base" autocomplete="tel" />
          </div>
        </div>
        <div>
          <label class="field-label" for="rv-cl-nom">Nombres completos *</label>
          <input id="rv-cl-nom" type="text" bind:value={formCliente.nombres} placeholder="Nombre y apellido" class="input-base" autocomplete="off" />
        </div>
        <div>
          <label class="field-label" for="rv-cl-cor">Correo electrónico</label>
          <input id="rv-cl-cor" type="email" bind:value={formCliente.correo} placeholder="cliente@correo.com" class="input-base" autocomplete="off" />
        </div>
        {#if errorCliente}
          <p class="text-[13px] text-rose-600">{errorCliente}</p>
        {/if}
        <div class="flex gap-3 justify-end pt-1">
          <button type="button" onclick={() => (modalCliente = false)}
            class="inline-flex items-center justify-center rounded-[10px] bg-white text-slate-700 ring-1 ring-inset ring-slate-300/80 px-4 py-2.5 text-[13.5px] font-semibold hover:bg-slate-50 active:scale-[0.98] transition-all cursor-pointer">
            Cancelar
          </button>
          <button type="button" onclick={guardarClienteNuevo} disabled={guardandoCliente}
            class="inline-flex items-center justify-center gap-2 rounded-[10px] bg-blue-600 text-white px-4 py-2.5 text-[13.5px] font-bold hover:bg-blue-700 active:scale-[0.98] disabled:opacity-60 disabled:pointer-events-none transition-all cursor-pointer">
            {#if guardandoCliente}Guardando…{:else}<Icon name="check" class="w-4 h-4" strokeWidth={2.4} />Guardar cliente{/if}
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Modal de éxito -->
{#if mostrarExito}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[3px]" role="presentation" onclick={cerrarExito}></div>
    <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl ring-1 ring-slate-900/5 p-7 text-center">
      <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
        <Icon name="circle-check" class="w-9 h-9" strokeWidth={2} />
      </div>
      <h2 class="text-[20px] font-bold text-slate-900 tracking-tight">¡Venta registrada!</h2>
      <p class="text-[13px] text-slate-500 mt-1.5">La venta se procesó correctamente.</p>

      <div class="mt-5 rounded-2xl bg-slate-50 ring-1 ring-inset ring-slate-100 divide-y divide-slate-100 text-left overflow-hidden">
        <div class="flex justify-between px-4 py-3 text-[13px]">
          <span class="text-slate-500">Forma de pago</span>
          <span class="font-semibold text-slate-800 flex items-center gap-1.5">
            {#each metodosPago as mp}
              {#if mp.id === ultimaVenta.formaPago}
                <Icon name={mp.icon} class="w-4 h-4 text-slate-500" />
              {/if}
            {/each}
            {ultimaVenta.formaPago}
          </span>
        </div>
        <div class="flex justify-between px-4 py-3 text-[13px]">
          <span class="text-slate-500">Items</span>
          <span class="font-semibold text-slate-800">{ultimaVenta.totalItems}</span>
        </div>
        <div class="flex justify-between px-4 py-3 text-[13px] bg-emerald-50/60">
          <span class="font-semibold text-emerald-700">Total</span>
          <span class="font-bold text-emerald-700 text-[15px]">{formatCOP(ultimaVenta.totalCarrito)}</span>
        </div>
      </div>

      {#if confWhatsapp}
        <div class="mt-4 text-left rounded-2xl bg-emerald-50/70 ring-1 ring-inset ring-emerald-100 p-3.5">
          <div class="flex items-center gap-2 mb-2.5">
            <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
              <Icon name="message" class="w-4 h-4" />
            </span>
            <div>
              <p class="text-[13px] font-bold text-slate-800 leading-tight">Enviar recibo por WhatsApp</p>
              <p class="text-[11px] text-slate-400">Se abrirá WhatsApp con el recibo listo para enviar.</p>
            </div>
          </div>
          <input
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            bind:value={telefono}
            placeholder="Número (ej. 3001234567)"
            aria-label="Número de WhatsApp para el recibo"
            class="input-base mb-2.5"
          />
          <a
            href={telefonoValido() ? urlWhatsapp() : '#'}
            target="_blank"
            rel="noopener noreferrer"
            aria-disabled={!telefonoValido()}
            class={'w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-[13.5px] font-bold transition-all ' + (telefonoValido()
              ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-[0_1px_2px_rgba(6,95,70,0.4),0_6px_14px_-6px_rgba(5,150,105,0.6)] active:scale-[0.99]'
              : 'bg-emerald-100 text-emerald-300 cursor-not-allowed')}
          >
            <Icon name="message" class="w-[18px] h-[18px]" />
            Enviar recibo por WhatsApp
          </a>
          {#if !telefonoValido()}
            <p class="text-[10.5px] text-slate-400 mt-1.5">Ingresa un número válido para habilitar el envío.</p>
          {/if}
        </div>
      {/if}

      <button
        type="button"
        onclick={cerrarExito}
        class="mt-5 w-full rounded-[12px] bg-blue-600 text-white text-[14px] font-bold py-3 hover:bg-blue-700 active:scale-[0.99] transition-all cursor-pointer
          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-1"
      >
        Aceptar
      </button>
    </div>
  </div>
{/if}
