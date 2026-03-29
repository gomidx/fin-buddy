<script setup>
import { LogOut } from 'lucide-vue-next'
import NotificationBell from '../ui/NotificationBell.vue'

const props = defineProps({
  user: { type: Object, default: null },
})

const emit = defineEmits(['logout'])

function getInitials(name) {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}
</script>

<template>
  <header class="app-header">
    <div class="brand">
      <div class="brand-dot"></div>
      <span class="brand-name">Fin Buddy</span>
    </div>
    <div class="header-right">
      <NotificationBell />
      <div v-if="user" class="avatar">{{ getInitials(user.name) }}</div>
      <button class="logout-btn" @click="emit('logout')" title="Sair">
        <LogOut :size="18" />
      </button>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--color-card);
  border-bottom: 1px solid var(--color-border);
  padding: 0 var(--spacing-md);
  align-items: center;
  justify-content: space-between;
  z-index: 100;
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
}

.brand-dot {
  width: 28px;
  height: 28px;
  background: var(--color-primary);
  border-radius: 50%;
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
}

.header-right {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.avatar {
  width: 32px;
  height: 32px;
  background: var(--color-primary);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

.logout-btn {
  border: none;
  background: none;
  color: var(--color-text-secondary);
  padding: 4px;
  display: flex;
  align-items: center;
}

.logout-btn:hover {
  color: var(--color-danger);
}

@media (max-width: 768px) {
  .app-header {
    display: flex;
  }
}
</style>
