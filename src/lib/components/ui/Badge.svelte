<script lang="ts">
  let { variant = 'default', dot = false, className = '', children }: {
    variant?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    dot?: boolean;
    className?: string;
    children?: any;
  } = $props();

  const tones: Record<string, string> = {
    default: 'bg-slate-100 text-slate-600 ring-slate-200',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    warning: 'bg-amber-50 text-amber-700 ring-amber-200',
    danger: 'bg-rose-50 text-rose-700 ring-rose-200',
    info: 'bg-blue-50 text-blue-700 ring-blue-200',
  };
</script>

<span class="chip ring-1 ring-inset {tones[variant]} {dot ? 'chip-dot' : ''} {className}">
  {@render children?.()}
</span>
