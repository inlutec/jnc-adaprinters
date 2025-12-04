import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type Installation = {
  id: number;
  printer_id: number;
  stock_id: number;
  quantity: number;
  observations?: string;
  installed_by?: number;
  installed_at: string;
  printer?: { id: number; name: string; ip_address?: string };
  stock?: {
    id: number;
    consumable?: { name: string; sku?: string };
    site?: { name: string };
    department?: { name: string };
  };
  installer?: { name: string; email: string };
  photos?: Array<{ id: number; photo_path: string; mime_type?: string }>;
};

export const useInstallationsStore = defineStore('installations', {
  state: () => ({
    installations: [] as Installation[],
    loading: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),
  actions: {
    async fetch(params: Record<string, any> = {}) {
      this.loading = true;
      try {
        const { data } = await api.get('/installations', { params });
        this.installations = data.data || data;
        if (data.meta) {
          this.pagination = data.meta;
        }
      } finally {
        this.loading = false;
      }
    },
    async create(formData: FormData) {
      const { data } = await api.post('/installations', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetch();
      return data;
    },
    async fetchOne(id: number) {
      const { data } = await api.get(`/installations/${id}`);
      // Laravel puede devolver los datos directamente o dentro de data.data
      return data.data || data;
    },
    async update(id: number, formData: FormData) {
      // Laravel requiere _method=PUT para FormData
      formData.append('_method', 'PUT');
      const { data } = await api.post(`/installations/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetch();
      return data;
    },
    async updateJson(id: number, payload: Record<string, any>) {
      const { data } = await api.put(`/installations/${id}`, payload);
      await this.fetch();
      return data;
    },
    async delete(id: number) {
      await api.delete(`/installations/${id}`);
      await this.fetch();
    },
  },
});

