<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Icon from './ui/Icon.svelte';

  let activado = $state(false);
  let clienteObligatorio = $state(false);
  let cargando = $state(true);
  let guardando = $state(false);
  let aviso = $state('');
  let avisoCliente = $state('');

  onMount(async () => {
    try {
      const cfg = await api.obtenerConfig();
      activado = cfg.enviar_whatsapp === 'TRUE';
      clienteObligatorio = cfg.cliente_obligatorio === 'TRUE';
    } catch (e) { console.error(e); }
    cargando = false;
  });

  async function alternar() {
    const nuevo = !activado;
    guardando = true;
    aviso = '';
    try {
      await api.guardarConfig({ enviar_whatsapp: nuevo ? 'TRUE' : 'FALSE' });
      activado = nuevo;
      aviso = nuevo ? 'Opción activada. Al registrar una venta se pedirá el número y se podrá enviar el recibo por WhatsApp.' : 'Opción desactivada. Ya no se pedirá el número para el recibo.';
      setTimeout(() => { aviso = ''; }, 5000);
    } catch (e: any) {
      aviso = 'No se pudo guardar: ' + (e.message || 'Error');
    }
    guardando = false;
  }

  async function alternarCliente() {
    const nuevo = !clienteObligatorio;
    guardando = true;
    avisoCliente = '';
    try {
      await api.guardarConfig({ cliente_obligatorio: nuevo ? 'TRUE' : 'FALSE' });
      clienteObligatorio = nuevo;
      avisoCliente = nuevo ? 'El cliente ahora es obligatorio para registrar una venta.' : 'El cliente vuelve a ser opcional al registrar una venta.';
      setTimeout(() => { avisoCliente = ''; }, 5000);
    } catch (e: any) {
      avisoCliente = 'No se pudo guardar: ' + (e.message || 'Error');
    }
    guardando = false;
  }
</script>

<div class="max-w-2xl space-y-5">
  <div class="flex items-center gap-3">
    <div class="w-11 h-11 rounded-[14px] bg-slate-100 text-slate-600 flex items-center justify-center">
      <Icon name="cog" class="w-[22px] h-[22px]" />
    </div>
    <div>
      <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">Configuración</h2>
      <p class="text-[12.5px] text-slate-500">Ajustes generales del punto de venta</p>
    </div>
  </div>

  {#if cargando}
    <div class="panel p-6 space-y-3">
      <div class="h-4 w-2/3 rounded bg-slate-100 animate-pulse"></div>
      <div class="h-4 w-1/2 rounded bg-slate-100 animate-pulse"></div>
    </div>
  {:else}
    <div class="panel p-5 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
          <div class="w-10 h-10 rounded-[12px] bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <Icon name="message" class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Recibos por WhatsApp</h3>
            <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
              Al registrar una venta se le pedirá al cajero el número del cliente y podrá
              <strong class="text-slate-700">enviar el recibo por WhatsApp</strong> con un solo toque.
            </p>
          </div>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={activado}
          aria-label="Activar recibos por WhatsApp"
          onclick={alternar}
          disabled={guardando}
          class="relative shrink-0 w-[52px] h-[30px] rounded-full transition-colors duration-200 cursor-pointer disabled:opacity-60
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 focus-visible:ring-offset-2
            {activado ? 'bg-emerald-500' : 'bg-slate-300'}"
        >
          <span class={'absolute top-[3px] left-[3px] w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-200 ' + (activado ? 'translate-x-[22px]' : 'translate-x-0')}></span>
        </button>
      </div>

      <div class="mt-5 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 p-3.5 flex items-start gap-2.5">
          <Icon name="check" class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" strokeWidth={2.4} />
          <p class="text-[12px] text-slate-600 leading-relaxed">El recibo se arma automáticamente con fecha, cajero, ítems, forma de pago y total.</p>
        </div>
        <div class="rounded-xl bg-slate-50/80 ring-1 ring-inset ring-slate-100 p-3.5 flex items-start gap-2.5">
          <Icon name="user" class="w-4 h-4 text-blue-600 mt-0.5 shrink-0" />
          <p class="text-[12px] text-slate-600 leading-relaxed">La opción es opcional: el cajero puede omitir el envío y solo registrar la venta.</p>
        </div>
      </div>

      {#if guardando}
        <p class="mt-4 text-[12.5px] text-slate-500 flex items-center gap-2">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
          </svg>
          Guardando…
        </p>
      {/if}
      {#if aviso && !guardando}
        <p class="mt-4 text-[12.5px] flex items-start gap-2 {aviso.startsWith('No') ? 'text-rose-600' : 'text-emerald-700'}">
          <Icon name={aviso.startsWith('No') ? 'alert' : 'circle-check'} class="w-4 h-4 mt-[1px] shrink-0" />
          {aviso}
        </p>
      {/if}
    </div>

    <div class="panel p-5 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
          <div class="w-10 h-10 rounded-[12px] bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <Icon name="user" class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Cliente en la venta</h3>
            <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
              Indica si registrar una venta requiere un cliente o si puede dejarse en blanco.
            </p>
            <span class="chip mt-2 {clienteObligatorio ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200' : 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200'}">
              {clienteObligatorio ? 'Obligatorio' : 'Opcional'}
            </span>
          </div>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={clienteObligatorio}
          aria-label="Cliente obligatorio en la venta"
          onclick={alternarCliente}
          disabled={guardando}
          class="relative shrink-0 w-[52px] h-[30px] rounded-full transition-colors duration-200 cursor-pointer disabled:opacity-60
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-2
            {clienteObligatorio ? 'bg-blue-500' : 'bg-slate-300'}"
        >
          <span class={'absolute top-[3px] left-[3px] w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-200 ' + (clienteObligatorio ? 'translate-x-[22px]' : 'translate-x-0')}></span>
        </button>
      </div>
      {#if avisoCliente && !guardando}
        <p class="mt-4 text-[12.5px] flex items-start gap-2 {avisoCliente.startsWith('No') ? 'text-rose-600' : 'text-emerald-700'}">
          <Icon name={avisoCliente.startsWith('No') ? 'alert' : 'circle-check'} class="w-4 h-4 mt-[1px] shrink-0" />
          {avisoCliente}
        </p>
      {/if}
    </div>

    <p class="text-[12px] text-slate-400 px-1 flex items-center gap-1.5">
      <Icon name="message" class="w-3.5 h-3.5 text-emerald-500" />
      El envío usa WhatsApp Web / app con el mensaje ya redactado; el cajero solo confirma el envío.
    </p>
  {/if}
</div>
