import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('fin_buddy_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('fin_buddy_user') || 'null'))

  const isAuthenticated = computed(() => !!token.value)

  function setAuth(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('fin_buddy_token', newToken)
    localStorage.setItem('fin_buddy_user', JSON.stringify(newUser))
  }

  function clearAuth() {
    token.value = null
    user.value = null
    localStorage.removeItem('fin_buddy_token')
    localStorage.removeItem('fin_buddy_user')
  }

  async function login(email, password) {
    const res = await api.post('/auth/login', { email, password })
    setAuth(res.data.token, res.data.user)
    return res.data
  }

  async function register(name, email, password, password_confirmation) {
    const res = await api.post('/auth/register', { name, email, password, password_confirmation })
    setAuth(res.data.token, res.data.user)
    return res.data
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    clearAuth()
  }

  return { token, user, isAuthenticated, login, register, logout, setAuth, clearAuth }
})
