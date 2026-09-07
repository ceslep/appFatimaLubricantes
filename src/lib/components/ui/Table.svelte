<script lang="ts">
  let { headers = [], className = '', children }: {
    headers?: string[];
    className?: string;
    children?: any;
  } = $props();
</script>

<div class="overflow-x-auto scrollbar-thin {className}">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-slate-200/80 bg-slate-50/60">
        {#each headers as h}
          <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">{h}</th>
        {/each}
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 bg-white">
      {@render children?.()}
    </tbody>
  </table>
</div>
