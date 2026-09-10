<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import { enlaceRegistro, comprimirImagen } from '../utils';
  import { generarQrDataUrl } from '../qr';
  import { setLogo } from '../stores';
  import Icon from './ui/Icon.svelte';

  let activado = $state(false);
  let clienteObligatorio = $state(false);
  let cargando = $state(true);
  let guardando = $state(false);
  let aviso = $state('');
  let avisoCliente = $state('');
  let mensajeRegistro = $state('');
  let avisoMensaje = $state('');
  let guardandoMensaje = $state(false);
  let copiado = $state(false);
  let logoPreview = $state('');
  let guardandoLogo = $state(false);
  let avisoLogo = $state('');
  let errorLogo = $state('');
  const enlaceRegistroPublico = enlaceRegistro();
  const qrRegistro = (() => {
    try { return generarQrDataUrl(enlaceRegistroPublico, 10, 2); }
    catch (e) { console.error(e); return ''; }
  })();

  onMount(async () => {
    try {
      const cfg = await api.obtenerConfig();
      activado = cfg.enviar_whatsapp === 'TRUE';
      clienteObligatorio = cfg.cliente_obligatorio === 'TRUE';
      mensajeRegistro = cfg.mensaje_registro || '';
      logoPreview = cfg.logo_data || '';
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

  async function onLogoFile(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    errorLogo = '';
    avisoLogo = '';
    if (!file.type.startsWith('image/')) {
      errorLogo = 'Selecciona una imagen PNG, JPG o WebP.';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      errorLogo = 'La imagen no debe superar 5 MB.';
      return;
    }
    try {
      logoPreview = await comprimirImagen(file);
    } catch (err: any) {
      errorLogo = err?.message || 'No se pudo procesar la imagen.';
    }
  }

  async function guardarLogo() {
    guardandoLogo = true;
    avisoLogo = '';
    errorLogo = '';
    try {
      await api.guardarConfig({ logo_data: logoPreview || '' });
      setLogo(logoPreview || '');
      avisoLogo = logoPreview ? 'Logo guardado. Ya se usa en toda la aplicación.' : 'Logo eliminado.';
      setTimeout(() => { avisoLogo = ''; }, 5000);
    } catch (e: any) {
      errorLogo = 'No se pudo guardar: ' + (e.message || 'Error');
    }
    guardandoLogo = false;
  }

  function quitarLogo() {
    logoPreview = '';
    avisoLogo = '';
    errorLogo = '';
  }

  async function guardarMensaje() {
    guardandoMensaje = true;
    avisoMensaje = '';
    try {
      await api.guardarConfig({ mensaje_registro: mensajeRegistro });
      avisoMensaje = 'Mensaje guardado. Ya aparece en la página de registro.';
      setTimeout(() => { avisoMensaje = ''; }, 5000);
    } catch (e: any) {
      avisoMensaje = 'No se pudo guardar: ' + (e.message || 'Error');
    }
    guardandoMensaje = false;
  }

  async function copiarEnlace() {
    try {
      await navigator.clipboard.writeText(enlaceRegistroPublico);
      copiado = true;
      setTimeout(() => { copiado = false; }, 2000);
    } catch (e) {
      console.error(e);
    }
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
      <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-[12px] bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
          <Icon name="droplet" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Logo de la aplicación</h3>
          <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
            Se muestra en el menú, el inicio de sesión y la página de registro de clientes. Sin logo se usa el ícono por defecto.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <div class="w-16 h-16 rounded-2xl bg-white ring-1 ring-inset ring-slate-200 flex items-center justify-center overflow-hidden shrink-0">
          {#if logoPreview}
            <img src={logoPreview} alt="Vista previa del logo" class="w-full h-full object-contain" />
          {:else}
            <Icon name="droplet" class="w-7 h-7 text-slate-300" />
          {/if}
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <label class="inline-flex items-center gap-2 rounded-[10px] bg-slate-100 text-slate-700 text-[12.5px] font-semibold px-3 py-2.5 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer">
            <Icon name="upload" class="w-4 h-4" />
            {logoPreview ? 'Cambiar imagen' : 'Subir imagen'}
            <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" onchange={onLogoFile} />
          </label>
          {#if logoPreview}
            <button type="button" onclick={quitarLogo}
              class="inline-flex items-center gap-1.5 rounded-[10px] bg-white text-rose-600 text-[12.5px] font-semibold px-3 py-2.5 ring-1 ring-inset ring-rose-200 hover:bg-rose-50 transition-colors cursor-pointer">
              <Icon name="trash" class="w-4 h-4" />
              Quitar
            </button>
          {/if}
        </div>
      </div>
      <p class="text-[11px] text-slate-400 mt-2">PNG, JPG o WebP. Se optimiza automáticamente (máx. 256 px).</p>

      {#if errorLogo}
        <p class="mt-3 text-[12.5px] text-rose-600 flex items-start gap-2">
          <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
          {errorLogo}
        </p>
      {/if}
      {#if avisoLogo}
        <p class="mt-3 text-[12.5px] text-emerald-700 flex items-start gap-2">
          <Icon name="circle-check" class="w-4 h-4 mt-[1px] shrink-0" />
          {avisoLogo}
        </p>
      {/if}

      <button type="button" onclick={guardarLogo} disabled={guardandoLogo}
        class="mt-4 inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-4 py-2.5
          hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer disabled:opacity-60 disabled:pointer-events-none
          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
        {#if guardandoLogo}Guardando…{:else}<Icon name="check" class="w-4 h-4" strokeWidth={2.4} />Guardar logo{/if}
      </button>
    </div>

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

    <div class="panel p-5 sm:p-6">
      <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-[12px] bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
          <Icon name="users" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <h3 class="text-[14.5px] font-bold text-slate-900 tracking-tight">Registro de clientes (fidelidad)</h3>
          <p class="text-[12.5px] text-slate-500 mt-0.5 leading-relaxed">
            Comparte el enlace con tus clientes para que se registren <strong class="text-slate-700">sin iniciar sesión</strong>.
            El mensaje de abajo aparece en la parte superior de esa página.
          </p>
        </div>
      </div>

      <label class="field-label" for="cfg-mensaje">Mensaje de bienvenida</label>
      <textarea
        id="cfg-mensaje"
        rows="4"
        maxlength="500"
        bind:value={mensajeRegistro}
        placeholder="Ej: Regístrate y acumula puntos en cada compra. ¡Recibe promociones exclusivas!"
        class="input-base min-h-[110px] py-3 resize-y leading-relaxed"
      ></textarea>
      <div class="mt-1 flex items-center justify-between gap-2">
        <p class="text-[11px] text-slate-400">Si lo dejas vacío se usa un mensaje por defecto.</p>
        <span class="text-[11px] text-slate-400">{mensajeRegistro.length}/500</span>
      </div>

      {#if guardandoMensaje}
        <p class="mt-4 text-[12.5px] text-slate-500 flex items-center gap-2">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
          </svg>
          Guardando…
        </p>
      {/if}
      {#if avisoMensaje && !guardandoMensaje}
        <p class="mt-4 text-[12.5px] flex items-start gap-2 {avisoMensaje.startsWith('No') ? 'text-rose-600' : 'text-emerald-700'}">
          <Icon name={avisoMensaje.startsWith('No') ? 'alert' : 'circle-check'} class="w-4 h-4 mt-[1px] shrink-0" />
          {avisoMensaje}
        </p>
      {/if}

      <button
        type="button"
        onclick={guardarMensaje}
        disabled={guardandoMensaje}
        class="mt-4 inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-4 py-2.5
          hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer disabled:opacity-60 disabled:pointer-events-none
          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
      >
        {#if guardandoMensaje}Guardando…{:else}<Icon name="check" class="w-4 h-4" strokeWidth={2.4} />Guardar mensaje{/if}
      </button>

      <div class="mt-5 pt-4 border-t border-slate-100">
        <p class="field-label">Enlace para compartir</p>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
          <input
            type="text"
            readonly
            value={enlaceRegistroPublico}
            class="input-base flex-1 !text-[12.5px] text-slate-600"
            onclick={(e) => (e.currentTarget as HTMLInputElement).select()}
          />
          <button
            type="button"
            onclick={copiarEnlace}
            class="inline-flex items-center justify-center gap-1.5 rounded-[10px] bg-slate-100 text-slate-700 text-[12.5px] font-semibold px-3 py-2.5 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer shrink-0"
          >
            <Icon name={copiado ? 'check' : 'copy'} class="w-4 h-4" />
            {copiado ? 'Copiado' : 'Copiar'}
          </button>
        </div>
        <p class="text-[11px] text-slate-400 mt-1.5">Los registros se guardan directamente en la hoja de clientes.</p>
      </div>

      {#if qrRegistro}
        <div class="mt-5 pt-4 border-t border-slate-100">
          <p class="field-label">Código QR del enlace</p>
          <div class="flex flex-col sm:flex-row items-start gap-4">
            <div class="p-3 bg-white rounded-2xl ring-1 ring-inset ring-slate-200 shrink-0">
              <img src={qrRegistro} alt="Código QR del enlace de registro de clientes" class="w-[180px] h-[180px]" />
            </div>
            <div class="min-w-0">
              <p class="text-[12.5px] text-slate-500 leading-relaxed">
                Tus clientes pueden escanearlo con la cámara del celular para abrir el formulario de registro, sin iniciar sesión.
              </p>
              <a
                href={qrRegistro}
                download="qr-registro-clientes.svg"
                class="mt-3 inline-flex items-center gap-2 rounded-[10px] bg-slate-100 text-slate-700 text-[12.5px] font-semibold px-3 py-2.5 ring-1 ring-inset ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer"
              >
                <Icon name="download" class="w-4 h-4" />
                Descargar QR (SVG)
              </a>
            </div>
          </div>
        </div>
      {/if}
    </div>

    <p class="text-[12px] text-slate-400 px-1 flex items-center gap-1.5">
      <Icon name="message" class="w-3.5 h-3.5 text-emerald-500" />
      El envío usa WhatsApp Web / app con el mensaje ya redactado; el cajero solo confirma el envío.
    </p>
  {/if}
</div>
