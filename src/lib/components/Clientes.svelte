<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Table from './ui/Table.svelte';
  import Button from './ui/Button.svelte';
  import Modal from './ui/Modal.svelte';
  import Icon from './ui/Icon.svelte';
  import { validarDocumento, validarTelefonoCO, validarPlaca, limitarPlaca, estadoDocumento, estadoTelefono, estadoPlaca, estadoNombres, estadoCorreo, limitarDocumento, limitarNombres, validarNombres, type EstadoCampo, type EstadoValidacion } from '../validaciones';
  import type { Cliente } from '../types';

  let clientes = $state<Cliente[]>([]);
  let loading = $state(true);
  let filtro = $state('');

  let showModal = $state(false);
  let editando = $state<Cliente | null>(null);
  let guardando = $state(false);
  let error = $state('');
  let intentoEnvio = $state(false);
  let verificandoDoc = $state(false);
  let docExistente = $state(false);
  let modalDuplicado = $state(false);

  let form = $state({ identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', notas: '', placa1: '', placa2: '', placa3: '', placa4: '' });

  function requerido(e: EstadoValidacion, msg: string): EstadoValidacion {
    if (intentoEnvio && e.estado === 'vacio') return { estado: 'error', mensaje: msg };
    return e;
  }

  // Solo cuenta como duplicado si el campo tiene documento
  let duplicado = $derived(docExistente && form.identificacion.trim() !== '');

  // Validación en vivo mientras se escribe
  let estIdBase = $derived(requerido(estadoDocumento(form.identificacion, 6), 'Ingresa la identificación.'));
  let estId = $derived<EstadoValidacion>(
    duplicado
      ? { estado: 'error', mensaje: 'Ya existe un cliente registrado con esta identificación.' }
      : verificandoDoc
        ? { estado: 'parcial', mensaje: 'Verificando identificación…' }
        : estIdBase
  );
  let estTel = $derived(requerido(estadoTelefono(form.telefono), 'Ingresa el celular.'));
  let estNom = $derived(requerido(estadoNombres(form.nombres), 'Ingresa el nombre completo.'));
  let estCor = $derived(estadoCorreo(form.correo));
  let estPlaca1 = $derived(estadoPlaca(form.placa1));
  let estPlaca2 = $derived(estadoPlaca(form.placa2));
  let estPlaca3 = $derived(estadoPlaca(form.placa3));
  let estPlaca4 = $derived(estadoPlaca(form.placa4));
  let formularioValido = $derived(
    estId.estado === 'ok' && estTel.estado === 'ok' && estNom.estado === 'ok'
    && estCor.estado !== 'error'
    && estPlaca1.estado !== 'error' && estPlaca2.estado !== 'error'
    && estPlaca3.estado !== 'error' && estPlaca4.estado !== 'error'
    && !duplicado && !verificandoDoc
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
    intentoEnvio = false;
    showModal = true;
  }

  function abrirEditar(c: Cliente) {
    editando = c;
    form = { identificacion: c.identificacion, nombres: c.nombres, telefono: c.telefono, correo: c.correo, direccion: c.direccion, notas: c.notas, placa1: c.placa1 || '', placa2: c.placa2 || '', placa3: c.placa3 || '', placa4: c.placa4 || '' };
    error = '';
    intentoEnvio = false;
    showModal = true;
  }

  function onDocInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarDocumento(el.value);
    el.value = limpio;
    form.identificacion = limpio;
    docExistente = false;
    verificandoDoc = false;
    modalDuplicado = false;
  }

  // Al salir del campo: verifica si la identificación ya está registrada
  async function verificarDocumento() {
    const r = validarDocumento(form.identificacion, 6);
    docExistente = false;
    if (!r.valido) return;
    // Al editar el mismo cliente no se considera duplicado
    if (editando && validarDocumento(editando.identificacion, 6).valorNormalizado === r.valorNormalizado) return;
    const consultado = r.valorNormalizado;
    verificandoDoc = true;
    try {
      const res = await api.clienteExiste(consultado);
      // Si el campo cambió o quedó vacío mientras se consultaba, se descarta la respuesta
      if (form.identificacion.trim() === '' || validarDocumento(form.identificacion, 6).valorNormalizado !== consultado) {
        docExistente = false;
        modalDuplicado = false;
        return;
      }
      docExistente = res.existe;
      modalDuplicado = res.existe;
    } catch (e) {
      console.error(e);
    } finally {
      verificandoDoc = false;
    }
  }

  function onTelInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    let v = el.value.replace(/[^\d+]/g, '');
    const mas = v.startsWith('+');
    v = v.replace(/\+/g, '');
    if (mas) v = '+' + v;
    v = v.slice(0, 13);
    el.value = v;
    form.telefono = v;
  }

  function onNomInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarNombres(el.value);
    el.value = limpio;
    form.nombres = limpio;
  }

  function onPlacaCampo(e: Event, campo: 'placa1' | 'placa2' | 'placa3' | 'placa4') {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarPlaca(el.value);
    el.value = limpio;
    form[campo] = limpio;
  }

  async function guardar() {
    if (!formularioValido) {
      intentoEnvio = true;
      error = 'Revisa los campos marcados.';
      return;
    }

    form.identificacion = validarDocumento(form.identificacion, 6).valorNormalizado;
    form.nombres = validarNombres(form.nombres).valorNormalizado;
    form.telefono = validarTelefonoCO(form.telefono).valorNormalizado;
    for (const k of ['placa1', 'placa2', 'placa3', 'placa4'] as const) {
      form[k] = validarPlaca(form[k] || '', false).valorNormalizado;
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
      class="toque inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-3.5 py-2.5 sm:ml-auto
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
                <button onclick={() => abrirEditar(c)} title="Editar" class="toque-icono p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer"><Icon name="pencil" class="w-[17px] h-[17px]" /></button>
                <button onclick={() => eliminar(c)} title="Eliminar" class="toque-icono p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"><Icon name="trash" class="w-[17px] h-[17px]" /></button>
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
            <button onclick={() => abrirEditar(c)} aria-label="Editar cliente" class="toque-icono p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer shrink-0"><Icon name="pencil" class="w-[18px] h-[18px]" /></button>
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
            <button onclick={() => eliminar(c)} class="toque inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
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
        <input id="cl-id" type="text" inputmode="numeric" maxlength="12" value={form.identificacion} oninput={onDocInput} onblur={verificarDocumento} placeholder="Cédula o NIT" class="input-base" autocomplete="off" style={bordeEstado(estId)} aria-invalid={estId.estado === 'error'} />
        {@render estadoLinea(estId, 'Cédula de 6 a 10 dígitos o NIT con guion (900123456-7).')}
      </div>
      <div>
        <label class="field-label" for="cl-tel">Teléfono / WhatsApp *</label>
        <input id="cl-tel" type="tel" inputmode="numeric" value={form.telefono} oninput={onTelInput} placeholder="3001234567" class="input-base" autocomplete="tel" style={bordeEstado(estTel)} aria-invalid={estTel.estado === 'error'} />
        {@render estadoLinea(estTel, 'Celular de 10 dígitos que empieza por 3. Puedes usar +57.')}
      </div>
    </div>
    <div>
      <label class="field-label" for="cl-nom">Nombres completos *</label>
      <input id="cl-nom" type="text" value={form.nombres} oninput={onNomInput} placeholder="Nombre y apellido" class="input-base" autocomplete="off" style={bordeEstado(estNom)} aria-invalid={estNom.estado === 'error'} />
      {@render estadoLinea(estNom, 'Nombre y apellido.')}
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="field-label" for="cl-cor">Correo electrónico</label>
        <input id="cl-cor" type="email" bind:value={form.correo} placeholder="cliente@correo.com" class="input-base" autocomplete="off" style={bordeEstado(estCor)} aria-invalid={estCor.estado === 'error'} />
        {@render estadoLinea(estCor, 'Opcional.')}
      </div>
      <div>
        <label class="field-label" for="cl-dir">Dirección</label>
        <input id="cl-dir" type="text" bind:value={form.direccion} placeholder="Dirección" class="input-base" autocomplete="off" />
      </div>
    </div>
    <div>
      <p class="field-label">Placas del vehículo del propietario (hasta 4, opcional)</p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <div>
          <input type="text" value={form.placa1} oninput={(e) => onPlacaCampo(e, 'placa1')} maxlength="7" placeholder="Placa 1" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 1" style={bordeEstado(estPlaca1)} aria-invalid={estPlaca1.estado === 'error'} />
          {@render estadoLinea(estPlaca1, '')}
        </div>
        <div>
          <input type="text" value={form.placa2} oninput={(e) => onPlacaCampo(e, 'placa2')} maxlength="7" placeholder="Placa 2" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 2" style={bordeEstado(estPlaca2)} aria-invalid={estPlaca2.estado === 'error'} />
          {@render estadoLinea(estPlaca2, '')}
        </div>
        <div>
          <input type="text" value={form.placa3} oninput={(e) => onPlacaCampo(e, 'placa3')} maxlength="7" placeholder="Placa 3" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 3" style={bordeEstado(estPlaca3)} aria-invalid={estPlaca3.estado === 'error'} />
          {@render estadoLinea(estPlaca3, '')}
        </div>
        <div>
          <input type="text" value={form.placa4} oninput={(e) => onPlacaCampo(e, 'placa4')} maxlength="7" placeholder="Placa 4" class="input-base uppercase font-mono" autocomplete="off" aria-label="Placa 4" style={bordeEstado(estPlaca4)} aria-invalid={estPlaca4.estado === 'error'} />
          {@render estadoLinea(estPlaca4, '')}
        </div>
      </div>
      <p class="mt-1 text-[11px] text-slate-400">Carro: ABC123 o ABC-123 · Moto: ABC12D.</p>
    </div>
    <div>
      <label class="field-label" for="cl-notas">Observaciones</label>
      <input id="cl-notas" type="text" bind:value={form.notas} placeholder="Observaciones (opcional)" class="input-base" autocomplete="off" />
    </div>
    {#if duplicado}
      <div class="notice-error" role="alert">
        <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
        <span><strong>El cliente ya existe.</strong> Esa identificación ya está registrada.</span>
      </div>
    {/if}
    {#if error}
      <p class="text-[13px] text-rose-600">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => (showModal = false)}>Cancelar</Button>
      <Button variant="primary" disabled={guardando || !formularioValido} onclick={guardar}>
        {guardando ? 'Guardando…' : (editando ? 'Guardar cambios' : 'Registrar cliente')}
      </Button>
    </div>
  </div>
</Modal>

<Modal show={modalDuplicado && duplicado} title="Cliente ya registrado" subtitle="No es posible registrar un cliente duplicado" onclose={() => (modalDuplicado = false)}>
  <div class="flex items-start gap-3 rounded-2xl bg-rose-50/70 ring-1 ring-inset ring-rose-100 p-4">
    <span class="w-9 h-9 rounded-[10px] bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
      <Icon name="alert" class="w-[18px] h-[18px]" />
    </span>
    <div class="text-[13.5px] text-slate-700 leading-relaxed">
      La identificación <strong class="text-slate-900">{form.identificacion}</strong> ya está registrada en la base de datos de clientes.
      <br />No es posible registrarla de nuevo.
    </div>
  </div>
  <div class="flex justify-end pt-5">
    <button type="button" onclick={() => (modalDuplicado = false)}
      class="inline-flex items-center justify-center rounded-[10px] bg-blue-600 text-white px-4 py-2.5 text-[13.5px] font-bold hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer">
      Entendido
    </button>
  </div>
</Modal>
