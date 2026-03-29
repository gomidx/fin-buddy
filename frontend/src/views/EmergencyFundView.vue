<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  Shield, Plus, Pencil, Check, X, Info, TrendingUp, AlertTriangle, CheckCircle2, Loader2,
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
const fund    = ref(null)
const loading = ref(false)
const error   = ref(null)

// ── Metas ────────────────────────────────────────
const editingGoal  = ref(false)
const goalMonths   = ref(6)
const goalAmount   = ref('')          // opcional; vazio = calculado automaticamente
const goalLoading  = ref(false)
const goalError    = ref(null)

// ── Depósito ─────────────────────────────────────
const depositOpen    = ref(false)
const depositAmount  = ref('')
const depositDesc    = ref('')
const depositDate    = ref(today())
const depositLoading = ref(false)
const depositError   = ref(null)
const depositSuccess = ref(false)

// ── Helpers ──────────────────────────────────────
function today() {
  return new Date().toISOString().slice(0, 10)
}

function formatCurrency(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
}

// ── Status config ────────────────────────────────
const statusConfig = {
  safe:           { label: 'Seguro',           color: 'success', icon: CheckCircle2 },
  attention:      { label: 'Atenção',          color: 'warning', icon: AlertTriangle },
  risk:           { label: 'Em risco',         color: 'danger',  icon: AlertTriangle },
  not_configured: { label: 'Sem dados',        color: 'neutral', icon: Info },
}

const currentStatus = computed(() => statusConfig[fund.value?.status] ?? statusConfig.not_configured)

// Cor da barra de progresso
const progressColor = computed(() => {
  const s = fund.value?.status
  if (s === 'safe')      return 'var(--color-success)'
  if (s === 'attention') return 'var(--color-warning)'
  if (s === 'risk')      return 'var(--color-danger)'
  return 'var(--color-primary)'
})

// Meses cobertos: texto legível
const monthsCoveredLabel = computed(() => {
  const m = fund.value?.months_covered ?? 0
  if (m === 0) return '0 meses'
  if (m < 1)   return 'menos de 1 mês'
  const rounded = Math.floor(m * 10) / 10
  return `${rounded} ${rounded === 1 ? 'mês' : 'meses'}`
})

// Quanto falta para atingir a meta
const remaining = computed(() => {
  if (!fund.value?.has_goal) return null
  return Math.max(0, fund.value.target_amount - fund.value.current_amount)
})

// ── Fetch ────────────────────────────────────────
async function fetchFund() {
  loading.value = true
  error.value   = null
  try {
    const res  = await api.get('/emergency-fund')
    fund.value = res.data
    goalMonths.value = res.data.target_months ?? 6
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao carregar reserva de emergência.'
  } finally {
    loading.value = false
  }
}

// ── Atualizar meta ───────────────────────────────
function startEditGoal() {
  goalMonths.value = fund.value?.target_months ?? 6
  goalAmount.value = ''
  goalError.value  = null
  editingGoal.value = true
}

function cancelEditGoal() {
  editingGoal.value = false
  goalError.value   = null
}

async function saveGoal() {
  goalError.value = null
  const months = parseInt(goalMonths.value)
  if (!months || months < 1 || months > 60) {
    goalError.value = 'Informe um número de meses entre 1 e 60.'
    return
  }

  goalLoading.value = true
  try {
    const payload = { target_months: months }
    if (goalAmount.value) payload.target_amount = parseFloat(goalAmount.value)

    const res  = await api.put('/emergency-fund', payload)
    fund.value = res.data
    editingGoal.value = false
    ui.notifyDataChanged()
  } catch (e) {
    goalError.value = e.response?.data?.message ?? 'Erro ao salvar meta.'
  } finally {
    goalLoading.value = false
  }
}

// ── Depósito ─────────────────────────────────────
function openDeposit() {
  depositAmount.value  = ''
  depositDesc.value    = ''
  depositDate.value    = today()
  depositError.value   = null
  depositSuccess.value = false
  depositOpen.value    = true
}

function cancelDeposit() {
  depositOpen.value  = false
  depositError.value = null
}

async function submitDeposit() {
  depositError.value   = null
  depositSuccess.value = false

  const amount = parseFloat(depositAmount.value)
  if (!amount || amount <= 0) { depositError.value = 'Informe um valor válido.'; return }
  if (!depositDate.value)     { depositError.value = 'Informe a data.'; return }

  depositLoading.value = true
  try {
    await api.post('/emergency-fund/deposit', {
      amount,
      description: depositDesc.value.trim() || 'Depósito na reserva de emergência',
      date:        depositDate.value,
    })
    depositSuccess.value = true
    depositOpen.value    = false
    ui.notifyDataChanged()
    await fetchFund()
  } catch (e) {
    depositError.value = e.response?.data?.message ?? 'Erro ao registrar depósito.'
  } finally {
    depositLoading.value = false
  }
}

// ── Logout ───────────────────────────────────────
async function logout() {
  await auth.logout()
  router.push('/')
}

// ── Init ─────────────────────────────────────────
onMounted(fetchFund)
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
            <Shield :size="22" class="page-icon" />
            <h1 class="page-title">Reserva de Emergência</h1>
          </div>
          <button v-if="fund && !depositOpen" class="btn-deposit" @click="openDeposit">
            <Plus :size="16" />
            Depositar
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

        <template v-else-if="fund">

          <!-- ── Status + meses cobertos ───────────── -->
          <div class="status-card" :class="`status-card--${fund.status}`">
            <div class="status-left">
              <div class="status-badge" :class="`badge--${currentStatus.color}`">
                <component :is="currentStatus.icon" :size="14" />
                {{ currentStatus.label }}
              </div>
              <div class="months-covered">
                <span class="months-number">{{ monthsCoveredLabel }}</span>
                <span class="months-label">de despesas cobertas</span>
              </div>
            </div>
            <div class="status-right">
              <div class="current-label">Valor acumulado</div>
              <div class="current-amount">{{ formatCurrency(fund.current_amount) }}</div>
            </div>
          </div>

          <!-- ── Progresso (se houver meta) ───────── -->
          <div v-if="fund.has_goal" class="card">
            <div class="card-header">
              <span class="card-title">Progresso da meta</span>
              <button class="btn-icon" @click="startEditGoal" title="Editar meta">
                <Pencil :size="15" />
              </button>
            </div>

            <!-- Barra -->
            <div class="progress-wrapper">
              <div class="progress-bar-bg">
                <div
                  class="progress-bar-fill"
                  :style="{ width: `${fund.percentage}%`, background: progressColor }"
                />
              </div>
              <span class="progress-pct">{{ fund.percentage }}%</span>
            </div>

            <!-- Valores -->
            <div class="progress-values">
              <span class="pv-current">{{ formatCurrency(fund.current_amount) }}</span>
              <span class="pv-sep">de</span>
              <span class="pv-target">{{ formatCurrency(fund.target_amount) }}</span>
            </div>

            <!-- Restante -->
            <div v-if="remaining > 0" class="remaining-hint">
              <TrendingUp :size="14" />
              Faltam {{ formatCurrency(remaining) }} para atingir a meta de {{ fund.target_months }} meses
            </div>
            <div v-else class="remaining-hint remaining-hint--done">
              <CheckCircle2 :size="14" />
              Meta atingida! Sua reserva está completa.
            </div>
          </div>

          <!-- ── Sem meta ainda ────────────────────── -->
          <div v-else class="card card--highlight">
            <div class="card-header">
              <span class="card-title">Defina sua meta</span>
            </div>
            <p class="hint-text">
              Configure quantos meses de despesas você quer guardar. O recomendado é <strong>6 meses</strong>.
            </p>
            <button class="btn-set-goal" @click="startEditGoal">
              <Plus :size="16" />
              Configurar meta
            </button>
          </div>

          <!-- ── Formulário: definir / editar meta ── -->
          <Transition name="slide">
            <div v-if="editingGoal" class="card form-card">
              <div class="card-header">
                <span class="card-title">{{ fund.has_goal ? 'Editar meta' : 'Configurar meta' }}</span>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label class="form-label">Meses de cobertura</label>
                  <div class="months-input-row">
                    <input
                      v-model.number="goalMonths"
                      type="number"
                      min="1"
                      max="60"
                      class="form-input"
                      placeholder="6"
                    />
                    <span class="input-suffix">meses</span>
                  </div>
                  <p class="form-hint">
                    Meta estimada:
                    <strong>
                      {{ fund.average_monthly_expenses > 0
                        ? formatCurrency(fund.average_monthly_expenses * goalMonths)
                        : 'Registre despesas para calcular' }}
                    </strong>
                  </p>
                </div>

                <div class="form-field">
                  <label class="form-label">Valor alvo (opcional)</label>
                  <input
                    v-model="goalAmount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-input"
                    placeholder="Calculado automaticamente"
                  />
                  <p class="form-hint">Deixe em branco para calcular pelo seu gasto médio.</p>
                </div>
              </div>

              <p v-if="goalError" class="form-error">{{ goalError }}</p>

              <div class="form-actions">
                <button class="btn btn-cancel" @click="cancelEditGoal" :disabled="goalLoading">
                  <X :size="15" /> Cancelar
                </button>
                <button class="btn btn-save" @click="saveGoal" :disabled="goalLoading">
                  <Loader2 v-if="goalLoading" :size="15" class="spin" />
                  <Check v-else :size="15" />
                  Salvar
                </button>
              </div>
            </div>
          </Transition>

          <!-- ── Formulário: depósito ──────────────── -->
          <Transition name="slide">
            <div v-if="depositOpen" class="card form-card">
              <div class="card-header">
                <span class="card-title">Registrar depósito</span>
                <button class="btn-icon" @click="cancelDeposit">
                  <X :size="15" />
                </button>
              </div>

              <div class="form-row">
                <div class="form-field">
                  <label class="form-label" for="dep-amount">Valor (R$)</label>
                  <input
                    id="dep-amount"
                    v-model="depositAmount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-input"
                    placeholder="0,00"
                    autofocus
                  />
                </div>

                <div class="form-field">
                  <label class="form-label" for="dep-date">Data</label>
                  <input
                    id="dep-date"
                    v-model="depositDate"
                    type="date"
                    class="form-input"
                    :max="today()"
                  />
                </div>
              </div>

              <div class="form-field">
                <label class="form-label" for="dep-desc">Descrição (opcional)</label>
                <input
                  id="dep-desc"
                  v-model="depositDesc"
                  type="text"
                  class="form-input"
                  placeholder="Ex: Depósito mensal"
                  maxlength="255"
                />
              </div>

              <p v-if="depositError" class="form-error">{{ depositError }}</p>

              <div class="form-actions">
                <button class="btn btn-cancel" @click="cancelDeposit" :disabled="depositLoading">
                  <X :size="15" /> Cancelar
                </button>
                <button class="btn btn-save btn-save--fund" @click="submitDeposit" :disabled="depositLoading">
                  <Loader2 v-if="depositLoading" :size="15" class="spin" />
                  <Check v-else :size="15" />
                  Confirmar
                </button>
              </div>
            </div>
          </Transition>

          <!-- ── Informações ─────────────────────── -->
          <div class="info-grid">
            <!-- Média mensal -->
            <div class="info-card">
              <span class="info-label">Gasto médio mensal</span>
              <span class="info-value">{{ formatCurrency(fund.average_monthly_expenses) }}</span>
              <span class="info-hint">Média dos últimos 3 meses</span>
            </div>

            <!-- Meses cobertos -->
            <div class="info-card" :class="`info-card--${fund.status}`">
              <span class="info-label">Cobertura atual</span>
              <span class="info-value info-value--big">{{ monthsCoveredLabel }}</span>
              <span class="info-hint">Recomendado: 6 meses</span>
            </div>

            <!-- Meta em meses (se existir) -->
            <div v-if="fund.has_goal" class="info-card">
              <span class="info-label">Meta definida</span>
              <span class="info-value">{{ fund.target_months }} meses</span>
              <span class="info-hint">{{ formatCurrency(fund.target_amount) }}</span>
            </div>
          </div>

          <!-- ── Dica financeira ─────────────────── -->
          <div class="tip-card">
            <Info :size="16" class="tip-icon" />
            <div>
              <p class="tip-title">Por que 6 meses?</p>
              <p class="tip-text">
                A reserva de emergência cobre imprevistos como demissão, problemas de saúde ou reparos.
                Com 6 meses de despesas guardados, você tem segurança para enfrentar situações difíceis
                sem comprometer seu planejamento financeiro.
              </p>
            </div>
          </div>

        </template>
      </main>
    </div>

    <BottomNav @logout="logout" />
  </div>
</template>

<style scoped>
.app-layout   { display: flex; min-height: 100vh; }

.main-wrapper {
  flex: 1;
  margin-left: var(--sidebar-width);
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.main-content {
  padding: var(--spacing-lg);
  max-width: 860px;
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

.btn-deposit {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
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

.btn-deposit:hover { opacity: 0.88; }

/* ── Status card ──────────────────────────────── */
.status-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-lg);
  padding: var(--spacing-xl);
  border-radius: var(--radius-lg);
  border-left: 5px solid var(--color-border);
  background: var(--color-card);
  box-shadow: var(--shadow-sm);
}

.status-card--safe        { border-left-color: var(--color-success); }
.status-card--attention   { border-left-color: var(--color-warning); }
.status-card--risk        { border-left-color: var(--color-danger); }
.status-card--not_configured { border-left-color: var(--color-border); }

.status-left {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 700;
  width: fit-content;
}

.badge--success { background: rgba(39,174,96,0.12); color: var(--color-success); }
.badge--warning { background: rgba(242,201,76,0.15); color: #9a7d0a; }
.badge--danger  { background: rgba(235,87,87,0.12);  color: var(--color-danger); }
.badge--neutral { background: rgba(107,114,128,0.1); color: var(--color-text-secondary); }

.months-number {
  font-size: 32px;
  font-weight: 800;
  color: var(--color-text);
  line-height: 1.1;
}

.months-label {
  font-size: 13px;
  color: var(--color-text-secondary);
}

.months-covered {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.status-right {
  text-align: right;
}

.current-label {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.current-amount {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text);
}

/* ── Card genérico ────────────────────────────── */
.card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-xl);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.card--highlight {
  border: 1.5px dashed var(--color-primary);
  background: rgba(46, 204, 113, 0.03);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);
}

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

.btn-icon:hover {
  background: rgba(0,0,0,0.05);
  color: var(--color-text);
}

/* ── Progress ─────────────────────────────────── */
.progress-wrapper {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.progress-bar-bg {
  flex: 1;
  height: 12px;
  background: var(--color-border);
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.6s ease;
}

.progress-pct {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  min-width: 40px;
  text-align: right;
}

.progress-values {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: 14px;
}

.pv-current { font-weight: 700; color: var(--color-text); }
.pv-sep     { color: var(--color-text-secondary); }
.pv-target  { color: var(--color-text-secondary); }

.remaining-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--color-text-secondary);
  background: var(--color-bg);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
}

.remaining-hint--done {
  color: var(--color-success);
  background: rgba(39,174,96,0.06);
}

/* ── Sem meta ─────────────────────────────────── */
.hint-text {
  font-size: 14px;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin: 0;
}

.btn-set-goal {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  width: fit-content;
  transition: opacity 0.15s;
}

.btn-set-goal:hover { opacity: 0.88; }

/* ── Formulários ──────────────────────────────── */
.form-card { gap: var(--spacing-lg); }

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-md);
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.form-input {
  padding: 10px 12px;
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

.months-input-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.input-suffix {
  font-size: 13px;
  color: var(--color-text-secondary);
  white-space: nowrap;
}

.form-hint {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 0;
}

.form-error {
  font-size: 13px;
  color: var(--color-danger);
  background: rgba(235, 87, 87, 0.08);
  border: 1px solid rgba(235, 87, 87, 0.2);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
  margin: 0;
}

.form-actions {
  display: flex;
  gap: var(--spacing-sm);
  justify-content: flex-end;
}

.btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s, background 0.15s;
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

.btn-save {
  background: var(--color-primary);
  color: white;
}

.btn-save--fund {
  background: var(--color-info);
}

.btn-save:hover:not(:disabled) { opacity: 0.88; }

/* ── Info grid ────────────────────────────────── */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-md);
}

.info-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: 4px;
  border-top: 3px solid var(--color-border);
}

.info-card--safe        { border-top-color: var(--color-success); }
.info-card--attention   { border-top-color: var(--color-warning); }
.info-card--risk        { border-top-color: var(--color-danger); }

.info-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.info-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text);
}

.info-value--big { font-size: 24px; }

.info-hint {
  font-size: 12px;
  color: var(--color-text-secondary);
}

/* ── Dica financeira ──────────────────────────── */
.tip-card {
  display: flex;
  gap: var(--spacing-md);
  background: rgba(47, 128, 237, 0.06);
  border: 1px solid rgba(47, 128, 237, 0.15);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg);
}

.tip-icon {
  color: var(--color-info);
  flex-shrink: 0;
  margin-top: 2px;
}

.tip-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 4px;
}

.tip-text {
  font-size: 13px;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin: 0;
}

/* ── Estados ──────────────────────────────────── */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  gap: var(--spacing-sm);
  color: var(--color-text-secondary);
}

.error-state { color: var(--color-danger); }
.text-muted  { color: var(--color-text-secondary); }

/* ── Spinner ──────────────────────────────────── */
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transição ────────────────────────────────── */
.slide-enter-active,
.slide-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Responsivo ───────────────────────────────── */
@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }

  .status-card {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--spacing-md);
  }

  .status-right { text-align: left; }

  .months-number { font-size: 26px; }

  .form-row { grid-template-columns: 1fr; }

  .form-actions { flex-direction: column-reverse; }
  .btn { justify-content: center; }
}
</style>
