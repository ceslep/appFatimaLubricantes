<script lang="ts">
  import { onMount } from 'svelte';
  import { api, negocioActual } from '../api';
  import { cacheLeer, cacheEscribir } from '../cache';
  import { validarDocumento, validarTelefonoCO, validarCorreo, validarPlaca, estadoDocumento, estadoTelefono, estadoNombres, estadoCorreo, limitarDocumento, limitarNombres, validarNombres, type EstadoCampo, type EstadoValidacion } from '../validaciones';
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

  const CLAVE_CACHE_CATALOGO = 'catalogo:' + negocioActual();

  // Pintado instantáneo: si ya visitamos esta pantalla, mostramos el catálogo
  // guardado mientras se refresca en segundo plano (stale-while-revalidate).
  const catalogoCache = cacheLeer<ProductoCatalogo[]>(CLAVE_CACHE_CATALOGO);
  if (catalogoCache && catalogoCache.length > 0) {
    catalogo = catalogoCache;
    loadingProductos = false;
  }
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
  let intentoEnvioCliente = $state(false);
  let verificandoDocCliente = $state(false);
  let docExistenteCliente = $state(false);

  function requeridoCliente(e: EstadoValidacion, msg: string): EstadoValidacion {
    if (intentoEnvioCliente && e.estado === 'vacio') return { estado: 'error', mensaje: msg };
    return e;
  }

  // Validación en vivo mientras se escribe
  let estClIdBase = $derived(requeridoCliente(estadoDocumento(formCliente.identificacion), 'Ingresa la identificación.'));
  let estClId = $derived<EstadoValidacion>(
    docExistenteCliente
      ? { estado: 'error', mensaje: 'Ya existe un cliente registrado con esta identificación.' }
      : verificandoDocCliente
        ? { estado: 'parcial', mensaje: 'Verificando identificación…' }
        : estClIdBase
  );
  let estClTel = $derived(requeridoCliente(estadoTelefono(formCliente.telefono), 'Ingresa el celular.'));
  let estClNom = $derived(requeridoCliente(estadoNombres(formCliente.nombres), 'Ingresa el nombre completo.'));
  let estClCor = $derived(estadoCorreo(formCliente.correo));
  let formularioClienteValido = $derived(
    estClId.estado === 'ok' && estClTel.estado === 'ok' && estClNom.estado === 'ok' && estClCor.estado !== 'error'
    && !docExistenteCliente && !verificandoDocCliente
  );

  function claseEstado(estado: EstadoCampo): string {
    if (estado === 'ok') return 'text-emerald-600';
    if (estado === 'error') return 'text-rose-600';
    if (estado === 'parcial') return 'text-amber-600';
    return 'text-slate-400';
  }

  function bordeEstado(e: EstadoValidacion): string {
    if (e.estado === 'error') return 'border-color:#fb7185';
    if (e.estado === 'ok') return 'border-color:#34d399';
    if (e.estado === 'parcial') return 'border-color:#fbbf24';
    return '';
  }

  let productosFiltrados = $derived(
    busqueda
      ? catalogo.filter((p) => p.producto.toLowerCase().includes(busqueda.toLowerCase()))
      : catalogo
  );

  // Acceso rápido: muestra los más vendidos, y mientras cargan usa el catálogo
  // para que los productos aparezcan sin esperas.
  let accesoRapido = $derived(topProductos.length > 0 ? topProductos : catalogo.slice(0, 16));

  let totalCarrito = $derived(carrito.reduce((sum, item) => sum + item.producto.precio_venta * item.cantidad, 0));
  let totalItems = $derived(carrito.reduce((sum, item) => sum + item.cantidad, 0));

  const metodosPago = [
    { id: 'Efectivo', icon: 'banknote' },
    { id: 'Tarjeta', icon: 'card' },
    { id: 'Transferencia', icon: 'landmark' },
  ];

  onMount(() => {
    // El catálogo es lo único que bloquea la aparición de productos:
    // se pide primero y el resto de datos carga en paralelo, sin bloquear.
    void cargarCatalogo();
    void cargarTop();
    void cargarConfigYClientes();
  });

  function pintarCatalogo(cat: ProductoCatalogo[]) {
    catalogo = cat;
    cacheEscribir(CLAVE_CACHE_CATALOGO, cat);
  }

  async function cargarCatalogo() {
    try {
      pintarCatalogo(await api.listarProductosCatalogo());
    } catch (e) { console.error(e); }
    finally { loadingProductos = false; }
  }

  async function cargarTop() {
    try { topProductos = await api.productosMasVendidos(); } catch (e) { console.error(e); }
  }

  async function cargarConfigYClientes() {
    const [cfgRes, cliRes] = await Promise.allSettled([
      api.obtenerConfig(),
      api.listarClientes(),
    ]);
    if (cfgRes.status === 'fulfilled') {
      confWhatsapp = cfgRes.value.enviar_whatsapp === 'TRUE';
      clienteObligatorio = cfgRes.value.cliente_obligatorio === 'TRUE';
    } else { console.error(cfgRes.reason); }
    if (cliRes.status === 'fulfilled') clientes = cliRes.value;
    else { console.error(cliRes.reason); }
  }

  // Refresca catálogo y más vendidos tras una venta y actualiza la caché local.
  async function refrescarCatalogoYTop() {
    const [catRes, topRes] = await Promise.allSettled([
      api.listarProductosCatalogo(),
      api.productosMasVendidos(),
    ]);
    if (catRes.status === 'fulfilled') pintarCatalogo(catRes.value);
    else { console.error(catRes.reason); }
    if (topRes.status === 'fulfilled') topProductos = topRes.value;
    else { console.error(topRes.reason); }
  }

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

  function onNomClienteInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarNombres(el.value);
    el.value = limpio;
    formCliente.nombres = limpio;
  }

  function onTelClienteInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    let v = el.value.replace(/[^\d+]/g, '');
    const mas = v.startsWith('+');
    v = v.replace(/\+/g, '');
    if (mas) v = '+' + v;
    v = v.slice(0, 13);
    el.value = v;
    formCliente.telefono = v;
  }

  function onDocClienteInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarDocumento(el.value);
    el.value = limpio;
    formCliente.identificacion = limpio;
    docExistenteCliente = false;
    verificandoDocCliente = false;
  }

  // Al salir del campo: verifica si la identificación ya está registrada
  async function verificarDocumentoCliente() {
    const r = validarDocumento(formCliente.identificacion);
    docExistenteCliente = false;
    if (!r.valido) return;
    verificandoDocCliente = true;
    try {
      const res = await api.clienteExiste(r.valorNormalizado);
      docExistenteCliente = res.existe;
    } catch (e) {
      console.error(e);
    }
    verificandoDocCliente = false;
  }

  function abrirModalCliente() {
    formCliente = { identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '' };
    errorCliente = '';
    intentoEnvioCliente = false;
    modalCliente = true;
  }

  async function guardarClienteNuevo() {
    if (!formularioClienteValido) {
      intentoEnvioCliente = true;
      errorCliente = 'Revisa los campos marcados.';
      return;
    }
    formCliente.identificacion = validarDocumento(formCliente.identificacion).valorNormalizado;
    formCliente.nombres = validarNombres(formCliente.nombres).valorNormalizado;
    formCliente.telefono = validarTelefonoCO(formCliente.telefono).valorNormalizado;
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
    const placaVal = validarPlaca(placa, false);
    if (!placaVal.valido) { error = placaVal.mensaje; return; }
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
        placa: placaVal.valorNormalizado,
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
      // Refresco en segundo plano: no retrasa el modal de éxito.
      void refrescarCatalogoYTop();
      if (!confWhatsapp) {
        setTimeout(() => { mostrarExito = false; telefono = ''; }, 4000);
      }
    } catch (e: any) { error = e.message; }
    loading = false;
  }
</script>

<svelte:window onkeydown={teclaGlobal} />

{#snippet estadoLinea(e: EstadoValidacion, ayuda: string)}
  {#if e.estado === 'vacio'}
    {#if ayuda}<p class="mt-1 text-[11px] text-slate-400">{ayuda}</p>{/if}
  {:else if e.mensaje}
    <p class="mt-1 text-[11.5px] {claseEstado(e.estado)} flex items-center gap-1">
      {#if e.estado === 'ok'}<Icon name="check" class="w-3.5 h-3.5 shrink-0" strokeWidth={3} />{/if}
      {#if e.estado === 'error'}<Icon name="alert" class="w-3.5 h-3.5 shrink-0" />{/if}
      <span>{e.mensaje}</span>
    </p>
  {/if}
{/snippet}

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
                class="toque w-full flex items-center justify-between gap-3 px-4 py-3 text-left hover:bg-blue-50/50 transition-colors border-b border-slate-50 last:border-0 disabled:opacity-45 disabled:cursor-not-allowed cursor-pointer"
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
    {:else if accesoRapido.length > 0}
      <div>
        <p class="field-label uppercase text-[10.5px] tracking-[0.12em] !mb-2.5">Acceso rápido</p>
        <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
          {#each accesoRapido as p}
            <button
              type="button"
              onclick={() => agregarAlCarrito(p)}
              disabled={p.stock <= 0}
              class="toque group relative panel panel-hover rounded-2xl p-3 sm:p-3.5 text-left flex flex-col gap-1.5
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
        class="toque shrink-0 inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white text-[14px] font-bold px-4 py-3
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
          class="toque-icono p-1.5 -m-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">
          <Icon name="x" class="w-5 h-5" />
        </button>
      </div>
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="field-label" for="rv-cl-id">Identificación *</label>
            <input id="rv-cl-id" type="text" inputmode="numeric" maxlength="12" value={formCliente.identificacion} oninput={onDocClienteInput} onblur={verificarDocumentoCliente} placeholder="Cédula o NIT" class="input-base" autocomplete="off" style={bordeEstado(estClId)} aria-invalid={estClId.estado === 'error'} />
            {@render estadoLinea(estClId, 'Cédula de 10 dígitos o NIT con guion.')}
          </div>
          <div>
            <label class="field-label" for="rv-cl-tel">Teléfono / WhatsApp *</label>
            <input id="rv-cl-tel" type="tel" inputmode="tel" maxlength="13" value={formCliente.telefono} oninput={onTelClienteInput} placeholder="3001234567 o +57 3001234567" class="input-base" autocomplete="tel" style={bordeEstado(estClTel)} aria-invalid={estClTel.estado === 'error'} />
            {@render estadoLinea(estClTel, 'Celular de 10 dígitos que empieza por 3. Puedes usar +57.')}
          </div>
        </div>
        <div>
          <label class="field-label" for="rv-cl-nom">Nombres completos *</label>
          <input id="rv-cl-nom" type="text" value={formCliente.nombres} oninput={onNomClienteInput} placeholder="Nombre y apellido" class="input-base" autocomplete="off" style={bordeEstado(estClNom)} aria-invalid={estClNom.estado === 'error'} />
          {@render estadoLinea(estClNom, 'Nombre y apellido.')}
        </div>
        <div>
          <label class="field-label" for="rv-cl-cor">Correo electrónico</label>
          <input id="rv-cl-cor" type="email" bind:value={formCliente.correo} placeholder="cliente@correo.com" class="input-base" autocomplete="off" style={bordeEstado(estClCor)} aria-invalid={estClCor.estado === 'error'} />
          {@render estadoLinea(estClCor, 'Opcional.')}
        </div>
        {#if errorCliente}
          <p class="text-[13px] text-rose-600">{errorCliente}</p>
        {/if}
        <div class="flex gap-3 justify-end pt-1">
          <button type="button" onclick={() => (modalCliente = false)}
            class="toque inline-flex items-center justify-center rounded-[10px] bg-white text-slate-700 ring-1 ring-inset ring-slate-300/80 px-4 py-2.5 text-[13.5px] font-semibold hover:bg-slate-50 active:scale-[0.98] transition-all cursor-pointer">
            Cancelar
          </button>
          <button type="button" onclick={guardarClienteNuevo} disabled={guardandoCliente || !formularioClienteValido}
            class="toque inline-flex items-center justify-center gap-2 rounded-[10px] bg-blue-600 text-white px-4 py-2.5 text-[13.5px] font-bold hover:bg-blue-700 active:scale-[0.98] disabled:opacity-60 disabled:pointer-events-none transition-all cursor-pointer">
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
