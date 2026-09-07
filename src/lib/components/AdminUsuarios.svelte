<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Table from './ui/Table.svelte';
  import Badge from './ui/Badge.svelte';
  import Button from './ui/Button.svelte';
  import Input from './ui/Input.svelte';
  import Select from './ui/Select.svelte';
  import Modal from './ui/Modal.svelte';
  import Icon from './ui/Icon.svelte';
  import type { Usuario } from '../types';

  let usuarios = $state<Usuario[]>([]);
  let loading = $state(true);
  let showModal = $state(false);
  let nuevoUsuario = $state('');
  let nuevaPassword = $state('');
  let nuevoNombre = $state('');
  let nuevoRol = $state('cajero');
  let error = $state('');
  let editandoUsuario = $state('');
  let nuevaPasswordEdit = $state('');
  let showModalEdit = $state(false);

  onMount(async () => {
    try { usuarios = await api.listarUsuarios(); } catch (e) { console.error(e); }
    loading = false;
  });

  function rolLabel(rol: string) {
    return rol === 'admin' ? 'Administrador' : rol === 'cajero' ? 'Cajero' : rol;
  }

  function inicialesDe(nombre: string, usr: string) {
    const base = (nombre || usr || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase();
    return base || 'U';
  }

  async function crear() {
    if (!nuevoUsuario || !nuevaPassword) { error = 'Complete usuario y contraseña'; return; }
    try {
      await api.crearUsuario({ usuario: nuevoUsuario, password: nuevaPassword, rol: nuevoRol, nombre: nuevoNombre });
      usuarios = await api.listarUsuarios();
      showModal = false;
      nuevoUsuario = ''; nuevaPassword = ''; nuevoNombre = ''; nuevoRol = 'cajero';
    } catch (e: any) { error = e.message; }
  }

  async function actualizarPassword() {
    if (!editandoUsuario || !nuevaPasswordEdit) { error = 'Complete la contraseña'; return; }
    try {
      await api.actualizarUsuario({ usuario: editandoUsuario, password: nuevaPasswordEdit });
      showModalEdit = false;
      editandoUsuario = '';
      nuevaPasswordEdit = '';
    } catch (e: any) { error = e.message; }
  }

  function abrirEditar(usr: string) {
    editandoUsuario = usr;
    nuevaPasswordEdit = '';
    error = '';
    showModalEdit = true;
  }

  async function desactivar(u: Usuario) {
    if (!confirm('Desactivar usuario ' + u.usuario + '?')) return;
    try {
      await api.eliminarUsuario(u.row);
      usuarios = await api.listarUsuarios();
    } catch (e: any) { alert(e.message); }
  }
</script>

<div class="space-y-5">
  <div class="flex flex-col sm:flex-row sm:items-center gap-3">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-[14px] bg-blue-50 text-blue-600 flex items-center justify-center">
        <Icon name="users" class="w-[22px] h-[22px]" />
      </div>
      <div>
        <h2 class="text-[17px] font-bold text-slate-900 tracking-tight">Usuarios del sistema</h2>
        <p class="text-[12.5px] text-slate-500">{usuarios.length} cuenta{usuarios.length === 1 ? '' : 's'} registrada{usuarios.length === 1 ? '' : 's'}</p>
      </div>
    </div>
    <button
      onclick={() => { error = ''; showModal = true; }}
      class="inline-flex items-center gap-2 rounded-[10px] bg-blue-600 text-white text-[13px] font-semibold px-3.5 py-2.5 sm:ml-auto
        shadow-[0_1px_2px_rgba(30,64,175,0.35),0_6px_14px_-6px_rgba(37,99,235,0.5)]
        hover:bg-blue-700 active:scale-[0.98] transition-all cursor-pointer
        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
    >
      <Icon name="plus" class="w-4 h-4" strokeWidth={2.4} />
      <span class="hidden xs:inline">Nuevo usuario</span>
    </button>
  </div>

  {#if loading}
    <div class="panel overflow-hidden">
      <div class="divide-y divide-slate-100">
        {#each Array(4) as _}
          <div class="px-5 py-4 flex items-center gap-4 animate-pulse">
            <div class="w-9 h-9 rounded-full bg-slate-200/70"></div>
            <div class="h-3 w-32 rounded bg-slate-200/60"></div>
            <div class="h-3 w-20 rounded bg-slate-200/50 ml-auto"></div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <!-- Escritorio: tabla -->
    <div class="hidden md:block panel overflow-hidden">
      <Table headers={['Usuario', 'Nombre', 'Rol', 'Estado', 'Acciones']}>
        {#each usuarios as u}
          <tr class="hover:bg-blue-50/30 transition-colors">
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white text-[11.5px] font-bold flex items-center justify-center shrink-0 select-none">{inicialesDe(u.nombre, u.usuario)}</span>
                <span class="font-semibold text-[13.5px] text-slate-900">{u.usuario}</span>
              </div>
            </td>
            <td class="px-4 py-3.5 text-[13px] text-slate-600">{u.nombre || '—'}</td>
            <td class="px-4 py-3.5"><Badge variant={u.rol === 'admin' ? 'info' : 'default'} dot>{rolLabel(u.rol)}</Badge></td>
            <td class="px-4 py-3.5"><Badge variant={u.activo === 'TRUE' ? 'success' : 'danger'} dot>{u.activo === 'TRUE' ? 'Activo' : 'Inactivo'}</Badge></td>
            <td class="px-4 py-3.5">
              {#if u.activo === 'TRUE'}
                <div class="flex items-center gap-1">
                  <button onclick={() => abrirEditar(u.usuario)} class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"><Icon name="key" class="w-3.5 h-3.5" />Cambiar clave</button>
                  <button onclick={() => desactivar(u)} class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"><Icon name="x" class="w-3.5 h-3.5" />Desactivar</button>
                </div>
              {/if}
            </td>
          </tr>
        {/each}
      </Table>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="md:hidden space-y-3">
      {#each usuarios as u}
        <div class="panel p-4">
          <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white text-[13px] font-bold flex items-center justify-center shrink-0 select-none">{inicialesDe(u.nombre, u.usuario)}</span>
            <div class="min-w-0 flex-1">
              <p class="text-[14.5px] font-bold text-slate-900 truncate">{u.nombre || u.usuario}</p>
              <p class="text-[11.5px] text-slate-400 truncate">@{u.usuario}</p>
            </div>
            <div class="shrink-0 text-right space-y-1">
              <div><Badge variant={u.rol === 'admin' ? 'info' : 'default'} dot>{rolLabel(u.rol)}</Badge></div>
              <div><Badge variant={u.activo === 'TRUE' ? 'success' : 'danger'} dot>{u.activo === 'TRUE' ? 'Activo' : 'Inactivo'}</Badge></div>
            </div>
          </div>
          {#if u.activo === 'TRUE'}
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2">
              <button onclick={() => abrirEditar(u.usuario)} class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-200 px-3 py-2 text-[12px] font-semibold text-slate-600 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer">
                <Icon name="key" class="w-3.5 h-3.5" />Cambiar clave
              </button>
              <button onclick={() => desactivar(u)} class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-200 px-3 py-2 text-[12px] font-semibold text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                <Icon name="x" class="w-3.5 h-3.5" />Desactivar
              </button>
            </div>
          {/if}
        </div>
      {/each}
    </div>
  {/if}
</div>

<Modal show={showModal} title="Nuevo usuario" subtitle="Crea una cuenta para acceso al punto de venta" onclose={() => (showModal = false)}>
  <div class="space-y-4">
    <Input label="Usuario" bind:value={nuevoUsuario} placeholder="nombre_usuario" required autocomplete="off" icon="user" />
    <Input label="Nombre completo" bind:value={nuevoNombre} placeholder="Nombre y apellido" autocomplete="off" icon="users" />
    <Input label="Contraseña" type="password" bind:value={nuevaPassword} placeholder="Mínimo 4 caracteres" required autocomplete="new-password" icon="lock" />
    <Select label="Rol" bind:value={nuevoRol} options={['admin', 'cajero']} icon="shield" />
    {#if error}
      <p class="text-[13px] text-rose-600">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => (showModal = false)}>Cancelar</Button>
      <Button variant="primary" onclick={crear}>Crear usuario</Button>
    </div>
  </div>
</Modal>

<Modal show={showModalEdit} title="Cambiar contraseña" subtitle={'Usuario: ' + editandoUsuario} onclose={() => (showModalEdit = false)}>
  <div class="space-y-4">
    <Input label="Nueva contraseña" type="password" bind:value={nuevaPasswordEdit} placeholder="Nueva contraseña" required autocomplete="new-password" icon="lock" />
    {#if error}
      <p class="text-[13px] text-rose-600">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => (showModalEdit = false)}>Cancelar</Button>
      <Button variant="primary" onclick={actualizarPassword}>Guardar</Button>
    </div>
  </div>
</Modal>
