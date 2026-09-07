<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Table from './ui/Table.svelte';
  import Button from './ui/Button.svelte';
  import Modal from './ui/Modal.svelte';
  import Icon from './ui/Icon.svelte';
  import type { Cliente } from '../types';

  let clientes = $state<Cliente[]>([]);
  let loading = $state(true);
  let filtro = $state('');

  let showModal = $state(false);
  let editando = $state<Cliente | null>(null);
  let guardando = $state(false);
  let error = $state('');

  let form = $state({ identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '', placa1: '', placa2: '', placa3: '', placa4: '' });

  let filtrados = $derived(
    filtro.trim()
      ? clientes.filter((c) =>
          [c.identificacion, c.nombres, c.telefono, c.correo, c.placa1 || '', c.placa2 || '', c.placa3 || '', c.placa4 || ''].some((v) =>
            (v || '').toLowerCase().includes(filtro.toLowerCase())
          )
        )
      : clientes
  );

  onMount(async () => {
    try { clientes = await api.listarClientes(); } catch (e) { console.error(e); }
    loading = false;
  });

  function abrirNuevo() {
    editando = null;
    form = { identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '', placa1: '', placa2: '', placa3: '', placa4: '' };
    error = '';
    showModal = true;
  }

  function abrirEditar(c: Cliente) {
    editando = c;
    form = { identificacion: c.identificacion, nombres: c.nombres, telefono: c.telefono, correo: c.correo, direccion: c.direccion, notas: c.notas, placa1: c.placa1 || '', placa2: c.placa2 || '', placa3: c.placa3 || '', placa4: c.placa4 || '' };
    error = '';
    showModal = true;
  }

  async function guardar() {
    // Normalizar placas a mayúsculas
    for (const k of ['placa1', 'placa2', 'placa3', 'placa4'] as const) {
      form[k] = (form[k] || '').trim().toUpperCase().replace(/\s+/g, '');
    }
    if (!form.identificacion.trim() || !form.nombres.trim() || !form.telefono.trim()) {
      error = 'Identificación, nombres y teléfono son requeridos';
      return;
    }
    guardando = true;
    error = '';
    try {
      if (editando) {
        await api.actualizarCliente({ row: editando.row, ...form });
      } else {
        await api.registrarCliente(form);
      }
      clientes = await api.listarClientes();
      showModal = false;
    } catch (e: any) {
      error = e.message || 'Error al guardar el cliente';
    }
    guardando = false;
  }

  async function eliminar(c: Cliente) {
    if (!confirm('Eliminar al cliente ' + c.nombres + ' (' + c.identificacion + ')?')) return;
    try {
      await api.eliminarCliente(c.row);
      clientes = await api.listarClientes();
    } catch (e: any) { alert(e.message); }
  }

  function iniciales(nombre: string) {
    const base = (nombre || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase();
    return base || 'C';
  }
</script>

<div class="space-y-5">
  <div class="flex flex-col sm:flex-row sm:items-center gap-3">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-[14px] bg-blue-50 text-blue-600 flex items-center justify-center">
        <Icon name="users" class="w-[22px] h-[22px]" />
      </div>
      <div>
        <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">Clientes</h2>
        <p class="text-[12.5px] text-slate-500">{clientes.length} cliente{clientes.length === 1 ? '' : 's'} registrado{clientes.length === 1 ? '' : 's'}</p>
      </div>
    </div>
    <button
      onclick={abrirNuevo}
      class="inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-3.5 py-2.5 sm:ml-auto
        shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
        hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
    >
      <Icon name="plus" class="w-4 h-4" strokeWidth={2.4} />
      <span class="hidden xs:inline">Nuevo cliente</span>
    </button>
  </div>

  <div class="relative sm:max-w-md">
    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
      <Icon name="search" class="w-[18px] h-[18px]" />
    </span>
    <input type="text" bind:value={filtro} placeholder="Buscar por identificación, nombre o teléfono…" class="input-base pl-10" />
  </div>

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(4) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="w-9 h-9 rounded-full bg-slate-200/70"></div>
            <div class="h-3 w-40 rounded bg-slate-200/60"></div>
            <div class="h-3 w-24 rounded bg-slate-200/50 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Identificación', 'Cliente', 'Teléfono', 'Contacto', 'Dirección', 'Vehículos', '']}>
        {#each filtrados as c}
          <tr class="hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3.5 font-mono text-[13px] text-slate-600 whitespace-nowrap">{c.identificacion}</td>
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-sky-600 text-white text-[11.5px] font-bold flex items-center justify-center shrink-0 select-none">{iniciales(c.nombres)}</span>
                <div class="min-w-0">
                  <p class="font-semibold text-[13.5px] text-slate-900 truncate max-w-[220px]">{c.nombres}</p>
                  {#if c.notas}<p class="text-[11px] text-slate-400 truncate max-w-[220px]">{c.notas}</p>{/if}
                </div>
              </div>
            </td>
            <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-700"><Icon name="message" class="w-3.5 h-3.5 text-emerald-500" />{c.telefono || '—'}</span></td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-500 max-w-[180px] truncate">{c.correo || '—'}</td>
            <td class="px-4 py-3.5 text-[12.5px] text-slate-500 max-w-[160px] truncate">{c.direccion || '—'}</td>
            <td class="px-4 py-3.5">
              {#if [c.placa1 || '', c.placa2 || '', c.placa3 || '', c.placa4 || ''].filter(Boolean).length > 0}
                <div class="flex flex-wrap gap-1 max-w-[200px]">
                  {#each [c.placa1 || '', c.placa2 || '', c.placa3 || '', c.placa4 || ''].filter(Boolean) as placa}
                    <span class="chip bg-slate-100 text-slate-600 font-mono ring-1 ring-inset ring-slate-200 normal-case">{placa}</span>
                  {/each}
                </div>
              {:else}<span class="text-[12.5px] text-slate-300">—</span>{/if}
            </td>
            <td class="px-4 py-3.5 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-1">
                <button onclick={() => abrirEditar(c)} title="Editar" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer"><Icon name="pencil" class="w-[17px] h-[17px]" /></button>
                <button onclick={() => eliminar(c)} title="Eliminar" class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"><Icon name="trash" class="w-[17px] h-[17px]" /></button>
              </div>
            </td>
          </tr>
        {/each}
        {#if filtrados.length === 0}
          <tr><td colspan="7" class="px-4 py-14 text-center text-slate-400">{filtro ? 'No se encontraron clientes' : 'Aún no hay clientes registrados'}</td></tr>
        {/if}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each filtrados as c}
        <div class="panel p-4">
          <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-full bg-gradient-to-br from-blue-500 to-sky-600 text-white text-[13px] font-bold flex items-center justify-center shrink-0 select-none">{iniciales(c.nombres)}</span>
            <div class="min-w-0 flex-1">
              <p class="text-[14.5px] font-bold text-slate-900 truncate">{c.nombres}</p>
              <p class="text-[11.5px] text-slate-400 font-mono truncate">{c.identificacion}</p>
              {#if c.notas}<p class="text-[11px] text-slate-400 truncate">{c.notas}</p>{/if}
            </div>
            <button onclick={() => abrirEditar(c)} aria-label="Editar cliente" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer shrink-0"><Icon name="pencil" class="w-[18px] h-[18px]" /></button>
          </div>

          <div class="mt-3 grid grid-cols-2 gap-2.5">
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">WhatsApp</p>
              <p class="flex items-center gap-1 text-[13.5px] font-semibold text-slate-800 mt-0.5 truncate"><Icon name="message" class="w-3.5 h-3.5 text-emerald-500 shrink-0" />{c.telefono || '—'}</p>
            </div>
            <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 px-3 py-2.5">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Correo</p>
              <p class="text-[13px] text-slate-600 mt-0.5 truncate">{c.correo || '—'}</p>
            </div>
          </div>

          {#if c.direccion}
            <p class="mt-2 text-[12px] text-slate-500 flex items-center gap-1.5"><Icon name="building" class="w-3.5 h-3.5 text-slate-400 shrink-0" />{c.direccion}</p>
          {/if}

          {#if [c.placa1 || '', c.placa2 || '', c.placa3 || '', c.placa4 || ''].filter(Boolean).length > 0}
            <div class="mt-2.5 pt-2.5 border-t border-slate-100">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1.5">Vehículos del propietario</p>
              <div class="flex flex-wrap gap-1.5">
                {#each [c.placa1 || '', c.placa2 || '', c.placa3 || '', c.placa4 || ''].filter(Boolean) as placa}
                  <span class="chip bg-slate-100 text-slate-600 font-mono ring-1 ring-inset ring-slate-200 normal-case">{placa}</span>
                {/each}
              </div>
            </div>
          {/if}

          <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-end">
            <button onclick={() => eliminar(c)} class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
              <Icon name="trash" class="w-3.5 h-3.5" />Eliminar
            </button>
          </div>
        </div>
      {/each}
      {#if filtrados.length === 0}
        <div class="panel px-5 py-10 text-center">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3"><Icon name="users" class="w-6 h-6" strokeWidth={1.5} /></div>
          <p class="text-[13px] text-slate-500">{filtro ? 'No se encontraron clientes' : 'Aún no hay clientes registrados'}</p>
        </div>
      {/if}
    </div>
  {/if}
</div>

<Modal show={showModal} title={editando ? 'Editar cliente' : 'Nuevo cliente'} subtitle="Datos que se usarán en la venta y para el envío del recibo" onclose={() => (showModal = false)}>
  <div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="field-label" for="cl-id">Identificación *</label>
        <input id="cl-id" type="text" bind:value={form.identificacion} placeholder="Cédula o NIT" class="input-base" autocomplete="off" />
      </div>
      <div>
        <label class="field-label" for="cl-tel">Teléfono / WhatsApp *</label>
        <input id="cl-tel" type="tel" inputmode="numeric" bind:value={form.telefono} placeholder="Ej: 3001234567" class="input-base" autocomplete="tel" />
      </div>
    </div>
    <div>
      <label class="field-label" for="cl-nom">Nombres completos *</label>
      <input id="cl-nom" type="text" bind:value={form.nombres} placeholder="Nombre y apellido" class="input-base" autocomplete="off" />
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="field-label" for="cl-cor">Correo electrónico</label>
        <input id="cl-cor" type="email" bind:value={form.correo} placeholder="cliente@correo.com" class="input-base" autocomplete="off" />
      </div>
      <div>
        <label class="field-label" for="cl-dir">Dirección</label>
        <input id="cl-dir" type="text" bind:value={form.direccion} placeholder="Dirección" class="input-base" autocomplete="off" />
      </div>
    </div>
    <div>
      <p class="field-label">Placas del vehículo del propietario (hasta 4, opcional)</p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <input type="text" bind:value={form.placa1} maxlength="8" placeholder="Placa 1" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 1" />
        <input type="text" bind:value={form.placa2} maxlength="8" placeholder="Placa 2" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 2" />
        <input type="text" bind:value={form.placa3} maxlength="8" placeholder="Placa 3" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 3" />
        <input type="text" bind:value={form.placa4} maxlength="8" placeholder="Placa 4" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 4" />
      </div>
    </div>
    <div>
      <label class="field-label" for="cl-notas">Observaciones</label>
      <input id="cl-notas" type="text" bind:value={form.notas} placeholder="Observaciones (opcional)" class="input-base" autocomplete="off" />
    </div>
    {#if error}
      <p class="text-[13px] text-rose-600">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => (showModal = false)}>Cancelar</Button>
      <Button variant="primary" disabled={guardando} onclick={guardar}>
        {guardando ? 'Guardando…' : (editando ? 'Guardar cambios' : 'Registrar cliente')}
      </Button>
    </div>
  </div>
</Modal>
