<script lang="ts">
  let { show = false, title = '', onclose = () => {}, children }: {
    show?: boolean;
    title?: string;
    onclose?: () => void;
    children?: any;
  } = $props();
</script>

{#if show}
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="absolute inset-0 bg-black/30 backdrop-blur-md" onclick={onclose}></div>
    <div class="relative glass-strong rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 animate-in">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-gray-900">{title}</h3>
        <button onclick={onclose} class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      {@render children?.()}
    </div>
  </div>
{/if}

<style>
  @keyframes slideIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
  .animate-in { animation: slideIn 0.2s ease-out; }
</style>
