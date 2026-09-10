<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { logo } from '../stores';
  import {
    validarDocumento, validarTelefonoCO, validarPlaca, limitarPlaca,
    estadoDocumento, estadoTelefono, estadoPlaca, estadoNombres, estadoCorreo, limitarDocumento, limitarNombres, validarNombres,
    type EstadoCampo, type EstadoValidacion,
  } from '../validaciones';
  import Icon from './ui/Icon.svelte';

  const MENSAJE_DEFECTO = 'Regístrate y haz parte de nuestro programa de fidelidad. Te contactaremos con promociones y beneficios exclusivos.';

  let mensaje = $state('');
  let cargando = $state(true);
  let enviando = $state(false);
  let error = $state('');
  let exito = $state(false);
  let intentoEnvio = $state(false);
  let verificandoDoc = $state(false);
  let docExistente = $state(false);

  let form = $state({ identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', placa1: '' });

  function requerido(e: EstadoValidacion, msg: string): EstadoValidacion {
    if (intentoEnvio && e.estado === 'vacio') return { estado: 'error', mensaje: msg };
    return e;
  }

  // Validación en vivo mientras se escribe
  let estIdBase = $derived(requerido(estadoDocumento(form.identificacion), 'Ingresa la identificación.'));
  let estId = $derived<EstadoValidacion>(
    docExistente
      ? { estado: 'error', mensaje: 'Ya existe un cliente registrado con esta identificación.' }
      : verificandoDoc
        ? { estado: 'parcial', mensaje: 'Verificando identificación…' }
        : estIdBase
  );
  let estTel = $derived(requerido(estadoTelefono(form.telefono), 'Ingresa el celular.'));
  let estNom = $derived(requerido(estadoNombres(form.nombres), 'Ingresa el nombre completo.'));
  let estCor = $derived(requerido(estadoCorreo(form.correo), 'Ingresa el correo electrónico.'));
  let estPlaca = $derived(requerido(estadoPlaca(form.placa1), 'Ingresa la placa del vehículo.'));
  // Todos los campos son obligatorios excepto la dirección.
  let formularioValido = $derived(
    estId.estado === 'ok' && estTel.estado === 'ok' && estNom.estado === 'ok'
    && estCor.estado === 'ok' && estPlaca.estado === 'ok'
    && !docExistente && !verificandoDoc
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

  onMount(async () => {
    try {
      const cfg = await api.obtenerConfigPublica();
      mensaje = (cfg.mensaje_registro || '').trim();
    } catch (e) {
      console.error(e);
    }
    cargando = false;
  });

  function limpiar() {
    form = { identificacion: '', nombres: '', telefono: '', correo: '', direccion: '', placa1: '' };
    intentoEnvio = false;
    error = '';
  }

  function onDocInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarDocumento(el.value);
    el.value = limpio;
    form.identificacion = limpio;
    docExistente = false;
    verificandoDoc = false;
  }

  // Al salir del campo: consulta si la identificación ya está registrada
  async function verificarDocumento() {
    const r = validarDocumento(form.identificacion);
    docExistente = false;
    if (!r.valido) return;
    verificandoDoc = true;
    try {
      const res = await api.clienteExiste(r.valorNormalizado);
      docExistente = res.existe;
    } catch (e) {
      console.error(e);
    }
    verificandoDoc = false;
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

  function onPlacaInput(e: Event) {
    const el = e.currentTarget as HTMLInputElement;
    const limpio = limitarPlaca(el.value);
    el.value = limpio;
    form.placa1 = limpio;
  }

  async function enviar() {
    error = '';
    if (!formularioValido) {
      intentoEnvio = true;
      error = 'Revisa los campos marcados.';
      return;
    }
    const doc = validarDocumento(form.identificacion);
    const tel = validarTelefonoCO(form.telefono);
    const placa = validarPlaca(form.placa1, false);
    enviando = true;
    try {
      await api.registrarCliente({
        identificacion: doc.valorNormalizado,
        nombres: validarNombres(form.nombres).valorNormalizado,
        telefono: tel.valorNormalizado,
        correo: form.correo.trim(),
        direccion: form.direccion.trim(),
        notas: 'Registro por enlace de fidelidad',
        placa1: placa.valorNormalizado,
      });
      exito = true;
      limpiar();
    } catch (e: any) {
      const msg = String(e?.message || '');
      error = /existe/i.test(msg)
        ? 'Ya estás registrado con esa identificación. ¡Gracias por volver!'
        : (msg || 'No se pudo completar el registro. Intenta de nuevo.');
    }
    enviando = false;
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

<div class="min-h-dvh flex items-center justify-center p-4 sm:p-6 relative overflow-hidden">
  <div class="pointer-events-none absolute -top-32 -right-24 w-[420px] h-[420px] rounded-full bg-blue-400/15 blur-3xl"></div>
  <div class="pointer-events-none absolute -bottom-36 -left-24 w-[420px] h-[420px] rounded-full bg-emerald-300/15 blur-3xl"></div>

  <div class="relative w-full max-w-[520px]">
    <div class="panel rounded-[22px] p-6 sm:p-8 shadow-[0_2px_4px_rgba(15,23,42,0.04),0_24px_60px_-24px_rgba(15,23,42,0.22)]">
      <!-- Marca -->
      <div class="flex flex-col items-center text-center mb-6">
        {#if $logo}
          <div class="w-14 h-14 rounded-[18px] bg-white ring-1 ring-inset ring-slate-200 flex items-center justify-center mb-4 overflow-hidden">
            <img src={$logo} alt="Logo de la empresa" class="w-full h-full object-contain" />
          </div>
        {:else}
          <div class="relative w-14 h-14 rounded-[18px] bg-gradient-to-br from-blue-600 via-blue-600 to-sky-500 shadow-[0_12px_28px_-10px_rgba(37,99,235,0.65)] flex items-center justify-center mb-4">
            <Icon name="droplet" class="w-6 h-6 text-white" strokeWidth={2.2} />
            <span class="absolute inset-0 rounded-[18px] ring-1 ring-inset ring-white/30"></span>
          </div>
        {/if}
        <h1 class="text-[21px] font-bold tracking-tight text-slate-900">Estación Fátima</h1>
        <p class="text-[13px] text-slate-500 mt-1">Programa de fidelidad · Lubricantes &amp; Aditivos</p>
      </div>

      {#if exito}
        <div class="text-center py-2">
          <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
            <Icon name="circle-check" class="w-9 h-9" strokeWidth={2} />
          </div>
          <h2 class="text-[19px] font-bold text-slate-900 tracking-tight">¡Registro exitoso!</h2>
          <p class="text-[13.5px] text-slate-500 mt-1.5 leading-relaxed">
            Gracias por unirte a nuestro programa de fidelidad. Ya haces parte de nuestros clientes preferenciales.
          </p>
          {#if mensaje}
            <div class="mt-5 text-left rounded-2xl bg-blue-50/70 ring-1 ring-inset ring-blue-100 p-4">
              <p class="text-[12.5px] text-slate-600 leading-relaxed whitespace-pre-line flex items-start gap-2">
                <Icon name="message" class="w-4 h-4 text-blue-600 mt-[1px] shrink-0" />
                <span>{mensaje}</span>
              </p>
            </div>
          {/if}
          <button
            type="button"
            onclick={() => (exito = false)}
            class="mt-6 w-full inline-flex items-center justify-center gap-2 rounded-[12px] bg-blue-600 text-white text-[14.5px] font-bold py-3
              hover:bg-blue-700 active:scale-[0.99] transition-all cursor-pointer
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-2"
          >
            <Icon name="plus" class="w-[18px] h-[18px]" strokeWidth={2.4} />
            Registrar otro cliente
          </button>
        </div>
      {:else}
        <!-- Mensaje programado desde Configuración -->
        <div class="rounded-2xl bg-blue-50/70 ring-1 ring-inset ring-blue-100 p-4 mb-6">
          {#if cargando}
            <div class="space-y-2 animate-pulse">
              <div class="h-3 w-3/4 rounded bg-blue-200/50"></div>
              <div class="h-3 w-1/2 rounded bg-blue-200/40"></div>
            </div>
          {:else}
            <p class="text-[12.5px] text-slate-600 leading-relaxed whitespace-pre-line flex items-start gap-2">
              <Icon name="message" class="w-4 h-4 text-blue-600 mt-[1px] shrink-0" />
              <span>{mensaje || MENSAJE_DEFECTO}</span>
            </p>
          {/if}
        </div>

        <form onsubmit={(e) => { e.preventDefault(); enviar(); }} novalidate>
          <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="field-label" for="reg-id">Identificación *</label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <Icon name="tag" class="w-[18px] h-[18px]" />
                  </span>
                  <input
                    id="reg-id"
                    class="input-base pl-10"
                    type="text"
                    inputmode="numeric"
                    maxlength="12"
                    placeholder="Cédula o NIT"
                    value={form.identificacion}
                    oninput={onDocInput}
                    onblur={verificarDocumento}
                    autocomplete="off"
                    style={bordeEstado(estId)}
                    aria-invalid={estId.estado === 'error'}
                  />
                </div>
                {@render estadoLinea(estId, 'Cédula de 10 dígitos o NIT con guion (900123456-7).')}
              </div>
              <div>
                <label class="field-label" for="reg-tel">Teléfono / WhatsApp *</label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <Icon name="message" class="w-[18px] h-[18px]" />
                  </span>
                  <input
                    id="reg-tel"
                    class="input-base pl-10"
                    type="tel"
                    inputmode="numeric"
                    placeholder="3001234567"
                    value={form.telefono}
                    oninput={onTelInput}
                    autocomplete="tel"
                    style={bordeEstado(estTel)}
                    aria-invalid={estTel.estado === 'error'}
                  />
                </div>
                {@render estadoLinea(estTel, 'Celular de 10 dígitos que empieza por 3. Puedes usar +57.')}
              </div>
            </div>

            <div>
              <label class="field-label" for="reg-nom">Nombres completos *</label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <Icon name="user" class="w-[18px] h-[18px]" />
                </span>
                <input id="reg-nom" class="input-base pl-10" type="text" placeholder="Nombre y apellido" value={form.nombres} oninput={onNomInput} autocomplete="name" style={bordeEstado(estNom)} aria-invalid={estNom.estado === 'error'} />
              </div>
              {@render estadoLinea(estNom, 'Nombre y apellido.')}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="field-label" for="reg-cor">Correo electrónico</label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <Icon name="note" class="w-[18px] h-[18px]" />
                  </span>
                  <input id="reg-cor" class="input-base pl-10" type="email" placeholder="cliente@correo.com" bind:value={form.correo} autocomplete="email" style={bordeEstado(estCor)} aria-invalid={estCor.estado === 'error'} />
                </div>
                {@render estadoLinea(estCor, 'Correo electrónico.')}
              </div>
              <div>
                <label class="field-label" for="reg-placa">Placa del vehículo</label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <Icon name="car" class="w-[18px] h-[18px]" />
                  </span>
                  <input
                    id="reg-placa"
                    class="input-base pl-10 uppercase font-mono"
                    type="text"
                    placeholder="ABC123"
                    value={form.placa1}
                    oninput={onPlacaInput}
                    maxlength="7"
                    autocomplete="off"
                    style={bordeEstado(estPlaca)}
                    aria-invalid={estPlaca.estado === 'error'}
                  />
                </div>
                {@render estadoLinea(estPlaca, 'Carro ABC123 o ABC-123 · Moto ABC12D.')}
              </div>
            </div>

            <div>
              <label class="field-label" for="reg-dir">Dirección</label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                  <Icon name="building" class="w-[18px] h-[18px]" />
                </span>
                <input id="reg-dir" class="input-base pl-10" type="text" placeholder="Dirección (opcional)" bind:value={form.direccion} autocomplete="street-address" />
              </div>
            </div>
          </div>

          {#if docExistente}
            <div class="notice-error" role="alert">
              <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
              <span><strong>El cliente ya existe.</strong> La identificación {form.identificacion} ya está registrada, no es posible registrarla de nuevo.</span>
            </div>
          {:else if error}
            <div class="notice-error" role="alert">
              <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
              <span>{error}</span>
            </div>
          {:else if !formularioValido && !enviando}
            <p class="mt-4 text-[11.5px] text-slate-400 text-center">Completa todos los campos obligatorios para continuar. La dirección es opcional.</p>
          {/if}

          <button
            type="submit"
            disabled={enviando || !formularioValido}
            class="mt-6 w-full inline-flex items-center justify-center gap-2 rounded-[12px] px-4 py-3 text-[15px] font-semibold text-white
              bg-blue-600 shadow-[0_1px_2px_rgba(30,64,175,0.4),0_10px_24px_-10px_rgba(37,99,235,0.7)]
              hover:bg-blue-700 active:scale-[0.99] transition-all duration-150 cursor-pointer
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-2
              disabled:opacity-60 disabled:pointer-events-none"
          >
            {#if enviando}
              <svg class="w-4.5 h-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
              </svg>
              Registrando…
            {:else}
              <Icon name="circle-check" class="w-[18px] h-[18px]" />
              Registrarme
            {/if}
          </button>
        </form>

        <p class="text-[11px] text-slate-400 mt-4 text-center leading-relaxed">
          Tus datos se usan para el programa de fidelidad y la comunicación de promociones.
        </p>
      {/if}
    </div>

    <p class="text-center text-[11.5px] text-slate-400 mt-6 tracking-wide">
      <a href="./" class="hover:text-slate-600 hover:underline">Acceso del personal</a>
      &middot; Estación de Servicio Fátima
    </p>
  </div>
</div>
