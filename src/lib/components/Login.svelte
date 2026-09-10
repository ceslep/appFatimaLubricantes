<script lang="ts">
  import { api } from '../api';
  import { login, logo } from '../stores';
  import Icon from './ui/Icon.svelte';

  let usuario = $state('');
  let password = $state('');
  let error = $state('');
  let loading = $state(false);

  async function handleLogin() {
    if (!usuario || !password) { error = 'Complete todos los campos'; return; }
    loading = true;
    error = '';
    try {
      const user = await api.login(usuario, password);
      login(user);
    } catch (e: any) {
      error = e.message || 'Error al iniciar sesión';
    } finally {
      loading = false;
    }
  }
</script>

<div class="min-h-dvh flex items-center justify-center p-4 sm:p-6 relative overflow-hidden">
  <!-- Brillo decorativo de fondo -->
  <div class="pointer-events-none absolute -top-32 -right-24 w-[420px] h-[420px] rounded-full bg-blue-400/15 blur-3xl"></div>
  <div class="pointer-events-none absolute -bottom-36 -left-24 w-[420px] h-[420px] rounded-full bg-emerald-300/15 blur-3xl"></div>

  <div class="relative w-full max-w-[400px]">
    <div class="panel rounded-[22px] p-7 sm:p-8 shadow-[0_2px_4px_rgba(15,23,42,0.04),0_24px_60px_-24px_rgba(15,23,42,0.22)]">
      <!-- Marca -->
      <div class="flex flex-col items-center text-center mb-8">
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
        <p class="text-[13px] text-slate-500 mt-1">Lubricantes &amp; Aditivos &middot; Punto de venta</p>
      </div>

      <form onsubmit={(e) => { e.preventDefault(); handleLogin(); }}>
        <div class="space-y-4">
          <div>
            <label class="field-label" for="login-usuario">Usuario</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="user" class="w-[18px] h-[18px]" />
              </span>
              <input
                id="login-usuario"
                class="input-base pl-10"
                type="text"
                placeholder="Ingrese su usuario"
                bind:value={usuario}
                autocomplete="username"
                required
              />
            </div>
          </div>

          <div>
            <label class="field-label" for="login-password">Contraseña</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <Icon name="lock" class="w-[18px] h-[18px]" />
              </span>
              <input
                id="login-password"
                class="input-base pl-10"
                type="password"
                placeholder="Ingrese su contraseña"
                bind:value={password}
                autocomplete="current-password"
                required
              />
            </div>
          </div>
        </div>

        {#if error}
          <div class="notice-error" role="alert">
            <Icon name="alert" class="w-4 h-4 mt-[1px] shrink-0" />
            <span>{error}</span>
          </div>
        {/if}

        <button
          type="submit"
          disabled={loading}
          class="mt-6 w-full inline-flex items-center justify-center gap-2 rounded-[12px] px-4 py-3 text-[15px] font-semibold text-white
            bg-blue-600 shadow-[0_1px_2px_rgba(30,64,175,0.4),0_10px_24px_-10px_rgba(37,99,235,0.7)]
            hover:bg-blue-700 active:scale-[0.99] transition-all duration-150 cursor-pointer
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-2
            disabled:opacity-60 disabled:pointer-events-none"
        >
          {#if loading}
            <svg class="w-4.5 h-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4z"></path>
            </svg>
            Ingresando…
          {:else}
            <Icon name="logout" class="w-[18px] h-[18px] rotate-180" />
            Iniciar Sesión
          {/if}
        </button>
      </form>
    </div>

    <p class="text-center text-[11.5px] text-slate-400 mt-6 tracking-wide">Sistema de ventas &middot; Estación de Servicio Fátima</p>
  </div>
</div>
