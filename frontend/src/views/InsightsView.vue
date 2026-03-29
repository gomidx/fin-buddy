<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  TrendingUp, TrendingDown, AlertTriangle, CheckCircle, Info,
  Heart, Wallet, PiggyBank, ShoppingBag, Flame,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import StatCard from '../components/ui/StatCard.vue'
import ProgressBar from '../components/ui/ProgressBar.vue'

const auth   = useAuthStore()
const router = useRouter()

const data    = ref(null)
const loading = ref(true)
const error   = ref(null)

async function fetchInsights() {
  loading.value = true
  error.value   = null
  try {
    const { default: api } = await import('../services/api.js')
    const res = await api.get('/insights')
    data.value = res.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao carregar insights.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchInsights)

async function logout() {
  await auth.logout()
  router.push('/')
}

function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0)
}

const healthConfig = {
  healthy:   { label: 'Saudável',         bg: 'health-healthy',   icon: CheckCircle },
  attention: { label: 'Atenção',           bg: 'health-attention', icon: AlertTriangle },
  risk:      { label: 'Risco financeiro',  bg: 'health-risk',      icon: Flame },
}

const recIconMap = {
  success: CheckCircle,
  warning: AlertTriangle,
  danger:  Flame,
  info:    Info,
}
</script>

<template>
  <div class="app-layout">
    <AppSidebar @logout="logout" />

    <div class="main-wrapper">
      <AppHeader :user="auth.user" @logout="logout" />

      <main class="main-content">
        <div class="page-header">
          <Heart :size="22" class="page-icon" />
          <h1 class="page-title">Saúde Financeira</h1>
        </div>

        <div v-if="loading" class="loading-state">Carregando...</div>

        <div v-else-if="error" class="error-state">{{ error }}</div>

        <template v-else-if="data">
          <!-- Indicador de saúde principal -->
          <div :class="['health-card', healthConfig[data.health.status]?.bg]">
            <div class="health-icon">
              <component :is="healthConfig[data.health.status]?.icon" :size="32" />
            </div>
            <div class="health-info">
              <p class="health-label">Indicador de saúde financeira</p>
              <p class="health-status">{{ data.health.label }}</p>
              <p class="health-sub">Taxa de economia: {{ data.metrics.savings_rate }}% este mês</p>
            </div>
          </div>

          <!-- Cards de métricas -->
          <div class="metrics-grid">
            <StatCard
              title="Taxa de Economia"
              :value="`${data.metrics.savings_rate}%`"
              :subtitle="`Meta: 20% | Economizado: ${formatCurrency(data.metrics.saved_amount)}`"
              :color="data.metrics.savings_rate >= 20 ? 'success' : data.metrics.savings_rate >= 10 ? 'warning' : 'danger'"
            />
            <StatCard
              title="Receitas do Mês"
              :value="formatCurrency(data.metrics.income)"
              color="success"
            />
            <StatCard
              title="Despesas do Mês"
              :value="formatCurrency(data.metrics.expenses)"
              color="danger"
            />
          </div>

          <!-- Breakdown essencial vs lazer -->
          <div class="breakdown-grid">
            <div class="breakdown-card">
              <div class="breakdown-header">
                <ShoppingBag :size="18" class="icon-essential" />
                <span>Gastos Essenciais</span>
              </div>
              <p class="breakdown-value">{{ formatCurrency(data.metrics.essential_expenses) }}</p>
              <p class="breakdown-ratio">{{ data.metrics.essential_ratio }}% da renda</p>
              <ProgressBar
                :percentage="Math.min(data.metrics.essential_ratio, 100)"
                color="blue"
              />
            </div>

            <div class="breakdown-card">
              <div class="breakdown-header">
                <TrendingDown :size="18" class="icon-leisure" />
                <span>Lazer & Supérfluos</span>
              </div>
              <p class="breakdown-value">{{ formatCurrency(data.metrics.leisure_expenses) }}</p>
              <p class="breakdown-ratio"
                :class="{ 'ratio-warning': data.metrics.leisure_ratio > 30 }"
              >{{ data.metrics.leisure_ratio }}% da renda</p>
              <ProgressBar
                :percentage="Math.min(data.metrics.leisure_ratio, 100)"
                :color="data.metrics.leisure_ratio > 30 ? 'danger' : 'primary'"
              />
            </div>

            <div class="breakdown-card" v-if="data.metrics.top_category">
              <div class="breakdown-header">
                <TrendingUp :size="18" class="icon-top" />
                <span>Maior Gasto do Mês</span>
              </div>
              <p class="breakdown-value">{{ data.metrics.top_category.name }}</p>
              <p class="breakdown-ratio">{{ formatCurrency(data.metrics.top_category.amount) }}</p>
            </div>

            <div class="breakdown-card" v-if="data.metrics.expense_change_pct !== null">
              <div class="breakdown-header">
                <Wallet :size="18" class="icon-change" />
                <span>Variação de Gastos</span>
              </div>
              <p class="breakdown-value"
                :class="data.metrics.expense_change_pct > 0 ? 'text-danger' : 'text-success'"
              >
                {{ data.metrics.expense_change_pct > 0 ? '+' : '' }}{{ data.metrics.expense_change_pct }}%
              </p>
              <p class="breakdown-ratio">vs. mês anterior</p>
            </div>
          </div>

          <!-- Reserva de emergência resumo -->
          <div class="ef-summary" v-if="data.metrics.emergency_fund_status">
            <div class="ef-summary-header">
              <PiggyBank :size="18" />
              <span>Reserva de Emergência</span>
              <span
                :class="['ef-badge', `ef-badge--${data.metrics.emergency_fund_status}`]"
              >{{ data.metrics.emergency_fund_status === 'safe' ? 'Segura' : data.metrics.emergency_fund_status === 'attention' ? 'Atenção' : data.metrics.emergency_fund_status === 'risk' ? 'Risco' : 'Não configurada' }}</span>
            </div>
            <p class="ef-months" v-if="data.metrics.emergency_fund_months !== null">
              Cobertura atual: <strong>{{ data.metrics.emergency_fund_months }} meses</strong>
              <span class="ef-months-target"> (recomendado: 6 meses)</span>
            </p>
          </div>

          <!-- Recomendações -->
          <section class="recommendations" v-if="data.recommendations.length">
            <h2 class="section-title">Recomendações</h2>
            <ul class="rec-list">
              <li
                v-for="(rec, i) in data.recommendations"
                :key="i"
                :class="['rec-item', `rec-${rec.type}`]"
              >
                <component :is="recIconMap[rec.type] ?? Info" :size="18" class="rec-icon" />
                <span>{{ rec.message }}</span>
              </li>
            </ul>
          </section>
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
}

.main-content {
  padding: var(--spacing-lg);
  max-width: 900px;
  margin: 0 auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

/* Page header */
.page-header {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.page-icon { color: var(--color-primary); }

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

/* Health card */
.health-card {
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);
}

.health-healthy   { background: rgba(39, 174, 96, 0.1);  border: 1px solid rgba(39,174,96,0.3); }
.health-attention { background: rgba(242, 201, 76, 0.1); border: 1px solid rgba(242,201,76,0.3); }
.health-risk      { background: rgba(235, 87, 87, 0.1);  border: 1px solid rgba(235,87,87,0.3); }

.health-healthy .health-icon   { color: var(--color-success); }
.health-attention .health-icon { color: var(--color-warning); }
.health-risk .health-icon      { color: var(--color-danger); }

.health-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--color-card);
  flex-shrink: 0;
}

.health-label  { font-size: 12px; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
.health-status { font-size: 22px; font-weight: 700; color: var(--color-text); margin: 4px 0; }
.health-sub    { font-size: 14px; color: var(--color-text-secondary); margin: 0; }

/* Metrics */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-md);
}

/* Breakdown */
.breakdown-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--spacing-md);
}

.breakdown-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.breakdown-header {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.icon-essential { color: var(--color-info); }
.icon-leisure   { color: var(--color-warning); }
.icon-top       { color: var(--color-primary); }
.icon-change    { color: var(--color-text-secondary); }

.breakdown-value { font-size: 20px; font-weight: 700; color: var(--color-text); margin: 0; }
.breakdown-ratio { font-size: 13px; color: var(--color-text-secondary); margin: 0; }
.ratio-warning   { color: var(--color-warning) !important; font-weight: 600; }
.text-danger     { color: var(--color-danger); }
.text-success    { color: var(--color-success); }

/* Emergency fund summary */
.ef-summary {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-lg);
}

.ef-summary-header {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: var(--spacing-sm);
}

.ef-badge {
  margin-left: auto;
  padding: 2px 10px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 600;
}

.ef-badge--safe       { background: rgba(39,174,96,0.12);  color: var(--color-success); }
.ef-badge--attention  { background: rgba(242,201,76,0.15); color: #b8860b; }
.ef-badge--risk       { background: rgba(235,87,87,0.12);  color: var(--color-danger); }
.ef-badge--not_configured { background: var(--color-bg);   color: var(--color-text-secondary); }

.ef-months { font-size: 14px; color: var(--color-text-secondary); margin: 0; }
.ef-months strong { color: var(--color-text); }
.ef-months-target { font-style: italic; }

/* Recommendations */
.section-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 var(--spacing-md);
}

.rec-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--spacing-sm); }

.rec-item {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
  padding: var(--spacing-md) var(--spacing-lg);
  border-radius: var(--radius-md);
  font-size: 14px;
  line-height: 1.5;
}

.rec-success { background: rgba(39,174,96,0.08);  color: #1a7a47; }
.rec-warning { background: rgba(242,201,76,0.12); color: #856404; }
.rec-danger  { background: rgba(235,87,87,0.08);  color: #b91c1c; }
.rec-info    { background: rgba(47,128,237,0.08); color: #1d4ed8; }

.rec-icon { flex-shrink: 0; margin-top: 2px; }

/* States */
.loading-state {
  display: flex; align-items: center; justify-content: center;
  min-height: 300px; font-size: 16px; color: var(--color-text-secondary);
}

.error-state {
  display: flex; align-items: center; justify-content: center;
  min-height: 200px; font-size: 15px; color: var(--color-danger);
}

/* Responsive */
@media (max-width: 1024px) {
  .metrics-grid   { grid-template-columns: repeat(2, 1fr); }
  .breakdown-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }
  .metrics-grid { grid-template-columns: 1fr; }
  .health-card  { flex-direction: column; text-align: center; }
}
</style>
