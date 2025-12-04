import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type ConsumableReference = {
  id: number;
  sku: string;
  name: string;
  brand?: string;
  type?: string;
  custom_type?: string;
  color?: string;
  compatible_models?: string[];
  description?: string;
  is_active: boolean;
};

export const useReferencesStore = defineStore('references', {
  state: () => ({
    references: [] as ConsumableReference[],
    loading: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),
  actions: {
    async fetchReferences(params: Record<string, any> = {}) {
      this.loading = true;
      try {
        const { data } = await api.get('/references', { params });
        this.references = data.data || data;
        if (data.meta) {
          this.pagination = data.meta;
        }
      } finally {
        this.loading = false;
      }
    },
    async createReference(reference: Partial<ConsumableReference>) {
      const { data } = await api.post('/references', reference);
      await this.fetchReferences();
      return data;
    },
    async updateReference(id: number, updates: Partial<ConsumableReference>) {
      const { data } = await api.put(`/references/${id}`, updates);
      await this.fetchReferences();
      return data;
    },
    async deleteReference(id: number) {
      await api.delete(`/references/${id}`);
      await this.fetchReferences();
    },
    async fetchMovements(id: number) {
      const { data } = await api.get(`/references/${id}/movements`);
      return { data };
    },
  },
});

