import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type Order = {
  id: number;
  uuid: string;
  printer_id?: number;
  consumable_id?: number;
  status: string;
  requested_at?: string;
  sent_at?: string;
  received_at?: string;
  email_sent_at?: string;
  email_to?: string;
  supplier_name?: string;
  notes?: string;
  printer?: any;
  consumable?: any;
  items?: OrderItem[];
  comments?: Array<{ id: number; comment: string; created_at: string; creator?: { name: string } }>;
};

type OrderItem = {
  id: number;
  order_id: number;
  consumable_id?: number;
  consumable_reference_id?: number;
  description: string;
  quantity: number;
  notes?: string;
};

type OrderEntry = {
  id: number;
  order_id?: number;
  site_id?: number;
  department_id?: number;
  received_at: string;
  delivery_note_path?: string;
  delivery_note_mime_type?: string;
  notes?: string;
  received_by?: number;
  order?: Order;
  site?: { id: number; name: string };
  department?: { id: number; name: string };
  items?: Array<{ id: number; consumable_reference_id: number; quantity: number; consumableReference?: any }>;
};

export const useOrdersStore = defineStore('orders', {
  state: () => ({
    orders: [] as Order[],
    entries: [] as OrderEntry[],
    loading: false,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  }),
  actions: {
    async fetchOrders(params: Record<string, any> = {}) {
      this.loading = true;
      try {
        const { data } = await api.get('/orders', { params });
        this.orders = data.data || data;
        if (data.meta) {
          this.pagination = data.meta;
        }
      } finally {
        this.loading = false;
      }
    },
    async fetchOrder(id: number) {
      const { data } = await api.get(`/orders/${id}`);
      return data;
    },
    async createOrder(order: Partial<Order>) {
      const { data } = await api.post('/orders', order);
      await this.fetchOrders();
      return data;
    },
    async updateOrder(id: number, updates: Partial<Order>) {
      const { data } = await api.put(`/orders/${id}`, updates);
      await this.fetchOrders();
      return data;
    },

    // Order Entries
    async fetchOrderEntries(orderId?: number) {
      const params = orderId ? { order_id: orderId } : {};
      const { data } = await api.get('/order-entries', { params });
      this.entries = data.data || data;
    },
    async createOrderEntry(formData: FormData) {
      const { data } = await api.post('/order-entries', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchOrderEntries();
      await this.fetchOrders({ status: 'pending' }); // Actualizar lista de pedidos pendientes
      return data;
    },
    async fetchOrderEntry(id: number) {
      const { data } = await api.get(`/order-entries/${id}`);
      return data;
    },
    async updateOrderEntry(id: number, formData: FormData) {
      const { data } = await api.put(`/order-entries/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchOrderEntries();
      return data;
    },
    async updateOrderEntryJson(id: number, payload: any) {
      const { data } = await api.put(`/order-entries/${id}`, payload);
      await this.fetchOrderEntries();
      return data;
    },
    async deleteOrderEntry(id: number) {
      await api.delete(`/order-entries/${id}`);
      await this.fetchOrderEntries();
    },
    async addComment(orderId: number, comment: string) {
      const { data } = await api.post(`/orders/${orderId}/comments`, { comment });
      return data;
    },
    async getComments(orderId: number) {
      const { data } = await api.get(`/orders/${orderId}/comments`);
      return data;
    },
  },
});

