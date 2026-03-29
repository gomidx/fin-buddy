<script setup>
defineProps({
  percentage: { type: Number, default: 0 },
  color: { type: String, default: '#2ECC71' },
})
</script>

<template>
  <div class="progress-track">
    <div
      class="progress-fill"
      :style="{ width: `${Math.min(100, Math.max(0, percentage))}%`, background: color }"
    ></div>
  </div>
</template>

<style scoped>
.progress-track {
  width: 100%;
  height: 10px;
  background: #E5E7EB;
  border-radius: 50px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 50px;
  transition: width 0.5s ease;
}
</style>
