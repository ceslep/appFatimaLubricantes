<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP } from '../utils';
  import { usuario, vistaActual } from '../stores';
  import Icon from './ui/Icon.svelte';
  import type { Venta } from '../types';

  // Datos del admin (toda la estación)
  let resumen = $state({ total_ventas: 0, total_items: 0, num_ventas: 0, total_entradas: 0 });
  let stockBajo = $state<{ producto: string; stock: number }[]>([]);
  // Ventas crudas (solo para el resumen personal del cajero)
  let ventas = $state<Venta[]>([]);
  let clientes = $state<{ identificacion: string; nombres: string; telefono: string }[]>([]);
  let loading = $state(true);

  // Rango de fechas del resumen personal (YYYY-MM-DD de los inputs date)
  let desde = $state(fechaInputHoy());
  let hasta = $state(fechaInputHoy());

  let esAdmin = $derived($usuario?.rol === 'admin');
  let miNombre = $derived(($usuario?.nombre || '').trim().toLowerCase());

  onMount(async () => {
    try {
      if ($usuario?.rol === 'admin') {
        const [r, inv] = await Promise.all([api.resumenDia(), api.inventario()]);
        resumen = r;
        stockBajo = inv.filter((i) => i.stock <= 5).sort((a, b) => a.stock - b.stock);
      } else {
        const [v, cli] = await Promise.all([api.listarVentas(), api.listarClientes()]);
        ventas = v;
        clientes = cli;
      }
    } catch (e) { console.error(e); }
    loading = false;
  });

  // ---- Utilidades de fecha ----
  function fechaInputHoy(): string {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // Convierte "06/09/2026 14:27" o "6/9/2026 14:27" a clave comparable "2026-09-06" (o null)
  function claveFecha(s: string): string | null {
    const parte = (s || '').trim().split(' ')[0];
    const m = parte.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!m) return null;
    return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
  }

  function fechaBonita(iso: string | null): string {
    if (!iso) return '';
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    return dt.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function setHoy() {
    desde = fechaInputHoy();
    hasta = fechaInputHoy();
  }

  function setTodo() {
    desde = '';
    hasta = '';
  }

  // ---- KPIs (admin) ----
  const adminKpis = $derived([
    { label: 'Ventas hoy', valor: formatCOP(resumen.total_ventas), detalle: 'Total facturado', icon: 'banknote', tile: 'bg-blue-50 text-blue-600' },
    { label: 'Transacciones', valor: String(resumen.num_ventas), detalle: 'Ventas registradas', icon: 'receipt', tile: 'bg-sky-50 text-sky-600' },
    { label: 'Items vendidos', valor: String(resumen.total_items), detalle: 'Unidades del día', icon: 'package', tile: 'bg-indigo-50 text-indigo-600' },
    { label: 'Entradas hoy', valor: String(resumen.total_entradas), detalle: 'Ingresos a inventario', icon: 'truck', tile: 'bg-emerald-50 text-emerald-600' },
  ]);

  // ---- Resumen personal (cajero): solo sus ventas dentro del rango ----
  let misVentas = $derived(
    ventas.filter((v) => (v.cajero || '').trim().toLowerCase() === miNombre)
  );

  let ventasRango = $derived(
    misVentas.filter((v) => {
      const k = claveFecha(v.fecha || '');
      if (!k) return false;
      if (desde && k < desde) return false;
      if (hasta && k > hasta) return false;
      return true;
    })
  );

  let totalRango = $derived(ventasRango.reduce((s, v) => s + Number(v.total || 0), 0));
  let itemsRango = $derived(ventasRango.reduce((s, v) => s + Number(v.cantidad || 0), 0));
  let ticketRango = $derived(ventasRango.length > 0 ? totalRango / ventasRango.length : 0);
  let ventasRangoLista = $derived([...ventasRango].reverse());

  let rangoTexto = $derived((() => {
    if (!desde && !hasta) return 'Todo tu historial de ventas';
    if (desde && hasta && desde === hasta) return 'Ventas del ' + fechaBonita(desde);
    if (desde && !hasta) return 'Desde el ' + fechaBonita(desde);
    if (!desde && hasta) return 'Hasta el ' + fechaBonita(hasta);
    return 'Del ' + fechaBonita(desde) + ' al ' + fechaBonita(hasta);
  })());

  const misKpis = $derived([
    { label: 'Ventas', valor: formatCOP(totalRango), detalle: 'Total facturado en el período', icon: 'banknote', tile: 'bg-blue-50 text-blue-600' },
    { label: 'Transacciones', valor: String(ventasRango.length), detalle: 'Ventas en el período', icon: 'receipt', tile: 'bg-sky-50 text-sky-600' },
    { label: 'Items vendidos', valor: String(itemsRango), detalle: 'Unidades vendidas', icon: 'package', tile: 'bg-indigo-50 text-indigo-600' },
    { label: 'Ticket promedio', valor: formatCOP(Math.round(ticketRango)), detalle: 'Valor promedio por venta', icon: 'tag', tile: 'bg-emerald-50 text-emerald-600' },
  ]);

  function chipPago(forma: string) {
    switch (forma) {
      case 'Tarjeta': return 'bg-sky-50 text-sky-700 ring-sky-200';
      case 'Transferencia': return 'bg-violet-50 text-violet-700 ring-violet-200';
      case 'Efectivo': return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
      default: return 'bg-slate-100 text-slate-600 ring-slate-200';
    }
  }

  function horaDe(fecha: string): string {
    return fecha.includes(' ') ? fecha.split(' ')[1] : fecha;
  }

  // ---- Reenvío de recibo por WhatsApp a clientes registrados ----
  function numeroWa(s: string): string {
    let d = (s || '').replace(/\D/g, '');
    if (d.startsWith('00')) d = d.slice(2);
    if (d.length === 10) d = '57' + d;
    if (d.length === 11 && d.startsWith('0')) d = '57' + d.slice(1);
    return d;
  }

  function telClienteDeVenta(v: Venta): string {
    if (!v.cliente_doc) return '';
    const c = clientes.find((x) => (x.identificacion || '').trim().toLowerCase() === (v.cliente_doc || '').trim().toLowerCase());
    return c ? (c.telefono || '') : '';
  }

  function urlReenvioWhatsapp(v: Venta): string {
    const n = numeroWa(telClienteDeVenta(v));
    const detalle = v.producto + (v.presentacion ? ' (' + v.presentacion + ')' : '');
    const lineas = [
      '*ESTACIÓN FÁTIMA · LUBRICANTES*',
      'Recibo de venta',
      'Fecha: ' + (v.fecha || ''),
      'Cajero: ' + (v.cajero || ''),
      v.cliente ? 'Cliente: ' + v.cliente : '',
      '──────────────────',
      '• ' + detalle + ' x' + v.cantidad + ' … ' + formatCOP(v.total),
      'Pago: ' + (v.forma_pago || '—'),
      'TOTAL: ' + formatCOP(v.total),
      '',
      '¡Gracias por tu compra!',
    ].filter(Boolean);
    return 'https://wa.me/' + n + '?text=' + encodeURIComponent(lineas.join('\n'));
  }
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between gap-3">
    <div>
      <h2 class="text-[15px] font-semibold text-slate-900 tracking-tight">
        {esAdmin ? 'Resumen del día' : 'Mi resumen de ventas'}
      </h2>
      <p class="text-[12.5px] text-slate-500">
        {esAdmin ? 'Actividad de hoy en la estación' : rangoTexto}
      </p>
    </div>
    <button
      onclick={() => vistaActual.set('registrar-venta')}
      class="hidden sm:inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-3.5 py-2
        shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)] hover:bg-blue-700 active:scale-[0.98]
        transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
    >
      <Icon name="plus" class="w-4 h-4" />
      Nueva venta
    </button>
  </div>

  {#if loading}
    <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-4 gap-4">
      {#each Array(4) as _}
        <div class="panel p-5 animate-pulse">
          <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-[14px] bg-slate-200/70"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 w-20 rounded bg-slate-200/70"></div>
              <div class="h-5 w-28 rounded bg-slate-200/60"></div>
            </div>
          </div>
        </div>
      {/each}
    </div>
  {:else if esAdmin}
    <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-4 gap-4">
      {#each adminKpis as kpi}
        <div class="panel panel-hover p-5">
          <div class="flex items-start justify-between">
            <div class="min-w-0">
              <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">{kpi.label}</p>
              <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900 truncate">{kpi.valor}</p>
              <p class="mt-1.5 text-[11.5px] text-slate-400 truncate">{kpi.detalle}</p>
            </div>
            <div class="w-11 h-11 rounded-[14px] flex items-center justify-center shrink-0 {kpi.tile}">
              <Icon name={kpi.icon} class="w-[22px] h-[22px]" />
            </div>
          </div>
        </div>
      {/each}
    </div>

    {#if stockBajo.length > 0}
      <div class="panel overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-amber-50/80 to-white">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-[11px] bg-amber-100 text-amber-600 flex items-center justify-center">
              <Icon name="alert" class="w-[19px] h-[19px]" />
            </div>
            <div>
              <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Alerta de stock bajo</h3>
              <p class="text-[12px] text-slate-500">{stockBajo.length} producto{stockBajo.length === 1 ? '' : 's'} por debajo del mínimo</p>
            </div>
          </div>
          <button
            onclick={() => vistaActual.set('registrar-entrada')}
            class="hidden sm:inline-flex items-center gap-1.5 rounded-lg text-[12.5px] font-semibold text-amber-700 hover:text-amber-800 px-2.5 py-1.5 hover:bg-amber-50 transition-colors cursor-pointer"
          >
            <Icon name="plus" class="w-3.5 h-3.5" />
            Registrar entrada
          </button>
        </div>
        <ul class="divide-y divide-slate-100">
          {#each stockBajo as item}
            <li class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
              <div class="flex items-center gap-3 min-w-0">
                <span class="w-2 h-2 rounded-full shrink-0 {item.stock <= 0 ? 'bg-rose-500' : 'bg-amber-400'}"></span>
                <span class="text-[13.5px] font-medium text-slate-800 truncate">{item.producto}</span>
              </div>
              <span class="chip ring-1 ring-inset {item.stock <= 0 ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-amber-50 text-amber-700 ring-amber-200'}">
                {item.stock <= 0 ? 'Agotado' : item.stock + ' unidad' + (item.stock === 1 ? '' : 'es')}
              </span>
            </li>
          {/each}
        </ul>
      </div>
    {/if}
  {:else}
    <!-- Resumen personal del cajero -->
    <!-- Filtro por rango de fechas -->
    <div class="panel p-4">
      <div class="flex flex-col lg:flex-row lg:items-end gap-3">
        <div class="grid grid-cols-2 gap-3 flex-1">
          <div>
            <label class="field-label" for="res-desde">Desde</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="calendar" class="w-[17px] h-[17px]" />
              </span>
              <input id="res-desde" type="date" bind:value={desde} class="input-base pl-9" />
            </div>
          </div>
          <div>
            <label class="field-label" for="res-hasta">Hasta</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="calendar" class="w-[17px] h-[17px]" />
              </span>
              <input id="res-hasta" type="date" bind:value={hasta} class="input-base pl-9" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            onclick={setHoy}
            class="inline-flex items-center gap-1.5 rounded-[10px] bg-blue-50 text-blue-700 text-[12.5px] font-semibold px-3 py-2 ring-1 ring-inset ring-blue-100 hover:bg-blue-100 transition-colors cursor-pointer"
          >
            <Icon name="check" class="w-3.5 h-3.5" />
            Hoy
          </button>
          <button
            type="button"
            onclick={setTodo}
            class="inline-flex items-center gap-1.5 rounded-[10px] bg-slate-100 text-slate-600 text-[12.5px] font-semibold px-3 py-2 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer"
          >
            Todo el historial
          </button>
        </div>
      </div>
      <p class="mt-2.5 text-[12px] text-slate-500 flex items-center gap-1.5">
        <Icon name="history" class="w-3.5 h-3.5 text-slate-400" />
        {rangoTexto} &middot; {ventasRango.length} venta{ventasRango.length === 1 ? '' : 's'} en total
      </p>
    </div>

    <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-4 gap-4">
      {#each misKpis as kpi}
        <div class="panel panel-hover p-5">
          <div class="flex items-start justify-between">
            <div class="min-w-0">
              <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">{kpi.label}</p>
              <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900 truncate">{kpi.valor}</p>
              <p class="mt-1.5 text-[11.5px] text-slate-400 truncate">{kpi.detalle}</p>
            </div>
            <div class="w-11 h-11 rounded-[14px] flex items-center justify-center shrink-0 {kpi.tile}">
              <Icon name={kpi.icon} class="w-[22px] h-[22px]" />
            </div>
          </div>
        </div>
      {/each}
    </div>

    <div class="panel overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between gap-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-[11px] bg-blue-50 text-blue-600 flex items-center justify-center">
            <Icon name="receipt" class="w-[19px] h-[19px]" />
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Tus ventas</h3>
            <p class="text-[12px] text-slate-500">{rangoTexto}</p>
          </div>
        </div>
        <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">{ventasRango.length} venta{ventasRango.length === 1 ? '' : 's'}</span>
      </div>

      {#if ventasRangoLista.length > 0}
        <div class="max-h-[420px] overflow-y-auto scrollbar-thin divide-y divide-slate-100">
          {#each ventasRangoLista as v}
            <div class="px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
              <div class="flex items-center gap-3 min-w-0">
                <span class="w-9 h-9 rounded-[10px] bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                  <Icon name="droplet" class="w-[17px] h-[17px]" />
                </span>
                <div class="min-w-0">
                  <p class="text-[13.5px] font-semibold text-slate-800 truncate">{v.producto}</p>
                  <p class="text-[11.5px] text-slate-400">
                    {horaDe(v.fecha || '')} &middot; {v.cantidad} und &middot; {v.cliente ? v.cliente : 'Sin cliente'}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                {#if v.cliente_doc && telClienteDeVenta(v)}
                  <a
                    href={urlReenvioWhatsapp(v)}
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Reenviar recibo por WhatsApp al cliente"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-emerald-600 hover:bg-emerald-50 transition-colors"
                  >
                    <Icon name="message" class="w-4 h-4" />
                    <span class="hidden md:inline text-[11px] font-bold">WhatsApp</span>
                  </a>
                {/if}
                <span class="hidden xs:inline-flex chip ring-1 ring-inset {chipPago(v.forma_pago)}">{v.forma_pago || '—'}</span>
                <span class="font-bold text-[14px] text-slate-900">{formatCOP(v.total)}</span>
              </div>
            </div>
          {/each}
        </div>
      {:else}
        <div class="flex flex-col items-center justify-center text-center px-6 py-12">
          <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
            <Icon name="receipt" class="w-7 h-7" strokeWidth={1.5} />
          </div>
          <p class="text-[14px] font-semibold text-slate-600">No hay ventas en este período</p>
          <p class="text-[12.5px] text-slate-400 mt-1 max-w-[280px]">Prueba con otro rango de fechas o registra una nueva venta.</p>
          <button
            onclick={() => vistaActual.set('registrar-venta')}
            class="mt-4 inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-4 py-2.5
              shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
              hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer"
          >
            <Icon name="cart" class="w-4 h-4" />
            Registrar venta
          </button>
        </div>
      {/if}
    </div>
  {/if}
</div>
