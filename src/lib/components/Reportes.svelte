<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { formatCOP, descargarCSV } from '../utils';
  import { usuario } from '../stores';
  import Icon from './ui/Icon.svelte';
  import type { Venta, Entrada } from '../types';

  interface InvItem {
    producto: string;
    presentacion: string;
    stock: number;
    precio_venta: number;
  }

  type Tab = 'ventas' | 'inventario' | 'productos';

  const tabsDefs: { id: Tab; label: string; icon: string }[] = [
    { id: 'ventas', label: 'Ventas', icon: 'receipt' },
    { id: 'inventario', label: 'Inventario', icon: 'droplet' },
    { id: 'productos', label: 'Productos', icon: 'trending-up' },
  ];

  let tab = $state<Tab>('ventas');
  let loading = $state(true);

  let esAdmin = $derived($usuario?.rol === 'admin');
  let miNombre = $derived(($usuario?.nombre || '').trim().toLowerCase());

  // Rango de fechas (por defecto: mes en curso)
  let desde = $state(primerDiaMesIso());
  let hasta = $state(fechaIsoHoy());

  let ventas = $state<Venta[]>([]);
  let inventario = $state<InvItem[]>([]);
  let entradas = $state<Entrada[]>([]);

  const COLORES = {
    efectivo: '#10b981',
    tarjeta: '#0ea5e9',
    transferencia: '#8b5cf6',
    otro: '#94a3b8',
    disponible: '#10b981',
    bajo: '#f59e0b',
    agotado: '#f43f5e',
  };

  // ---------- Utilidades de fecha ----------
  function fmtIso(d: Date): string {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function fechaIsoHoy(): string {
    return fmtIso(new Date());
  }
  function primerDiaMesIso(): string {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';
  }
  // "06/09/2026 14:27" o "6/9/2026 14:27" -> "2026-09-06"
  function claveFecha(s: string): string | null {
    const parte = (s || '').trim().split(' ')[0];
    const m = parte.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!m) return null;
    return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
  }
  function diffDias(a: string, b: string): number {
    const da = new Date(a + 'T00:00:00').getTime();
    const db = new Date(b + 'T00:00:00').getTime();
    return Math.round((db - da) / 86400000);
  }
  function sumarDias(iso: string, n: number): string {
    const [y, m, d] = iso.split('-').map(Number);
    return fmtIso(new Date(y, m - 1, d + n));
  }
  function mesSiguiente(mkey: string): string {
    const [y, m] = mkey.split('-').map(Number);
    const d = new Date(y, m, 1);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  }
  function etiquetaDia(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short' });
  }
  function etiquetaMes(mkey: string): string {
    const [y, m] = mkey.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString('es-CO', { month: 'short', year: '2-digit' });
  }
  function isoBonita(iso: string | null): string {
    if (!iso) return '';
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function fmtNum(n: number): string {
    return (n || 0).toLocaleString('es-CO');
  }

  function textoEstadoStock(stock: number): string {
    if (stock <= 0) return 'Agotado';
    if (stock <= 5) return 'Stock bajo';
    return 'Disponible';
  }

  function exportarReporte() {
    if (tab === 'ventas') {
      const filas = ventasR.map((v) => [v.fecha || '', v.producto || '', v.presentacion || '', v.cantidad || 0, v.precio_unitario || 0, v.total || 0, v.forma_pago || '', v.cliente || '', v.placa || '', v.cajero || '']);
      descargarCSV('reporte_ventas', ['Fecha', 'Producto', 'Presentación', 'Cantidad', 'Precio unitario', 'Total', 'Forma de pago', 'Cliente', 'Placa', 'Cajero'], filas);
    } else if (tab === 'inventario') {
      const filas = inventario.map((i) => [i.producto, i.presentacion, Number(i.stock || 0), Number(i.precio_venta || 0), Number(i.stock || 0) * Number(i.precio_venta || 0), textoEstadoStock(Number(i.stock || 0))]);
      descargarCSV('reporte_inventario', ['Producto', 'Presentación', 'Stock', 'Precio venta', 'Valor', 'Estado'], filas);
    } else {
      const lista = [...statProductos].sort((a, b) => b.vendidos - a.vendidos);
      const filas = lista.map((p) => [p.producto, p.presentacion, p.stock, p.precio, p.vendidos, p.monto]);
      descargarCSV('reporte_productos', ['Producto', 'Presentación', 'Stock', 'Precio venta', 'Unidades vendidas', 'Ventas $'], filas);
    }
  }

  function setHoy() { desde = fechaIsoHoy(); hasta = fechaIsoHoy(); }
  function set7dias() { hasta = fechaIsoHoy(); desde = sumarDias(hasta, -6); }
  function setMes() { desde = primerDiaMesIso(); hasta = fechaIsoHoy(); }
  function setAnio() { const y = new Date().getFullYear(); desde = y + '-01-01'; hasta = fechaIsoHoy(); }
  function setTodo() { desde = ''; hasta = ''; }

  onMount(async () => {
    try {
      if ($usuario?.rol === 'admin') {
        const [v, inv, ent] = await Promise.all([api.listarVentas(), api.inventario(), api.listarEntradas()]);
        ventas = v;
        inventario = inv;
        entradas = ent;
      } else {
        ventas = await api.listarVentas();
      }
    } catch (e) { console.error(e); }
    loading = false;
  });

  // ---------- Filtros ----------
  let rangoValido = $derived(!desde || !hasta || desde <= hasta);

  let ventasR = $derived(
    ventas
      .filter((v) => esAdmin || (v.cajero || '').trim().toLowerCase() === miNombre)
      .filter((v) => {
      const k = claveFecha(v.fecha || '');
      if (!k) return false;
      if (desde && k < desde) return false;
      if (hasta && k > hasta) return false;
      return true;
    })
  );

  let rangoTexto = $derived((() => {
    if (!desde && !hasta) return 'Todo el historial';
    if (desde && hasta && desde === hasta) return 'Ventas del ' + isoBonita(desde);
    if (desde && !hasta) return 'Desde el ' + isoBonita(desde);
    if (!desde && hasta) return 'Hasta el ' + isoBonita(hasta);
    return 'Del ' + isoBonita(desde) + ' al ' + isoBonita(hasta);
  })());

  // ---------- Ventas: KPIs y serie ----------
  let totalVentasR = $derived(ventasR.reduce((s, v) => s + Number(v.total || 0), 0));
  let itemsVentasR = $derived(ventasR.reduce((s, v) => s + Number(v.cantidad || 0), 0));
  let ticketR = $derived(ventasR.length > 0 ? totalVentasR / ventasR.length : 0);

  interface PuntoSerie { clave: string; etiqueta: string; total: number; }
  let serie = $derived((() => {
    const claves = ventasR.map((v) => claveFecha(v.fecha || '')).filter((k): k is string => Boolean(k));
    let inicio = desde || (claves.length ? claves.reduce((a, b) => (a < b ? a : b)) : fechaIsoHoy());
    let fin = hasta || (claves.length ? claves.reduce((a, b) => (a > b ? a : b)) : fechaIsoHoy());
    if (inicio > fin) return [];
    const mapa: Record<string, number> = {};
    for (const v of ventasR) {
      const k = claveFecha(v.fecha || '');
      if (!k) continue;
      mapa[k] = (mapa[k] || 0) + Number(v.total || 0);
    }
    const puntos: PuntoSerie[] = [];
    if (diffDias(inicio, fin) <= 92) {
      let cur = inicio;
      while (cur <= fin) {
        puntos.push({ clave: cur, etiqueta: etiquetaDia(cur), total: mapa[cur] || 0 });
        cur = sumarDias(cur, 1);
      }
    } else {
      const mapaMes: Record<string, number> = {};
      for (const v of ventasR) {
        const k = claveFecha(v.fecha || '');
        if (!k) continue;
        const mk = k.slice(0, 7);
        mapaMes[mk] = (mapaMes[mk] || 0) + Number(v.total || 0);
      }
      let cur = inicio.slice(0, 7);
      const finMes = fin.slice(0, 7);
      while (cur <= finMes) {
        puntos.push({ clave: cur, etiqueta: etiquetaMes(cur), total: mapaMes[cur] || 0 });
        cur = mesSiguiente(cur);
      }
    }
    return puntos;
  })());

  let maxSerie = $derived(serie.reduce((m, p) => (p.total > m ? p.total : m), 0));
  let mostrarEjes = $derived(serie.length <= 20);

  // Formas de pago
  let porPago = $derived((() => {
    const grupos: { nombre: string; color: string; monto: number; n: number }[] = [
      { nombre: 'Efectivo', color: COLORES.efectivo, monto: 0, n: 0 },
      { nombre: 'Tarjeta', color: COLORES.tarjeta, monto: 0, n: 0 },
      { nombre: 'Transferencia', color: COLORES.transferencia, monto: 0, n: 0 },
      { nombre: 'Otro', color: COLORES.otro, monto: 0, n: 0 },
    ];
    for (const v of ventasR) {
      const fp = (v.forma_pago || '').trim();
      let g = grupos.find((x) => x.nombre === fp);
      if (!g) g = grupos[3];
      g.monto += Number(v.total || 0);
      g.n += 1;
    }
    return grupos.filter((g) => g.n > 0);
  })());

  let fondoDonutPago = $derived((() => {
    const total = porPago.reduce((s, g) => s + g.monto, 0);
    if (total <= 0) return 'conic-gradient(#e2e8f0 0% 100%)';
    let acc = 0;
    const segs = porPago.map((g) => {
      const a = (acc / total) * 100;
      acc += g.monto;
      return g.color + ' ' + a + '% ' + (acc / total) * 100 + '%';
    });
    return 'conic-gradient(' + segs.join(', ') + ')';
  })());

  // Ventas por cajero
  let porCajero = $derived((() => {
    const mapa = new Map<string, { nombre: string; monto: number; n: number }>();
    for (const v of ventasR) {
      const c = (v.cajero || '').trim() || 'Sin asignar';
      const e = mapa.get(c) || { nombre: c, monto: 0, n: 0 };
      e.monto += Number(v.total || 0);
      e.n += 1;
      mapa.set(c, e);
    }
    return [...mapa.values()].sort((a, b) => b.monto - a.monto);
  })());

  // ---------- Inventario ----------
  let skus = $derived(inventario.length);
  let unidadesStock = $derived(inventario.reduce((s, i) => s + Number(i.stock || 0), 0));
  let valorInventario = $derived(inventario.reduce((s, i) => s + Number(i.stock || 0) * Number(i.precio_venta || 0), 0));
  let estadoInv = $derived((() => {
    let disp = 0, bajo = 0, agot = 0;
    for (const i of inventario) {
      if (Number(i.stock) <= 0) agot++;
      else if (Number(i.stock) <= 5) bajo++;
      else disp++;
    }
    return [
      { nombre: 'Disponible', color: COLORES.disponible, n: disp },
      { nombre: 'Stock bajo', color: COLORES.bajo, n: bajo },
      { nombre: 'Agotado', color: COLORES.agotado, n: agot },
    ];
  })());

  let fondoDonutInv = $derived((() => {
    const total = estadoInv.reduce((s, g) => s + g.n, 0);
    if (total <= 0) return 'conic-gradient(#e2e8f0 0% 100%)';
    let acc = 0;
    const segs = estadoInv.map((g) => {
      const a = (acc / total) * 100;
      acc += g.n;
      return g.color + ' ' + a + '% ' + (acc / total) * 100 + '%';
    });
    return 'conic-gradient(' + segs.join(', ') + ')';
  })());

  let topValorInv = $derived(
    inventario
      .map((i) => ({ ...i, valorStock: Number(i.stock || 0) * Number(i.precio_venta || 0) }))
      .sort((a, b) => b.valorStock - a.valorStock)
      .slice(0, 8)
  );
  let maxValorInv = $derived(topValorInv.length ? topValorInv[0].valorStock : 1);

  let entradasR = $derived(
    entradas.filter((e) => {
      const k = claveFecha(e.fecha || '');
      if (!k) return false;
      if (desde && k < desde) return false;
      if (hasta && k > hasta) return false;
      return true;
    })
  );
  let unidadesEntradasR = $derived(entradasR.reduce((s, e) => s + Number(e.cantidad || 0), 0));

  // ---------- Productos (rotación) ----------
  interface ProdStat { producto: string; presentacion: string; stock: number; precio: number; vendidos: number; monto: number; }
  let statProductos = $derived((() => {
    const mapa = new Map<string, ProdStat>();
    for (const i of inventario) {
      mapa.set(i.producto, { producto: i.producto, presentacion: i.presentacion, stock: Number(i.stock || 0), precio: Number(i.precio_venta || 0), vendidos: 0, monto: 0 });
    }
    for (const v of ventasR) {
      const p = v.producto || '';
      const e = mapa.get(p);
      if (e) {
        e.vendidos += Number(v.cantidad || 0);
        e.monto += Number(v.total || 0);
      } else {
        // producto vendido que ya no está en catálogo
        if (!mapa.has(p)) {
          mapa.set(p, { producto: p, presentacion: '', stock: 0, precio: Number(v.precio_unitario || 0), vendidos: Number(v.cantidad || 0), monto: Number(v.total || 0) });
        }
      }
    }
    return [...mapa.values()];
  })());

  let masVendidos = $derived([...statProductos].sort((a, b) => b.vendidos - a.vendidos).filter((p) => p.vendidos > 0).slice(0, 12));
  let menosVendidos = $derived(
    [...statProductos]
      .sort((a, b) => a.vendidos - b.vendidos || b.stock - a.stock)
      .filter((p) => p.stock > 0)
      .slice(0, 12)
  );
  let maxVendidos = $derived(masVendidos.length ? masVendidos[0].vendidos : 1);
</script>

<div class="space-y-5">
  <!-- Encabezado con pestañas -->
  <div class="flex flex-col md:flex-row md:items-center gap-3">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-[14px] bg-gradient-to-br from-blue-600 to-sky-500 text-white flex items-center justify-center shadow-[0_6px_16px_-8px_rgba(37,99,235,0.6)]">
        <Icon name="chart" class="w-[22px] h-[22px]" />
      </div>
      <div>
        <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">{esAdmin ? 'Reportes y estadísticas' : 'Mis estadísticas'}</h2>
        <p class="text-[12.5px] text-slate-500">{esAdmin ? rangoTexto : 'Tus ventas · ' + rangoTexto}</p>
      </div>
    </div>

    <div class="md:ml-auto grid {esAdmin ? 'grid-cols-3' : 'grid-cols-2'} gap-1 rounded-xl bg-slate-100/90 p-1">
      {#each (esAdmin ? tabsDefs : [tabsDefs[0], tabsDefs[2]]) as t}
        <button
          type="button"
          onclick={() => (tab = t.id as Tab)}
          aria-pressed={tab === t.id}
          class="inline-flex items-center justify-center gap-1.5 rounded-[10px] px-3 py-2 text-[12.5px] font-semibold transition-all duration-150 cursor-pointer
            {tab === t.id ? 'bg-white text-blue-700 shadow-sm ring-1 ring-inset ring-slate-200' : 'text-slate-500 hover:text-slate-700'}"
        >
          <Icon name={t.icon} class={'w-4 h-4 ' + (tab === t.id ? 'text-blue-600' : '')} />
          {t.label}
        </button>
      {/each}
    </div>
  </div>

  <!-- Filtro de período -->
  <div class="panel p-4">
    <div class="flex flex-col lg:flex-row lg:items-end gap-3">
      <div class="grid grid-cols-2 gap-3 flex-1">
        <div>
          <label class="field-label" for="rep-desde">Desde</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><Icon name="calendar" class="w-[17px] h-[17px]" /></span>
            <input id="rep-desde" type="date" bind:value={desde} class="input-base pl-9" />
          </div>
        </div>
        <div>
          <label class="field-label" for="rep-hasta">Hasta</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><Icon name="calendar" class="w-[17px] h-[17px]" /></span>
            <input id="rep-hasta" type="date" bind:value={hasta} class="input-base pl-9" />
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        {#each [
          { label: 'Hoy', fn: setHoy },
          { label: '7 días', fn: set7dias },
          { label: 'Mes', fn: setMes },
          { label: 'Año', fn: setAnio },
          { label: 'Todo', fn: setTodo },
        ] as pre}
          <button
            type="button"
            onclick={pre.fn}
            class="rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"
          >
            {pre.label}
          </button>
        {/each}
      </div>
      <div class="flex justify-start lg:justify-end">
        <button
          type="button"
          onclick={exportarReporte}
          class="inline-flex items-center gap-1.5 rounded-[10px] px-3 py-2 text-[12.5px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-300/80 hover:text-blue-700 hover:bg-blue-50 hover:ring-blue-200 transition-colors cursor-pointer"
        >
          <Icon name="download" class="w-4 h-4" />
          Exportar CSV
        </button>
      </div>
    </div>
  </div>

  {#if loading}
    <div class="panel p-6 space-y-3">
      {#each Array(4) as _}
        <div class="h-4 rounded bg-slate-100 animate-pulse"></div>
      {/each}
    </div>
  {:else if !rangoValido}
    <div class="panel flex flex-col items-center justify-center text-center px-6 py-14 text-slate-400">
      <Icon name="calendar" class="w-9 h-9 mb-3" strokeWidth={1.5} />
      <p class="text-[14px] font-semibold text-slate-600">Rango de fechas inválido</p>
      <p class="text-[12.5px] mt-1">La fecha “desde” no puede ser mayor que la “hasta”.</p>
    </div>
  {:else if tab === 'ventas'}
    <!-- ============ VENTAS ============ -->
    <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Ventas del período</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900 truncate">{formatCOP(totalVentasR)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">{ventasR.length} transacciones</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Items vendidos</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900">{fmtNum(itemsVentasR)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">unidades en el período</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Ticket promedio</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900 truncate">{formatCOP(Math.round(ticketR))}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">por transacción</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Días con venta</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900">{serie.filter((p) => p.total > 0).length}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">{serie.length} día{serie.length === 1 ? '' : 's'} en el rango</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
      <!-- Serie temporal -->
      <div class="panel p-5 lg:col-span-3">
        <div class="flex items-center justify-between gap-3 mb-5">
          <div>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Evolución de ventas</h3>
            <p class="text-[12px] text-slate-400">{rangoTexto}</p>
          </div>
          <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">{formatCOP(totalVentasR)}</span>
        </div>
        {#if serie.length > 0 && maxSerie > 0}
          <div class="flex items-end gap-[3px]" style="height: 220px;">
            {#each serie as p}
              <div
                class="group/bar relative flex-1 min-w-0 h-full flex items-end"
                title={(p.etiqueta + ': ' + formatCOP(p.total))}
              >
                <div
                  class="w-full rounded-t-[4px] bg-gradient-to-t from-blue-600 to-sky-400 transition-all duration-300 group-hover/bar:from-blue-700 group-hover/bar:to-sky-500"
                  style={'height: ' + (p.total > 0 ? Math.max(3, (p.total / maxSerie) * 100) : 2) + '%; opacity: ' + (p.total > 0 ? 1 : 0.18) + ';'}
                ></div>
              </div>
            {/each}
          </div>
          {#if mostrarEjes}
            <div class="flex gap-[3px] mt-1.5">
              {#each serie as p}
                <span class="flex-1 text-center text-[8.5px] text-slate-400 truncate px-0.5">{p.etiqueta}</span>
              {/each}
            </div>
          {/if}
        {:else}
          <div class="flex flex-col items-center justify-center text-center py-12 text-slate-400">
            <Icon name="chart" class="w-8 h-8 mb-2" strokeWidth={1.5} />
            <p class="text-[13px] font-medium">Sin ventas en el período seleccionado</p>
          </div>
        {/if}
      </div>

      <!-- Mezcla de pagos -->
      <div class="panel p-5 lg:col-span-2">
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Formas de pago</h3>
        <p class="text-[12px] text-slate-400 mb-5">Distribución del período</p>
        {#if totalVentasR > 0}
          <div class="flex flex-col items-center gap-5">
            <div class="relative w-40 h-40 rounded-full" style={'background: ' + fondoDonutPago + ';'}>
              <div class="absolute inset-[22px] bg-white rounded-full flex flex-col items-center justify-center text-center">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Total</span>
                <span class="text-[15px] font-bold text-slate-900 leading-tight">{formatCOP(totalVentasR)}</span>
              </div>
            </div>
            <div class="w-full space-y-2">
              {#each porPago as g}
                <div class="flex items-center gap-2.5 text-[13px]">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" style={'background:' + g.color + ';'}></span>
                  <span class="text-slate-600 flex-1 truncate">{g.nombre}</span>
                  <span class="text-slate-400 text-[12px]">{g.n} venta{g.n === 1 ? '' : 's'}</span>
                  <span class="font-bold text-slate-800">{formatCOP(g.monto)}</span>
                </div>
              {/each}
            </div>
          </div>
        {:else}
          <div class="text-center py-10 text-slate-400 text-[13px]">Sin datos para mostrar</div>
        {/if}
      </div>
    </div>

    {#if esAdmin && porCajero.length > 1}
      <div class="panel p-5">
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight mb-1">Ventas por cajero</h3>
        <p class="text-[12px] text-slate-400 mb-4">{rangoTexto}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
          {#each porCajero as c}
            <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-4 py-3">
              <div class="min-w-0">
                <p class="text-[13.5px] font-semibold text-slate-800 truncate">{c.nombre}</p>
                <p class="text-[11.5px] text-slate-400">{c.n} venta{c.n === 1 ? '' : 's'}</p>
              </div>
              <span class="font-bold text-[14px] text-slate-900">{formatCOP(c.monto)}</span>
            </div>
          {/each}
        </div>
      </div>
    {/if}

  {:else if tab === 'inventario' && esAdmin}
    <!-- ============ INVENTARIO ============ -->
    <div class="grid grid-cols-1 xs:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Productos</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900">{fmtNum(skus)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">referencias en catálogo</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Unidades en stock</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900">{fmtNum(unidadesStock)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">inventario físico</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Valor del inventario</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900 truncate">{formatCOP(valorInventario)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">a precio de venta</p>
      </div>
      <div class="panel panel-hover p-5">
        <p class="text-[12px] font-semibold uppercase tracking-wide text-slate-400">Ingresos del período</p>
        <p class="mt-2 text-[24px] leading-7 font-bold tracking-tight text-slate-900">{fmtNum(unidadesEntradasR)}</p>
        <p class="mt-1.5 text-[11.5px] text-slate-400">{entradasR.length} entrada{entradasR.length === 1 ? '' : 's'} &middot; {rangoTexto}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
      <div class="panel p-5 lg:col-span-2">
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Estado del inventario</h3>
        <p class="text-[12px] text-slate-400 mb-5">Referencias por disponibilidad</p>
        {#if skus > 0}
          <div class="flex flex-col items-center gap-5">
            <div class="relative w-40 h-40 rounded-full" style={'background: ' + fondoDonutInv + ';'}>
              <div class="absolute inset-[22px] bg-white rounded-full flex flex-col items-center justify-center text-center">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Total</span>
                <span class="text-[20px] font-bold text-slate-900 leading-tight">{skus}</span>
              </div>
            </div>
            <div class="w-full space-y-2">
              {#each estadoInv as g}
                <div class="flex items-center gap-2.5 text-[13px]">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" style={'background:' + g.color + ';'}></span>
                  <span class="text-slate-600 flex-1">{g.nombre}</span>
                  <span class="font-bold text-slate-800">{g.n}</span>
                </div>
              {/each}
            </div>
          </div>
        {:else}
          <div class="text-center py-10 text-slate-400 text-[13px]">Sin productos en inventario</div>
        {/if}
      </div>

      <div class="panel p-5 lg:col-span-3">
        <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Mayor valor en inventario</h3>
        <p class="text-[12px] text-slate-400 mb-4">Top referencias por (stock &times; precio de venta)</p>
        {#if topValorInv.length > 0}
          <div class="space-y-3">
            {#each topValorInv as it}
              <div>
                <div class="flex items-center justify-between gap-3 text-[13px] mb-1">
                  <span class="font-medium text-slate-700 truncate">{it.producto} <span class="text-slate-400 text-[11.5px]">· {it.presentacion}</span></span>
                  <span class="font-bold text-slate-900 shrink-0">{formatCOP(it.valorStock)}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-sky-400" style={'width: ' + Math.max(2, (it.valorStock / maxValorInv) * 100) + '%;'}></div>
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">{fmtNum(it.stock)} und &middot; {formatCOP(it.precio_venta)} c/u</p>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-10 text-slate-400 text-[13px]">Sin productos en inventario</div>
        {/if}
      </div>
    </div>

  {:else}
    <!-- ============ PRODUCTOS ============ -->
    <div class="grid grid-cols-1 {esAdmin ? 'lg:grid-cols-2' : ''} gap-4">
      <!-- Más vendidos -->
      <div class="panel overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
          <div class="w-9 h-9 rounded-[11px] bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <Icon name="trending-up" class="w-[19px] h-[19px]" />
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">{esAdmin ? 'Los que más se mueven' : 'Tus productos más vendidos'}</h3>
            <p class="text-[12px] text-slate-400">{rangoTexto}</p>
          </div>
        </div>
        {#if masVendidos.length > 0}
          <div class="divide-y divide-slate-100 max-h-[480px] overflow-y-auto scrollbar-thin">
            {#each masVendidos as p, idx}
              <div class="px-5 py-3.5 hover:bg-slate-50/70 transition-colors">
                <div class="flex items-center gap-3">
                  <span class="w-6 h-6 rounded-lg text-[11px] font-bold flex items-center justify-center shrink-0
                    {idx === 0 ? 'bg-amber-100 text-amber-700' : idx === 1 ? 'bg-slate-200 text-slate-600' : idx === 2 ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-400'}">
                    {idx + 1}
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-[13.5px] font-semibold text-slate-800 truncate">{p.producto}</p>
                    <div class="flex items-center gap-2 mt-1">
                      <div class="h-1.5 flex-1 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400" style={'width:' + Math.max(2, (p.vendidos / maxVendidos) * 100) + '%;'}></div>
                      </div>
                      <span class="text-[11.5px] font-semibold text-emerald-600 shrink-0">{p.vendidos} und</span>
                    </div>
                  </div>
                  <span class="font-bold text-[13.5px] text-slate-900 shrink-0">{formatCOP(p.monto)}</span>
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-12 text-slate-400 text-[13px]">Sin ventas en el período</div>
        {/if}
      </div>

      {#if esAdmin}
      <!-- Menos vendidos -->
      <div class="panel overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
          <div class="w-9 h-9 rounded-[11px] bg-amber-50 text-amber-600 flex items-center justify-center">
            <Icon name="alert" class="w-[19px] h-[19px]" />
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">Los que menos se mueven</h3>
            <p class="text-[12px] text-slate-400">{rangoTexto} &middot; con stock disponible</p>
          </div>
        </div>
        {#if menosVendidos.length > 0}
          <div class="divide-y divide-slate-100 max-h-[480px] overflow-y-auto scrollbar-thin">
            {#each menosVendidos as p}
              <div class="px-5 py-3.5 flex items-center justify-between gap-3 hover:bg-slate-50/70 transition-colors">
                <div class="min-w-0 flex-1">
                  <p class="text-[13.5px] font-semibold text-slate-800 truncate">{p.producto}</p>
                  <p class="text-[11.5px] text-slate-400 truncate">{p.presentacion} &middot; {fmtNum(p.stock)} und en stock</p>
                </div>
                {#if p.vendidos === 0}
                  <span class="chip bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200">Sin ventas</span>
                {:else if p.vendidos <= 5}
                  <span class="chip bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">{p.vendidos} und vendida{p.vendidos === 1 ? '' : 's'}</span>
                {:else}
                  <span class="chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">{p.vendidos} und</span>
                {/if}
              </div>
            {/each}
          </div>
        {:else}
          <div class="text-center py-12 text-slate-400 text-[13px]">No hay productos en inventario</div>
        {/if}
      </div>
      {/if}
    </div>

    {#if esAdmin}
      <p class="text-[12px] text-slate-400 flex items-center gap-1.5 px-1">
        <Icon name="alert" class="w-3.5 h-3.5 text-amber-400" />
        Los productos marcados “Sin ventas” tienen existencias pero no registraron movimiento en el período seleccionado: revisa su rotación para evitar capital inmovilizado.
      </p>
    {/if}
  {/if}
</div>
