<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  level: number;
  color: string;
  label: string;
  slot?: string;
}>();

const consumableColor = computed(() => {
  const colors: Record<string, string> = {
    black: '#000000',
    cyan: '#00FFFF',
    magenta: '#FF00FF',
    yellow: '#FFFF00',
    K: '#000000',
    C: '#00FFFF',
    M: '#FF00FF',
    Y: '#FFFF00',
  };
  return colors[props.color] || '#666666';
});

const levelColor = computed(() => {
  if (props.level > 50) return 'text-emerald-600';
  if (props.level > 20) return 'text-amber-600';
  return 'text-rose-600';
});
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-center justify-between text-sm">
      <span class="font-semibold text-slate-700">{{ label }}</span>
      <span :class="['font-bold', levelColor]">{{ level }}%</span>
    </div>
    <div class="relative h-4 bg-slate-200 rounded-full overflow-hidden shadow-inner">
      <div
        :style="{
          width: `${Math.max(0, Math.min(100, level))}%`,
          backgroundColor: consumableColor,
          transition: 'width 0.8s cubic-bezier(0.4, 0, 0.2, 1)',
          boxShadow: `0 0 10px ${consumableColor}40`,
        }"
        class="h-full rounded-full relative"
      >
        <div
          class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-shimmer"
          :style="{ animationDuration: '2s' }"
        ></div>
      </div>
      <div
        v-if="level > 0"
        class="absolute inset-0 flex items-center justify-center pointer-events-none"
        :style="{ left: `${Math.max(0, Math.min(100, level))}%` }"
      >
        <div
          class="w-1 h-1 rounded-full"
          :style="{ backgroundColor: consumableColor, boxShadow: `0 0 4px ${consumableColor}` }"
        ></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.animate-shimmer {
  animation: shimmer 2s infinite;
}
</style>

