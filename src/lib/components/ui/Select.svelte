<script lang="ts">
  import Icon from './Icon.svelte';

  let { label = '', value = $bindable(''), options = [], placeholder = 'Seleccionar...', required = false, icon = '' }: {
    label?: string;
    value?: string;
    options: string[];
    placeholder?: string;
    required?: boolean;
    icon?: string;
  } = $props();
</script>

<div>
  {#if label}
    <!-- svelte-ignore a11y_label_has_associated_control -->
    <label class="field-label">{label}{required ? ' *' : ''}</label>
  {/if}
  <div class="relative">
    {#if icon}
      <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
        <Icon name={icon} class="w-[18px] h-[18px]" />
      </span>
    {/if}
    <select
      {required}
      bind:value={value}
      class="input-base appearance-none pr-10 cursor-pointer disabled:bg-slate-50 {icon ? 'pl-10' : ''}"
    >
      <option value="">{placeholder}</option>
      {#each options as opt}
        <option value={opt}>{opt}</option>
      {/each}
    </select>
    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
      <Icon name="chevron-down" class="w-4 h-4" />
    </span>
  </div>
</div>
