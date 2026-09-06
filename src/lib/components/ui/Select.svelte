<script lang="ts">
  let { label = '', value = $bindable(''), options = [], placeholder = 'Seleccionar...', required = false }: {
    label?: string;
    value?: string;
    options: string[];
    placeholder?: string;
    required?: boolean;
  } = $props();
</script>

<div class="space-y-1.5">
  {#if label}
    <!-- svelte-ignore a11y_label_has_associated_control -->
    <label class="block text-sm font-medium text-gray-700">{label}{required ? ' *' : ''}</label>
  {/if}
  <select
    {required}
    bind:value={value}
    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-900 text-sm
      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
      transition-all duration-200 appearance-none cursor-pointer
      bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%236b7280%22%3E%3Cpath%20fill-rule%3D%22evenodd%22%20d%3D%22M5.23%207.21a.75.75%200%20011.06.02L10%2011.168l3.71-3.938a.75.75%200%20111.08%201.04l-4.25%204.5a.75.75%200%2001-1.08%200l-4.25-4.5a.75.75%200%2001.02-1.06z%22%20clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px] bg-[right_8px_center] bg-no-repeat pr-10"
  >
    <option value="">{placeholder}</option>
    {#each options as opt}
      <option value={opt}>{opt}</option>
    {/each}
  </select>
</div>
