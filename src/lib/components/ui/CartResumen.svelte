<script lang="ts">
  import { formatCOP } from '../../utils';
  import { estadoPlaca, limitarPlaca, type EstadoCampo, type EstadoValidacion } from '../../validaciones';
  import type { ProductoCatalogo } from '../../types';
  import Icon from './Icon.svelte';

  type Linea = { producto: ProductoCatalogo; cantidad: number };
  type ClienteOpt = { identificacion: string; nombres: string; telefono: string; placa1?: string; placa2?: string; placa3?: string; placa4?: string };

  let { items = [], variant = 'card', formaPago = $bindable('Efectivo'), clientes = [], clienteDoc = $bindable(''), clienteNombre = $bindable(''), clienteTelefono = $bindable(''), clienteObligatorio = false, placa = $bindable(''), preguntarWhatsapp = false, telefono = $bindable(''), error = '', loading = false, onIncrement = (_i: number) => {}, onDecrement = (_i: number) => {}, onRemove = (_i: number) => {}, onRegistrar = () => {}, onNuevoCliente = () => {}, onClienteCambio = (_c: ClienteOpt) => {} }: {
    items?: Linea[];
    variant?: 'card' | 'sheet';
    formaPago?: string;
    clientes?: ClienteOpt[];
    clienteDoc?: string;
    clienteNombre?: string;
    clienteTelefono?: string;
    clienteObligatorio?: boolean;
    placa?: string;
    preguntarWhatsapp?: boolean;
    telefono?: string;
    error?: string;
    loading?: boolean;
    onIncrement?: (index: number) => void;
    onDecrement?: (index: number) => void;
    onRemove?: (index: number) => void;
    onRegistrar?: () => void;
    onNuevoCliente?: () => void;
    onClienteCambio?: (c: ClienteOpt) => void;
  } = $props();

  let estPlaca = $derived(estadoPlaca(placa));

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

  function onPlacaInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarPlaca(el.value);
    el.value = limpio;
    placa = limpio;
  }

  let clienteSeleccionado = $derived(clientes.find((c) => c.identificacion === clienteDoc) || null);
  let placasCliente = $derived(
    [clienteSeleccionado?.placa1 || '', clienteSeleccionado?.placa2 || '', clienteSeleccionado?.placa3 || '', clienteSeleccionado?.placa4 || ''].filter(Boolean)
  );

  const metodosPago = [
    { id: 'Efectivo', icon: 'banknote' },
    { id: 'Tarjeta', icon: 'card' },
    { id: 'Transferencia', icon: 'landmark' },
  ];

  let totalCarrito = $derived(items.reduce((s, it) => s + it.producto.precio_venta * it.cantidad, 0));
  let totalItems = $derived(items.reduce((s, it) => s + it.cantidad, 0));

  // ---- Buscador de clientes ----
  let busquedaCliente = $state('');
  let abiertoCliente = $state(false);

  let clientesFiltrados = $derived(
    clientes.filter((c) => {
      const q = busquedaCliente.trim().toLowerCase();
      if (!q) return true;
      return (
        c.nombres.toLowerCase().includes(q) ||
        c.identificacion.toLowerCase().includes(q) ||
        c.telefono.toLowerCase().includes(q)
      );
    })
  );

  function elegirCliente(c: ClienteOpt) {
    clienteDoc = c.identificacion;
    clienteNombre = c.nombres;
    clienteTelefono = c.telefono;
    busquedaCliente = '';
    abiertoCliente = false;
    onClienteCambio(c);
  }

  function quitarCliente() {
    clienteDoc = '';
    clienteNombre = '';
    clienteTelefono = '';
  }

  function iniciales(nombre: string) {
    const base = (nombre || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase();
    return base || 'C';
  }
</script>

<div class={variant === 'card' ? 'panel rounded-2xl flex flex-col overflow-hidden' : 'flex flex-col gap-4'}>
  {#if variant === 'card'}
    <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-[10px] bg-blue-50 text-blue-600 flex items-center justify-center">
          <Icon name="cart" class="w-[17px] h-[17px]" />
        </div>
        <h2 class="text-[15px] font-bold text-slate-900 tracking-tight">Venta actual</h2>
      </div>
      <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">{totalItems} item{totalItems === 1 ? '' : 's'}</span>
    </div>
  {/if}

  {#if items.length > 0}
    <!-- Total y forma de pago arriba: visibles sin desplazarse -->
    <div class={variant === 'card' ? 'px-5 pt-4 space-y-4' : 'space-y-4'}>
      <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-4 text-white shadow-lg">
        <div class="flex items-center justify-between text-[12px] text-slate-300">
          <span>Artículos ({totalItems})</span>
          <span>{formatCOP(totalCarrito)}</span>
        </div>
        <div class="mt-2 pt-2.5 border-t border-white/10 flex items-end justify-between">
          <span class="text-[13px] font-medium text-slate-200">Total a pagar</span>
          <span class="text-[26px] leading-7 font-bold tracking-tight">{formatCOP(totalCarrito)}</span>
        </div>
      </div>

      <!-- Forma de pago -->
      <div>
        <p class="field-label uppercase !mb-2 text-[10.5px] tracking-[0.12em]">Forma de pago</p>
        <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100/90 p-1">
          {#each metodosPago as mp}
            <button
              type="button"
              onclick={() => (formaPago = mp.id)}
              aria-pressed={formaPago === mp.id}
              class="toque flex flex-col items-center gap-1 rounded-[10px] py-2.5 text-[11.5px] font-semibold transition-all duration-150 cursor-pointer
                {formaPago === mp.id
                  ? 'bg-white text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200'
                  : 'text-slate-500 hover:text-slate-700'}"
            >
              <Icon name={mp.icon} class={'w-[18px] h-[18px] ' + (formaPago === mp.id ? 'text-blue-600' : '')} />
              {mp.id}
            </button>
          {/each}
        </div>
      </div>
    </div>
  {/if}

  {#if items.length > 0}
    <div class="overflow-y-auto scrollbar-thin space-y-2.5
      {variant === 'card' ? 'max-h-64 lg:max-h-[calc(100dvh-500px)] px-5 py-4' : 'max-h-48 px-1'}">
      {#each items as item, i}
        <div class="rounded-2xl border border-slate-200/90 bg-slate-50/50 p-3.5">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-[13.5px] text-slate-900 truncate">{item.producto.producto}</p>
              <p class="text-[11.5px] text-slate-400 mt-0.5 truncate">
                {item.producto.presentacion ? item.producto.presentacion + ' · ' : ''}{formatCOP(item.producto.precio_venta)} c/u
              </p>
            </div>
            <button
              type="button"
              onclick={() => onRemove(i)}
              aria-label={'Eliminar ' + item.producto.producto}
              class="toque-icono p-1.5 -m-1 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
            >
              <Icon name="trash" class="w-4 h-4" />
            </button>
          </div>
          <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-0.5">
              <button
                type="button"
                onclick={() => onDecrement(i)}
                disabled={item.cantidad <= 1}
                aria-label="Disminuir cantidad"
                class="toque-icono w-8 h-8 rounded-lg bg-white ring-1 ring-inset ring-slate-200 text-slate-600 hover:bg-slate-100 active:scale-95 disabled:opacity-35 disabled:pointer-events-none transition-all flex items-center justify-center cursor-pointer"
              >
                <Icon name="minus" class="w-4 h-4" strokeWidth={2.4} />
              </button>
              <span class="w-9 text-center text-[15px] font-bold text-slate-900">{item.cantidad}</span>
              <button
                type="button"
                onclick={() => onIncrement(i)}
                disabled={item.cantidad >= item.producto.stock}
                aria-label="Aumentar cantidad"
                class="toque-icono w-8 h-8 rounded-lg bg-white ring-1 ring-inset ring-slate-200 text-slate-600 hover:bg-slate-100 active:scale-95 disabled:opacity-35 disabled:pointer-events-none transition-all flex items-center justify-center cursor-pointer"
              >
                <Icon name="plus" class="w-4 h-4" strokeWidth={2.4} />
              </button>
            </div>
            <span class="font-bold text-[15px] text-slate-900">{formatCOP(item.producto.precio_venta * item.cantidad)}</span>
          </div>
        </div>
      {/each}
    </div>
  {:else if variant === 'card'}
    <div class="flex-1 flex flex-col items-center justify-center text-center px-6 py-10">
      <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-300 flex items-center justify-center mb-3">
        <Icon name="cart" class="w-7 h-7" strokeWidth={1.5} />
      </div>
      <p class="text-[14px] font-semibold text-slate-600">El carrito está vacío</p>
      <p class="text-[12.5px] text-slate-400 mt-1 max-w-[230px]">Busca un producto o toca uno del acceso rápido para comenzar.</p>
    </div>
  {/if}

  {#if items.length > 0}
    <div class={variant === 'card' ? 'px-5 pb-5 pt-4 space-y-4 border-t border-slate-100' : 'space-y-4'}>
      <!-- Cliente (registrado, opcional u obligatorio según configuración) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <p class="text-[10.5px] font-bold uppercase tracking-[0.12em] text-slate-400">
            Cliente {clienteObligatorio ? '(obligatorio)' : '(opcional)'}
          </p>
          {#if !clienteDoc}
            <button
              type="button"
              onclick={onNuevoCliente}
              class="toque inline-flex items-center gap-1 text-[12px] font-semibold text-blue-600 hover:text-blue-700 cursor-pointer"
            >
              <Icon name="plus" class="w-3.5 h-3.5" strokeWidth={2.6} />
              Registrar nuevo
            </button>
          {/if}
        </div>

        {#if clienteDoc}
          <div class="flex items-center gap-3 rounded-xl bg-blue-50/70 ring-1 ring-inset ring-blue-200 px-3.5 py-2.5">
            <span class="w-9 h-9 rounded-full bg-blue-600 text-white text-[11.5px] font-bold flex items-center justify-center shrink-0 select-none">
              {iniciales(clienteNombre)}
            </span>
            <div class="min-w-0 flex-1 leading-tight">
              <p class="text-[13.5px] font-semibold text-slate-900 truncate">{clienteNombre}</p>
              <p class="text-[11.5px] text-slate-500 truncate">
                {clienteDoc}{clienteTelefono ? ' · ' + clienteTelefono : ''}
              </p>
            </div>
            <button
              type="button"
              onclick={quitarCliente}
              aria-label="Quitar cliente"
              class="toque-icono p-1.5 -m-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
            >
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>
        {:else}
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
              <Icon name="search" class="w-[17px] h-[17px]" />
            </span>
            <input
              type="text"
              bind:value={busquedaCliente}
              onfocus={() => (abiertoCliente = true)}
              oninput={() => (abiertoCliente = true)}
              placeholder="Buscar por documento o nombre…"
              aria-label="Buscar cliente"
              autocomplete="off"
              class="input-base pl-9"
            />
            {#if abiertoCliente}
              <div class="absolute inset-x-0 mt-1.5 z-30 bg-white rounded-xl shadow-[0_16px_40px_-14px_rgba(15,23,42,0.3)] ring-1 ring-slate-900/5 max-h-56 overflow-y-auto scrollbar-thin">
                {#if clientesFiltrados.length > 0}
                  {#each clientesFiltrados as c}
                    <button
                      type="button"
                      onclick={() => elegirCliente(c)}
                      class="toque w-full flex items-center gap-2.5 px-3.5 py-2.5 text-left hover:bg-blue-50/60 transition-colors border-b border-slate-50 last:border-0 cursor-pointer"
                    >
                      <span class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 text-[10px] font-bold flex items-center justify-center shrink-0">
                        {iniciales(c.nombres)}
                      </span>
                      <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-medium text-slate-800 truncate">{c.nombres}</span>
                        <span class="block text-[11px] text-slate-400 truncate">{c.identificacion}</span>
                      </span>
                      {#if c.telefono}
                        <Icon name="message" class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      {/if}
                    </button>
                  {/each}
                {:else}
                  <p class="px-4 py-5 text-center text-[12.5px] text-slate-400">
                    Sin clientes para “{busquedaCliente}” · <button type="button" class="text-blue-600 font-semibold underline" onclick={onNuevoCliente}>registrar uno</button>
                  </p>
                {/if}
              </div>
            {/if}
          </div>
        {/if}

        {#if clienteDoc && placasCliente.length > 0}
          <div class="mt-2.5 flex items-start gap-2">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mt-1 shrink-0">Placa del vehículo</span>
            <div class="flex flex-wrap gap-1.5">
              {#each placasCliente as ppla}
                <button
                  type="button"
                  onclick={() => (placa = ppla)}
                  aria-pressed={placa === ppla}
                  class={'rounded-lg px-2.5 py-1 font-mono text-[12px] font-semibold transition-colors cursor-pointer ' + (placa === ppla ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}
                >
                  {ppla}
                </button>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Placa (opcional) -->
        <div class="mt-2">
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><Icon name="car" class="w-4 h-4" /></span>
            <input type="text" value={placa} oninput={onPlacaInput} maxlength="7" placeholder="Placa del vehículo (opcional)" class="input-base pl-9 uppercase font-mono" aria-label="Placa del vehículo" style={bordeEstado(estPlaca)} aria-invalid={estPlaca.estado === 'error'} />
          </div>
          {#if estPlaca.estado !== 'vacio' && estPlaca.mensaje}
            <p class="mt-1 text-[11.5px] flex items-center gap-1 {claseEstado(estPlaca.estado)}">
              {#if estPlaca.estado === 'ok'}<Icon name="check" class="w-3.5 h-3.5 shrink-0" strokeWidth={3} />{/if}
              {#if estPlaca.estado === 'error'}<Icon name="alert" class="w-3.5 h-3.5 shrink-0" />{/if}
              <span>{estPlaca.mensaje}</span>
            </p>
          {/if}
        </div>
      </div>

      {#if preguntarWhatsapp}
        <div class="rounded-xl bg-emerald-50/60 ring-1 ring-inset ring-emerald-100 px-3.5 py-3">
          <div class="flex items-center gap-2 mb-2">
            <Icon name="message" class="w-4 h-4 text-emerald-600 shrink-0" />
            <p class="text-[13px] font-semibold text-slate-800">¿Enviar recibo por WhatsApp?</p>
            <span class="chip bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-200 ml-auto normal-case">Opcional</span>
          </div>
          <input
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            bind:value={telefono}
            placeholder="Número (ej. 3001234567)"
            aria-label="Número de WhatsApp para el recibo"
            class="input-base"
          />
          <p class="text-[11px] text-slate-400 mt-1.5">Al registrar la venta podrás enviar el recibo desde WhatsApp.</p>
        </div>
      {/if}

      {#if error}
        <div class="notice-error !mt-0" role="alert">
          <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
          <span>{error}</span>
        </div>
      {/if}

      <button
        type="button"
        onclick={onRegistrar}
        disabled={loading}
        class="toque w-full inline-flex items-center justify-center gap-2 rounded-[12px] bg-blue-600 text-white text-[15px] font-bold py-3.5
          shadow-[0_1px_2px_rgba(30,64,175,0.4),0_10px_24px_-10px_rgba(37,99,235,0.65)]
          hover:bg-blue-700 active:scale-[0.99] disabled:opacity-60 disabled:pointer-events-none
          transition-all duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-1"
      >
        {#if loading}
          <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
          </svg>
          Registrando venta…
        {:else}
          <Icon name="check" class="w-5 h-5" strokeWidth={2.4} />
          Registrar Venta
        {/if}
      </button>
    </div>
  {/if}
</div>
