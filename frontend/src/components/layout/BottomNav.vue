<script setup>
import { useRouter, useRoute } from 'vue-router'
import { LayoutDashboard, ArrowLeftRight, Plus, Target, User } from 'lucide-vue-next'
import { useUiStore } from '../../stores/ui.js'

const emit   = defineEmits(['logout'])
const router = useRouter()
const route  = useRoute()
const ui     = useUiStore()
</script>

<template>
  <nav class="bottom-nav">
    <button
      :class="['nav-item', { active: route.name === 'dashboard' }]"
      @click="router.push('/dashboard')"
    >
      <LayoutDashboard :size="20" />
      <span>Dashboard</span>
    </button>

    <button
      :class="['nav-item', { active: route.name === 'history' }]"
      @click="router.push('/history')"
    >
      <ArrowLeftRight :size="20" />
      <span>Transações</span>
    </button>

    <button class="nav-item fab-btn" @click="ui.openTransactionModal()" aria-label="Nova transação">
      <Plus :size="24" />
    </button>

    <button
      :class="['nav-item', { active: route.name === 'insights' }]"
      @click="router.push('/insights')"
    >
      <Target :size="20" />
      <span>Saúde</span>
    </button>

    <button :class="['nav-item']">
      <User :size="20" />
      <span>Perfil</span>
    </button>
  </nav>
</template>

<style scoped>
.bottom-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: var(--color-card);
  border-top: 1px solid var(--color-border);
  align-items: center;
  justify-content: space-around;
  z-index: 100;
  padding: 0 var(--spacing-xs);
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: none;
  background: none;
  color: var(--color-text-secondary);
  font-size: 10px;
  font-weight: 500;
  padding: var(--spacing-xs);
  flex: 1;
  cursor: pointer;
  transition: color 0.15s;
}

.nav-item.active {
  color: var(--color-primary);
}

.fab-btn {
  width: 52px;
  height: 52px;
  background: var(--color-primary);
  color: white;
  border-radius: 50%;
  border: 3px solid var(--color-bg);
  box-shadow: 0 4px 12px rgba(46, 204, 113, 0.4);
  margin-bottom: 16px;
  flex: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.fab-btn:hover {
  opacity: 0.9;
}

@media (max-width: 768px) {
  .bottom-nav {
    display: flex;
  }
}
</style>
