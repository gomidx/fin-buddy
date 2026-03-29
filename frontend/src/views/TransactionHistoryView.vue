<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftRight, ChevronLeft, ChevronRight, SlidersHorizontal, X,
  TrendingUp, TrendingDown, Minus, Plus,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui.js'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import api from '../services/api.js'

const auth   = useAuthStore()
const router = useRouter()
const ui     = useUiStore()

// ── Estado ──────────────────────────────────────
const transactions = ref([])
const categories   = ref([])
const loading      = ref(false)
const error        = ref(null)
const pagination   = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })

// Filtros
const now         = new Date()
const filterType  = ref('')                           // '' | 'income' | 'expense'
const filterCat   = ref('')                           // category_id | ''
const filterYear  = ref(now.getFullYear())
const filterMonth = ref(now.getMonth() + 1)           // 1–12; 0 = todos os meses

const MONTHS = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']

// ── Fetch ────────────────────────────────────────
async function fetchCategories() {
  try {
    const res = await api.get('/categories')
    categories.value = res.data
  } catch { /* silencioso */ }
}

async function fetchTransactions(page = 1) {
  loading.value = true
  error.value   = null
  try {
    const params = { page }
    if (filterType.value)  params.type        = filterType.value
    if (filterCat.value)   params.category_id = filterCat.value
    if (filterMonth.value) {
      params.month = filterMonth.value
      params.year  = filterYear.value
    } else {
      params.year = filterYear.value
      // sem mês: usar date_from/date_to para o ano inteiro
      params.date_from = `${filterYear.value}-01-01`
      params.date_to   = `${filterYear.value}-12-31`
    }

    const res = await api.get('/transactions', { params })
    transactions.value = res.data.data
    pagination.value   = {
      current_page: res.data.current_page,
      last_page:    res.data.last_page,
      total:        res.data.total,
      per_page:     res.data.per_page,
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao carregar transações.'
  } finally {
    loading.value = false
  }
}

// ── Grouping por mês ─────────────────────────────
const groupedByMonth = computed(() => {
  const groups = {}
  for (const t of transactions.value) {
    const key = t.date.slice(0, 7) // 'YYYY-MM'
    if (!groups[key]) groups[key] = { label: formatMonthLabel(key), items: [], income: 0, expenses: 0 }
    groups[key].items.push(t)
    if (t.type === 'income')  groups[key].income   += parseFloat(t.amount)
    if (t.type === 'expense') groups[key].expenses += parseFloat(t.amount)
  }
  return Object.values(groups).sort((a, b) => b.label < a.label ? -1 : 1)
})

function formatMonthLabel(ym) {
  const [y, m] = ym.split('-')
  return `${MONTHS[parseInt(m) - 1]} ${y}`
}

// ── Formatação ───────────────────────────────────
function formatCurrency(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
}

function formatDate(dateStr) {
  const d = new Date(dateStr.slice(0, 10) + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

// ── Anos disponíveis (5 anos para trás) ─────────
const availableYears = computed(() => {
  const current = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => current - i)
})

// ── Navegação de página ──────────────────────────
function goPage(p) {
  if (p < 1 || p > pagination.value.last_page) return
  fetchTransactions(p)
}

// ── Watch filtros e nova transação ───────────────
watch([filterType, filterCat, filterMonth, filterYear], () => fetchTransactions(1))
watch(() => ui.transactionVersion, () => fetchTransactions(pagination.value.current_page))

// ── Logout ───────────────────────────────────────
async function logout() {
  await auth.logout()
  router.push('/')
}

// ── Init ─────────────────────────────────────────
onMounted(() => {
  fetchCategories()
  fetchTransactions()
})
</script>

<template>
  <div class="app-layout">
    <AppSidebar @logout="logout" />

    <div class="main-wrapper">
      <AppHeader :user="auth.user" @logout="logout" />

      <main class="main-content">
        <!-- Page header -->
        <div class="page-header">
          <div class="page-header-left">
            <ArrowLeftRight :size="22" class="page-icon" />
            <h1 class="page-title">Histórico Financeiro</h1>
          </div>
          <button class="btn-new-tx" @click="ui.openTransactionModal()">
            <Plus :size="16" />
            Nova transação
          </button>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
          <SlidersHorizontal :size="16" class="filter-icon" />

          <!-- Tipo -->
          <select v-model="filterType" class="filter-select">
            <option value="">Todos os tipos</option>
            <option value="income">Receitas</option>
            <option value="expense">Despesas</option>
          </select>

          <!-- Categoria -->
          <select v-model="filterCat" class="filter-select">
            <option value="">Todas as categorias</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>

          <!-- Mês -->
          <select v-model.number="filterMonth" class="filter-select">
            <option :value="0">Todos os meses</option>
            <option v-for="(m, i) in MONTHS" :key="i" :value="i + 1">{{ m }}</option>
          </select>

          <!-- Ano -->
          <select v-model.number="filterYear" class="filter-select">
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>

          <!-- Limpar filtros -->
          <button
            v-if="filterType || filterCat || filterMonth !== (new Date().getMonth() + 1)"
            class="btn-clear"
            @click="filterType = ''; filterCat = ''; filterMonth = new Date().getMonth() + 1; filterYear = new Date().getFullYear()"
          >
            <X :size="14" />
            Limpar
          </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="loading-state">Carregando...</div>

        <!-- Error -->
        <div v-else-if="error" class="error-state">{{ error }}</div>

        <!-- Empty state -->
        <div v-else-if="!transactions.length" class="empty-state">
          <ArrowLeftRight :size="40" class="empty-icon" />
          <p>Nenhuma transação encontrada.</p>
          <span>Tente ajustar os filtros.</span>
        </div>

        <!-- Transaction groups -->
        <template v-else>
          <div v-for="group in groupedByMonth" :key="group.label" class="month-group">
            <!-- Month header -->
            <div class="month-header">
              <span class="month-label">{{ group.label }}</span>
              <div class="month-totals">
                <span class="total-income">+{{ formatCurrency(group.income) }}</span>
                <span class="total-sep">·</span>
                <span class="total-expense">-{{ formatCurrency(group.expenses) }}</span>
              </div>
            </div>

            <!-- Transaction rows -->
            <div class="transactions-list">
              <div
                v-for="t in group.items"
                :key="t.id"
                class="transaction-row"
              >
                <!-- Type icon -->
                <div :class="['tx-icon', `tx-icon--${t.type}`]">
                  <TrendingUp v-if="t.type === 'income'" :size="16" />
                  <TrendingDown v-else-if="t.type === 'expense'" :size="16" />
                  <Minus v-else :size="16" />
                </div>

                <!-- Details -->
                <div class="tx-details">
                  <span class="tx-description">{{ t.description }}</span>
                  <span class="tx-meta">
                    {{ t.category?.name ?? 'Sem categoria' }} · {{ formatDate(t.date) }}
                  </span>
                </div>

                <!-- Amount -->
                <span :class="['tx-amount', `tx-amount--${t.type}`]">
                  {{ t.type === 'income' ? '+' : '-' }}{{ formatCurrency(t.amount) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div class="pagination" v-if="pagination.last_page > 1">
            <button
              class="page-btn"
              :disabled="pagination.current_page === 1"
              @click="goPage(pagination.current_page - 1)"
            >
              <ChevronLeft :size="16" />
            </button>

            <span class="page-info">
              {{ pagination.current_page }} / {{ pagination.last_page }}
              <small>({{ pagination.total }} transações)</small>
            </span>

            <button
              class="page-btn"
              :disabled="pagination.current_page === pagination.last_page"
              @click="goPage(pagination.current_page + 1)"
            >
              <ChevronRight :size="16" />
            </button>
          </div>

          <p class="total-count" v-else>
            {{ pagination.total }} transação{{ pagination.total !== 1 ? 'ões' : '' }}
          </p>
        </template>
      </main>
    </div>

    <BottomNav @logout="logout" />
  </div>
</template>

<style scoped>
.app-layout { display: flex; min-height: 100vh; }

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
  justify-content: space-between;
  gap: var(--spacing-sm);
}

.page-header-left {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.page-icon   { color: var(--color-primary); }
.page-title  { font-size: 22px; font-weight: 700; color: var(--color-text); margin: 0; }

.btn-new-tx {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
  white-space: nowrap;
}

.btn-new-tx:hover { opacity: 0.88; }

/* Filters bar */
.filters-bar {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  flex-wrap: wrap;
  background: var(--color-card);
  border-radius: var(--radius-lg);
  padding: var(--spacing-md) var(--spacing-lg);
  box-shadow: var(--shadow-sm);
}

.filter-icon { color: var(--color-text-secondary); flex-shrink: 0; }

.filter-select {
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-bg);
  cursor: pointer;
  outline: none;
  transition: border-color 0.15s;
}

.filter-select:focus { border-color: var(--color-primary); }

.btn-clear {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  font-size: 13px;
  color: var(--color-text-secondary);
  background: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}

.btn-clear:hover { color: var(--color-danger); border-color: var(--color-danger); }

/* Month group */
.month-group {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.month-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
  background: var(--color-bg);
}

.month-label {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-secondary);
}

.month-totals {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: 13px;
  font-weight: 600;
}

.total-income  { color: var(--color-success); }
.total-expense { color: var(--color-danger); }
.total-sep     { color: var(--color-border); }

/* Transaction list */
.transactions-list { display: flex; flex-direction: column; }

.transaction-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-md) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
  transition: background 0.1s;
}

.transaction-row:last-child { border-bottom: none; }
.transaction-row:hover { background: rgba(0,0,0,0.02); }

/* Type icon */
.tx-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.tx-icon--income  { background: rgba(39,174,96,0.12);  color: var(--color-success); }
.tx-icon--expense { background: rgba(235,87,87,0.12);  color: var(--color-danger); }
.tx-icon--investment,
.tx-icon--emergency_fund { background: rgba(45,156,219,0.12); color: var(--color-info); }

/* Details */
.tx-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.tx-description {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tx-meta {
  font-size: 12px;
  color: var(--color-text-secondary);
}

/* Amount */
.tx-amount {
  font-size: 15px;
  font-weight: 700;
  flex-shrink: 0;
}

.tx-amount--income  { color: var(--color-success); }
.tx-amount--expense { color: var(--color-danger); }
.tx-amount--investment,
.tx-amount--emergency_fund { color: var(--color-info); }

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-md);
}

.page-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-card);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  color: var(--color-text);
}

.page-btn:hover:not(:disabled) {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: white;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 14px;
  color: var(--color-text-secondary);
}

.page-info small { margin-left: var(--spacing-xs); font-size: 12px; }

.total-count {
  text-align: center;
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
}

/* States */
.loading-state, .error-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  gap: var(--spacing-sm);
  color: var(--color-text-secondary);
  font-size: 15px;
}

.error-state { color: var(--color-danger); }

.empty-icon { opacity: 0.3; }
.empty-state p { font-weight: 600; color: var(--color-text); margin: 0; }
.empty-state span { font-size: 13px; }

/* Responsive */
@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }

  .filters-bar { gap: var(--spacing-xs); }
  .filter-select { font-size: 12px; padding: 5px 8px; }

  .transaction-row { padding: var(--spacing-sm) var(--spacing-md); }
  .month-header    { padding: var(--spacing-sm) var(--spacing-md); }
}
</style>
