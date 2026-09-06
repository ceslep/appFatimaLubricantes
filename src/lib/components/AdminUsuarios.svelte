<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../api';
  import Table from './ui/Table.svelte';
  import Badge from './ui/Badge.svelte';
  import Button from './ui/Button.svelte';
  import Input from './ui/Input.svelte';
  import Select from './ui/Select.svelte';
  import Modal from './ui/Modal.svelte';
  import type { Usuario } from '../types';

  const ICONS = {
    user: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-dae1lHATNY8hkh3GxeAnScrtd34Ii5.png',
    userPlus: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-dae1lHATNY8hkh3GxeAnScrtd34Ii5.png',
    key: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-eoyH7M8JNf4jKVNRWkR3rGMBafmrKZ.png',
    shield: 'https://lftz25oez4aqbxpq.public.blob.vercel-storage.com/image-Qep2rmAXunWu8R2o1FCgLLDrtiD1q2.png',
  };

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

  async function crear() {
    if (!nuevoUsuario || !nuevaPassword) { error = 'Complete usuario y contrasena'; return; }
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

  function abrirEditar(usuario: string) {
    editandoUsuario = usuario;
    nuevaPasswordEdit = '';
    error = '';
    showModalEdit = true;
  }

  async function desactivar(u: Usuario) {
    if (!confirm(`Desactivar usuario ${u.usuario}?`)) return;
    try {
      await api.eliminarUsuario(u.row);
      usuarios = await api.listarUsuarios();
    } catch (e: any) { alert(e.message); }
  }
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src={ICONS.user} alt="Usuarios" class="icon-thumb" />
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Usuarios</h1>
        <p class="text-gray-500 text-sm">{usuarios.length} usuarios registrados</p>
      </div>
    </div>
    <Button variant="primary" onclick={() => showModal = true}>+ Nuevo Usuario</Button>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
  {:else}
    <div class="glass-strong rounded-2xl overflow-hidden">
      <Table headers={['Usuario', 'Nombre', 'Rol', 'Estado', '']}>
        {#each usuarios as u}
          <tr class="hover:bg-white/50 transition-colors">
            <td class="px-4 py-3 font-medium text-gray-900">{u.usuario}</td>
            <td class="px-4 py-3 text-gray-600">{u.nombre}</td>
            <td class="px-4 py-3">
              <Badge variant={u.rol === 'admin' ? 'info' : 'default'}>{u.rol}</Badge>
            </td>
            <td class="px-4 py-3">
              <Badge variant={u.activo === 'TRUE' ? 'success' : 'danger'}>
                {u.activo === 'TRUE' ? 'Activo' : 'Inactivo'}
              </Badge>
            </td>
            <td class="px-4 py-3">
              {#if u.activo === 'TRUE'}
                <div class="flex gap-2">
                  <button onclick={() => abrirEditar(u.usuario)} class="text-blue-400 hover:text-blue-600 text-sm flex items-center gap-1">
                    <img src={ICONS.key} alt="" class="w-3 h-3" />
                    Editar
                  </button>
                  <button onclick={() => desactivar(u)} class="text-red-400 hover:text-red-600 text-sm">Desactivar</button>
                </div>
              {/if}
            </td>
          </tr>
        {/each}
      </Table>
    </div>
  {/if}
</div>

<Modal show={showModal} title="Nuevo Usuario" onclose={() => showModal = false}>
  <div class="space-y-4">
    <div class="flex items-center gap-2">
      <img src={ICONS.user} alt="" class="icon-thumb-sm" />
      <div class="flex-1">
        <Input label="Usuario" bind:value={nuevoUsuario} placeholder="nombre_usuario" required />
      </div>
    </div>
    <Input label="Nombre Completo" bind:value={nuevoNombre} placeholder="Nombre y apellido" />
    <div class="flex items-center gap-2">
      <img src={ICONS.key} alt="" class="icon-thumb-sm" />
      <div class="flex-1">
        <Input label="Contrasena" type="password" bind:value={nuevaPassword} placeholder="Minimo 4 caracteres" required />
      </div>
    </div>
    <div class="flex items-center gap-2">
      <img src={ICONS.shield} alt="" class="icon-thumb-sm" />
      <div class="flex-1">
        <Select label="Rol" bind:value={nuevoRol} options={['admin', 'cajero']} />
      </div>
    </div>
    {#if error}
      <p class="text-red-500 text-sm">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => showModal = false}>Cancelar</Button>
      <Button variant="primary" onclick={crear}>Crear Usuario</Button>
    </div>
  </div>
</Modal>

<Modal show={showModalEdit} title="Cambiar Contraseña" onclose={() => showModalEdit = false}>
  <div class="space-y-4">
    <div class="flex items-center gap-2">
      <img src={ICONS.user} alt="" class="icon-thumb-sm" />
      <p class="text-sm text-gray-600">Usuario: <strong>{editandoUsuario}</strong></p>
    </div>
    <div class="flex items-center gap-2">
      <img src={ICONS.key} alt="" class="icon-thumb-sm" />
      <div class="flex-1">
        <Input label="Nueva Contraseña" type="password" bind:value={nuevaPasswordEdit} placeholder="Nueva contraseña" required />
      </div>
    </div>
    {#if error}
      <p class="text-red-500 text-sm">{error}</p>
    {/if}
    <div class="flex gap-3 justify-end pt-2">
      <Button variant="secondary" onclick={() => showModalEdit = false}>Cancelar</Button>
      <Button variant="primary" onclick={actualizarPassword}>Guardar</Button>
    </div>
  </div>
</Modal>
