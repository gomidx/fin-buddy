<script setup>
import { onMounted, watch } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui.js'
import { useRouter } from 'vue-router'

import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import StatCard from '../components/ui/StatCard.vue'
import InsightCard from '../components/ui/InsightCard.vue'
import EmergencyFundCard from '../components/dashboard/EmergencyFundCard.vue'
import ExpensesByCategoryChart from '../components/dashboard/ExpensesByCategoryChart.vue'
import MonthlyTotalsChart from '../components/dashboard/MonthlyTotalsChart.vue'
import FinancialEvolutionChart from '../components/dashboard/FinancialEvolutionChart.vue'
import TotalInvestedCard from '../components/dashboard/TotalInvestedCard.vue'

const dashboard = useDashboardStore()
const auth      = useAuthStore()
const router    = useRouter()
const ui        = useUiStore()

onMounted(() => dashboard.fetch())
watch(() => ui.transactionVersion, () => dashboard.fetch())

async function logout() {
  await auth.logout()
  router.push('/')
}

function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0)
}
</script>

<template>
  <div class="app-layout">
    <AppSidebar @logout="logout" />

    <div class="main-wrapper">
      <AppHeader :user="auth.user" @logout="logout" />

      <main class="main-content">
        <!-- Loading state -->
        <div v-if="dashboard.loading" class="loading-state">Carregando...</div>

        <!-- Error state -->
        <div v-else-if="dashboard.error" class="error-state">
          Erro ao carregar dados: {{ dashboard.error }}
        </div>

        <!-- Content -->
        <template v-else-if="dashboard.data">
          <!-- Stats row -->
          <div class="stats-grid">
            <StatCard
              title="Entradas"
              :value="formatCurrency(dashboard.data.current_month.income)"
              color="success"
            />
            <StatCard
              title="Despesas"
              :value="formatCurrency(dashboard.data.current_month.expenses)"
              color="danger"
            />
            <StatCard
              title="Alocações"
              :value="formatCurrency(dashboard.data.current_month.allocations)"
              :subtitle="`${dashboard.data.current_month.allocation_rate}% da renda`"
              color="warning"
            />
            <StatCard
              title="Saldo Livre"
              :value="formatCurrency(dashboard.data.current_month.free_balance)"
              :positive="dashboard.data.current_month.free_balance >= 0"
            />
          </div>

          <!-- Charts row -->
          <div class="charts-row">
            <ExpensesByCategoryChart :data="dashboard.data.expenses_by_category" />
            <EmergencyFundCard :data="dashboard.data.emergency_fund" />
          </div>

          <!-- Evolution chart -->
          <FinancialEvolutionChart :data="dashboard.data.financial_evolution" />

          <!-- Monthly + invested row -->
          <div class="charts-row">
            <MonthlyTotalsChart :data="dashboard.data.monthly_totals" />
            <TotalInvestedCard :value="formatCurrency(dashboard.data.total_invested)" />
          </div>

          <!-- Insight -->
          <InsightCard v-if="dashboard.data.insight" :text="dashboard.data.insight" />
        </template>
      </main>
    </div>

    <BottomNav @logout="logout" />
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
}

.main-wrapper {
  flex: 1;
  margin-left: var(--sidebar-width);
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow-x: hidden;
}

.main-content {
  padding: var(--spacing-lg);
  max-width: 1280px;
  margin: 0 auto;
  width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md);
}

.charts-row {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: var(--spacing-md);
  min-width: 0;
}

.charts-row > * {
  min-width: 0;
  overflow: hidden;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  font-size: 18px;
  color: var(--color-text-secondary);
}

.error-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  font-size: 15px;
  color: var(--color-danger);
}

@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .charts-row { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }
  .main-content {
    padding: var(--spacing-md);
  }
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
