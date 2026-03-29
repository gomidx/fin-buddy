<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { Eye, EyeOff } from 'lucide-vue-next'

const router = useRouter()
const auth = useAuthStore()

const activeTab = ref('login')
const loading = ref(false)
const errorMsg = ref('')
const fieldErrors = reactive({})
const showPassword = ref(false)
const showRegisterPassword = ref(false)
const showConfirmPassword = ref(false)

const loginForm = reactive({ email: '', password: '' })
const registerForm = reactive({ name: '', email: '', password: '', password_confirmation: '' })

function clearErrors() {
  errorMsg.value = ''
  Object.keys(fieldErrors).forEach(k => delete fieldErrors[k])
}

function switchTab(tab) {
  activeTab.value = tab
  clearErrors()
}

async function handleLogin() {
  clearErrors()
  loading.value = true
  try {
    await auth.login(loginForm.email, loginForm.password)
    router.push('/dashboard')
  } catch (e) {
    handleError(e)
  } finally {
    loading.value = false
  }
}

async function handleRegister() {
  clearErrors()
  loading.value = true
  try {
    await auth.register(registerForm.name, registerForm.email, registerForm.password, registerForm.password_confirmation)
    router.push('/dashboard')
  } catch (e) {
    handleError(e)
  } finally {
    loading.value = false
  }
}

function handleError(e) {
  if (e.response?.status === 422) {
    const errors = e.response.data.errors || {}
    Object.assign(fieldErrors, errors)
    errorMsg.value = 'Verifique os campos abaixo.'
  } else {
    errorMsg.value = e.response?.data?.message || 'Ocorreu um erro. Tente novamente.'
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="brand">
        <div class="brand-dot"></div>
        <span class="brand-name">Fin Buddy</span>
      </div>

      <div class="tab-switcher">
        <button
          :class="['tab-btn', { active: activeTab === 'login' }]"
          @click="switchTab('login')"
        >Entrar</button>
        <button
          :class="['tab-btn', { active: activeTab === 'register' }]"
          @click="switchTab('register')"
        >Criar conta</button>
      </div>

      <div v-if="errorMsg" class="error-alert">{{ errorMsg }}</div>

      <!-- Login Form -->
      <form v-if="activeTab === 'login'" @submit.prevent="handleLogin" class="auth-form">
        <div class="field-group">
          <label for="login-email">E-mail</label>
          <input
            id="login-email"
            v-model="loginForm.email"
            type="email"
            placeholder="seu@email.com"
            :class="{ 'input-error': fieldErrors.email }"
            required
          />
          <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email[0] }}</span>
        </div>

        <div class="field-group">
          <label for="login-password">Senha</label>
          <div class="input-with-icon">
            <input
              id="login-password"
              v-model="loginForm.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              :class="{ 'input-error': fieldErrors.password }"
              required
            />
            <button type="button" class="icon-btn" @click="showPassword = !showPassword">
              <EyeOff v-if="showPassword" :size="18" />
              <Eye v-else :size="18" />
            </button>
          </div>
          <span v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password[0] }}</span>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading">
          <span v-if="loading">Entrando...</span>
          <span v-else>Entrar</span>
        </button>
      </form>

      <!-- Register Form -->
      <form v-else @submit.prevent="handleRegister" class="auth-form">
        <div class="field-group">
          <label for="reg-name">Nome</label>
          <input
            id="reg-name"
            v-model="registerForm.name"
            type="text"
            placeholder="Seu nome"
            :class="{ 'input-error': fieldErrors.name }"
            required
          />
          <span v-if="fieldErrors.name" class="field-error">{{ fieldErrors.name[0] }}</span>
        </div>

        <div class="field-group">
          <label for="reg-email">E-mail</label>
          <input
            id="reg-email"
            v-model="registerForm.email"
            type="email"
            placeholder="seu@email.com"
            :class="{ 'input-error': fieldErrors.email }"
            required
          />
          <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email[0] }}</span>
        </div>

        <div class="field-group">
          <label for="reg-password">Senha</label>
          <div class="input-with-icon">
            <input
              id="reg-password"
              v-model="registerForm.password"
              :type="showRegisterPassword ? 'text' : 'password'"
              placeholder="••••••••"
              :class="{ 'input-error': fieldErrors.password }"
              required
            />
            <button type="button" class="icon-btn" @click="showRegisterPassword = !showRegisterPassword">
              <EyeOff v-if="showRegisterPassword" :size="18" />
              <Eye v-else :size="18" />
            </button>
          </div>
          <span v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password[0] }}</span>
        </div>

        <div class="field-group">
          <label for="reg-confirm">Confirmar senha</label>
          <div class="input-with-icon">
            <input
              id="reg-confirm"
              v-model="registerForm.password_confirmation"
              :type="showConfirmPassword ? 'text' : 'password'"
              placeholder="••••••••"
              :class="{ 'input-error': fieldErrors.password_confirmation }"
              required
            />
            <button type="button" class="icon-btn" @click="showConfirmPassword = !showConfirmPassword">
              <EyeOff v-if="showConfirmPassword" :size="18" />
              <Eye v-else :size="18" />
            </button>
          </div>
          <span v-if="fieldErrors.password_confirmation" class="field-error">{{ fieldErrors.password_confirmation[0] }}</span>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading">
          <span v-if="loading">Criando conta...</span>
          <span v-else>Criar conta</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg);
  padding: var(--spacing-md);
}

.auth-card {
  background: var(--color-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-xl);
  width: 100%;
  max-width: 420px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: center;
  margin-bottom: var(--spacing-lg);
}

.brand-dot {
  width: 36px;
  height: 36px;
  background: var(--color-primary);
  border-radius: 50%;
}

.brand-name {
  font-size: 22px;
  font-weight: 700;
}

.tab-switcher {
  display: flex;
  background: var(--color-bg);
  border-radius: 50px;
  padding: 4px;
  margin-bottom: var(--spacing-lg);
}

.tab-btn {
  flex: 1;
  padding: 8px 16px;
  border: none;
  background: none;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-secondary);
  transition: background 0.2s, color 0.2s;
}

.tab-btn.active {
  background: var(--color-card);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.error-alert {
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: var(--color-danger);
  border-radius: var(--radius-md);
  padding: var(--spacing-sm) var(--spacing-md);
  font-size: 14px;
  margin-bottom: var(--spacing-md);
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.field-group label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
}

.field-group input {
  padding: 10px var(--spacing-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 15px;
  color: var(--color-text);
  background: var(--color-card);
  transition: border-color 0.15s;
  outline: none;
  width: 100%;
}

.field-group input:focus {
  border-color: var(--color-primary);
}

.field-group input.input-error {
  border-color: var(--color-danger);
}

.input-with-icon {
  position: relative;
}

.input-with-icon input {
  padding-right: 44px;
}

.icon-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: none;
  color: var(--color-text-secondary);
  padding: 0;
  display: flex;
  align-items: center;
}

.field-error {
  font-size: 12px;
  color: var(--color-danger);
}

.submit-btn {
  width: 100%;
  padding: 12px;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 15px;
  font-weight: 600;
  transition: opacity 0.15s;
  margin-top: var(--spacing-xs);
}

.submit-btn:hover {
  opacity: 0.9;
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
