<script setup>
defineProps({
  text: { type: String, required: true },
})
</script>

<template>
  <div class="insight-card">
    <span class="insight-icon">💡</span>
    <p class="insight-text">{{ text }}</p>
  </div>
</template>

<style scoped>
.insight-card {
  background: #EFF6FF;
  border-left: 4px solid var(--color-info);
  border-radius: var(--radius-md);
  padding: var(--spacing-md) var(--spacing-lg);
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
}

.insight-icon {
  font-size: 20px;
  flex-shrink: 0;
  line-height: 1.5;
}

.insight-text {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
}
</style>
