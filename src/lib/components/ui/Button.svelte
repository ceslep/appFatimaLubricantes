<script lang="ts">
  let { variant = 'primary', disabled = false, onclick = () => {}, children }: {
    variant?: 'primary' | 'secondary' | 'danger' | 'success';
    disabled?: boolean;
    onclick?: () => void;
    children?: any;
  } = $props();

  const base =
    'toque inline-flex items-center justify-center gap-2 rounded-[10px] px-4 py-2.5 text-sm font-semibold ' +
    'transition-all duration-150 select-none whitespace-nowrap cursor-pointer ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 focus-visible:ring-offset-1 ' +
    'active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none';

  const variants: Record<string, string> = {
    primary:
      'bg-blue-600 text-white shadow-[0_1px_2px_rgba(30,64,175,0.4),0_6px_16px_-6px_rgba(37,99,235,0.55)] hover:bg-blue-700',
    secondary:
      'bg-white text-slate-700 ring-1 ring-inset ring-slate-300/80 shadow-sm hover:bg-slate-50 hover:text-slate-900 hover:ring-slate-300',
    danger: 'bg-rose-600 text-white shadow-[0_1px_2px_rgba(159,18,57,0.4),0_6px_16px_-6px_rgba(225,29,72,0.5)] hover:bg-rose-700',
    success: 'bg-emerald-600 text-white shadow-[0_1px_2px_rgba(6,95,70,0.4),0_6px_16px_-6px_rgba(5,150,105,0.5)] hover:bg-emerald-700',
  };
</script>

<button class="{base} {variants[variant]}" {disabled} onclick={onclick}>
  {@render children?.()}
</button>
