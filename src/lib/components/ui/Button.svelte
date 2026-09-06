<script lang="ts">
  let { variant = 'primary', disabled = false, onclick = () => {}, children }: {
    variant?: 'primary' | 'secondary' | 'danger' | 'success';
    disabled?: boolean;
    onclick?: () => void;
    children?: any;
  } = $props();
</script>

<button
  class="px-4 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed
    {variant === 'primary' ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-md shadow-blue-200 active:scale-95' : ''}
    {variant === 'secondary' ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200 active:scale-95' : ''}
    {variant === 'danger' ? 'bg-red-500 text-white hover:bg-red-600 shadow-md shadow-red-200 active:scale-95' : ''}
    {variant === 'success' ? 'bg-emerald-500 text-white hover:bg-emerald-600 shadow-md shadow-emerald-200 active:scale-95' : ''}"
  {disabled}
  onclick={onclick}
>
  {@render children?.()}
</button>
