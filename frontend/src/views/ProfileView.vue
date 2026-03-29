<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  User, Mail, Lock, Globe, LogOut, Check, X, Loader2,
  AlertTriangle, CheckCircle2, Eye, EyeOff, Shield,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import AppSidebar from '../components/layout/AppSidebar.vue'
import AppHeader from '../components/layout/AppHeader.vue'
import BottomNav from '../components/layout/BottomNav.vue'
import api from '../services/api.js'

const auth   = useAuthStore()
const router = useRouter()

// ── Dados do perfil ──────────────────────────────
const profileLoading = ref(false)

// ── Seção: informações pessoais ───────────────────
const personalForm    = reactive({ name: '', email: '' })
const personalLoading = ref(false)
const personalError   = ref(null)
const personalSuccess = ref(false)
let personalTimer = null

// ── Seção: senha ──────────────────────────────────
const passwordForm    = reactive({ password: '', password_confirmation: '' })
const passwordLoading = ref(false)
const passwordError   = ref(null)
const passwordSuccess = ref(false)
const showPassword    = ref(false)
const showConfirm     = ref(false)
let passwordTimer = null

// ── Seção: preferências ───────────────────────────
const prefForm    = reactive({ currency: 'BRL' })
const prefLoading = ref(false)
const prefError   = ref(null)
const prefSuccess = ref(false)
let prefTimer = null

// ── Opções de moeda ───────────────────────────────
const CURRENCIES = [
  { code: 'BRL', label: 'Real Brasileiro',  symbol: 'R$'  },
  { code: 'USD', label: 'Dólar Americano',  symbol: '$'   },
  { code: 'EUR', label: 'Euro',             symbol: '€'   },
  { code: 'GBP', label: 'Libra Esterlina',  symbol: '£'   },
  { code: 'ARS', label: 'Peso Argentino',   symbol: '$'   },
  { code: 'CLP', label: 'Peso Chileno',     symbol: '$'   },
]

// ── Avatar / iniciais ─────────────────────────────
const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?'
})

const memberSince = computed(() => {
  const d = auth.user?.created_at
  if (!d) return null
  return new Date(d).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
})

// ── Força da senha ────────────────────────────────
const passwordStrength = computed(() => {
  const p = passwordForm.password
  if (!p) return null
  let score = 0
  if (p.length >= 8)  score++
  if (p.length >= 12) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  if (score <= 1) return { label: 'Fraca',   color: 'var(--color-danger)',  bars: 1 }
  if (score <= 2) return { label: 'Regular', color: 'var(--color-warning)', bars: 2 }
  if (score <= 3) return { label: 'Boa',     color: 'var(--color-info)',    bars: 3 }
  return            { label: 'Forte',  color: 'var(--color-success)', bars: 4 }
})

// ── Fetch perfil ──────────────────────────────────
async function fetchProfile() {
  profileLoading.value = true
  try {
    const res = await api.get('/profile')
    syncForms(res.data)
    auth.setAuth(auth.token, res.data)
  } catch { /* silencioso — usa dados do store */ } finally {
    profileLoading.value = false
  }
}

function syncForms(user) {
  personalForm.name  = user.name  ?? ''
  personalForm.email = user.email ?? ''
  prefForm.currency  = user.currency ?? 'BRL'
}

// ── Salvar informações pessoais ───────────────────
async function savePersonal() {
  personalError.value   = null
  personalSuccess.value = false
  clearTimeout(personalTimer)

  if (!personalForm.name.trim())        { personalError.value = 'Informe seu nome.'; return }
  if (!personalForm.email.trim())       { personalError.value = 'Informe seu e-mail.'; return }
  if (!/\S+@\S+\.\S+/.test(personalForm.email)) { personalError.value = 'E-mail inválido.'; return }

  personalLoading.value = true
  try {
    const res = await api.put('/profile', {
      name:  personalForm.name.trim(),
      email: personalForm.email.trim(),
    })
    auth.setAuth(auth.token, res.data.user)
    personalSuccess.value = true
    personalTimer = setTimeout(() => { personalSuccess.value = false }, 3000)
  } catch (e) {
    personalError.value = e.response?.data?.message ?? 'Erro ao salvar informações.'
  } finally {
    personalLoading.value = false
  }
}

// ── Salvar senha ──────────────────────────────────
async function savePassword() {
  passwordError.value   = null
  passwordSuccess.value = false
  clearTimeout(passwordTimer)

  if (!passwordForm.password)                           { passwordError.value = 'Informe a nova senha.'; return }
  if (passwordForm.password.length < 8)                { passwordError.value = 'A senha deve ter pelo menos 8 caracteres.'; return }
  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordError.value = 'As senhas não conferem.'
    return
  }

  passwordLoading.value = true
  try {
    await api.put('/profile', {
      password:              passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    passwordForm.password              = ''
    passwordForm.password_confirmation = ''
    showPassword.value = false
    showConfirm.value  = false
    passwordSuccess.value = true
    passwordTimer = setTimeout(() => { passwordSuccess.value = false }, 3000)
  } catch (e) {
    passwordError.value = e.response?.data?.message ?? 'Erro ao atualizar senha.'
  } finally {
    passwordLoading.value = false
  }
}

// ── Salvar preferências ───────────────────────────
async function savePreferences() {
  prefError.value   = null
  prefSuccess.value = false
  clearTimeout(prefTimer)

  prefLoading.value = true
  try {
    const res = await api.put('/profile', { currency: prefForm.currency })
    auth.setAuth(auth.token, res.data.user)
    prefSuccess.value = true
    prefTimer = setTimeout(() => { prefSuccess.value = false }, 3000)
  } catch (e) {
    prefError.value = e.response?.data?.message ?? 'Erro ao salvar preferências.'
  } finally {
    prefLoading.value = false
  }
}

// ── Logout ────────────────────────────────────────
async function logout() {
  await auth.logout()
  router.push('/')
}

// ── Init ──────────────────────────────────────────
onMounted(() => {
  if (auth.user) syncForms(auth.user)
  fetchProfile()
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
            <User :size="22" class="page-icon" />
            <h1 class="page-title">Perfil</h1>
          </div>
        </div>

        <!-- ── Card de identidade ──────────────────── -->
        <div class="identity-card">
          <div class="avatar-lg">{{ initials }}</div>
          <div class="identity-info">
            <h2 class="identity-name">{{ auth.user?.name ?? '—' }}</h2>
            <p class="identity-email">{{ auth.user?.email ?? '—' }}</p>
            <p v-if="memberSince" class="identity-since">
              Membro desde {{ memberSince }}
            </p>
          </div>
        </div>

        <!-- ── Informações pessoais ───────────────── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title-row">
              <User :size="18" class="section-icon" />
              <h3 class="section-title">Informações pessoais</h3>
            </div>
          </div>

          <div class="section-body">
            <div class="form-row">
              <!-- Nome -->
              <div class="form-field">
                <label class="form-label" for="p-name">Nome completo</label>
                <div class="input-wrapper">
                  <User :size="16" class="input-icon" />
                  <input
                    id="p-name"
                    v-model="personalForm.name"
                    type="text"
                    class="form-input input-with-icon"
                    placeholder="Seu nome"
                    maxlength="255"
                  />
                </div>
              </div>

              <!-- Email -->
              <div class="form-field">
                <label class="form-label" for="p-email">E-mail</label>
                <div class="input-wrapper">
                  <Mail :size="16" class="input-icon" />
                  <input
                    id="p-email"
                    v-model="personalForm.email"
                    type="email"
                    class="form-input input-with-icon"
                    placeholder="seu@email.com"
                    maxlength="255"
                  />
                </div>
              </div>
            </div>

            <div class="section-footer">
              <Transition name="feedback">
                <div v-if="personalError" class="feedback feedback--error">
                  <AlertTriangle :size="14" />
                  {{ personalError }}
                </div>
                <div v-else-if="personalSuccess" class="feedback feedback--success">
                  <CheckCircle2 :size="14" />
                  Informações atualizadas com sucesso!
                </div>
              </Transition>

              <button class="btn-save" @click="savePersonal" :disabled="personalLoading">
                <Loader2 v-if="personalLoading" :size="15" class="spin" />
                <Check v-else :size="15" />
                Salvar alterações
              </button>
            </div>
          </div>
        </div>

        <!-- ── Segurança ──────────────────────────── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title-row">
              <Shield :size="18" class="section-icon" />
              <h3 class="section-title">Segurança</h3>
            </div>
            <p class="section-desc">Defina uma nova senha para sua conta.</p>
          </div>

          <div class="section-body">
            <div class="form-row">
              <!-- Nova senha -->
              <div class="form-field">
                <label class="form-label" for="p-pass">Nova senha</label>
                <div class="input-wrapper">
                  <Lock :size="16" class="input-icon" />
                  <input
                    id="p-pass"
                    v-model="passwordForm.password"
                    :type="showPassword ? 'text' : 'password'"
                    class="form-input input-with-icon input-with-toggle"
                    placeholder="Mínimo 8 caracteres"
                  />
                  <button
                    class="toggle-visibility"
                    type="button"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Ocultar senha' : 'Exibir senha'"
                  >
                    <EyeOff v-if="showPassword" :size="15" />
                    <Eye v-else :size="15" />
                  </button>
                </div>

                <!-- Força da senha -->
                <div v-if="passwordStrength" class="strength-indicator">
                  <div class="strength-bars">
                    <div
                      v-for="i in 4"
                      :key="i"
                      class="strength-bar"
                      :style="{
                        background: i <= passwordStrength.bars
                          ? passwordStrength.color
                          : 'var(--color-border)'
                      }"
                    />
                  </div>
                  <span class="strength-label" :style="{ color: passwordStrength.color }">
                    {{ passwordStrength.label }}
                  </span>
                </div>
              </div>

              <!-- Confirmar senha -->
              <div class="form-field">
                <label class="form-label" for="p-confirm">Confirmar nova senha</label>
                <div class="input-wrapper">
                  <Lock :size="16" class="input-icon" />
                  <input
                    id="p-confirm"
                    v-model="passwordForm.password_confirmation"
                    :type="showConfirm ? 'text' : 'password'"
                    class="form-input input-with-icon input-with-toggle"
                    placeholder="Repita a senha"
                  />
                  <button
                    class="toggle-visibility"
                    type="button"
                    @click="showConfirm = !showConfirm"
                    :aria-label="showConfirm ? 'Ocultar senha' : 'Exibir senha'"
                  >
                    <EyeOff v-if="showConfirm" :size="15" />
                    <Eye v-else :size="15" />
                  </button>
                </div>
                <!-- Match indicator -->
                <span
                  v-if="passwordForm.password && passwordForm.password_confirmation"
                  :class="['match-hint', {
                    'match-hint--ok':  passwordForm.password === passwordForm.password_confirmation,
                    'match-hint--err': passwordForm.password !== passwordForm.password_confirmation,
                  }]"
                >
                  <Check v-if="passwordForm.password === passwordForm.password_confirmation" :size="12" />
                  <X v-else :size="12" />
                  {{ passwordForm.password === passwordForm.password_confirmation ? 'Senhas conferem' : 'Senhas não conferem' }}
                </span>
              </div>
            </div>

            <div class="section-footer">
              <Transition name="feedback">
                <div v-if="passwordError" class="feedback feedback--error">
                  <AlertTriangle :size="14" />
                  {{ passwordError }}
                </div>
                <div v-else-if="passwordSuccess" class="feedback feedback--success">
                  <CheckCircle2 :size="14" />
                  Senha atualizada com sucesso!
                </div>
              </Transition>

              <button
                class="btn-save btn-save--security"
                @click="savePassword"
                :disabled="passwordLoading || !passwordForm.password"
              >
                <Loader2 v-if="passwordLoading" :size="15" class="spin" />
                <Lock v-else :size="15" />
                Atualizar senha
              </button>
            </div>
          </div>
        </div>

        <!-- ── Preferências ───────────────────────── -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title-row">
              <Globe :size="18" class="section-icon" />
              <h3 class="section-title">Preferências</h3>
            </div>
            <p class="section-desc">Configurações de exibição da sua conta.</p>
          </div>

          <div class="section-body">
            <div class="form-field">
              <label class="form-label">Moeda principal</label>
              <div class="currency-grid">
                <button
                  v-for="cur in CURRENCIES"
                  :key="cur.code"
                  :class="['currency-opt', { active: prefForm.currency === cur.code }]"
                  @click="prefForm.currency = cur.code"
                  type="button"
                >
                  <span class="currency-symbol">{{ cur.symbol }}</span>
                  <span class="currency-code">{{ cur.code }}</span>
                  <span class="currency-name">{{ cur.label }}</span>
                </button>
              </div>
            </div>

            <div class="section-footer">
              <Transition name="feedback">
                <div v-if="prefError" class="feedback feedback--error">
                  <AlertTriangle :size="14" />
                  {{ prefError }}
                </div>
                <div v-else-if="prefSuccess" class="feedback feedback--success">
                  <CheckCircle2 :size="14" />
                  Preferências salvas!
                </div>
              </Transition>

              <button class="btn-save" @click="savePreferences" :disabled="prefLoading">
                <Loader2 v-if="prefLoading" :size="15" class="spin" />
                <Check v-else :size="15" />
                Salvar preferências
              </button>
            </div>
          </div>
        </div>

        <!-- ── Conta ──────────────────────────────── -->
        <div class="section-card section-card--danger">
          <div class="section-header">
            <div class="section-title-row">
              <LogOut :size="18" class="section-icon section-icon--danger" />
              <h3 class="section-title">Sessão</h3>
            </div>
            <p class="section-desc">Encerrar a sessão atual em todos os dispositivos.</p>
          </div>
          <div class="section-body">
            <button class="btn-logout" @click="logout">
              <LogOut :size="15" />
              Sair da conta
            </button>
          </div>
        </div>

      </main>
    </div>

    <BottomNav @logout="logout" />
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
  max-width: 780px;
  margin: 0 auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

/* ── Page header ──────────────────────────────── */
.page-header      { display: flex; align-items: center; }
.page-header-left { display: flex; align-items: center; gap: var(--spacing-sm); }
.page-icon        { color: var(--color-primary); }
.page-title       { font-size: 22px; font-weight: 700; color: var(--color-text); margin: 0; }

/* ── Identity card ────────────────────────────── */
.identity-card {
  display: flex;
  align-items: center;
  gap: var(--spacing-xl);
  background: var(--color-card);
  border-radius: var(--radius-lg);
  padding: var(--spacing-xl);
  box-shadow: var(--shadow-sm);
  border-left: 5px solid var(--color-primary);
}

.avatar-lg {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--color-primary);
  color: white;
  font-size: 26px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  letter-spacing: -1px;
}

.identity-info { display: flex; flex-direction: column; gap: 3px; }

.identity-name {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.identity-email {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.identity-since {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 4px 0 0;
  opacity: 0.7;
}

/* ── Section card ─────────────────────────────── */
.section-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.section-card--danger {
  border: 1px solid rgba(235, 87, 87, 0.2);
}

.section-header {
  padding: var(--spacing-lg) var(--spacing-xl);
  border-bottom: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.section-icon       { color: var(--color-primary); }
.section-icon--danger { color: var(--color-danger); }

.section-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.section-desc {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
  padding-left: 26px;
}

.section-body {
  padding: var(--spacing-xl);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
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
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 12px;
  color: var(--color-text-secondary);
  pointer-events: none;
  z-index: 1;
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-bg);
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.form-input:focus { border-color: var(--color-primary); }

.input-with-icon        { padding-left: 38px; }
.input-with-toggle      { padding-right: 40px; }

.toggle-visibility {
  position: absolute;
  right: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: none;
  cursor: pointer;
  color: var(--color-text-secondary);
  border-radius: var(--radius-md);
  transition: color 0.15s;
  z-index: 1;
}

.toggle-visibility:hover { color: var(--color-text); }

/* ── Força da senha ───────────────────────────── */
.strength-indicator {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  margin-top: 2px;
}

.strength-bars {
  display: flex;
  gap: 3px;
  flex: 1;
}

.strength-bar {
  height: 4px;
  flex: 1;
  border-radius: 99px;
  transition: background 0.3s;
}

.strength-label {
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  transition: color 0.3s;
}

/* ── Match indicator ──────────────────────────── */
.match-hint {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
}

.match-hint--ok  { color: var(--color-success); }
.match-hint--err { color: var(--color-danger); }

/* ── Currency grid ────────────────────────────── */
.currency-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-sm);
}

.currency-opt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--spacing-md) var(--spacing-sm);
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  background: none;
  cursor: pointer;
  transition: all 0.15s;
}

.currency-opt:hover:not(.active) {
  border-color: var(--color-text-secondary);
  background: rgba(0,0,0,0.02);
}

.currency-opt.active {
  border-color: var(--color-primary);
  background: rgba(46, 204, 113, 0.06);
}

.currency-symbol {
  font-size: 18px;
  font-weight: 800;
  color: var(--color-text);
}

.currency-code {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
}

.currency-name {
  font-size: 10px;
  color: var(--color-text-secondary);
  text-align: center;
  line-height: 1.3;
}

.currency-opt.active .currency-symbol,
.currency-opt.active .currency-code {
  color: var(--color-primary);
}

/* ── Section footer ───────────────────────────── */
.section-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-md);
  flex-wrap: wrap;
  padding-top: var(--spacing-sm);
  border-top: 1px solid var(--color-border);
}

/* ── Feedback ─────────────────────────────────── */
.feedback {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  padding: 6px 12px;
  border-radius: var(--radius-md);
}

.feedback--error   { color: var(--color-danger);  background: rgba(235,87,87,0.08); }
.feedback--success { color: var(--color-success); background: rgba(39,174,96,0.08); }

/* ── Buttons ──────────────────────────────────── */
.btn-save {
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
  margin-left: auto;
}

.btn-save:hover:not(:disabled) { opacity: 0.88; }
.btn-save:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-save--security { background: var(--color-info); }

.btn-logout {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  background: rgba(235, 87, 87, 0.08);
  color: var(--color-danger);
  border: 1.5px solid rgba(235, 87, 87, 0.25);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.btn-logout:hover {
  background: rgba(235, 87, 87, 0.14);
  border-color: var(--color-danger);
}

/* ── Spinner ──────────────────────────────────── */
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transition ───────────────────────────────── */
.feedback-enter-active,
.feedback-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.feedback-enter-from,
.feedback-leave-to     { opacity: 0; transform: translateX(-6px); }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 768px) {
  .main-wrapper {
    margin-left: 0;
    padding-top: 60px;
    padding-bottom: 80px;
  }

  .identity-card {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--spacing-md);
  }

  .form-row        { grid-template-columns: 1fr; }
  .currency-grid   { grid-template-columns: repeat(2, 1fr); }

  .section-footer  { flex-direction: column; align-items: stretch; }
  .btn-save        { margin-left: 0; justify-content: center; }
}
</style>
