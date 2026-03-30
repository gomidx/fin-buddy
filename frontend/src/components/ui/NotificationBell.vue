<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'

const props = defineProps({
  // 'right' → painel abre para a esquerda (padrão header)
  // 'left'  → painel abre para a direita (para uso na sidebar)
  align: { type: String, default: 'right' },
  // 'down' → painel abre para baixo (padrão header)
  // 'up'   → painel abre para cima (para uso na sidebar)
  direction: { type: String, default: 'down' },
})
import { Bell, X, CheckCheck, AlertTriangle, Target, Clock } from 'lucide-vue-next'
import api from '../../services/api.js'

const open        = ref(false)
const loading     = ref(false)
const notifications = ref([])
const unreadCount = ref(0)

const iconMap = {
  recurring_due:  Clock,
  goal_reminder:  Target,
  no_activity:    AlertTriangle,
}

// ── Fetch ─────────────────────────────────────
async function fetchCount() {
  try {
    const res = await api.get('/notifications/unread-count')
    unreadCount.value = res.data.count
  } catch { /* silencioso */ }
}

async function fetchNotifications() {
  loading.value = true
  try {
    const res = await api.get('/notifications')
    notifications.value = res.data
    unreadCount.value   = res.data.filter(n => !n.is_read).length
  } catch { /* silencioso */ } finally {
    loading.value = false
  }
}

// ── Toggle ────────────────────────────────────
function toggle() {
  open.value = !open.value
  if (open.value) fetchNotifications()
}

// ── Mark as read ──────────────────────────────
async function markRead(notification) {
  if (notification.is_read) return
  try {
    await api.post(`/notifications/${notification.id}/read`)
    notification.is_read = true
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch { /* silencioso */ }
}

async function markAllRead() {
  try {
    await api.post('/notifications/read-all')
    notifications.value.forEach(n => { n.is_read = true })
    unreadCount.value = 0
  } catch { /* silencioso */ }
}

// ── Close on outside click ────────────────────
function onClickOutside(e) {
  const el = document.querySelector('.notification-wrapper')
  if (el && !el.contains(e.target)) open.value = false
}

// ── Format ────────────────────────────────────
function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const min  = Math.floor(diff / 60000)
  if (min < 60) return `${min}min atrás`
  const h = Math.floor(min / 60)
  if (h < 24) return `${h}h atrás`
  return `${Math.floor(h / 24)}d atrás`
}

onMounted(() => {
  fetchCount()
  document.addEventListener('click', onClickOutside)
  // Polling leve a cada 5 minutos
  const interval = setInterval(fetchCount, 5 * 60 * 1000)
  onUnmounted(() => {
    clearInterval(interval)
    document.removeEventListener('click', onClickOutside)
  })
})
</script>

<template>
  <div class="notification-wrapper">
    <!-- Bell button -->
    <button class="bell-btn" @click.stop="toggle" :aria-label="`Notificações (${unreadCount} não lidas)`">
      <Bell :size="20" />
      <span v-if="unreadCount > 0" class="badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
    </button>

    <!-- Dropdown panel -->
    <Transition name="dropdown">
      <div
        v-if="open"
        class="notification-panel"
        :style="{
          ...(props.align === 'left' ? { left: 0 } : { right: 0 }),
          ...(props.direction === 'up'
            ? { bottom: 'calc(100% + 8px)', top: 'auto' }
            : { top: 'calc(100% + 8px)', bottom: 'auto' }),
        }"
        @click.stop
      >
        <!-- Header -->
        <div class="panel-header">
          <span class="panel-title">Notificações</span>
          <div class="panel-actions">
            <button
              v-if="unreadCount > 0"
              class="btn-read-all"
              @click="markAllRead"
              title="Marcar todas como lidas"
            >
              <CheckCheck :size="16" />
            </button>
            <button class="btn-close" @click="open = false">
              <X :size="16" />
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="panel-state">Carregando...</div>

        <!-- Empty -->
        <div v-else-if="!notifications.length" class="panel-state">
          <Bell :size="28" class="empty-icon" />
          <p>Nenhuma notificação</p>
        </div>

        <!-- List -->
        <ul v-else class="notif-list">
          <li
            v-for="n in notifications"
            :key="n.id"
            :class="['notif-item', { unread: !n.is_read }]"
            @click="markRead(n)"
          >
            <div :class="['notif-icon', `notif-icon--${n.type}`]">
              <component :is="iconMap[n.type] ?? Bell" :size="15" />
            </div>
            <div class="notif-body">
              <p class="notif-title">{{ n.title }}</p>
              <p class="notif-message">{{ n.message }}</p>
              <span class="notif-time">{{ timeAgo(n.created_at) }}</span>
            </div>
            <span v-if="!n.is_read" class="unread-dot" aria-label="Não lida" />
          </li>
        </ul>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.notification-wrapper {
  position: relative;
}

/* Bell */
.bell-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.bell-btn:hover {
  background: rgba(46, 204, 113, 0.08);
  color: var(--color-primary);
}

.badge {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  background: var(--color-danger);
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 99px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

/* Panel */
.notification-panel {
  position: absolute;
  width: 340px;
  max-height: 440px;
  background: var(--color-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  z-index: 200;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-md) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

.panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
}

.btn-read-all,
.btn-close {
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

.btn-read-all:hover { background: rgba(46,204,113,0.1); color: var(--color-primary); }
.btn-close:hover    { background: rgba(235,87,87,0.1);  color: var(--color-danger); }

/* State */
.panel-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-xl);
  color: var(--color-text-secondary);
  font-size: 14px;
}

.empty-icon { opacity: 0.25; }
.panel-state p { margin: 0; }

/* List */
.notif-list {
  list-style: none;
  padding: 0;
  margin: 0;
  overflow-y: auto;
  flex: 1;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
  padding: var(--spacing-md) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
  cursor: pointer;
  transition: background 0.1s;
  position: relative;
}

.notif-item:last-child { border-bottom: none; }
.notif-item:hover      { background: rgba(0,0,0,0.02); }
.notif-item.unread     { background: rgba(46,204,113,0.04); }

/* Icon */
.notif-icon {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
}

.notif-icon--recurring_due  { background: rgba(47,128,237,0.12); color: var(--color-info); }
.notif-icon--goal_reminder  { background: rgba(242,201,76,0.15); color: #b8860b; }
.notif-icon--no_activity    { background: rgba(235,87,87,0.1);   color: var(--color-danger); }

/* Body */
.notif-body { flex: 1; min-width: 0; }

.notif-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 2px;
}

.notif-message {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 0 0 4px;
  line-height: 1.4;
}

.notif-time {
  font-size: 11px;
  color: var(--color-text-secondary);
  opacity: 0.7;
}

/* Unread dot */
.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-primary);
  flex-shrink: 0;
  margin-top: 6px;
}

/* Transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (max-width: 768px) {
  .notification-panel {
    position: fixed;
    top: 68px;
    left: 12px !important;
    right: 12px !important;
    width: auto;
    max-height: 70vh;
  }
}
</style>
