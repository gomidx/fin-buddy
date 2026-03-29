<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Target, Plus, Pencil, Trash2, Check, X, Loader2,
  AlertTriangle, CheckCircle2, Clock, TrendingUp, Wallet,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import api from '../services/api.js'

const auth   = useAuthStore()
const router = useRouter()

// ── Estado principal ─────────────────────────────
const goals   = ref([])
const loading = ref(false)
const error   = ref(null)

// ── Modal criar / editar ──────────────────────────
const modal        = ref(false)
const modalMode    = ref('create')
const modalLoading = ref(false)
const modalError   = ref(null)
const editingId    = ref(null)
const form = ref(defaultForm())

function defaultForm() {
  return { name: '', target_amount: '', current_amount: '', target_date: '' }
}

// ── Depósito inline por card ──────────────────────
const depositCard    = ref(null)   // id do card com depósito aberto
const depositAmount  = ref('')
const depositLoading = ref(false)
const depositError   = ref(null)

// ── Confirmação de exclusão ───────────────────────
const confirmDelete = ref(null)
const deleteLoading = ref(false)

// ── Resumo geral ──────────────────────────────────
const summary = computed(() => {
  const total   = goals.value.length
  const done    = goals.value.filter(g => g.progress_percentage >= 100).length
  const saved   = goals.value.reduce((s, g) => s + parseFloat(g.current_amount ?? 0), 0)
  const target  = goals.value.reduce((s, g) => s + parseFloat(g.target_amount  ?? 0), 0)
  const overall = target > 0 ? Math.min(100, Math.round((saved / target) * 100)) : 0
  return { total, done, saved, target, overall }
})

// ── Progresso: cor e label ────────────────────────
function progressColor(pct) {
  if (pct >= 100) return 'var(--color-success)'
  if (pct >= 75)  return 'var(--color-info)'
  if (pct >= 25)  return 'var(--color-warning)'
  return 'var(--color-danger)'
}

function goalStatus(goal) {
  const pct = goal.progress_percentage
  if (pct >= 100) return { label: 'Concluída',  color: 'success', icon: CheckCircle2 }
  if (goal.target_date && isPastDeadline(goal.target_date)) return { label: 'Atrasada', color: 'danger', icon: AlertTriangle }
  if (pct >= 75)  return { label: 'Quase lá',   color: 'info',    icon: TrendingUp }
  return { label: 'Em andamento', color: 'neutral', icon: Target }
}

function isPastDeadline(dateStr) {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

// ── Dias restantes ────────────────────────────────
function daysRemaining(dateStr) {
  if (!dateStr) return null
  const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000)
  if (diff < 0)  return { label: `Vencida há ${Math.abs(diff)} dias`, danger: true }
  if (diff === 0) return { label: 'Vence hoje', danger: true }
  if (diff <= 30) return { label: `${diff} dia${diff !== 1 ? 's' : ''} restante${diff !== 1 ? 's' : ''}`, danger: false }
  const months = Math.floor(diff / 30)
  return { label: `${months} mês${months !== 1 ? 'es' : ''} restante${months !== 1 ? 's' : ''}`, danger: false }
}

// ── Formatação ────────────────────────────────────
function formatCurrency(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
}

function formatDate(dateStr) {
  if (!dateStr) return null
  return new Date(dateStr.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

// ── Fetch ─────────────────────────────────────────
async function fetchGoals() {
  loading.value = true
  error.value   = null
  try {
    const res  = await api.get('/financial-goals')
    // Ordena: incompletas primeiro (por % asc), concluídas por último
    goals.value = res.data.sort((a, b) => {
      const aDone = a.progress_percentage >= 100
      const bDone = b.progress_percentage >= 100
      if (aDone !== bDone) return aDone ? 1 : -1
      return a.progress_percentage - b.progress_percentage
    })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao carregar metas.'
  } finally {
    loading.value = false
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

function openEdit(goal) {
  modalMode.value  = 'edit'
  editingId.value  = goal.id
  form.value = {
    name:           goal.name,
    target_amount:  goal.target_amount,
    current_amount: goal.current_amount,
    target_date:    goal.target_date ? goal.target_date.slice(0, 10) : '',
  }
  modalError.value = null
  modal.value      = true
}

function closeModal() {
  if (modalLoading.value) return
  modal.value      = false
  modalError.value = null
}

async function saveGoal() {
  modalError.value = null

  const targetAmt  = parseFloat(form.value.target_amount)
  const currentAmt = parseFloat(form.value.current_amount) || 0

  if (!form.value.name.trim())        { modalError.value = 'Informe o nome da meta.'; return }
  if (!targetAmt || targetAmt <= 0)   { modalError.value = 'Informe um valor alvo válido.'; return }
  if (currentAmt < 0)                 { modalError.value = 'O valor atual não pode ser negativo.'; return }
  if (currentAmt > targetAmt)         { modalError.value = 'O valor atual não pode ser maior que a meta.'; return }

  const payload = {
    name:           form.value.name.trim(),
    target_amount:  targetAmt,
    current_amount: currentAmt,
    target_date:    form.value.target_date || null,
  }

  modalLoading.value = true
  try {
    if (modalMode.value === 'create') {
      await api.post('/financial-goals', payload)
    } else {
      await api.put(`/financial-goals/${editingId.value}`, payload)
    }
    modal.value = false
    await fetchGoals()
  } catch (e) {
    modalError.value = e.response?.data?.message ?? 'Erro ao salvar meta.'
  } finally {
    modalLoading.value = false
  }
}

// ── Depósito inline ───────────────────────────────
function openDeposit(goal) {
  depositCard.value   = goal.id
  depositAmount.value = ''
  depositError.value  = null
}

function closeDeposit() {
  depositCard.value  = null
  depositError.value = null
}

async function submitDeposit(goal) {
  depositError.value  = null
  const add = parseFloat(depositAmount.value)
  if (!add || add <= 0) { depositError.value = 'Informe um valor válido.'; return }

  const newCurrent = parseFloat(goal.current_amount) + add

  depositLoading.value = true
  try {
    await api.put(`/financial-goals/${goal.id}`, { current_amount: newCurrent })
    depositCard.value = null
    await fetchGoals()
  } catch (e) {
    depositError.value = e.response?.data?.message ?? 'Erro ao atualizar a meta.'
  } finally {
    depositLoading.value = false
  }
}

// ── Excluir ───────────────────────────────────────
function askDelete(goal) {
  confirmDelete.value = goal
}

async function deleteGoal() {
  deleteLoading.value = true
  try {
    await api.delete(`/financial-goals/${confirmDelete.value.id}`)
    confirmDelete.value = null
    await fetchGoals()
  } catch {
    confirmDelete.value = null
  } finally {
    deleteLoading.value = false
  }
}

// ── Logout ────────────────────────────────────────
async function logout() {
  await auth.logout()
  router.push('/')
}

// ── Init ──────────────────────────────────────────
onMounted(fetchGoals)
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
            <Target :size="22" class="page-icon" />
            <h1 class="page-title">Metas Financeiras</h1>
          </div>
          <button class="btn-primary" @click="openCreate">
            <Plus :size="16" />
            Nova meta
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

          <!-- ── Resumo geral ────────────────────── -->
          <div v-if="goals.length" class="summary-bar">
            <div class="summary-item">
              <span class="summary-label">Metas ativas</span>
              <span class="summary-value">{{ summary.total }}</span>
            </div>
            <div class="summary-divider" />
            <div class="summary-item">
              <span class="summary-label">Concluídas</span>
              <span class="summary-value summary-value--success">{{ summary.done }}</span>
            </div>
            <div class="summary-divider" />
            <div class="summary-item">
              <span class="summary-label">Total guardado</span>
              <span class="summary-value">{{ formatCurrency(summary.saved) }}</span>
            </div>
            <div class="summary-divider" />
            <div class="summary-item">
              <span class="summary-label">Total a atingir</span>
              <span class="summary-value">{{ formatCurrency(summary.target) }}</span>
            </div>
            <div class="summary-divider" />
            <!-- Progresso geral -->
            <div class="summary-progress">
              <span class="summary-label">Progresso geral</span>
              <div class="summary-progress-row">
                <div class="progress-bar-bg summary-bar-bg">
                  <div
                    class="progress-bar-fill"
                    :style="{ width: `${summary.overall}%`, background: progressColor(summary.overall) }"
                  />
                </div>
                <span class="summary-pct">{{ summary.overall }}%</span>
              </div>
            </div>
          </div>

          <!-- ── Empty state ────────────────────── -->
          <div v-if="!goals.length" class="state-box empty-state">
            <Target :size="48" class="empty-icon" />
            <p>Nenhuma meta cadastrada</p>
            <span>Defina objetivos financeiros e acompanhe seu progresso.</span>
            <button class="btn-primary mt-sm" @click="openCreate">
              <Plus :size="15" />
              Criar primeira meta
            </button>
          </div>

          <!-- ── Grade de metas ─────────────────── -->
          <div v-else class="goals-grid">
            <div
              v-for="goal in goals"
              :key="goal.id"
              class="goal-card"
              :class="{ 'goal-card--done': goal.progress_percentage >= 100 }"
            >
              <!-- Card header -->
              <div class="goal-card-header">
                <div class="goal-title-row">
                  <!-- Status badge -->
                  <span :class="['status-badge', `status-badge--${goalStatus(goal).color}`]">
                    <component :is="goalStatus(goal).icon" :size="12" />
                    {{ goalStatus(goal).label }}
                  </span>
                  <!-- Actions -->
                  <div class="goal-actions">
                    <button class="btn-icon" @click="openEdit(goal)" title="Editar">
                      <Pencil :size="14" />
                    </button>
                    <button class="btn-icon btn-icon--danger" @click="askDelete(goal)" title="Excluir">
                      <Trash2 :size="14" />
                    </button>
                  </div>
                </div>
                <h3 class="goal-name">{{ goal.name }}</h3>
              </div>

              <!-- Progresso -->
              <div class="goal-progress">
                <div class="progress-bar-bg">
                  <div
                    class="progress-bar-fill"
                    :style="{
                      width: `${goal.progress_percentage}%`,
                      background: progressColor(goal.progress_percentage),
                    }"
                  />
                </div>
                <span class="progress-pct" :style="{ color: progressColor(goal.progress_percentage) }">
                  {{ Math.floor(goal.progress_percentage) }}%
                </span>
              </div>

              <!-- Valores -->
              <div class="goal-amounts">
                <div class="amounts-row">
                  <div class="amount-block">
                    <span class="amount-label">Guardado</span>
                    <span class="amount-value amount-value--current">
                      {{ formatCurrency(goal.current_amount) }}
                    </span>
                  </div>
                  <div class="amount-sep">de</div>
                  <div class="amount-block amount-block--right">
                    <span class="amount-label">Meta</span>
                    <span class="amount-value">{{ formatCurrency(goal.target_amount) }}</span>
                  </div>
                </div>

                <!-- Faltam -->
                <div v-if="goal.progress_percentage < 100" class="remaining-row">
                  <span class="remaining-label">Faltam</span>
                  <span class="remaining-value">
                    {{ formatCurrency(parseFloat(goal.target_amount) - parseFloat(goal.current_amount)) }}
                  </span>
                </div>
              </div>

              <!-- Data alvo -->
              <div v-if="goal.target_date" class="goal-deadline">
                <Clock :size="13" />
                <span>{{ formatDate(goal.target_date) }}</span>
                <span
                  v-if="daysRemaining(goal.target_date)"
                  :class="['deadline-remaining', { 'deadline-remaining--danger': daysRemaining(goal.target_date).danger }]"
                >
                  · {{ daysRemaining(goal.target_date).label }}
                </span>
              </div>

              <!-- Depositar inline -->
              <div class="goal-footer">
                <template v-if="depositCard !== goal.id">
                  <button
                    v-if="goal.progress_percentage < 100"
                    class="btn-deposit"
                    @click="openDeposit(goal)"
                  >
                    <Wallet :size="13" />
                    Depositar
                  </button>
                  <span v-else class="done-label">
                    <CheckCircle2 :size="14" />
                    Meta atingida!
                  </span>
                </template>

                <!-- Form depósito -->
                <Transition name="slide">
                  <div v-if="depositCard === goal.id" class="deposit-form">
                    <input
                      v-model="depositAmount"
                      type="number"
                      min="0.01"
                      step="0.01"
                      placeholder="Valor a depositar"
                      class="deposit-input"
                      autofocus
                      @keyup.enter="submitDeposit(goal)"
                      @keyup.esc="closeDeposit"
                    />
                    <button
                      class="btn-confirm"
                      @click="submitDeposit(goal)"
                      :disabled="depositLoading"
                      title="Confirmar"
                    >
                      <Loader2 v-if="depositLoading" :size="14" class="spin" />
                      <Check v-else :size="14" />
                    </button>
                    <button
                      class="btn-cancel-sm"
                      @click="closeDeposit"
                      :disabled="depositLoading"
                      title="Cancelar"
                    >
                      <X :size="14" />
                    </button>
                  </div>
                </Transition>

                <p v-if="depositCard === goal.id && depositError" class="deposit-error">
                  {{ depositError }}
                </p>
              </div>

            </div>
          </div>

        </template>
      </main>
    </div>

    <BottomNav @logout="logout" />

    <!-- ── Modal criar / editar ─────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="modal" class="modal-backdrop" @click.self="closeModal">
          <div class="modal" role="dialog" aria-modal="true">

            <div class="modal-header">
              <h2 class="modal-title">
                {{ modalMode === 'create' ? 'Nova meta' : 'Editar meta' }}
              </h2>
              <button class="btn-close" @click="closeModal" :disabled="modalLoading">
                <X :size="18" />
              </button>
            </div>

            <div class="modal-body">

              <!-- Nome -->
              <div class="form-field">
                <label class="form-label" for="goal-name">Nome da meta</label>
                <input
                  id="goal-name"
                  v-model="form.name"
                  type="text"
                  placeholder="Ex: Viagem Europa, Carro novo, Apartamento…"
                  class="form-input"
                  maxlength="255"
                />
              </div>

              <div class="form-row">
                <!-- Valor alvo -->
                <div class="form-field">
                  <label class="form-label" for="goal-target">Valor alvo (R$)</label>
                  <input
                    id="goal-target"
                    v-model="form.target_amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    placeholder="10.000,00"
                    class="form-input"
                  />
                </div>

                <!-- Valor atual -->
                <div class="form-field">
                  <label class="form-label" for="goal-current">Já guardei (R$)</label>
                  <input
                    id="goal-current"
                    v-model="form.current_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0,00"
                    class="form-input"
                  />
                  <span class="form-hint">Valor que já possui guardado.</span>
                </div>
              </div>

              <!-- Data alvo -->
              <div class="form-field">
                <label class="form-label" for="goal-date">Data alvo (opcional)</label>
                <input
                  id="goal-date"
                  v-model="form.target_date"
                  type="date"
                  class="form-input"
                />
                <span class="form-hint">Deixe em branco se não tiver prazo definido.</span>
              </div>

              <!-- Preview do progresso -->
              <div
                v-if="form.target_amount && parseFloat(form.target_amount) > 0"
                class="progress-preview"
              >
                <span class="preview-label">Progresso atual</span>
                <div class="progress-preview-row">
                  <div class="progress-bar-bg">
                    <div
                      class="progress-bar-fill"
                      :style="{
                        width: `${Math.min(100, Math.round((parseFloat(form.current_amount) || 0) / parseFloat(form.target_amount) * 100))}%`,
                        background: progressColor(Math.min(100, Math.round((parseFloat(form.current_amount) || 0) / parseFloat(form.target_amount) * 100))),
                        transition: 'width 0.3s ease',
                      }"
                    />
                  </div>
                  <span class="preview-pct">
                    {{ Math.min(100, Math.round((parseFloat(form.current_amount) || 0) / parseFloat(form.target_amount) * 100)) }}%
                  </span>
                </div>
              </div>

              <p v-if="modalError" class="form-error">{{ modalError }}</p>
            </div>

            <div class="modal-footer">
              <button class="btn btn-cancel" @click="closeModal" :disabled="modalLoading">
                Cancelar
              </button>
              <button class="btn btn-save" @click="saveGoal" :disabled="modalLoading">
                <Loader2 v-if="modalLoading" :size="15" class="spin" />
                <Check v-else :size="15" />
                {{ modalMode === 'create' ? 'Criar meta' : 'Salvar' }}
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
              <h2 class="modal-title">Excluir meta</h2>
              <button class="btn-close" @click="confirmDelete = null"><X :size="18" /></button>
            </div>
            <div class="modal-body">
              <p class="confirm-text">
                Tem certeza que deseja excluir a meta
                <strong>{{ confirmDelete.name }}</strong>?
                Esta ação não pode ser desfeita.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-cancel" @click="confirmDelete = null" :disabled="deleteLoading">
                Cancelar
              </button>
              <button class="btn btn-danger" @click="deleteGoal" :disabled="deleteLoading">
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
  max-width: 960px;
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

/* ── Summary bar ──────────────────────────────── */
.summary-bar {
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);
  flex-wrap: wrap;
  background: var(--color-card);
  border-radius: var(--radius-lg);
  padding: var(--spacing-lg) var(--spacing-xl);
  box-shadow: var(--shadow-sm);
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.summary-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.summary-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text);
}

.summary-value--success { color: var(--color-success); }

.summary-divider {
  width: 1px;
  height: 36px;
  background: var(--color-border);
  flex-shrink: 0;
}

.summary-progress {
  flex: 1;
  min-width: 140px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-progress-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.summary-bar-bg { flex: 1; }

.summary-pct {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  white-space: nowrap;
}

/* ── Progress bar base ────────────────────────── */
.progress-bar-bg {
  height: 10px;
  background: var(--color-border);
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.6s ease;
  min-width: 4px;
}

/* ── Goals grid ───────────────────────────────── */
.goals-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--spacing-md);
}

/* ── Goal card ────────────────────────────────── */
.goal-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-lg);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  border: 1.5px solid transparent;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.goal-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

.goal-card--done {
  border-color: rgba(39, 174, 96, 0.3);
  background: linear-gradient(135deg, var(--color-card) 0%, rgba(39,174,96,0.03) 100%);
}

/* Card header */
.goal-card-header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.goal-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.goal-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
  line-height: 1.3;
}

/* Status badge */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 700;
}

.status-badge--success { background: rgba(39,174,96,0.1);   color: var(--color-success); }
.status-badge--info    { background: rgba(47,128,237,0.1);  color: var(--color-info); }
.status-badge--danger  { background: rgba(235,87,87,0.1);   color: var(--color-danger); }
.status-badge--neutral { background: rgba(107,114,128,0.1); color: var(--color-text-secondary); }

/* Actions */
.goal-actions {
  display: flex;
  gap: 2px;
}

/* Progress */
.goal-progress {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.progress-pct {
  font-size: 14px;
  font-weight: 700;
  min-width: 38px;
  text-align: right;
  flex-shrink: 0;
}

/* Amounts */
.goal-amounts {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.amounts-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.amount-block {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.amount-block--right { text-align: right; margin-left: auto; }

.amount-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
}

.amount-value {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);
}

.amount-value--current { color: var(--color-primary); }

.amount-sep {
  font-size: 12px;
  color: var(--color-text-secondary);
  padding-top: 14px;
}

.remaining-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  font-size: 12px;
}

.remaining-label { color: var(--color-text-secondary); }
.remaining-value { font-weight: 600; color: var(--color-text); }

/* Deadline */
.goal-deadline {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--color-text-secondary);
}

.deadline-remaining { font-weight: 500; }
.deadline-remaining--danger { color: var(--color-danger); font-weight: 700; }

/* Footer / Deposit */
.goal-footer {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
  margin-top: auto;
}

.btn-deposit {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: rgba(46, 204, 113, 0.08);
  color: var(--color-primary);
  border: 1px solid rgba(46, 204, 113, 0.25);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  width: fit-content;
}

.btn-deposit:hover { background: rgba(46, 204, 113, 0.15); }

.done-label {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-success);
}

/* Deposit form inline */
.deposit-form {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
}

.deposit-input {
  flex: 1;
  padding: 7px 10px;
  border: 1.5px solid var(--color-primary);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-bg);
  outline: none;
  min-width: 0;
}

.btn-confirm,
.btn-cancel-sm {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 0.15s;
}

.btn-confirm { background: var(--color-primary); color: white; }
.btn-cancel-sm { background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-secondary); }
.btn-confirm:hover:not(:disabled) { opacity: 0.85; }
.btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }

.deposit-error {
  font-size: 12px;
  color: var(--color-danger);
  margin: 0;
}

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

.btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--color-text-secondary);
  transition: background 0.15s, color 0.15s;
}

.btn-icon:hover         { background: rgba(0,0,0,0.05); color: var(--color-text); }
.btn-icon--danger:hover { background: rgba(235,87,87,0.1); color: var(--color-danger); }

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

.modal-title { font-size: 17px; font-weight: 700; color: var(--color-text); margin: 0; }

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

/* ── Form elements ────────────────────────────── */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-md);
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
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

.form-hint {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 0;
}

.form-error {
  font-size: 13px;
  color: var(--color-danger);
  background: rgba(235,87,87,0.08);
  border: 1px solid rgba(235,87,87,0.2);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
  margin: 0;
}

/* Progress preview no modal */
.progress-preview {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: var(--spacing-md);
  background: var(--color-bg);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.preview-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.progress-preview-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.preview-pct {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
  min-width: 36px;
  text-align: right;
}

/* Confirm text */
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
.empty-icon   { opacity: 0.2; }
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

.slide-enter-active,
.slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.slide-enter-from,
.slide-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }

  .goals-grid { grid-template-columns: 1fr; }

  .summary-bar {
    flex-wrap: wrap;
    gap: var(--spacing-md);
  }

  .summary-divider { display: none; }

  .summary-item { min-width: calc(50% - var(--spacing-sm)); }

  .summary-progress { width: 100%; min-width: 100%; }

  .form-row { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
  .modal-backdrop { align-items: flex-end; padding: 0; }
  .modal { border-bottom-left-radius: 0; border-bottom-right-radius: 0; max-width: 100%; }
  .modal-fade-enter-from .modal,
  .modal-fade-leave-to .modal { transform: translateY(100%); opacity: 1; }
}
</style>
