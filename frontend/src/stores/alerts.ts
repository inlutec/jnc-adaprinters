import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type AlertItem = {
  id: number;
  title: string;
  severity: string;
  status: string;
  type: string;
  message?: string;
  printer_id?: number;
  printer?: { id?: number; name: string; ip_address?: string; site?: { id?: number; name: string } };
  site?: { name: string };
  created_at: string;
};

export const useAlertsStore = defineStore('alerts', {
  state: () => ({
    loading: false,
    items: [] as AlertItem[],
    filters: {
      status: '',
      severity: '',
      perPage: 15,
    },
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),
  actions: {
    async fetch(page = 1) {
      this.loading = true;
      try {
        const { data } = await api.get('/alerts', {
          params: {
            page,
            per_page: this.filters.perPage,
            status: this.filters.status || undefined,
            severity: this.filters.severity || undefined,
          },
        });

        this.items = data.data || [];
        this.meta = {
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
        };
      } finally {
        this.loading = false;
      }
    },
    async updateStatus(alertId: number, status: string) {
      if (status === 'acknowledged') {
        await api.post(`/alerts/${alertId}/acknowledge`);
      } else if (status === 'resolved') {
        await api.post(`/alerts/${alertId}/resolve`);
      } else if (status === 'dismissed') {
        await api.post(`/alerts/${alertId}/dismiss`);
      } else {
        await api.put(`/alerts/${alertId}`, { status });
      }
      await this.fetch(this.meta.current_page);
    },
    async deleteAlert(alertId: number) {
      await api.delete(`/alerts/${alertId}`);
      await this.fetch(this.meta.current_page);
    },
  },
});

