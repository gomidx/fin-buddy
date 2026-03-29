import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('fin_buddy_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      // Importações lazy para evitar dependência circular na inicialização
      import('../stores/auth').then(({ useAuthStore }) => {
        useAuthStore().clearAuth()
      })
      import('../router').then(({ default: router }) => {
        router.replace({ name: 'auth' })
      })
    }
    return Promise.reject(err)
  }
)

export default api
