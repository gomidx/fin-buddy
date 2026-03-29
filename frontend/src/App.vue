<script setup>
import TransactionModal from './components/transactions/TransactionModal.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
</script>

<template>
  <RouterView />
  <TransactionModal v-if="auth.isAuthenticated" />
</template>
