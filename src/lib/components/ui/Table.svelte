<script lang="ts">
  let { headers = [], children }: {
    headers?: string[];
    children?: any;
  } = $props();
</script>

<div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-white/30">
        {#each headers as h}
          <th class="px-4 py-3 text-left font-semibold text-gray-600">{h}</th>
        {/each}
      </tr>
    </thead>
    <tbody class="divide-y divide-white/20">
      {@render children?.()}
    </tbody>
  </table>
</div>
