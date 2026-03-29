<script setup>
defineProps({
  title: { type: String, required: true },
  value: { type: String, required: true },
  subtitle: { type: String, default: '' },
  color: { type: String, default: 'default' },
  positive: { type: Boolean, default: null },
})
</script>

<template>
  <div class="stat-card">
    <p class="card-title">{{ title }}</p>
    <p :class="['card-value', `color-${color}`, { 'positive': positive === true, 'negative': positive === false }]">
      {{ value }}
    </p>
    <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
  </div>
</template>

<style scoped>
.stat-card {
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
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text);
}

.card-value.color-success { color: var(--color-success); }
.card-value.color-danger { color: var(--color-danger); }
.card-value.color-warning { color: var(--color-warning); }
.card-value.positive { color: var(--color-success); }
.card-value.negative { color: var(--color-danger); }

.card-subtitle {
  font-size: 13px;
  color: var(--color-text-secondary);
}
</style>
