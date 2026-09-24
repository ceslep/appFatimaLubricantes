<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { usuario } from '../stores';
  import { formatCOP } from '../utils';
  import type { IslaLectura } from '../types';
  import Icon from './ui/Icon.svelte';
  import Badge from './ui/Badge.svelte';

  // Cada isla tiene 2 mangueras. El orden define cómo se muestran.
  const COMBUSTIBLES = ['Gasolina', 'ACPM'];

  let esAdmin = $derived($usuario?.rol === 'admin');

  let islas = $state<string[]>([]);
  let precios = $state<Record<string, number>>({ Gasolina: 0, ACPM: 0 });
  let lecturas = $state<IslaLectura[]>([]);
  let cargando = $state(true);
  let error = $state('');
  let aviso = $state('');
  let guardandoIsla = $state('');

  type LecturaForm = { inicial: string; final: string };
  let form = $state<Record<string, Record<string, LecturaForm>>>({});

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

  let diaISO = $state(hoyISO());
  // La API usa fechas dd/mm/YYYY (igual que ventas y entradas).
  let diaApi = $derived(diaISO.split('-').reverse().join('/'));

  // Rango del panel de consulta. Es independiente del día del cierre (que es el
  // que se registra): permite revisar varios días, útil cuando un cajero parte
  // el día o quiere verificar la semana.
  let desdeISO = $state(hoyISO());
  let hastaISO = $state(hoyISO());

  // Día (YYYY-MM-DD) de una fecha guardada como dd/mm/YYYY con o sin hora.
  function claveDia(fecha: string): string {
    const m = /(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(fecha || '');
    return m ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}` : '';
  }

  function num(v: string): number {
    const n = parseFloat(String(v ?? '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  }

  // ¿La lectura la registró el usuario conectado? El formulario solo precarga y
  // marca lo propio: si otro cajero ya cerró la isla, no debe ver ni heredar sus
  // números (ni pisarlos al guardar).
  let miUsuario = $derived(($usuario?.usuario || '').trim().toLowerCase());
  let miNombre = $derived(($usuario?.nombre || '').trim().toLowerCase());

  function esMia(l: IslaLectura): boolean {
    const autor = (l.usuario || '').trim().toLowerCase();
    if (autor) return autor === miUsuario;
    return (l.cajero || '').trim().toLowerCase() === miNombre && miNombre !== '';
  }

  // El admin ve todas las lecturas; el cajero, solo las suyas.
  let lecturasVisibles = $derived(esAdmin ? lecturas : lecturas.filter(esMia));
  let lecturasDia = $derived(lecturasVisibles.filter((l) => claveDia(l.fecha) === diaISO));
  let misLecturasDia = $derived(lecturasDia.filter(esMia));
  let totalDia = $derived(lecturasDia.reduce((s, l) => s + l.total, 0));
  let galonesDia = $derived(lecturasDia.reduce((s, l) => s + l.galones, 0));

  // Registros dentro del rango consultado, del más reciente al más antiguo.
  let lecturasRango = $derived(
    lecturasVisibles
      .filter((l) => {
        const k = claveDia(l.fecha);
        if (!k) return false;
        if (desdeISO && k < desdeISO) return false;
        if (hastaISO && k > hastaISO) return false;
        return true;
      })
      .sort((a, b) => claveDia(b.fecha).localeCompare(claveDia(a.fecha)))
  );
  let totalRango = $derived(lecturasRango.reduce((s, l) => s + l.total, 0));
  let galonesRango = $derived(lecturasRango.reduce((s, l) => s + l.galones, 0));
  let preciosSinConfigurar = $derived(!precios.Gasolina && !precios.ACPM);

  // Identificadores del usuario conectado para pedir solo sus registros.
  function autorActual(): { usuario?: string; cajero?: string } | undefined {
    if (esAdmin) return undefined;
    return { usuario: $usuario?.usuario || '', cajero: $usuario?.nombre || '' };
  }

  onMount(cargar);

  async function cargar() {
    cargando = true;
    error = '';
    try {
      const [cfg, lec] = await Promise.all([api.obtenerConfig(), api.listarIslas(autorActual())]);
      islas = cfg.islas || [];
      precios = {
        Gasolina: Number(cfg.precio_gasolina || 0),
        ACPM: Number(cfg.precio_acpm || 0),
      };
      lecturas = lec;
      construirForm();
    } catch (e: any) {
      error = 'No se pudieron cargar las islas: ' + (e?.message || 'Error de conexión');
    }
    cargando = false;
  }

  // Refresca solo las lecturas guardadas: no toca lo que el cajero escribió en
  // otras islas y aún no ha guardado.
  async function refrescarLecturas() {
    lecturas = await api.listarIslas(autorActual());
  }

  // Precarga en el formulario lo que YA registró este mismo usuario ese día,
  // para poder corregirlo. Las lecturas de otros cajeros se dejan intactas.
  function construirForm() {
    const nuevo: Record<string, Record<string, LecturaForm>> = {};
    for (const isla of islas) {
      nuevo[isla] = {};
      for (const comb of COMBUSTIBLES) {
        const previa = lecturas.find(
          (l) => claveDia(l.fecha) === diaISO && l.isla === isla && l.combustible === comb && esMia(l)
        );
        nuevo[isla][comb] = {
          inicial: previa ? String(previa.lectura_inicial) : '',
          final: previa ? String(previa.lectura_final) : '',
        };
      }
    }
    form = nuevo;
  }

  function cambiarDia() {
    error = '';
    aviso = '';
    // El panel de consulta sigue al día del cierre hasta que el usuario lo cambie.
    desdeISO = diaISO;
    hastaISO = diaISO;
    construirForm();
  }

  function formatoISO(d: Date): string {
    return (
      d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
    );
  }

  function isoHaceDias(dias: number): string {
    const d = new Date();
    d.setDate(d.getDate() - dias);
    return formatoISO(d);
  }

  function rangoHoy() {
    desdeISO = diaISO;
    hastaISO = diaISO;
  }

  function rango7Dias() {
    hastaISO = hoyISO();
    desdeISO = isoHaceDias(6);
  }

  function rangoEsteMes() {
    const d = new Date();
    desdeISO = formatoISO(new Date(d.getFullYear(), d.getMonth(), 1));
    hastaISO = hoyISO();
  }

  function rangoTodo() {
    desdeISO = '';
    hastaISO = '';
  }

  function fechaBonita(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  // Fecha corta de una lectura guardada; si no se puede interpretar, muestra el texto tal cual.
  function fechaCorta(fecha: string): string {
    const k = claveDia(fecha);
    return k ? fechaBonita(k) : (fecha || '').split(' ')[0];
  }

  let rangoTexto = $derived((() => {
    if (!desdeISO && !hastaISO) return 'todo el historial';
    if (desdeISO && hastaISO && desdeISO === hastaISO) return 'el ' + fechaBonita(desdeISO);
    if (desdeISO && !hastaISO) return 'desde el ' + fechaBonita(desdeISO);
    if (!desdeISO && hastaISO) return 'hasta el ' + fechaBonita(hastaISO);
    return 'del ' + fechaBonita(desdeISO) + ' al ' + fechaBonita(hastaISO);
  })());

  function galonesDe(isla: string, comb: string): number {
    const f = form[isla]?.[comb];
    if (!f || f.inicial === '' || f.final === '') return 0;
    const g = num(f.final) - num(f.inicial);
    return g > 0 ? g : 0;
  }

  function totalDe(isla: string, comb: string): number {
    return galonesDe(isla, comb) * (precios[comb] || 0);
  }

  function totalIsla(isla: string): number {
    return COMBUSTIBLES.reduce((s, c) => s + totalDe(isla, c), 0);
  }

  function galonesIsla(isla: string): number {
    return COMBUSTIBLES.reduce((s, c) => s + galonesDe(isla, c), 0);
  }

  function tieneRegistro(isla: string, comb: string): boolean {
    return misLecturasDia.some((l) => l.isla === isla && l.combustible === comb);
  }

  function formatearGalones(v: number): string {
    return v.toLocaleString('es-CO', { maximumFractionDigits: 3 });
  }

  async function guardar(isla: string) {
    error = '';
    aviso = '';
    const lecturasPayload: Record<string, { inicial: number; final: number }> = {};
    for (const comb of COMBUSTIBLES) {
      const f = form[isla]?.[comb];
      if (!f) continue;
      if (f.inicial === '' && f.final === '') continue;
      if (f.inicial === '' || f.final === '') {
        error = `En ${isla} · ${comb} completa la lectura inicial y la final.`;
        return;
      }
      const inicial = num(f.inicial);
      const final = num(f.final);
      if (final < inicial) {
        error = `En ${isla} · ${comb} la lectura final (${formatearGalones(final)}) no puede ser menor que la inicial (${formatearGalones(inicial)}).`;
        return;
      }
      lecturasPayload[comb] = { inicial, final };
    }
    if (Object.keys(lecturasPayload).length === 0) {
      error = `Ingresa al menos una lectura en ${isla}.`;
      return;
    }
    guardandoIsla = isla;
    try {
      const r = await api.registrarIsla({
        fecha: diaApi,
        isla,
        cajero: $usuario?.nombre || $usuario?.usuario || '',
        usuario: $usuario?.usuario || '',
        lecturas: lecturasPayload,
      });
      aviso = `${isla} · ${diaApi}: ${r.message || 'lecturas guardadas'}`;
      await refrescarLecturas();
      setTimeout(() => {
        aviso = '';
      }, 5000);
    } catch (e: any) {
      error = `${isla}: ` + (e?.message || 'no se pudieron guardar las lecturas');
    }
    guardandoIsla = '';
  }

  async function eliminar(l: IslaLectura) {
    if (!confirm(`¿Eliminar la lectura de ${l.isla} · ${l.combustible} del ${l.fecha}?`)) return;
    error = '';
    try {
      await api.eliminarIsla(l.row);
      await refrescarLecturas();
      // Limpia el formulario de esa manguera para que no quede el valor borrado a la vista.
      const campo = form[l.isla]?.[l.combustible];
      if (campo) {
        campo.inicial = '';
        campo.final = '';
      }
    } catch (e: any) {
      error = e?.message || 'No se pudo eliminar la lectura';
    }
  }
</script>

<div class="space-y-5">
  <!-- Encabezado: día del cierre y precios vigentes -->
  <div class="panel p-5 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
      <div class="flex items-start gap-3 min-w-0">
        <div class="w-10 h-10 rounded-[12px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
          <Icon name="fuel" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Cierre de islas</h3>
          <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
            Registra la lectura inicial y final de cada manguera. Los galones y el total se calculan
            automáticamente con el precio configurado.
          </p>
        </div>
      </div>

      <div class="sm:ml-auto shrink-0">
        <label class="field-label" for="isla-dia">Día del cierre</label>
        <input id="isla-dia" type="date" bind:value={diaISO} onchange={cambiarDia} class="input-base" />
      </div>
    </div>

    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-2">
      {#each COMBUSTIBLES as comb}
        <span class="chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
          <Icon name="droplet" class="w-3.5 h-3.5 text-slate-400" />
          {comb}:
          <strong class="text-slate-800">{precios[comb] ? formatCOP(precios[comb]) : 'sin precio'}</strong>
          <span class="text-slate-400 font-medium">/ galón</span>
        </span>
      {/each}
      <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">
        {esAdmin ? 'Día' : 'Mi registro'}: {diaApi} · {formatCOP(totalDia)} · {formatearGalones(galonesDia)} gal
      </span>
    </div>
  </div>

  {#if error}
    <p class="text-[13px] text-rose-600 flex items-start gap-2 px-1">
      <Icon name="alert" class="w-4 h-4 mt-[2px] shrink-0" />
      {error}
    </p>
  {/if}
  {#if aviso}
    <p class="text-[13px] text-emerald-700 flex items-start gap-2 px-1">
      <Icon name="circle-check" class="w-4 h-4 mt-[2px] shrink-0" />
      {aviso}
    </p>
  {/if}
  {#if preciosSinConfigurar}
    <p class="text-[12.5px] text-amber-700 bg-amber-50 ring-1 ring-inset ring-amber-200 rounded-xl px-4 py-3 flex items-start gap-2">
      <Icon name="alert" class="w-4 h-4 mt-[2px] shrink-0" />
      Los precios de Gasolina y ACPM aún no están configurados: los totales se calcularán en $0 hasta
      que el administrador los registre en Configuración.
    </p>
  {/if}

  {#if cargando}
    <div class="panel p-6 space-y-3">
      {#each Array(2) as _}
        <div class="h-4 w-1/3 rounded bg-slate-100 animate-pulse"></div>
        <div class="h-10 w-full rounded bg-slate-100/70 animate-pulse"></div>
      {/each}
    </div>
  {:else if islas.length === 0}
    <div class="panel px-5 py-12 text-center">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
        <Icon name="fuel" class="w-6 h-6" strokeWidth={1.5} />
      </div>
      <p class="text-[13.5px] font-semibold text-slate-700">Aún no hay islas configuradas</p>
      <p class="text-[12.5px] text-slate-500 mt-1">
        El administrador debe crearlas en Configuración → Islas de combustible.
      </p>
    </div>
  {:else}
    {#each islas as isla (isla)}
      <div class="panel overflow-hidden">
        <div class="px-5 py-4 flex items-center gap-3 border-b border-slate-100">
          <span class="w-9 h-9 rounded-[11px] bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <Icon name="fuel" class="w-[18px] h-[18px]" />
          </span>
          <div class="min-w-0">
            <p class="text-[14.5px] font-bold text-slate-900 tracking-tight truncate">{isla}</p>
            <p class="text-[11.5px] text-slate-400">2 mangueras · Gasolina y ACPM</p>
          </div>
          <span class="ml-auto chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200 whitespace-nowrap">
            {formatearGalones(galonesIsla(isla))} gal · {formatCOP(totalIsla(isla))}
          </span>
        </div>

        <div class="divide-y divide-slate-100">
          {#each COMBUSTIBLES as comb}
            <div class="px-5 py-4">
              <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full {comb === 'Gasolina' ? 'bg-emerald-500' : 'bg-slate-700'}"></span>
                <p class="text-[13px] font-bold text-slate-800">{comb}</p>
                <span class="text-[11.5px] text-slate-400">{formatCOP(precios[comb] || 0)} / galón</span>
                {#if tieneRegistro(isla, comb)}
                  <Badge variant="success" dot>Registrado</Badge>
                {/if}
              </div>

              <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                  <label class="field-label !mb-1" for={'ini-' + isla + '-' + comb}>Lectura inicial</label>
                  <input
                    id={'ini-' + isla + '-' + comb}
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="input-base text-right"
                    bind:value={form[isla][comb].inicial}
                  />
                </div>
                <div>
                  <label class="field-label !mb-1" for={'fin-' + isla + '-' + comb}>Lectura final</label>
                  <input
                    id={'fin-' + isla + '-' + comb}
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="input-base text-right"
                    bind:value={form[isla][comb].final}
                  />
                </div>
                <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
                  <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Galones</p>
                  <p class="text-[15px] font-bold text-slate-800 leading-tight">
                    {formatearGalones(galonesDe(isla, comb))}
                  </p>
                </div>
                <div class="rounded-xl bg-emerald-50/70 ring-1 ring-inset ring-emerald-100 px-3 py-2.5">
                  <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/70">Total</p>
                  <p class="text-[15px] font-bold text-emerald-800 leading-tight">
                    {formatCOP(totalDe(isla, comb))}
                  </p>
                </div>
              </div>
            </div>
          {/each}
        </div>

        <div class="px-5 py-4 bg-slate-50/60 border-t border-slate-100 flex items-center gap-3">
          <p class="text-[12.5px] text-slate-500">
            Total <strong class="text-slate-800">{isla}</strong>: {formatCOP(totalIsla(isla))}
          </p>
          <button
            type="button"
            onclick={() => guardar(isla)}
            disabled={guardandoIsla === isla}
            class="toque ml-auto inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-4 py-2.5
              shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
              hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer disabled:opacity-60 disabled:pointer-events-none
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
          >
            {#if guardandoIsla === isla}
              Guardando…
            {:else}
              <Icon name="check" class="w-4 h-4" strokeWidth={2.4} />
              Guardar {isla}
            {/if}
          </button>
        </div>
      </div>
    {/each}

    <!-- Registros del rango consultado -->
    <div class="panel overflow-hidden">
      <div class="px-5 py-4 flex flex-col lg:flex-row lg:items-end gap-3 border-b border-slate-100">
        <div class="flex items-center gap-2 min-w-0">
          <Icon name="history" class="w-[18px] h-[18px] text-slate-400 shrink-0" />
          <div class="min-w-0">
            <h3 class="text-[14px] font-bold text-slate-900 tracking-tight">
              {esAdmin ? 'Registros de islas' : 'Mi registro de islas'}
            </h3>
            <p class="text-[11.5px] text-slate-400 truncate">{rangoTexto}</p>
          </div>
        </div>
        <div class="lg:ml-auto flex flex-col sm:flex-row sm:items-end gap-3 shrink-0">
          <div>
            <label class="field-label" for="rango-desde">Desde</label>
            <input id="rango-desde" type="date" bind:value={desdeISO} class="input-base" />
          </div>
          <div>
            <label class="field-label" for="rango-hasta">Hasta</label>
            <input id="rango-hasta" type="date" bind:value={hastaISO} class="input-base" />
          </div>
        </div>
      </div>

      <div class="px-5 py-3 bg-slate-50/40 border-b border-slate-100 flex flex-wrap items-center gap-2">
        <button type="button" onclick={rangoHoy}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">Hoy</button>
        <button type="button" onclick={rango7Dias}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">7 días</button>
        <button type="button" onclick={rangoEsteMes}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">Este mes</button>
        <button type="button" onclick={rangoTodo}
          class="toque rounded-full px-3.5 py-1.5 text-[12px] font-semibold bg-white text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">Todo</button>
        <span class="ml-auto chip bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
          {lecturasRango.length} lectura{lecturasRango.length === 1 ? '' : 's'}
        </span>
        <span class="chip bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">
          {formatearGalones(galonesRango)} gal
        </span>
      </div>

      {#if lecturasRango.length === 0}
        <p class="px-5 py-10 text-center text-[13px] text-slate-400">
          {esAdmin
            ? 'Todavía no hay lecturas registradas en este rango.'
            : 'Todavía no has registrado lecturas en este rango.'}
        </p>
      {:else}
        <div class="divide-y divide-slate-100">
          {#each lecturasRango as l (l.row)}
            <div class="px-5 py-3.5 flex items-center gap-3">
              <span class="w-2 h-2 rounded-full shrink-0 {l.combustible === 'Gasolina' ? 'bg-emerald-500' : 'bg-slate-700'}"></span>
              <div class="min-w-0">
                <p class="text-[13px] font-semibold text-slate-800 truncate">
                  {l.isla} <span class="text-slate-400 font-medium">· {l.combustible}</span>
                  {#if esMia(l)}
                    <span class="ml-1 align-middle text-[10.5px] font-bold uppercase tracking-wide text-blue-700 bg-blue-50 ring-1 ring-inset ring-blue-200 rounded-full px-2 py-0.5">Tú</span>
                  {/if}
                </p>
                <p class="text-[11.5px] text-slate-400">
                  {#if desdeISO !== hastaISO}· {fechaCorta(l.fecha)}{/if}
                  {formatearGalones(l.lectura_inicial)} → {formatearGalones(l.lectura_final)}
                  · {formatearGalones(l.galones)} gal
                  {#if l.cajero}· {l.cajero}{/if}{#if l.usuario} <span class="text-slate-300">@{l.usuario}</span>{/if}
                </p>
              </div>
              <div class="ml-auto text-right shrink-0">
                <p class="text-[13.5px] font-bold text-slate-900">{formatCOP(l.total)}</p>
                <p class="text-[11px] text-slate-400">{formatCOP(l.precio)} / galón</p>
              </div>
              {#if esAdmin}
                <button
                  type="button"
                  onclick={() => eliminar(l)}
                  aria-label={'Eliminar lectura de ' + l.isla + ' ' + l.combustible}
                  class="p-2 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer shrink-0"
                >
                  <Icon name="trash" class="w-4 h-4" />
                </button>
              {/if}
            </div>
          {/each}
        </div>
        <div class="px-5 py-3.5 bg-slate-50/60 border-t border-slate-100 flex items-center justify-between">
          <p class="text-[12.5px] text-slate-500">{esAdmin ? 'Total del rango' : 'Mi total del rango'}</p>
          <p class="text-[15px] font-extrabold text-slate-900">{formatCOP(totalRango)}</p>
        </div>
      {/if}
    </div>
  {/if}
</div>
