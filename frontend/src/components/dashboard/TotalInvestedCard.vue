<script setup>
defineProps({
  value: { type: String, required: true },
})
</script>

<template>
  <div class="card">
    <p class="card-title">Total Investido</p>
    <p class="card-value">{{ value }}</p>
  </div>
</template>

<style scoped>
.card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.card-title {
  font-size: 13px;
  color: var(--color-text-secondary);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.card-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--color-secondary);
}
</style>
