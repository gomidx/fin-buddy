<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  TrendingUp, Zap, Building2, Lock, Plus, Pencil, Trash2,
  ChevronDown, ChevronUp, X, Check, Loader2, AlertTriangle,
  ArrowUpCircle, ArrowDownCircle, Gift,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import api from '../services/api.js'

const auth   = useAuthStore()
const router = useRouter()

// ── Configurações de tipo ────────────────────────
const INVEST_TYPE = {
  stock:        { label: 'Ações',       icon: TrendingUp, color: 'success' },
  crypto:       { label: 'Cripto',      icon: Zap,        color: 'warning' },
  fund:         { label: 'Fundos',      icon: Building2,  color: 'info'    },
  fixed_income: { label: 'Renda Fixa',  icon: Lock,       color: 'neutral' },
}

const TX_TYPE = {
  buy:      { label: 'Compra',    icon: ArrowUpCircle,   color: 'success' },
  sell:     { label: 'Venda',     icon: ArrowDownCircle, color: 'danger'  },
  dividend: { label: 'Dividendo', icon: Gift,            color: 'info'    },
}

// ── Estado principal ─────────────────────────────
const investments  = ref([])
const totals       = ref({})
const grandTotal   = ref(0)
const loading      = ref(false)
const error        = ref(null)

// ── Investimento expandido ────────────────────────
const expanded     = ref(null)     // id do investimento expandido
const txList       = ref([])
const txLoading    = ref(false)
const txError      = ref(null)

// ── Modal: criar / editar investimento ───────────
const modal         = ref(false)
const modalMode     = ref('create')   // 'create' | 'edit'
const modalLoading  = ref(false)
const modalError    = ref(null)
const editingId     = ref(null)
const form = ref(defaultForm())

function defaultForm() {
  return { name: '', type: 'stock', institution: '' }
}

// ── Formulário: nova movimentação ─────────────────
const txFormOpen    = ref(false)
const txFormLoading = ref(false)
const txFormError   = ref(null)
const txForm = ref(defaultTxForm())

function defaultTxForm() {
  return { type: 'buy', amount: '', date: today(), description: '' }
}

// ── Confirmação de exclusão ───────────────────────
const confirmDelete  = ref(null)   // investment id a excluir
const deleteLoading  = ref(false)

// ── Helpers ──────────────────────────────────────
function today() {
  return new Date().toISOString().slice(0, 10)
}

function formatCurrency(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// ── Resumo por tipo ───────────────────────────────
const summaryTypes = computed(() =>
  Object.entries(INVEST_TYPE)
    .filter(([key]) => (totals.value[key] ?? 0) > 0)
    .map(([key, cfg]) => ({ key, ...cfg, total: totals.value[key] ?? 0 }))
)

// ── Fetch investimentos ───────────────────────────
async function fetchInvestments() {
  loading.value = true
  error.value   = null
  try {
    const res       = await api.get('/investments')
    investments.value = res.data.investments
    totals.value      = res.data.totals_by_type ?? {}
    grandTotal.value  = res.data.grand_total ?? 0
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao carregar investimentos.'
  } finally {
    loading.value = false
  }
}

// ── Expand / collapse investimento ───────────────
async function toggleExpand(inv) {
  if (expanded.value === inv.id) {
    expanded.value = null
    txFormOpen.value = false
    return
  }
  expanded.value   = inv.id
  txFormOpen.value = false
  txList.value     = []
  txError.value    = null
  await fetchTransactions(inv.id)
}

async function fetchTransactions(investmentId) {
  txLoading.value = true
  txError.value   = null
  try {
    const res  = await api.get(`/investments/${investmentId}/transactions`)
    txList.value = res.data
  } catch (e) {
    txError.value = e.response?.data?.message ?? 'Erro ao carregar movimentações.'
  } finally {
    txLoading.value = false
  }
}

// ── Modal criar / editar ──────────────────────────
function openCreate() {
  modalMode.value  = 'create'
  form.value       = defaultForm()
  modalError.value = null
  editingId.value  = null
  modal.value      = true
}

function openEdit(inv) {
  modalMode.value  = 'edit'
  form.value       = { name: inv.name, type: inv.type, institution: inv.institution ?? '' }
  editingId.value  = inv.id
  modalError.value = null
  modal.value      = true
}

function closeModal() {
  if (modalLoading.value) return
  modal.value      = false
  modalError.value = null
}

async function saveInvestment() {
  modalError.value = null
  if (!form.value.name.trim()) { modalError.value = 'Informe o nome do investimento.'; return }

  modalLoading.value = true
  try {
    const payload = {
      name:        form.value.name.trim(),
      type:        form.value.type,
      institution: form.value.institution.trim() || null,
    }

    if (modalMode.value === 'create') {
      const res = await api.post('/investments', payload)
      investments.value.push(res.data)
    } else {
      const res = await api.put(`/investments/${editingId.value}`, payload)
      const idx = investments.value.findIndex(i => i.id === editingId.value)
      if (idx !== -1) investments.value[idx] = res.data
    }

    await fetchInvestments()   // re-fetch totals
    modal.value = false
  } catch (e) {
    modalError.value = e.response?.data?.message ?? 'Erro ao salvar investimento.'
  } finally {
    modalLoading.value = false
  }
}

// ── Excluir investimento ──────────────────────────
function askDelete(inv) {
  confirmDelete.value = inv
}

async function deleteInvestment() {
  deleteLoading.value = true
  try {
    await api.delete(`/investments/${confirmDelete.value.id}`)
    if (expanded.value === confirmDelete.value.id) expanded.value = null
    await fetchInvestments()
    confirmDelete.value = null
  } catch (e) {
    // erro silencioso — refetch resolve estado
    confirmDelete.value = null
  } finally {
    deleteLoading.value = false
  }
}

// ── Registrar movimentação ────────────────────────
function openTxForm() {
  txForm.value    = defaultTxForm()
  txFormError.value = null
  txFormOpen.value  = true
}

function closeTxForm() {
  txFormOpen.value  = false
  txFormError.value = null
}

async function submitTransaction() {
  txFormError.value = null
  const amount = parseFloat(txForm.value.amount)
  if (!amount || amount <= 0) { txFormError.value = 'Informe um valor válido.'; return }
  if (!txForm.value.date)     { txFormError.value = 'Informe a data.'; return }

  txFormLoading.value = true
  try {
    await api.post(`/investments/${expanded.value}/transactions`, {
      type:        txForm.value.type,
      amount,
      date:        txForm.value.date,
      description: txForm.value.description.trim() || null,
    })
    txFormOpen.value = false
    await fetchTransactions(expanded.value)
    await fetchInvestments()   // atualiza total_invested
  } catch (e) {
    txFormError.value = e.response?.data?.message ?? 'Erro ao registrar movimentação.'
  } finally {
    txFormLoading.value = false
  }
}

// ── Logout ───────────────────────────────────────
async function logout() {
  await auth.logout()
  router.push('/')
}

// ── Init ─────────────────────────────────────────
onMounted(fetchInvestments)
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
            <TrendingUp :size="22" class="page-icon" />
            <h1 class="page-title">Investimentos</h1>
          </div>
          <button class="btn-primary" @click="openCreate">
            <Plus :size="16" />
            Novo investimento
          </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="state-box">
          <Loader2 :size="32" class="spin text-muted" />
          <p>Carregando...</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="state-box error-state">
          <AlertTriangle :size="32" />
          <p>{{ error }}</p>
        </div>

        <template v-else>

          <!-- ── Resumo total ───────────────────── -->
          <div class="summary-section">
            <!-- Grand total -->
            <div class="grand-total-card">
              <span class="grand-label">Total investido</span>
              <span class="grand-value">{{ formatCurrency(grandTotal) }}</span>
            </div>

            <!-- Por tipo -->
            <div v-if="summaryTypes.length" class="type-summary">
              <div
                v-for="t in summaryTypes"
                :key="t.key"
                class="type-pill"
                :class="`type-pill--${t.color}`"
              >
                <component :is="t.icon" :size="14" />
                <span class="type-pill-label">{{ t.label }}</span>
                <span class="type-pill-value">{{ formatCurrency(t.total) }}</span>
              </div>
            </div>
          </div>

          <!-- ── Empty state ───────────────────── -->
          <div v-if="!investments.length" class="state-box empty-state">
            <TrendingUp :size="44" class="empty-icon" />
            <p>Nenhum investimento cadastrado</p>
            <span>Registre suas ações, fundos ou criptos para acompanhar sua carteira.</span>
            <button class="btn-primary mt-sm" @click="openCreate">
              <Plus :size="15" />
              Adicionar investimento
            </button>
          </div>

          <!-- ── Lista de investimentos ─────────── -->
          <div v-else class="investments-list">
            <div
              v-for="inv in investments"
              :key="inv.id"
              class="invest-card"
              :class="{ 'invest-card--expanded': expanded === inv.id }"
            >
              <!-- Card header -->
              <div class="invest-row" @click="toggleExpand(inv)">
                <!-- Ícone tipo -->
                <div :class="['invest-icon', `invest-icon--${INVEST_TYPE[inv.type]?.color}`]">
                  <component :is="INVEST_TYPE[inv.type]?.icon ?? TrendingUp" :size="18" />
                </div>

                <!-- Info -->
                <div class="invest-info">
                  <span class="invest-name">{{ inv.name }}</span>
                  <div class="invest-meta">
                    <span :class="['type-badge', `type-badge--${INVEST_TYPE[inv.type]?.color}`]">
                      {{ INVEST_TYPE[inv.type]?.label ?? inv.type }}
                    </span>
                    <span v-if="inv.institution" class="invest-institution">
                      · {{ inv.institution }}
                    </span>
                  </div>
                </div>

                <!-- Total -->
                <div class="invest-total">
                  <span class="total-value">{{ formatCurrency(inv.total_invested) }}</span>
                </div>

                <!-- Ações -->
                <div class="invest-actions" @click.stop>
                  <button class="btn-icon" @click="openEdit(inv)" title="Editar">
                    <Pencil :size="15" />
                  </button>
                  <button class="btn-icon btn-icon--danger" @click="askDelete(inv)" title="Excluir">
                    <Trash2 :size="15" />
                  </button>
                </div>

                <!-- Chevron -->
                <ChevronUp v-if="expanded === inv.id" :size="16" class="chevron" />
                <ChevronDown v-else :size="16" class="chevron" />
              </div>

              <!-- Panel expandido: movimentações -->
              <Transition name="expand">
                <div v-if="expanded === inv.id" class="transactions-panel">

                  <!-- Header do painel -->
                  <div class="panel-header">
                    <span class="panel-title">Movimentações</span>
                    <button v-if="!txFormOpen" class="btn-sm" @click="openTxForm">
                      <Plus :size="13" />
                      Registrar
                    </button>
                  </div>

                  <!-- Formulário de nova movimentação -->
                  <Transition name="slide">
                    <div v-if="txFormOpen" class="tx-form">
                      <div class="tx-form-row">
                        <!-- Tipo -->
                        <div class="form-field">
                          <label class="form-label">Tipo</label>
                          <select v-model="txForm.type" class="form-input form-select">
                            <option value="buy">Compra</option>
                            <option value="sell">Venda</option>
                            <option value="dividend">Dividendo</option>
                          </select>
                        </div>

                        <!-- Valor -->
                        <div class="form-field">
                          <label class="form-label">Valor (R$)</label>
                          <input
                            v-model="txForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0,00"
                            class="form-input"
                          />
                        </div>

                        <!-- Data -->
                        <div class="form-field">
                          <label class="form-label">Data</label>
                          <input
                            v-model="txForm.date"
                            type="date"
                            class="form-input"
                            :max="today()"
                          />
                        </div>
                      </div>

                      <!-- Descrição -->
                      <div class="form-field">
                        <label class="form-label">Descrição (opcional)</label>
                        <input
                          v-model="txForm.description"
                          type="text"
                          placeholder="Ex: Aporte mensal"
                          class="form-input"
                          maxlength="255"
                        />
                      </div>

                      <p v-if="txFormError" class="form-error">{{ txFormError }}</p>

                      <div class="tx-form-actions">
                        <button class="btn btn-cancel" @click="closeTxForm" :disabled="txFormLoading">
                          <X :size="14" /> Cancelar
                        </button>
                        <button class="btn btn-save" @click="submitTransaction" :disabled="txFormLoading">
                          <Loader2 v-if="txFormLoading" :size="14" class="spin" />
                          <Check v-else :size="14" />
                          Confirmar
                        </button>
                      </div>
                    </div>
                  </Transition>

                  <!-- Loading transações -->
                  <div v-if="txLoading" class="tx-state">
                    <Loader2 :size="20" class="spin text-muted" />
                  </div>

                  <div v-else-if="txError" class="tx-state tx-state--error">
                    {{ txError }}
                  </div>

                  <!-- Lista vazia -->
                  <div v-else-if="!txList.length" class="tx-state">
                    <span>Nenhuma movimentação registrada.</span>
                  </div>

                  <!-- Linhas de movimentação -->
                  <div v-else class="tx-list">
                    <div
                      v-for="tx in txList"
                      :key="tx.id"
                      class="tx-row"
                    >
                      <div :class="['tx-badge', `tx-badge--${TX_TYPE[tx.type]?.color}`]">
                        <component :is="TX_TYPE[tx.type]?.icon ?? ArrowUpCircle" :size="13" />
                        {{ TX_TYPE[tx.type]?.label ?? tx.type }}
                      </div>
                      <span class="tx-desc">{{ tx.description || '—' }}</span>
                      <span class="tx-date">{{ formatDate(tx.date) }}</span>
                      <span :class="['tx-amount', `tx-amount--${TX_TYPE[tx.type]?.color}`]">
                        {{ tx.type === 'sell' ? '-' : '+' }}{{ formatCurrency(tx.amount) }}
                      </span>
                    </div>
                  </div>

                </div>
              </Transition>

            </div>
          </div>

        </template>

      </main>
    </div>

    <BottomNav @logout="logout" />

    <!-- ── Modal: criar / editar investimento ──── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="modal" class="modal-backdrop" @click.self="closeModal">
          <div class="modal" role="dialog" aria-modal="true">

            <div class="modal-header">
              <h2 class="modal-title">
                {{ modalMode === 'create' ? 'Novo investimento' : 'Editar investimento' }}
              </h2>
              <button class="btn-close" @click="closeModal">
                <X :size="18" />
              </button>
            </div>

            <div class="modal-body">
              <!-- Nome -->
              <div class="form-field">
                <label class="form-label" for="inv-name">Nome</label>
                <input
                  id="inv-name"
                  v-model="form.name"
                  type="text"
                  placeholder="Ex: PETR4, Bitcoin, Tesouro Direto…"
                  class="form-input"
                  maxlength="255"
                />
              </div>

              <!-- Tipo -->
              <div class="form-field">
                <label class="form-label">Tipo</label>
                <div class="type-toggle">
                  <button
                    v-for="(cfg, key) in INVEST_TYPE"
                    :key="key"
                    :class="['type-opt', `type-opt--${cfg.color}`, { active: form.type === key }]"
                    @click="form.type = key"
                    type="button"
                  >
                    <component :is="cfg.icon" :size="15" />
                    {{ cfg.label }}
                  </button>
                </div>
              </div>

              <!-- Instituição -->
              <div class="form-field">
                <label class="form-label" for="inv-inst">Corretora / Instituição (opcional)</label>
                <input
                  id="inv-inst"
                  v-model="form.institution"
                  type="text"
                  placeholder="Ex: XP, Nu Invest, Binance…"
                  class="form-input"
                  maxlength="255"
                />
              </div>

              <p v-if="modalError" class="form-error">{{ modalError }}</p>
            </div>

            <div class="modal-footer">
              <button class="btn btn-cancel" @click="closeModal" :disabled="modalLoading">
                Cancelar
              </button>
              <button class="btn btn-save" @click="saveInvestment" :disabled="modalLoading">
                <Loader2 v-if="modalLoading" :size="15" class="spin" />
                <Check v-else :size="15" />
                {{ modalMode === 'create' ? 'Criar' : 'Salvar' }}
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Confirmação de exclusão ──────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="confirmDelete" class="modal-backdrop" @click.self="confirmDelete = null">
          <div class="modal modal--sm" role="dialog" aria-modal="true">
            <div class="modal-header">
              <h2 class="modal-title">Excluir investimento</h2>
              <button class="btn-close" @click="confirmDelete = null"><X :size="18" /></button>
            </div>
            <div class="modal-body">
              <p class="confirm-text">
                Tem certeza que deseja excluir <strong>{{ confirmDelete.name }}</strong>?
                Todas as movimentações associadas serão removidas.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-cancel" @click="confirmDelete = null" :disabled="deleteLoading">
                Cancelar
              </button>
              <button class="btn btn-danger" @click="deleteInvestment" :disabled="deleteLoading">
                <Loader2 v-if="deleteLoading" :size="15" class="spin" />
                <Trash2 v-else :size="15" />
                Excluir
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.app-layout  { display: flex; min-height: 100vh; }

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

/* ── Page header ──────────────────────────────── */
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

.page-icon  { color: var(--color-primary); }
.page-title { font-size: 22px; font-weight: 700; color: var(--color-text); margin: 0; }

/* ── Resumo ───────────────────────────────────── */
.summary-section {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  flex-wrap: wrap;
  background: var(--color-card);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg) var(--spacing-xl);
  box-shadow: var(--shadow-sm);
}

.grand-total-card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-right: var(--spacing-lg);
  border-right: 1px solid var(--color-border);
  margin-right: var(--spacing-sm);
}

.grand-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.grand-value {
  font-size: 26px;
  font-weight: 800;
  color: var(--color-text);
}

.type-summary {
  display: flex;
  gap: var(--spacing-sm);
  flex-wrap: wrap;
  flex: 1;
}

.type-pill {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 99px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
}

.type-pill--success { background: rgba(39,174,96,0.1);   color: var(--color-success); }
.type-pill--warning { background: rgba(242,201,76,0.15); color: #9a7d0a; }
.type-pill--info    { background: rgba(47,128,237,0.1);  color: var(--color-info); }
.type-pill--neutral { background: rgba(107,114,128,0.1); color: var(--color-text-secondary); }

.type-pill-label { font-weight: 500; }
.type-pill-value { font-weight: 700; }

/* ── Investments list ─────────────────────────── */
.investments-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.invest-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  border: 1.5px solid transparent;
  transition: border-color 0.15s;
}

.invest-card--expanded {
  border-color: var(--color-primary);
}

.invest-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-md) var(--spacing-lg);
  cursor: pointer;
  transition: background 0.1s;
}

.invest-row:hover { background: rgba(0,0,0,0.02); }

/* Type icon */
.invest-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.invest-icon--success { background: rgba(39,174,96,0.1);   color: var(--color-success); }
.invest-icon--warning { background: rgba(242,201,76,0.15); color: #9a7d0a; }
.invest-icon--info    { background: rgba(47,128,237,0.1);  color: var(--color-info); }
.invest-icon--neutral { background: rgba(107,114,128,0.1); color: var(--color-text-secondary); }

/* Info */
.invest-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.invest-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.invest-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
}

.type-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
}

.type-badge--success { background: rgba(39,174,96,0.1);   color: var(--color-success); }
.type-badge--warning { background: rgba(242,201,76,0.15); color: #9a7d0a; }
.type-badge--info    { background: rgba(47,128,237,0.1);  color: var(--color-info); }
.type-badge--neutral { background: rgba(107,114,128,0.1); color: var(--color-text-secondary); }

.invest-institution { color: var(--color-text-secondary); }

/* Total */
.invest-total { text-align: right; }

.total-value {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text);
  white-space: nowrap;
}

/* Actions */
.invest-actions {
  display: flex;
  gap: 2px;
}

.chevron {
  color: var(--color-text-secondary);
  flex-shrink: 0;
  transition: transform 0.15s;
}

/* ── Transactions panel ───────────────────────── */
.transactions-panel {
  border-top: 1px solid var(--color-border);
  background: var(--color-bg);
  padding: var(--spacing-md) var(--spacing-lg) var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.panel-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

/* ── TX form ──────────────────────────────────── */
.tx-form {
  background: var(--color-card);
  border-radius: var(--radius-md);
  padding: var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  border: 1px solid var(--color-border);
}

.tx-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: var(--spacing-sm);
}

.tx-form-actions {
  display: flex;
  gap: var(--spacing-sm);
  justify-content: flex-end;
}

/* ── TX list ──────────────────────────────────── */
.tx-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-md);
  font-size: 13px;
  color: var(--color-text-secondary);
}

.tx-state--error { color: var(--color-danger); }

.tx-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tx-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-md);
  transition: background 0.1s;
}

.tx-row:hover { background: var(--color-card); }

.tx-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
}

.tx-badge--success { background: rgba(39,174,96,0.1);   color: var(--color-success); }
.tx-badge--danger  { background: rgba(235,87,87,0.1);   color: var(--color-danger); }
.tx-badge--info    { background: rgba(47,128,237,0.1);  color: var(--color-info); }

.tx-desc {
  flex: 1;
  font-size: 13px;
  color: var(--color-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tx-date {
  font-size: 12px;
  color: var(--color-text-secondary);
  white-space: nowrap;
}

.tx-amount {
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
}

.tx-amount--success { color: var(--color-success); }
.tx-amount--danger  { color: var(--color-danger); }
.tx-amount--info    { color: var(--color-info); }

/* ── Shared form elements ─────────────────────── */
.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.form-input {
  padding: 9px 11px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-bg);
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus { border-color: var(--color-primary); }
.form-select      { cursor: pointer; }

.form-error {
  font-size: 13px;
  color: var(--color-danger);
  background: rgba(235, 87, 87, 0.08);
  border: 1px solid rgba(235, 87, 87, 0.2);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
  margin: 0;
}

/* ── Type toggle (modal) ──────────────────────── */
.type-toggle {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-sm);
}

.type-opt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 8px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
  background: none;
  cursor: pointer;
  color: var(--color-text-secondary);
  transition: all 0.15s;
}

.type-opt:hover:not(.active) {
  border-color: var(--color-text-secondary);
  background: rgba(0,0,0,0.03);
}

.type-opt--success.active { border-color: var(--color-success); background: rgba(39,174,96,0.08);  color: var(--color-success); }
.type-opt--warning.active { border-color: #9a7d0a;              background: rgba(242,201,76,0.12); color: #9a7d0a; }
.type-opt--info.active    { border-color: var(--color-info);    background: rgba(47,128,237,0.08); color: var(--color-info); }
.type-opt--neutral.active { border-color: var(--color-text-secondary); background: rgba(107,114,128,0.1); color: var(--color-text); }

/* ── Buttons ──────────────────────────────────── */
.btn-primary {
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

.btn-primary:hover { opacity: 0.88; }

.btn-sm {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  background: rgba(46,204,113,0.08);
  color: var(--color-primary);
  border: 1px solid rgba(46,204,113,0.3);
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-sm:hover { background: rgba(46,204,113,0.15); }

.btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  background: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--color-text-secondary);
  transition: background 0.15s, color 0.15s;
}

.btn-icon:hover          { background: rgba(0,0,0,0.05); color: var(--color-text); }
.btn-icon--danger:hover  { background: rgba(235,87,87,0.1); color: var(--color-danger); }

.btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 16px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-cancel {
  background: var(--color-bg);
  border: 1.5px solid var(--color-border);
  color: var(--color-text-secondary);
}

.btn-cancel:hover:not(:disabled) {
  border-color: var(--color-text-secondary);
  color: var(--color-text);
}

.btn-save   { background: var(--color-primary); color: white; }
.btn-danger { background: var(--color-danger);  color: white; }
.btn-save:hover:not(:disabled),
.btn-danger:hover:not(:disabled) { opacity: 0.88; }

/* ── Modal ────────────────────────────────────── */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: var(--spacing-md);
}

.modal {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal--sm { max-width: 400px; }

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-lg) var(--spacing-xl);
  border-bottom: 1px solid var(--color-border);
}

.modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.btn-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: var(--radius-md);
  background: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.btn-close:hover { background: rgba(235,87,87,0.1); color: var(--color-danger); }

.modal-body {
  padding: var(--spacing-xl);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.modal-footer {
  display: flex;
  gap: var(--spacing-sm);
  padding: var(--spacing-lg) var(--spacing-xl);
  border-top: 1px solid var(--color-border);
  justify-content: flex-end;
}

.confirm-text {
  font-size: 14px;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin: 0;
}

/* ── States ───────────────────────────────────── */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  gap: var(--spacing-sm);
  color: var(--color-text-secondary);
  text-align: center;
}

.error-state  { color: var(--color-danger); }
.empty-state p { font-weight: 700; color: var(--color-text); margin: 0; font-size: 16px; }
.empty-state span { font-size: 13px; }
.empty-icon   { opacity: 0.25; }
.text-muted   { color: var(--color-text-secondary); }
.mt-sm        { margin-top: var(--spacing-sm); }

/* ── Spinner ──────────────────────────────────── */
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transitions ──────────────────────────────── */
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-active .modal,
.modal-fade-leave-active .modal { transition: transform 0.2s ease, opacity 0.2s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .modal,
.modal-fade-leave-to .modal { transform: translateY(-10px); opacity: 0; }

.expand-enter-active,
.expand-leave-active { transition: opacity 0.2s ease; }
.expand-enter-from,
.expand-leave-to { opacity: 0; }

.slide-enter-active,
.slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.slide-enter-from,
.slide-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }

  .summary-section {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--spacing-md);
  }

  .grand-total-card {
    border-right: none;
    border-bottom: 1px solid var(--color-border);
    padding-right: 0;
    padding-bottom: var(--spacing-md);
    margin-right: 0;
    width: 100%;
  }

  .tx-form-row      { grid-template-columns: 1fr; }
  .type-toggle      { grid-template-columns: repeat(2, 1fr); }
  .invest-row       { gap: var(--spacing-sm); padding: var(--spacing-md); }
  .total-value      { font-size: 14px; }
}
</style>
