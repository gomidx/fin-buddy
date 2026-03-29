<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { X, TrendingUp, TrendingDown, Loader2 } from 'lucide-vue-next'
import api from '../../services/api.js'
import { useUiStore } from '../../stores/ui.js'

const ui = useUiStore()

// ── Estado ──────────────────────────────────────
const categories  = ref([])
const loading     = ref(false)
const error       = ref(null)
const suggesting  = ref(false)

const form = ref(defaultForm())

function defaultForm() {
  return {
    type:        'expense',
    amount:      '',
    category_id: '',
    description: '',
    date:        today(),
  }
}

function today() {
  return new Date().toISOString().slice(0, 10)
}

// ── Categorias ───────────────────────────────────
async function fetchCategories() {
  try {
    const res = await api.get('/categories')
    categories.value = res.data
  } catch { /* silencioso */ }
}

// ── Sugestão de categoria ────────────────────────
let suggestTimer = null

watch(() => form.value.description, (val) => {
  if (form.value.category_id) return   // usuário já escolheu, não sobrescrever
  clearTimeout(suggestTimer)
  if (val.trim().length < 3) return
  suggestTimer = setTimeout(() => suggestCategory(val.trim()), 500)
})

async function suggestCategory(description) {
  suggesting.value = true
  try {
    const res = await api.get('/transactions/suggest-category', { params: { description } })
    if (res.data?.category_id && !form.value.category_id) {
      form.value.category_id = String(res.data.category_id)
    }
  } catch { /* silencioso */ } finally {
    suggesting.value = false
  }
}

// ── Formatação do valor ──────────────────────────
const amountDisplay = computed(() => {
  const n = parseFloat(form.value.amount)
  if (!n) return ''
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
})

// ── Submit ───────────────────────────────────────
async function submit() {
  error.value = null

  const amount = parseFloat(form.value.amount)
  if (!amount || amount <= 0) { error.value = 'Informe um valor válido.'; return }
  if (!form.value.category_id) { error.value = 'Selecione uma categoria.'; return }
  if (!form.value.description.trim()) { error.value = 'Informe uma descrição.'; return }
  if (!form.value.date) { error.value = 'Informe a data.'; return }

  loading.value = true
  try {
    await api.post('/transactions', {
      type:        form.value.type,
      amount,
      category_id: Number(form.value.category_id),
      description: form.value.description.trim(),
      date:        form.value.date,
    })
    ui.notifyTransactionCreated()
    reset()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erro ao salvar transação.'
  } finally {
    loading.value = false
  }
}

// ── Fechar / Reset ───────────────────────────────
function close() {
  if (loading.value) return
  ui.closeTransactionModal()
  reset()
}

function reset() {
  form.value = defaultForm()
  error.value = null
}

// ── Init ─────────────────────────────────────────
onMounted(fetchCategories)
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="ui.transactionModalOpen" class="modal-backdrop" @click.self="close">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">

          <!-- Header -->
          <div class="modal-header">
            <h2 id="modal-title" class="modal-title">Nova transação</h2>
            <button class="btn-close" @click="close" aria-label="Fechar">
              <X :size="18" />
            </button>
          </div>

          <!-- Body -->
          <div class="modal-body">

            <!-- Tipo -->
            <div class="field">
              <label class="field-label">Tipo</label>
              <div class="type-toggle">
                <button
                  :class="['type-btn', 'type-btn--income', { active: form.type === 'income' }]"
                  type="button"
                  @click="form.type = 'income'"
                >
                  <TrendingUp :size="16" />
                  Receita
                </button>
                <button
                  :class="['type-btn', 'type-btn--expense', { active: form.type === 'expense' }]"
                  type="button"
                  @click="form.type = 'expense'"
                >
                  <TrendingDown :size="16" />
                  Despesa
                </button>
              </div>
            </div>

            <!-- Valor -->
            <div class="field">
              <label class="field-label" for="amount">Valor (R$)</label>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                min="0.01"
                step="0.01"
                placeholder="0,00"
                class="field-input"
                :class="{ 'field-input--income': form.type === 'income', 'field-input--expense': form.type === 'expense' }"
              />
              <span v-if="amountDisplay" class="amount-preview">{{ amountDisplay }}</span>
            </div>

            <!-- Descrição -->
            <div class="field">
              <label class="field-label" for="description">
                Descrição
                <Loader2 v-if="suggesting" :size="13" class="spin" />
              </label>
              <input
                id="description"
                v-model="form.description"
                type="text"
                placeholder="Ex: Almoço, Salário, Uber…"
                class="field-input"
                maxlength="255"
              />
            </div>

            <!-- Categoria -->
            <div class="field">
              <label class="field-label" for="category">Categoria</label>
              <select id="category" v-model="form.category_id" class="field-input field-select">
                <option value="">Selecione…</option>
                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <!-- Data -->
            <div class="field">
              <label class="field-label" for="date">Data</label>
              <input
                id="date"
                v-model="form.date"
                type="date"
                class="field-input"
                :max="today()"
              />
            </div>

            <!-- Erro -->
            <p v-if="error" class="error-msg">{{ error }}</p>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button class="btn btn-cancel" type="button" @click="close" :disabled="loading">
              Cancelar
            </button>
            <button
              class="btn btn-save"
              :class="{ 'btn-save--income': form.type === 'income' }"
              type="button"
              @click="submit"
              :disabled="loading"
            >
              <Loader2 v-if="loading" :size="16" class="spin" />
              <span>{{ loading ? 'Salvando…' : 'Salvar' }}</span>
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Backdrop */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: var(--spacing-md);
}

/* Modal */
.modal {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Header */
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-lg) var(--spacing-xl);
  border-bottom: 1px solid var(--color-border);
}

.modal-title {
  font-size: 18px;
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

.btn-close:hover {
  background: rgba(235, 87, 87, 0.1);
  color: var(--color-danger);
}

/* Body */
.modal-body {
  padding: var(--spacing-xl);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

/* Field */
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary);
  display: flex;
  align-items: center;
  gap: 6px;
}

.field-input {
  padding: 10px 12px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-bg);
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
  box-sizing: border-box;
}

.field-input:focus {
  border-color: var(--color-primary);
}

.field-input--income:focus { border-color: var(--color-success); }
.field-input--expense:focus { border-color: var(--color-danger); }

.field-select {
  cursor: pointer;
  appearance: auto;
}

.amount-preview {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary);
  padding-left: 2px;
}

/* Type toggle */
.type-toggle {
  display: flex;
  gap: var(--spacing-sm);
}

.type-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  background: none;
  cursor: pointer;
  color: var(--color-text-secondary);
  transition: all 0.15s;
}

.type-btn--income.active {
  border-color: var(--color-success);
  background: rgba(39, 174, 96, 0.08);
  color: var(--color-success);
}

.type-btn--expense.active {
  border-color: var(--color-danger);
  background: rgba(235, 87, 87, 0.08);
  color: var(--color-danger);
}

.type-btn:hover:not(.active) {
  background: rgba(0, 0, 0, 0.03);
  border-color: var(--color-text-secondary);
}

/* Error */
.error-msg {
  font-size: 13px;
  color: var(--color-danger);
  background: rgba(235, 87, 87, 0.08);
  border: 1px solid rgba(235, 87, 87, 0.2);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
  margin: 0;
}

/* Footer */
.modal-footer {
  display: flex;
  gap: var(--spacing-sm);
  padding: var(--spacing-lg) var(--spacing-xl);
  border-top: 1px solid var(--color-border);
}

.btn {
  flex: 1;
  padding: 11px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s, background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

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
  background: var(--color-danger);
  color: white;
}

.btn-save--income {
  background: var(--color-success);
}

.btn-save:hover:not(:disabled) {
  opacity: 0.88;
}

/* Spinner */
.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-active .modal,
.modal-fade-leave-active .modal {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-from .modal,
.modal-fade-leave-to .modal {
  transform: translateY(-12px);
  opacity: 0;
}

/* Mobile */
@media (max-width: 480px) {
  .modal-backdrop {
    align-items: flex-end;
    padding: 0;
  }

  .modal {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    max-width: 100%;
  }

  .modal-fade-enter-from .modal,
  .modal-fade-leave-to .modal {
    transform: translateY(100%);
    opacity: 1;
  }
}
</style>
