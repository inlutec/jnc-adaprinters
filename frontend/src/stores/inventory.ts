import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type StockItem = {
  id: number;
  quantity: number;
  minimum_quantity: number;
  consumable?: { name: string; sku?: string; color?: string };
  site?: { name: string };
  department?: { name: string };
};

export const useInventoryStore = defineStore('inventory', {
  state: () => ({
    loading: false,
    stocks: [] as StockItem[],
    lowOnly: false,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
  }),
  actions: {
    async fetch(page = 1) {
      this.loading = true;
      try {
        const { data } = await api.get('/stocks', {
          params: {
            page,
            per_page: this.meta.per_page,
            low_only: this.lowOnly ? 1 : undefined,
          },
        });

        this.stocks = data.data;
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
    toggleLowOnly() {
      this.lowOnly = !this.lowOnly;
      this.fetch();
    },
    async regularize(stockId: number, quantity: number, justification?: string) {
      await api.post(`/stocks/${stockId}/regularize`, { quantity, justification });
      await this.fetch(this.meta.current_page);
    },
    async addMovement(stockId: number, movementType: 'in' | 'out' | 'adjustment', quantity: number, justification?: string) {
      await api.post(`/stocks/${stockId}/movements`, {
        movement_type: movementType,
        quantity,
        justification,
      });
      await this.fetch(this.meta.current_page);
    },
    async updateMinimumQuantity(stockId: number, minimumQuantity: number) {
      await api.put(`/stocks/${stockId}/minimum-quantity`, { minimum_quantity: minimumQuantity });
      await this.fetch(this.meta.current_page);
    },
  },
});

