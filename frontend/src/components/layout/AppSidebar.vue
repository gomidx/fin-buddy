<script setup>
import { useRouter, useRoute } from 'vue-router'
import { LayoutDashboard, ArrowLeftRight, Shield, TrendingUp, Target, User, LogOut, Heart, Plus } from 'lucide-vue-next'
import NotificationBell from '../ui/NotificationBell.vue'
import { useUiStore } from '../../stores/ui.js'

const emit = defineEmits(['logout'])
const router = useRouter()
const route  = useRoute()
const ui     = useUiStore()
</script>

<template>
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-dot"></div>
      <span class="brand-name">Fin Buddy</span>
    </div>

    <!-- Nova Transação -->
    <div class="new-tx-wrapper">
      <button class="btn-new-tx" @click="ui.openTransactionModal()">
        <Plus :size="16" />
        Nova transação
      </button>
    </div>

    <nav class="nav">
      <button
        :class="['nav-link', { active: route.name === 'dashboard' }]"
        @click="router.push('/dashboard')"
      >
        <LayoutDashboard :size="18" />
        Dashboard
      </button>

      <button
        :class="['nav-link', { active: route.name === 'history' }]"
        @click="router.push('/history')"
      >
        <ArrowLeftRight :size="18" />
        Transações
      </button>

      <button
        :class="['nav-link', { active: route.name === 'insights' }]"
        @click="router.push('/insights')"
      >
        <Heart :size="18" />
        Saúde Financeira
      </button>

      <button
        :class="['nav-link', { active: route.name === 'emergency-fund' }]"
        @click="router.push('/emergency-fund')"
      >
        <Shield :size="18" />
        Reserva de Emergência
      </button>

      <button
        :class="['nav-link', { active: route.name === 'investments' }]"
        @click="router.push('/investments')"
      >
        <TrendingUp :size="18" />
        Investimentos
      </button>

      <button
        :class="['nav-link', { active: route.name === 'goals' }]"
        @click="router.push('/goals')"
      >
        <Target :size="18" />
        Metas
      </button>

      <button
        :class="['nav-link', { active: route.name === 'profile' }]"
        @click="router.push('/profile')"
      >
        <User :size="18" />
        Perfil
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-notif">
        <NotificationBell align="left" direction="up" />
      </div>
      <button class="nav-link logout-btn" @click="emit('logout')">
        <LogOut :size="18" />
        Sair
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  width: var(--sidebar-width);
  background: var(--color-card);
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  padding: var(--spacing-lg) 0;
  z-index: 100;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 var(--spacing-lg) var(--spacing-lg);
  border-bottom: 1px solid var(--color-border);
  margin-bottom: var(--spacing-md);
}

.brand-dot {
  width: 32px;
  height: 32px;
  background: var(--color-primary);
  border-radius: 50%;
}

.brand-name {
  font-size: 18px;
  font-weight: 700;
}

.new-tx-wrapper {
  padding: 0 var(--spacing-md) var(--spacing-md);
}

.btn-new-tx {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 10px var(--spacing-md);
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}

.btn-new-tx:hover {
  opacity: 0.88;
}

.nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0 var(--spacing-sm);
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px var(--spacing-md);
  border-radius: var(--radius-md);
  color: var(--color-text-secondary);
  font-size: 14px;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  border: none;
  background: none;
  width: 100%;
  text-align: left;
}

.nav-link:hover {
  background: rgba(46, 204, 113, 0.08);
  color: var(--color-primary);
}

.nav-link.active {
  background: var(--color-primary);
  color: white;
}

.sidebar-footer {
  padding: var(--spacing-sm) var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.sidebar-notif {
  display: flex;
  justify-content: flex-start;
  padding: 0 var(--spacing-xs);
}

.logout-btn:hover {
  background: rgba(235, 87, 87, 0.08);
  color: var(--color-danger);
}

@media (max-width: 768px) {
  .sidebar {
    display: none;
  }
}
</style>
