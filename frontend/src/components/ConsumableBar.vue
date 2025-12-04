<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  level: number;
  color: string;
  label: string;
  size?: 'small' | 'medium' | 'large';
}>();

const consumableColor = computed(() => {
  // Si el color es un código hexadecimal, usarlo directamente
  if (props.color.startsWith('#')) {
    return props.color;
  }
  
  const colors: Record<string, string> = {
    black: '#000000',
    cyan: '#00FFFF',
    magenta: '#FF00FF',
    yellow: '#FFFF00',
    K: '#000000',
    C: '#00FFFF',
    M: '#FF00FF',
    Y: '#FFFF00',
    // Consumibles adicionales
    drum: '#8B7355',
    waste: '#964B00',
    fuser: '#FF6B35',
    transfer: '#4A90E2',
    maintenance: '#95A5A6',
    paper: '#ECF0F1',
  };
  const colorKey = props.color.toLowerCase();
  return colors[colorKey] || colors[props.color] || '#666666';
});

const levelColor = computed(() => {
  if (props.level > 50) return 'text-emerald-600';
  if (props.level > 20) return 'text-amber-600';
  return 'text-rose-600';
});

const height = computed(() => {
  return props.size === 'small' ? 'h-8' : props.size === 'large' ? 'h-24' : 'h-16';
});
</script>

<template>
  <div class="flex flex-col items-center gap-0.5 min-w-[2rem] max-w-[2.75rem] flex-shrink-0">
    <div class="text-[9px] font-semibold whitespace-nowrap truncate max-w-full leading-none" :style="{ color: consumableColor }" :title="label">{{ label }}</div>
    <div
      class="relative rounded-full border-2 overflow-hidden transition-all duration-300 bg-slate-100"
      :class="[height, props.size === 'small' ? 'w-6' : props.size === 'large' ? 'w-12' : 'w-10']"
      :style="{ borderColor: consumableColor }"
    >
      <div
        class="absolute bottom-0 left-0 right-0 transition-all duration-700 ease-out relative overflow-hidden"
        :style="{
          height: `${Math.max(0, Math.min(100, level))}%`,
          backgroundColor: consumableColor,
          boxShadow: `0 0 15px ${consumableColor}60, inset 0 0 10px ${consumableColor}40`,
        }"
      >
        <div
          class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent animate-pulse-slow"
        ></div>
        <div
          class="absolute top-0 left-0 right-0 h-1/3 bg-gradient-to-b from-white/40 to-transparent"
        ></div>
        <div
          class="absolute inset-0 bg-gradient-to-b from-white/30 via-transparent to-transparent animate-shimmer-vertical"
          :style="{ animationDuration: '3s' }"
        ></div>
      </div>
      <div
        v-if="level > 0"
        class="absolute top-0 left-1/2 transform -translate-x-1/2 w-1 h-1 rounded-full"
        :style="{
          backgroundColor: consumableColor,
          boxShadow: `0 0 8px ${consumableColor}`,
          top: `${100 - Math.max(0, Math.min(100, level))}%`,
        }"
      ></div>
    </div>
    <div :class="['text-[9px] font-bold leading-none', levelColor]">{{ level }}%</div>
  </div>
</template>

<style scoped>
@keyframes pulse-slow {
  0%, 100% {
    opacity: 0.3;
  }
  50% {
    opacity: 0.6;
  }
}

@keyframes shimmer-vertical {
  0% {
    transform: translateY(-100%);
  }
  100% {
    transform: translateY(100%);
  }
}

.animate-pulse-slow {
  animation: pulse-slow 3s ease-in-out infinite;
}

.animate-shimmer-vertical {
  animation: shimmer-vertical 3s ease-in-out infinite;
}
</style>

