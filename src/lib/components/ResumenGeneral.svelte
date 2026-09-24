<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario } from '../stores';
  import { formatCOP } from '../utils';
  import type { ResumenGeneral } from '../types';
  import Icon from './ui/Icon.svelte';
  import Table from './ui/Table.svelte';

  function hoyISO(): string {
    const d = new Date();
    return (
      d.getFullYear() +
      '-' +
      String(d.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }

  function iso(fecha: Date): string {
    return (
      fecha.getFullYear() +
      '-' +
      String(fecha.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(fecha.getDate()).padStart(2, '0')
    );
  }

  // La API usa dd/mm/YYYY (igual que ventas, entradas e islas).
  function aApi(diaISO: string): string {
    return diaISO.split('-').reverse().join('/');
  }

  let desdeISO = $state(hoyISO());
  let hastaISO = $state(hoyISO());
  let filtroCajero = $state('');
  let cajeros = $state<{ valor: string; etiqueta: string }[]>([]);
  let resumen = $state<ResumenGeneral | null>(null);
  let cargando = $state(true);
  let error = $state('');

  // El admin elige a quién mirar; el cajero queda acotado a sus propios
  // movimientos sin poder cambiarlo.
  let esAdmin = $derived($usuario?.rol === 'admin');
  let autorPropio = $derived((($usuario?.nombre || $usuario?.usuario) || '').trim());
  let cajeroActivo = $derived(esAdmin ? filtroCajero : autorPropio);

  onMount(() => {
    // La lista de cajeros solo la necesita el admin para filtrar.
    if (esAdmin) void cargarCajeros();
    void cargar();
  });

  // Los cajeros salen de la hoja de usuarios (compartida entre negocios).
  // El valor del filtro es el nombre visible, que es lo que guardan las ventas;
  // en islas además coincide con el login guardado en la columna Usuario.
  async function cargarCajeros() {
    try {
      const usuarios = await api.listarUsuarios();
      const vistos = new Set<string>();
      const lista: { valor: string; etiqueta: string }[] = [];
      for (const u of usuarios) {
        if (u.rol === 'admin') continue;
        const nombre = (u.nombre || '').trim();
        const login = (u.usuario || '').trim();
        const valor = nombre || login;
        if (!valor || vistos.has(valor.toLowerCase())) continue;
        vistos.add(valor.toLowerCase());
        lista.push({
          valor,
          etiqueta:
            (nombre || login) +
            (login ? ' (@' + login + ')' : '') +
            (u.activo === 'TRUE' ? '' : ' — inactivo'),
        });
      }
      cajeros = lista.sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
    } catch (e) {
      console.error(e);
    }
  }

  async function cargar() {
    cargando = true;
    error = '';
    try {
      resumen = await api.resumenGeneral(aApi(desdeISO), aApi(hastaISO), cajeroActivo);
    } catch (e: any) {
      error = e?.message || 'No se pudo cargar el resumen';
      resumen = null;
    }
    cargando = false;
  }

  async function aplicarRango(desde: string, hasta: string) {
    desdeISO = desde;
    hastaISO = hasta;
    await cargar();
  }

  function hoy() {
    const d = hoyISO();
    aplicarRango(d, d);
  }

  function ayer() {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const x = iso(d);
    aplicarRango(x, x);
  }

  function esteMes() {
    const d = new Date();
    const primero = new Date(d.getFullYear(), d.getMonth(), 1);
    aplicarRango(iso(primero), iso(d));
  }

  function mesPasado() {
    const d = new Date();
    const primero = new Date(d.getFullYear(), d.getMonth() - 1, 1);
    const ultimo = new Date(d.getFullYear(), d.getMonth(), 0);
    aplicarRango(iso(primero), iso(ultimo));
  }

  let combustibles = $derived(resumen ? Object.entries(resumen.islas.combustibles) : []);

  function formatearGalones(v: number): string {
    return v.toLocaleString('es-CO', { maximumFractionDigits: 3 });
  }

  function chipRango(desde: string, hasta: string): string {
    return desde === hasta ? desde : `${desde} → ${hasta}`;
  }

  let etiquetaCajero = $derived(
    cajeros.find((c) => c.valor === cajeroActivo)?.etiqueta || cajeroActivo
  );

  const botones = [
    { label: 'Hoy', fn: hoy },
    { label: 'Ayer', fn: ayer },
    { label: 'Este mes', fn: esteMes },
    { label: 'Mes pasado', fn: mesPasado },
  ];
</script>

<div class="space-y-5">
  <!-- Rango de fechas -->
  <div class="panel p-5 sm:p-6">
    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
      <div class="flex items-start gap-3 min-w-0">
        <div class="w-10 h-10 rounded-[12px] bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
          <Icon name="chart" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">
            {esAdmin ? 'Resumen general' : 'Mi resumen general'}
          </h3>
          <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
            Total de <strong class="text-slate-700">islas</strong> (combustible),
            <strong class="text-slate-700">Tienda Fátima</strong> y
            <strong class="text-slate-700">Estación Fátima</strong> en el rango elegido.
            {esAdmin ? 'Puedes filtrar por cajero.' : 'Solo se muestran tus movimientos.'}
          </p>
        </div>
      </div>

      <div class="lg:ml-auto flex flex-col sm:flex-row sm:items-end gap-3 shrink-0">
        <div>
          <label class="field-label" for="rg-desde">Desde</label>
          <input id="rg-desde" type="date" bind:value={desdeISO} class="input-base" />
        </div>
        <div>
          <label class="field-label" for="rg-hasta">Hasta</label>
          <input id="rg-hasta" type="date" bind:value={hastaISO} class="input-base" />
        </div>
        {#if esAdmin}
          <div>
            <label class="field-label" for="rg-cajero">Cajero</label>
            <select
              id="rg-cajero"
              bind:value={filtroCajero}
              onchange={cargar}
              class="input-base appearance-none cursor-pointer pr-9 sm:min-w-[190px]"
            >
              <option value="">Todos los cajeros</option>
              {#each cajeros as c (c.valor)}
                <option value={c.valor}>{c.etiqueta}</option>
              {/each}
            </select>
          </div>
        {:else}
          <div>
            <span class="field-label">Cajero</span>
            <div class="input-base flex items-center gap-2 bg-slate-50 text-slate-600 cursor-default" title="Solo ves tus propios movimientos">
              <Icon name="user" class="w-4 h-4 text-slate-400 shrink-0" />
              <span class="truncate">{autorPropio || '—'}</span>
            </div>
          </div>
        {/if}
        <button
          type="button"
          onclick={cargar}
          disabled={cargando}
          class="toque inline-flex items-center justify-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-4 py-2.5
            shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
            hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer disabled:opacity-60 disabled:pointer-events-none
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
        >
          <Icon name="search" class="w-4 h-4" strokeWidth={2.4} />
          {cargando ? 'Consultando…' : 'Consultar'}
        </button>
      </div>
    </div>

    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
      {#each botones as b}
        <button
          type="button"
          onclick={b.fn}
          disabled={cargando}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold ring-1 ring-inset transition-colors cursor-pointer
            bg-white text-slate-600 ring-slate-200 hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none"
        >
          {b.label}
        </button>
      {/each}
      {#if filtroCajero}
        <button
          type="button"
          onclick={() => { filtroCajero = ''; void cargar(); }}
          title="Quitar el filtro de cajero"
          class="toque chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200 hover:bg-blue-100 transition-colors cursor-pointer"
        >
          <Icon name="user" class="w-3.5 h-3.5" />
          {etiquetaCajero}
          <Icon name="x" class="w-3.5 h-3.5" />
        </button>
      {/if}
      <span class="ml-auto chip bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">
        {chipRango(aApi(desdeISO), aApi(hastaISO))}
      </span>
    </div>
  </div>

  {#if error}
    <p class="text-[13px] text-rose-600 flex items-start gap-2 px-1">
      <Icon name="alert" class="w-4 h-4 mt-[2px] shrink-0" />
      {error}
    </p>
  {/if}

  {#if cargando && !resumen}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      {#each Array(3) as _}
        <div class="panel p-5 space-y-3">
          <div class="h-3 w-24 rounded bg-slate-100 animate-pulse"></div>
          <div class="h-6 w-32 rounded bg-slate-100 animate-pulse"></div>
        </div>
      {/each}
    </div>
  {:else if resumen}
    <!-- Totales por unidad de negocio -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="panel p-5">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-[12px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <Icon name="fuel" class="w-5 h-5" />
          </span>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Islas</p>
            <p class="text-[22px] font-extrabold text-slate-900 leading-tight">{formatCOP(resumen.islas.total)}</p>
          </div>
        </div>
        <p class="text-[12px] text-slate-500 mt-3">
          {combustibles.length === 0
            ? 'Sin lecturas registradas en el rango.'
            : combustibles.map(([c, d]) => `${c}: ${formatearGalones(d.galones)} gal`).join(' · ')}
        </p>
      </div>

      <div class="panel p-5">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-[12px] bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
            <Icon name="cart" class="w-5 h-5" />
          </span>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tienda Fátima</p>
            <p class="text-[22px] font-extrabold text-slate-900 leading-tight">{formatCOP(resumen.tienda.total_ventas)}</p>
          </div>
        </div>
        <p class="text-[12px] text-slate-500 mt-3">
          {resumen.tienda.num_ventas} venta{resumen.tienda.num_ventas === 1 ? '' : 's'}
          · {formatearGalones(resumen.tienda.total_items)} ítems
        </p>
      </div>

      <div class="panel p-5">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-[12px] bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <Icon name="building" class="w-5 h-5" />
          </span>
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Estación Fátima</p>
            <p class="text-[22px] font-extrabold text-slate-900 leading-tight">{formatCOP(resumen.estacion.total_ventas)}</p>
          </div>
        </div>
        <p class="text-[12px] text-slate-500 mt-3">
          {resumen.estacion.num_ventas} venta{resumen.estacion.num_ventas === 1 ? '' : 's'}
          · {formatearGalones(resumen.estacion.total_items)} ítems
        </p>
      </div>
    </div>

    <!-- Gran total -->
    <div class="panel p-5 sm:p-6 bg-gradient-to-br from-blue-600 to-sky-500 !ring-0">
      <div class="flex items-center gap-4">
        <div class="w-11 h-11 rounded-[13px] bg-white/20 text-white flex items-center justify-center shrink-0">
          <Icon name="banknote" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <p class="text-[11.5px] font-bold uppercase tracking-wide text-white/75">
            Total islas + tienda + estación{resumen.cajero ? ' · ' + etiquetaCajero : ''}
          </p>
          <p class="text-[26px] font-extrabold text-white leading-tight">{formatCOP(resumen.total)}</p>
        </div>
        <div class="ml-auto hidden sm:block text-right text-white/85">
          <p class="text-[12px]">Islas {formatCOP(resumen.islas.total)}</p>
          <p class="text-[12px]">Tienda {formatCOP(resumen.tienda.total_ventas)}</p>
          <p class="text-[12px]">Estación {formatCOP(resumen.estacion.total_ventas)}</p>
        </div>
      </div>
    </div>

    <!-- Resumen por cajero: solo el admin compara cajeros -->
    {#if esAdmin}
    <div class="panel overflow-hidden">
      <div class="px-5 py-4 flex items-center gap-2 border-b border-slate-100">
        <Icon name="users" class="w-[18px] h-[18px] text-slate-400" />
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Resumen por cajero</h3>
        <span class="ml-auto chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
          {resumen.por_cajero.length} cajero{resumen.por_cajero.length === 1 ? '' : 's'}
        </span>
      </div>

      {#if resumen.por_cajero.length === 0}
        <p class="px-5 py-10 text-center text-[13px] text-slate-400">
          Ningún cajero tiene movimientos en el rango seleccionado.
        </p>
      {:else}
        <!-- Escritorio: tabla -->
        <div class="hidden md:block">
          <Table headers={['Cajero', 'Islas', 'Tienda', 'Estación', 'Total']}>
            {#each resumen.por_cajero as f (f.cajero)}
              <tr class="hover:bg-blue-50/30 transition-colors">
                <td class="px-4 py-3.5">
                  <p class="text-[13.5px] font-semibold text-slate-900">{f.cajero}</p>
                  <p class="text-[11px] text-slate-400">
                    {f.num_ventas} venta{f.num_ventas === 1 ? '' : 's'}
                    {#if f.islas_galones > 0}· {formatearGalones(f.islas_galones)} gal{/if}
                  </p>
                </td>
                <td class="px-4 py-3.5 text-[13px] font-semibold text-amber-700 whitespace-nowrap">{formatCOP(f.islas)}</td>
                <td class="px-4 py-3.5 text-[13px] text-slate-600 whitespace-nowrap">{formatCOP(f.tienda)}</td>
                <td class="px-4 py-3.5 text-[13px] text-slate-600 whitespace-nowrap">{formatCOP(f.estacion)}</td>
                <td class="px-4 py-3.5 text-[13.5px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(f.total)}</td>
              </tr>
            {/each}
            <tr class="bg-slate-50/70">
              <td class="px-4 py-3.5 text-[12px] font-bold uppercase tracking-wide text-slate-500">Total</td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(resumen.islas.total)}</td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(resumen.tienda.total_ventas)}</td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(resumen.estacion.total_ventas)}</td>
              <td class="px-4 py-3.5 text-[13.5px] font-extrabold text-slate-900 whitespace-nowrap">{formatCOP(resumen.total)}</td>
            </tr>
          </Table>
        </div>

        <!-- Móvil: tarjetas -->
        <div class="md:hidden divide-y divide-slate-100">
          {#each resumen.por_cajero as f (f.cajero)}
            <div class="px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <p class="text-[14px] font-bold text-slate-900 truncate">{f.cajero}</p>
                <p class="text-[15px] font-extrabold text-slate-900 shrink-0">{formatCOP(f.total)}</p>
              </div>
              <p class="text-[11.5px] text-slate-400 mt-0.5">
                {f.num_ventas} venta{f.num_ventas === 1 ? '' : 's'}
                {#if f.islas_galones > 0}· {formatearGalones(f.islas_galones)} gal{/if}
              </p>
              <div class="mt-2.5 grid grid-cols-3 gap-2">
                <div class="rounded-lg bg-amber-50/70 ring-1 ring-inset ring-amber-100 px-2.5 py-2">
                  <p class="text-[9.5px] font-bold uppercase tracking-wide text-amber-700/70">Islas</p>
                  <p class="text-[12.5px] font-bold text-amber-800">{formatCOP(f.islas)}</p>
                </div>
                <div class="rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-100 px-2.5 py-2">
                  <p class="text-[9.5px] font-bold uppercase tracking-wide text-slate-400">Tienda</p>
                  <p class="text-[12.5px] font-bold text-slate-700">{formatCOP(f.tienda)}</p>
                </div>
                <div class="rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-100 px-2.5 py-2">
                  <p class="text-[9.5px] font-bold uppercase tracking-wide text-slate-400">Estación</p>
                  <p class="text-[12.5px] font-bold text-slate-700">{formatCOP(f.estacion)}</p>
                </div>
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>
    {/if}

    <!-- Resumen por forma de pago -->
    <div class="panel overflow-hidden">
      <div class="px-5 py-4 flex items-center gap-2 border-b border-slate-100">
        <Icon name="card" class="w-[18px] h-[18px] text-slate-400" />
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Por forma de pago</h3>
        <span class="text-[11.5px] text-slate-400 hidden sm:inline">Solo ventas de tienda y estación</span>
        <span class="ml-auto chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
          {resumen.por_forma_pago.length} forma{resumen.por_forma_pago.length === 1 ? '' : 's'}
        </span>
      </div>

      {#if resumen.por_forma_pago.length === 0}
        <p class="px-5 py-10 text-center text-[13px] text-slate-400">
          No hay ventas en el rango seleccionado.
        </p>
      {:else}
        <!-- Escritorio: tabla -->
        <div class="hidden md:block">
          <Table headers={['Forma de pago', 'Ventas', 'Tienda', 'Estación', 'Total']}>
            {#each resumen.por_forma_pago as f (f.forma)}
              <tr class="hover:bg-blue-50/30 transition-colors">
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-[9px] bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                      <Icon name="card" class="w-4 h-4" />
                    </span>
                    <span class="text-[13.5px] font-semibold text-slate-900">{f.forma}</span>
                  </div>
                </td>
                <td class="px-4 py-3.5 text-[13px] text-slate-500">{f.num_ventas}</td>
                <td class="px-4 py-3.5 text-[13px] text-slate-600 whitespace-nowrap">{formatCOP(f.tienda)}</td>
                <td class="px-4 py-3.5 text-[13px] text-slate-600 whitespace-nowrap">{formatCOP(f.estacion)}</td>
                <td class="px-4 py-3.5 text-[13.5px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(f.total)}</td>
              </tr>
            {/each}
            <tr class="bg-slate-50/70">
              <td class="px-4 py-3.5 text-[12px] font-bold uppercase tracking-wide text-slate-500">Total</td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900">
                {resumen.tienda.num_ventas + resumen.estacion.num_ventas}
              </td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(resumen.tienda.total_ventas)}</td>
              <td class="px-4 py-3.5 text-[13px] font-bold text-slate-900 whitespace-nowrap">{formatCOP(resumen.estacion.total_ventas)}</td>
              <td class="px-4 py-3.5 text-[13.5px] font-extrabold text-slate-900 whitespace-nowrap">
                {formatCOP(resumen.tienda.total_ventas + resumen.estacion.total_ventas)}
              </td>
            </tr>
          </Table>
        </div>

        <!-- Móvil: tarjetas -->
        <div class="md:hidden divide-y divide-slate-100">
          {#each resumen.por_forma_pago as f (f.forma)}
            <div class="px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="w-8 h-8 rounded-[9px] bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                    <Icon name="card" class="w-4 h-4" />
                  </span>
                  <div class="min-w-0">
                    <p class="text-[13.5px] font-bold text-slate-900 truncate">{f.forma}</p>
                    <p class="text-[11px] text-slate-400">{f.num_ventas} venta{f.num_ventas === 1 ? '' : 's'}</p>
                  </div>
                </div>
                <p class="text-[14.5px] font-extrabold text-slate-900 shrink-0">{formatCOP(f.total)}</p>
              </div>
              <div class="mt-2.5 grid grid-cols-2 gap-2">
                <div class="rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-100 px-2.5 py-2">
                  <p class="text-[9.5px] font-bold uppercase tracking-wide text-slate-400">Tienda</p>
                  <p class="text-[12.5px] font-bold text-slate-700">{formatCOP(f.tienda)}</p>
                </div>
                <div class="rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-100 px-2.5 py-2">
                  <p class="text-[9.5px] font-bold uppercase tracking-wide text-slate-400">Estación</p>
                  <p class="text-[12.5px] font-bold text-slate-700">{formatCOP(f.estacion)}</p>
                </div>
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Detalle de combustibles -->
    <div class="panel overflow-hidden">
      <div class="px-5 py-4 flex items-center gap-2 border-b border-slate-100">
        <Icon name="droplet" class="w-[18px] h-[18px] text-slate-400" />
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Detalle de islas por combustible</h3>
      </div>

      {#if combustibles.length === 0}
        <p class="px-5 py-10 text-center text-[13px] text-slate-400">
          No hay lecturas de islas en el rango seleccionado.
        </p>
      {:else}
        <div class="divide-y divide-slate-100">
          {#each combustibles as [comb, d] (comb)}
            <div class="px-5 py-4 flex items-center gap-3">
              <span class="w-2 h-2 rounded-full shrink-0 {comb === 'Gasolina' ? 'bg-emerald-500' : 'bg-slate-700'}"></span>
              <div class="min-w-0">
                <p class="text-[13.5px] font-semibold text-slate-800">{comb}</p>
                <p class="text-[11.5px] text-slate-400">
                  {d.registros} registro{d.registros === 1 ? '' : 's'} · {formatearGalones(d.galones)} galones
                </p>
              </div>
              <p class="ml-auto text-[14px] font-bold text-slate-900">{formatCOP(d.total)}</p>
            </div>
          {/each}
        </div>
        <div class="px-5 py-3.5 bg-slate-50/60 border-t border-slate-100 flex items-center justify-between">
          <p class="text-[12.5px] text-slate-500">Total combustible</p>
          <p class="text-[15px] font-extrabold text-slate-900">{formatCOP(resumen.islas.total)}</p>
        </div>
      {/if}
    </div>
  {/if}
</div>
