<script lang="ts">
  let { variant = 'default', children }: {
    variant?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    children?: any;
  } = $props();

  const colors: Record<string, string> = {
    default: 'bg-gray-100 text-gray-700',
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-red-100 text-red-700',
    info: 'bg-blue-100 text-blue-700',
  };
</script>

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {colors[variant]}">
  {@render children?.()}
</span>
