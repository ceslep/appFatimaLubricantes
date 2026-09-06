<script lang="ts">
  let { label = '', type = 'text', value = $bindable(''), placeholder = '', required = false, disabled = false }: {
    label?: string;
    type?: string;
    value?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
  } = $props();
</script>

<div class="space-y-1.5">
  {#if label}
    <!-- svelte-ignore a11y_label_has_associated_control -->
    <label class="block text-sm font-medium text-gray-700">{label}{required ? ' *' : ''}</label>
  {/if}
  <input
    {type}
    {placeholder}
    {required}
    {disabled}
    bind:value={value}
    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-900 text-sm
      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
      placeholder:text-gray-400 disabled:bg-gray-50 disabled:text-gray-500
      transition-all duration-200"
  />
</div>
