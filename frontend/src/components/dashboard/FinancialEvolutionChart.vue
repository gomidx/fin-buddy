<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps({
  data: { type: Array, default: () => [] },
})

const MONTH_NAMES = {
  '01': 'Jan', '02': 'Fev', '03': 'Mar', '04': 'Abr',
  '05': 'Mai', '06': 'Jun', '07': 'Jul', '08': 'Ago',
  '09': 'Set', '10': 'Out', '11': 'Nov', '12': 'Dez',
}

function formatMonth(str) {
  if (!str) return str
  const [year, month] = str.split('-')
  return `${MONTH_NAMES[month] || month}/${year.slice(2)}`
}

const chartData = computed(() => {
  const balances = props.data.map(d => d.balance)
  return {
    labels: props.data.map(d => formatMonth(d.month)),
    datasets: [{
      label: 'Saldo',
      data: balances,
      borderColor: '#2ECC71',
      backgroundColor: 'rgba(46,204,113,0.12)',
      pointBackgroundColor: balances.map(b => b < 0 ? '#EB5757' : '#2ECC71'),
      pointBorderColor: balances.map(b => b < 0 ? '#EB5757' : '#2ECC71'),
      pointRadius: 5,
      tension: 0.3,
      fill: true,
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: {
      position: 'bottom',
      labels: { font: { size: 12 }, padding: 12 },
    },
  },
  scales: {
    x: { grid: { display: false } },
    y: { grid: { color: '#F3F4F6' } },
  },
}
</script>

<template>
  <div class="card">
    <h3 class="card-title">Evolução Financeira</h3>
    <Line :data="chartData" :options="chartOptions" />
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
</style>
