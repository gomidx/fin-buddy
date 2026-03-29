import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', () => {
  const transactionModalOpen = ref(false)
  // Incrementado após cada transação criada — views assistem para re-buscar dados
  const transactionVersion = ref(0)

  function openTransactionModal() {
    transactionModalOpen.value = true
  }

  function closeTransactionModal() {
    transactionModalOpen.value = false
  }

  function notifyTransactionCreated() {
    transactionVersion.value++
    transactionModalOpen.value = false
  }

  function notifyDataChanged() {
    transactionVersion.value++
  }

  return {
    transactionModalOpen,
    transactionVersion,
    openTransactionModal,
    closeTransactionModal,
    notifyTransactionCreated,
    notifyDataChanged,
  }
})
