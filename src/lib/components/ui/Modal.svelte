<script lang="ts">
  import { fade, fly } from 'svelte/transition';

  let { show = false, title = '', subtitle = '', onclose = () => {}, children }: {
    show?: boolean;
    title?: string;
    subtitle?: string;
    onclose?: () => void;
    children?: any;
  } = $props();
</script>

<svelte:window onkeydown={(e) => { if (show && e.key === 'Escape') onclose(); }} />

{#if show}
  <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
      class="absolute inset-0 bg-slate-900/40 backdrop-blur-[3px]"
      role="presentation"
      onclick={onclose}
      transition:fade={{ duration: 150 }}
    ></div>

    <div
      class="relative w-full sm:max-w-md bg-white sm:rounded-2xl rounded-t-2xl shadow-2xl ring-1 ring-slate-900/5 max-h-[92vh] overflow-y-auto scrollbar-thin"
      role="dialog"
      aria-modal="true"
      aria-label={title}
      transition:fly={{ y: 16, duration: 200 }}
    >
      <div class="flex items-start justify-between gap-3 px-5 pt-5 pb-1 sm:px-6 sm:pt-6">
        <div>
          <h3 class="text-[17px] font-bold text-slate-900 leading-6">{title}</h3>
          {#if subtitle}
            <p class="text-[13px] text-slate-500 mt-0.5">{subtitle}</p>
          {/if}
        </div>
        <button
          onclick={onclose}
          aria-label="Cerrar"
          class="shrink-0 -mt-1 -mr-1 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
      <div class="px-5 pt-3 pb-5 sm:px-6 sm:pb-6">
        {@render children?.()}
      </div>
    </div>
  </div>
{/if}
