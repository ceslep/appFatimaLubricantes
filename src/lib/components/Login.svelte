<script lang="ts">
  import { api } from '../api';
  import { login } from '../stores';
  import Button from './ui/Button.svelte';
  import Input from './ui/Input.svelte';

  let usuario = $state('');
  let password = $state('');
  let error = $state('');
  let loading = $state(false);

  const gasPumpIcon = 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Y9XTBPYYZden371yTkDUk5RakCVmX9.png';

  async function handleLogin() {
    if (!usuario || !password) { error = 'Complete todos los campos'; return; }
    loading = true;
    error = '';
    try {
      const user = await api.login(usuario, password);
      login(user);
    } catch (e: any) {
      error = e.message || 'Error al iniciar sesion';
    } finally {
      loading = false;
    }
  }
</script>

<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <img src={gasPumpIcon} alt="Fatima LB" class="icon-thumb-lg mx-auto mb-4" />
      <h1 class="text-2xl font-bold text-gray-900">Fatima LB</h1>
      <p class="text-gray-500 text-sm mt-1">Estacion de Servicio - San Clemente</p>
    </div>

    <div class="glass-strong rounded-2xl p-6">
      <form onsubmit={(e) => { e.preventDefault(); handleLogin(); }}>
        <div class="space-y-4">
          <Input label="Usuario" bind:value={usuario} placeholder="Ingrese su usuario" required />
          <Input label="Contrasena" type="password" bind:value={password} placeholder="Ingrese su contrasena" required />
        </div>

        {#if error}
          <div class="mt-3 p-3 rounded-xl bg-red-50/80 border border-red-100 text-red-600 text-sm">{error}</div>
        {/if}

        <div class="mt-6">
          <Button variant="primary" disabled={loading}>
            {loading ? 'Ingresando...' : 'Iniciar Sesion'}
          </Button>
        </div>
      </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">Sistema de Venta de Lubricantes</p>
  </div>
</div>
