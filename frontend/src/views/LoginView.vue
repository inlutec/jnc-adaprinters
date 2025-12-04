<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const app = useAppStore();
const form = reactive({
  email: 'admin@jnc-adaprinters.local',
  password: 'admin123',
});
const errorMessage = ref('');

const submit = async () => {
  errorMessage.value = '';
  try {
    await auth.login(form);
    const redirect = (route.query.redirect as string) ?? '/';
    router.push(redirect);
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message ?? 'Credenciales no válidas';
    app.notify(errorMessage.value, 'error');
  }
};
</script>

<template>
  <div class="min-h-screen bg-slate-900 text-white flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-3xl bg-white/95 p-8 text-slate-900 shadow-2xl">
      <p class="text-xs uppercase tracking-[0.3em] text-ada-primary font-semibold">
        Agencia Digital de Andalucía
      </p>
      <h1 class="mt-2 text-3xl font-black">JNC · AdaPrinters</h1>
      <p class="text-sm text-slate-500">Autentícate para acceder al panel de control.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="text-xs font-semibold uppercase text-slate-500">Correo</label>
          <input
            v-model="form.email"
            type="email"
            required
            class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-ada-primary focus:outline-none"
          />
        </div>
        <div>
          <label class="text-xs font-semibold uppercase text-slate-500">Contraseña</label>
          <input
            v-model="form.password"
            type="password"
            required
            class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-ada-primary focus:outline-none"
          />
        </div>
        <p v-if="errorMessage" class="text-sm text-rose-500">{{ errorMessage }}</p>
        <button
          type="submit"
          class="w-full rounded-2xl bg-ada-primary px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-ada-primary/30 transition hover:bg-ada-primary/90"
          :disabled="auth.loading"
        >
          {{ auth.loading ? 'Validando…' : 'Entrar' }}
        </button>
      </form>
    </div>
  </div>
</template>

