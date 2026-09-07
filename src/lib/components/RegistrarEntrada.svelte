<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario, vistaActual } from '../stores';
  import { formatCOP } from '../utils';
  import Input from './ui/Input.svelte';
  import Icon from './ui/Icon.svelte';

  interface Opcion {
    producto: string;
    presentacion: string;
  }

  let cantidad = $state('');
  let precioCompra = $state('');
  let proveedor = $state('');
  let observaciones = $state('');
  // Calculadora de precio de venta
  let pagaIva = $state(false);
  let ivaPorc = $state(19);
  let gananciaPorc = $state(30);
  let redondeo = $state(100); // 1 = sin redondeo, 50/100/500
  const opcionesRedondeo = [
    { valor: 1, etiqueta: 'Sin redondeo' },
    { valor: 50, etiqueta: '$50' },
    { valor: 100, etiqueta: '$100' },
    { valor: 500, etiqueta: '$500' },
  ];
  let precioVenta = $state('');
  let actualizarPrecio = $state(true);
  let opciones = $state<Opcion[]>([]);
  let cargando = $state(true);
  let error = $state('');
  let success = $state('');
  let loading = $state(false);

  // Selector de producto (nombre + presentación)
  let busqueda = $state('');
  let abierto = $state(false);
  let seleccion = $state<Opcion | null>(null);

  onMount(async () => {
    try {
      const cat = await api.listarProductosCatalogo();
      opciones = cat
        .map((p) => ({ producto: p.producto, presentacion: p.presentacion || '' }))
        .sort((a, b) => a.producto.localeCompare(b.producto, 'es') || a.presentacion.localeCompare(b.presentacion, 'es'));
    } catch (e) { console.error(e); }
    cargando = false;
  });

  let filtradas = $derived(
    opciones.filter((o) => {
      const q = busqueda.trim().toLowerCase();
      if (!q) return true;
      return (
        o.producto.toLowerCase().includes(q) ||
        o.presentacion.toLowerCase().includes(q)
      );
    })
  );

  // Agrupa por nombre de producto para elegir por clave "nombre → presentación"
  interface Grupo { nombre: string; presentaciones: Opcion[]; }
  let agrupadas = $derived((() => {
    const grupos: Grupo[] = [];
    for (const o of filtradas) {
      const ultimo = grupos.length > 0 ? grupos[grupos.length - 1] : null;
      if (ultimo && ultimo.nombre === o.producto) {
        ultimo.presentaciones.push(o);
      } else {
        grupos.push({ nombre: o.producto, presentaciones: [o] });
      }
    }
    return grupos;
  })());

  function onBusqueda(e: Event) {
    busqueda = (e.target as HTMLInputElement).value;
    abierto = true;
  }

  function elegir(o: Opcion) {
    seleccion = o;
    busqueda = '';
    abierto = false;
  }

  // Fórmula: margen sobre precio de venta sin IVA; si el producto paga IVA se suma al precio final
  let costoCompra = $derived(Number(precioCompra || 0) || 0);
  let sugeridoSinIva = $derived(
    costoCompra > 0 && gananciaPorc < 100 ? Math.round(costoCompra / (1 - gananciaPorc / 100)) : 0
  );
  let ivaSugerido = $derived(
    pagaIva && ivaPorc > 0 ? Math.round((sugeridoSinIva * ivaPorc) / 100) : 0
  );
  let precioBruto = $derived(sugeridoSinIva + ivaSugerido);
  // Redondeo del precio final hacia arriba (para no perder margen)
  let precioSugerido = $derived(precioBruto > 0 && redondeo > 1 ? Math.ceil(precioBruto / redondeo) * redondeo : precioBruto);

  function usarSugerido() {
    if (precioSugerido > 0) {
      precioVenta = String(precioSugerido);
      actualizarPrecio = true;
    }
  }

  function abrirBuscador() {
    busqueda = '';
    abierto = true;
  }

  function limpiar() {
    seleccion = null;
    busqueda = '';
    abierto = false;
  }

  function teclaBusqueda(e: KeyboardEvent) {
    if (e.key === 'Escape') { abierto = false; return; }
    if (e.key === 'Enter' && abierto && filtradas.length > 0) {
      elegir(filtradas[0]);
    }
  }

  async function registrar() {
    if (!seleccion || !cantidad) {
      error = 'Selecciona el producto (nombre y presentación) y escribe la cantidad';
      return;
    }
    loading = true; error = ''; success = '';
    try {
      await api.registrarEntrada({
        producto: seleccion.producto,
        presentacion: seleccion.presentacion,
        cantidad: Number(cantidad),
        precio_compra: Number(precioCompra || 0),
        precio_venta: actualizarPrecio && Number(precioVenta) > 0 ? Number(precioVenta) : undefined,
        proveedor,
        observaciones,
        cajero: $usuario?.nombre || '',
      });
      success = 'Entrada registrada: ' + seleccion.producto + (seleccion.presentacion ? ' (' + seleccion.presentacion + ')' : '');
      cantidad = ''; precioCompra = ''; proveedor = ''; observaciones = '';
      precioVenta = ''; actualizarPrecio = true; pagaIva = false;
      seleccion = null; busqueda = ''; abierto = false;
      setTimeout(() => { success = ''; }, 4000);
    } catch (e: any) { error = e.message; }
    loading = false;
  }
</script>

<div class="max-w-2xl">
  <div class="flex items-center justify-between gap-3 mb-5">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-[14px] bg-emerald-50 text-emerald-600 flex items-center justify-center">
        <Icon name="truck" class="w-[22px] h-[22px]" />
      </div>
      <div>
        <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">Ingreso de inventario</h2>
        <p class="text-[12.5px] text-slate-500">Registra la compra al proveedor</p>
      </div>
    </div>
    <button
      onclick={() => vistaActual.set('historial-entradas')}
      class="hidden sm:inline-flex items-center gap-1.5 rounded-[10px] text-[13px] font-semibold text-slate-600 hover:text-blue-700 hover:bg-blue-50 px-3 py-2 transition-colors cursor-pointer"
    >
      <Icon name="history" class="w-4 h-4" />
      Ver historial
    </button>
  </div>

  <div class="panel p-5 sm:p-7 rounded-2xl">
    <form onsubmit={(e) => { e.preventDefault(); registrar(); }}>
      <div class="space-y-4">
        <!-- Producto (nombre + presentación) -->
        <div>
          <label class="field-label" for="entrada-producto">Producto *</label>
          <div class="relative">
            {#if seleccion && !abierto}
              <button
                type="button"
                onclick={abrirBuscador}
                class="w-full flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50/60 px-3.5 py-2.5 text-left hover:border-emerald-300 transition-colors cursor-pointer"
              >
                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                  <Icon name="check" class="w-4 h-4" strokeWidth={2.6} />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block font-semibold text-[14px] text-slate-900 truncate">{seleccion.producto}</span>
                  <span class="flex items-center gap-1.5 text-[12.5px] font-medium text-emerald-700 truncate mt-0.5">
                    <span class="text-emerald-500 select-none">→</span>
                    {seleccion.presentacion || 'Sin presentación'}
                  </span>
                </span>
                <span class="text-[12px] font-medium text-emerald-700 shrink-0">Cambiar</span>
              </button>
            {:else}
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <Icon name="search" class="w-[18px] h-[18px]" />
                </span>
                <input
                  id="entrada-producto"
                  type="text"
                  value={busqueda}
                  oninput={onBusqueda}
                  onfocus={() => (abierto = true)}
                  onkeydown={teclaBusqueda}
                  placeholder="Busca por nombre o presentación (ej. 1 LITRO)…"
                  autocomplete="off"
                  class="input-base pl-10 pr-10"
                />
                {#if busqueda}
                  <button
                    type="button"
                    onclick={limpiar}
                    aria-label="Limpiar búsqueda"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                  >
                    <Icon name="x" class="w-4 h-4" />
                  </button>
                {/if}
              </div>
            {/if}

            {#if abierto}
              <div class="absolute inset-x-0 mt-2 z-20 bg-white rounded-2xl shadow-[0_16px_48px_-16px_rgba(15,23,42,0.28)] ring-1 ring-slate-900/5 max-h-72 overflow-y-auto scrollbar-thin">
                {#if cargando}
                  <p class="px-4 py-6 text-center text-[13px] text-slate-400">Cargando productos…</p>
                {:else if agrupadas.length > 0}
                  {#each agrupadas as g}
                    <div>
                      <p class="px-4 pt-3 pb-1 text-[10.5px] font-bold uppercase tracking-[0.12em] text-slate-400">{g.nombre}</p>
                      {#each g.presentaciones as o}
                        <button
                          type="button"
                          onclick={() => elegir(o)}
                          class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-emerald-50/60 transition-colors cursor-pointer border-b border-slate-50 last:border-0"
                        >
                          <span class="w-6 text-right text-blue-600 text-[14px] shrink-0 select-none">→</span>
                          <span class="min-w-0 flex-1 truncate text-[13.5px] font-medium text-slate-800">
                            {o.presentacion || 'Sin presentación'}
                          </span>
                          <Icon name="chevron-right" class="w-4 h-4 text-slate-300 shrink-0" />
                        </button>
                      {/each}
                    </div>
                  {/each}
                {:else}
                  <p class="px-4 py-6 text-center text-[13px] text-slate-400">Sin resultados para “{busqueda}”</p>
                {/if}
              </div>
            {/if}
          </div>
          {#if seleccion && !abierto}
            <p class="text-[11.5px] text-emerald-600 mt-1.5 flex items-center gap-1">
              <Icon name="check" class="w-3.5 h-3.5" />
              {seleccion.producto}{seleccion.presentacion ? ' · ' + seleccion.presentacion : ''} seleccionado — el stock se sumará a esta referencia exacta.
            </p>
          {/if}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input label="Cantidad" type="number" bind:value={cantidad} placeholder="0" required icon="package" />
          <Input label="Precio de compra" type="number" bind:value={precioCompra} placeholder="0" icon="tag" />
        </div>

        <Input label="Proveedor" bind:value={proveedor} placeholder="Nombre del proveedor" icon="building" />
        <Input label="Observaciones" bind:value={observaciones} placeholder="Notas adicionales (opcional)" icon="note" />

        <!-- Calculadora de precio de venta -->
        <div class="rounded-2xl border border-blue-100 bg-blue-50/40 p-4 space-y-3.5">
          <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
              <Icon name="tag" class="w-4 h-4" />
            </span>
            <p class="text-[13.5px] font-bold text-slate-900 tracking-tight">Precio de venta sugerido</p>
            <span class="chip bg-blue-100 text-blue-700 ring-1 ring-inset ring-blue-200 ml-auto normal-case">Opcional</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="field-label" for="ent-iva">¿El producto paga IVA?</label>
              <button
                type="button"
                id="ent-iva"
                role="switch"
                aria-checked={pagaIva}
                aria-label="El producto paga IVA"
                onclick={() => (pagaIva = !pagaIva)}
                class="relative w-[52px] h-[30px] rounded-full transition-colors duration-200 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-2 {pagaIva ? 'bg-blue-500' : 'bg-slate-300'}"
              >
                <span class={'absolute top-[3px] left-[3px] w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-200 ' + (pagaIva ? 'translate-x-[22px]' : 'translate-x-0')}></span>
              </button>
              {#if pagaIva}
                <div class="relative mt-2">
                  <input type="number" bind:value={ivaPorc} min="0" max="100" class="input-base pr-9" aria-label="Porcentaje de IVA" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[12px] pointer-events-none">%</span>
                </div>
              {/if}
            </div>
            <div>
              <label class="field-label" for="ent-ganancia">Margen de ganancia (%)</label>
              <div class="relative">
                <input id="ent-ganancia" type="number" bind:value={gananciaPorc} min="0" max="99" class="input-base pr-9" />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[12px] pointer-events-none">%</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1.5">Margen sobre el precio de venta sin IVA.</p>
            </div>
          </div>

          <div>
            <p class="field-label uppercase !mb-2 text-[10.5px] tracking-[0.12em]">Redondear precio final a</p>
            <div class="flex flex-wrap gap-1.5">
              {#each opcionesRedondeo as op}
                <button
                  type="button"
                  onclick={() => (redondeo = op.valor)}
                  aria-pressed={redondeo === op.valor}
                  class="rounded-lg px-3 py-1.5 text-[12px] font-semibold transition-colors cursor-pointer
                    {redondeo === op.valor ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}"
                >
                  {op.etiqueta}
                </button>
              {/each}
            </div>
          </div>

          {#if costoCompra > 0}
            <div class="rounded-xl bg-white ring-1 ring-inset ring-slate-200/80 p-3.5 grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-[10.5px] font-semibold uppercase tracking-wide text-slate-400">Costo compra</p>
                <p class="text-[14px] font-bold text-slate-800 mt-1">{formatCOP(costoCompra)}</p>
              </div>
              <div>
                <p class="text-[10.5px] font-semibold uppercase tracking-wide text-slate-400">Venta sin IVA</p>
                <p class="text-[14px] font-bold text-slate-800 mt-1">{formatCOP(sugeridoSinIva)}</p>
              </div>
              <div>
                <p class="text-[10.5px] font-semibold uppercase tracking-wide text-slate-400">{pagaIva ? 'IVA (' + ivaPorc + '%)' : 'IVA'}</p>
                <p class="text-[14px] font-bold text-slate-800 mt-1">{formatCOP(ivaSugerido)}</p>
              </div>
            </div>
            <div class="flex items-center justify-between gap-3 rounded-xl bg-gradient-to-br from-blue-600 to-sky-500 text-white px-4 py-3 shadow-md">
              <div class="min-w-0 leading-tight">
                <p class="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-blue-100">Precio de venta sugerido</p>
                <p class="text-[19px] font-bold tracking-tight truncate">{formatCOP(precioSugerido)}</p>
              </div>
              <button
                type="button"
                onclick={usarSugerido}
                disabled={precioSugerido <= 0}
                class="shrink-0 rounded-[10px] bg-white text-blue-700 text-[12.5px] font-bold px-3.5 py-2.5 hover:bg-blue-50 active:scale-[0.98] transition-all disabled:opacity-50 disabled:pointer-events-none cursor-pointer"
              >
                Usar sugerido
              </button>
            </div>
          {:else}
            <p class="text-[12px] text-slate-400">Escribe el <strong>precio de compra</strong> arriba para calcular el precio de venta sugerido.</p>
          {/if}

          <div class="pt-1">
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="tag" class="w-[18px] h-[18px]" />
              </span>
              <input
                id="ent-precio-venta"
                type="number"
                bind:value={precioVenta}
                min="0"
                placeholder="Precio de venta a aplicar (0 = no cambiar)"
                class="input-base pl-10"
              />
            </div>
            <label class="flex items-center gap-2 mt-2.5 text-[12.5px] text-slate-600 cursor-pointer select-none">
              <input
                type="checkbox"
                bind:checked={actualizarPrecio}
                class="w-4 h-4 rounded accent-blue-600"
              />
              Actualizar el precio de venta de {seleccion ? (seleccion.producto + (seleccion.presentacion ? ' → ' + seleccion.presentacion : '')) : 'la referencia seleccionada'} al registrar la entrada
            </label>
          </div>
        </div>
      </div>

      {#if error}
        <div class="notice-error" role="alert">
          <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
          <span>{error}</span>
        </div>
      {/if}
      {#if success}
        <div class="notice-success" role="status">
          <Icon name="circle-check" class="w-4 h-4 mt-[1px] shrink-0" />
          <span>{success}</span>
        </div>
      {/if}

      <div class="mt-6 flex flex-col sm:flex-row gap-2.5">
        <button
          type="submit"
          disabled={loading}
          class="inline-flex items-center justify-center gap-2 rounded-[12px] bg-emerald-600 text-white text-[14.5px] font-bold px-5 py-3 flex-1
            shadow-[0_1px_2px_rgba(6,95,70,0.4),0_8px_20px_-8px_rgba(5,150,105,0.6)]
            hover:bg-emerald-700 active:scale-[0.99] disabled:opacity-60 disabled:pointer-events-none
            transition-all duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 focus-visible:ring-offset-1"
        >
          {#if loading}
            <svg class="w-4.5 h-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
            </svg>
            Registrando…
          {:else}
            <Icon name="package" class="w-5 h-5" />
            Registrar Entrada
          {/if}
        </button>
        <button
          type="button"
          onclick={() => vistaActual.set('inventario')}
          class="inline-flex items-center justify-center gap-2 rounded-[12px] bg-white text-slate-700 ring-1 ring-inset ring-slate-300/80 text-[14.5px] font-semibold px-5 py-3
            hover:bg-slate-50 active:scale-[0.99] transition-all cursor-pointer"
        >
          Ir a inventario
        </button>
      </div>
    </form>
  </div>
</div>
