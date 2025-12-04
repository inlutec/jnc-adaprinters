import { defineStore } from 'pinia';

export const useAppStore = defineStore('app', {
  state: () => ({
    loading: false,
    notifications: [] as Array<{ id: number; message: string; type: 'success' | 'error' | 'info' }>,
  }),
  actions: {
    setLoading(state: boolean) {
      this.loading = state;
    },
    notify(message: string, type: 'success' | 'error' | 'info' = 'info') {
      const id = Date.now();
      this.notifications.push({ id, message, type });
      setTimeout(() => {
        this.notifications = this.notifications.filter((n) => n.id !== id);
      }, 4000);
    },
  },
});

