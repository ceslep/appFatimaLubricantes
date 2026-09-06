<script lang="ts">
  let { children, className = '' }: { children?: any; className?: string } = $props();
</script>

<div class="glass-card rounded-2xl {className}">
  {@render children?.()}
</div>
