<script setup>
import ProgressBar from '../ui/ProgressBar.vue'
import AppBadge from '../ui/AppBadge.vue'

defineProps({
  data: { type: Object, required: true },
})

function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0)
}

function statusBadge(status) {
  const map = {
    safe: { variant: 'success', label: '🟢 Seguro' },
    attention: { variant: 'warning', label: '🟡 Atenção' },
    risk: { variant: 'danger', label: '🔴 Risco' },
    not_configured: { variant: 'secondary', label: '⚫ Não configurada' },
  }
  return map[status] || map['not_configured']
}
</script>

<template>
  <div class="card">
    <h3 class="card-title">Reserva de Emergência</h3>

    <template v-if="data && data.has_goal">
      <ProgressBar :percentage="data.percentage" />
      <div class="amounts">
        <span>{{ formatCurrency(data.current_amount) }}</span>
        <span class="sep">/</span>
        <span>{{ formatCurrency(data.target_amount) }}</span>
      </div>
      <AppBadge :variant="statusBadge(data.status).variant" :label="statusBadge(data.status).label" />
    </template>

    <template v-else>
      <p class="no-goal">Configure sua reserva de emergência</p>
      <AppBadge variant="secondary" label="⚫ Não configurada" />
    </template>
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
  gap: var(--spacing-md);
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text);
}

.amounts {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  font-size: 14px;
  color: var(--color-text-secondary);
}

.sep {
  color: var(--color-border);
}

.no-goal {
  font-size: 14px;
  color: var(--color-text-secondary);
}
</style>
