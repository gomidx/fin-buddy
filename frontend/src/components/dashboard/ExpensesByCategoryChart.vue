<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const COLORS = ['#2ECC71','#2D9CDB','#F2C94C','#EB5757','#2F80ED','#9B59B6','#E67E22','#1ABC9C']

const chartData = computed(() => ({
  labels: props.data.map(d => d.category),
  datasets: [{
    data: props.data.map(d => d.amount),
    backgroundColor: COLORS.slice(0, props.data.length),
    borderWidth: 2,
    borderColor: '#fff',
  }],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: {
      position: 'bottom',
      labels: { font: { size: 12 }, padding: 12 },
    },
  },
}
</script>

<template>
  <div class="card">
    <h3 class="card-title">Gastos por Categoria</h3>
    <div v-if="data && data.length > 0" class="chart-wrapper">
      <Doughnut :data="chartData" :options="chartOptions" />
    </div>
    <div v-else class="empty-state">Sem gastos registrados este mês.</div>
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

.chart-wrapper {
  max-width: 300px;
  margin: 0 auto;
}

.empty-state {
  font-size: 14px;
  color: var(--color-text-secondary);
  text-align: center;
  padding: var(--spacing-xl) 0;
}
</style>
