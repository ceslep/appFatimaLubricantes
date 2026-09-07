<script lang="ts">
  let { children, className = '', hover = false }: { children?: any; className?: string; hover?: boolean } = $props();
</script>

<div class="panel {hover ? 'panel-hover' : ''} {className}">
  {@render children?.()}
</div>
