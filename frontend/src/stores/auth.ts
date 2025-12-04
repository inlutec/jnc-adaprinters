import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type User = {
  id: number;
  name: string;
  email: string;
  page_permissions?: string[];
  location_permissions?: {
    provinces?: number[];
    sites?: number[];
    departments?: number[];
  };
  read_write_permissions?: string[];
};

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: localStorage.getItem('adaprinters_token'),
    loading: false,
    initialized: false,
  }),
  getters: {
    isAuthenticated(state) {
      return Boolean(state.user);
    },
  },
  actions: {
    async initialize() {
      if (this.initialized) return;

      if (this.token) {
        try {
          const { data } = await api.get('/auth/me');
          this.user = data.user;
        } catch {
          this.clearSession();
        }
      }
      this.initialized = true;
    },
    async login(credentials: { email: string; password: string }) {
      this.loading = true;
      try {
        const { data } = await api.post('/auth/login', credentials);
        this.token = data.token;
        localStorage.setItem('adaprinters_token', data.token);
        api.defaults.headers.common.Authorization = `Bearer ${data.token}`;
        this.user = data.user;
      } finally {
        this.loading = false;
      }
    },
    async logout() {
      try {
        await api.post('/auth/logout');
      } catch {
        // ignore
      }
      this.clearSession();
    },
    clearSession() {
      this.user = null;
      this.token = null;
      localStorage.removeItem('adaprinters_token');
      delete api.defaults.headers.common.Authorization;
    },
  },
});

