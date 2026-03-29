<script setup>
defineProps({
  variant: { type: String, default: 'secondary' },
  label: { type: String, required: true },
})
</script>

<template>
  <span :class="['badge', `badge-${variant}`]">{{ label }}</span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: 50px;
  font-size: 12px;
  font-weight: 600;
}

.badge-success { background: #D1FAE5; color: #065F46; }
.badge-warning { background: #FEF3C7; color: #92400E; }
.badge-danger { background: #FEE2E2; color: #991B1B; }
.badge-secondary { background: #F3F4F6; color: var(--color-text-secondary); }
</style>
