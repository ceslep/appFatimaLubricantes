<script lang="ts">
  import Icon from './Icon.svelte';
  import type { HTMLInputAttributes } from 'svelte/elements';

  let { label = '', type = 'text', value = $bindable(''), placeholder = '', required = false, disabled = false, autocomplete = 'off', icon = '' }: {
    label?: string;
    type?: string;
    value?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    autocomplete?: HTMLInputAttributes['autocomplete'];
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
    <input
      {type}
      {placeholder}
      {required}
      {disabled}
      {autocomplete}
      bind:value={value}
      class="input-base {icon ? 'pl-10' : ''}"
    />
  </div>
</div>
