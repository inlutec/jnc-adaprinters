<script setup lang="ts">
defineProps<{
  label?: string;
}>();
</script>

<template>
  <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center">
    <p class="text-xs uppercase tracking-[0.3em] text-slate-400 font-semibold mb-3">
      En desarrollo
    </p>
    <h2 class="text-2xl font-black text-slate-900">
      {{ label ?? 'Este módulo estará disponible en breve.' }}
    </h2>
    <p class="mt-4 text-sm text-slate-500 max-w-xl mx-auto">
      Estamos trasladando los flujos completos de Inventario, Alertas y Registro de impresiones
      al nuevo frontend. Mientras tanto puedes consultar los datos desde el backend o el panel
      actual.
    </p>
  </div>
</template>

