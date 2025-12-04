import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type PrintLog = {
  id: number;
  printer?: { name: string; ip_address?: string };
  end_counter: number;
  color_counter_total: number;
  bw_counter_total: number;
  total_prints: number;
  color_prints: number;
  bw_prints: number;
  started_at: string;
  ended_at: string;
  diff_total?: number | null;
  diff_color?: number | null;
  diff_bw?: number | null;
};

type TrendPoint = {
  date: string;
  value: number;
};

export const usePrintLogsStore = defineStore('printLogs', {
  state: () => ({
    loading: false,
    items: [] as PrintLog[],
    aggregates: {
      total_prints: 0,
      color_prints: 0,
      bw_prints: 0,
    },
    trend: [] as TrendPoint[],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
    filters: {
      type: 'all',
      printer_id: null as number | null,
      province_id: null as number | null,
      site_id: null as number | null,
      department_id: null as number | null,
      date_from: '',
      date_to: '',
      proveedor: null as string | null,
    },
  }),
  getters: {
    printers: () => [] as any[],
  },
  actions: {
    async fetch(page = 1) {
      this.loading = true;
      try {
        const params: any = {
          page,
          per_page: this.meta.per_page,
        };
        if (this.filters.type !== 'all') params.type = this.filters.type;
        if (this.filters.printer_id) params.printer_id = this.filters.printer_id;
        if (this.filters.province_id) params.province_id = this.filters.province_id;
        if (this.filters.site_id) params.site_id = this.filters.site_id;
        if (this.filters.department_id) params.department_id = this.filters.department_id;
        if (this.filters.date_from) params.date_from = this.filters.date_from;
        if (this.filters.date_to) params.date_to = this.filters.date_to;
        if (this.filters.proveedor) params.proveedor = this.filters.proveedor;

        const { data } = await api.get('/print-logs', { params });

        this.items = data.data;
        this.aggregates = data.aggregates;
        this.trend = data.trend;
        this.meta = data.meta;
      } finally {
        this.loading = false;
      }
    },
    async export() {
      const params: any = {};
      if (this.filters.type !== 'all') params.type = this.filters.type;
      if (this.filters.printer_id) params.printer_id = this.filters.printer_id;
      if (this.filters.province_id) params.province_id = this.filters.province_id;
      if (this.filters.site_id) params.site_id = this.filters.site_id;
      if (this.filters.department_id) params.department_id = this.filters.department_id;
      if (this.filters.date_from) params.date_from = this.filters.date_from;
      if (this.filters.date_to) params.date_to = this.filters.date_to;
      if (this.filters.proveedor) params.proveedor = this.filters.proveedor;

      const response = await api.get('/print-logs/export', { params, responseType: 'blob' });
      const blob = new Blob([response.data], { type: 'text/html' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `informe-impresiones-${new Date().toISOString().split('T')[0]}.html`;
      link.click();
      window.URL.revokeObjectURL(url);
    },
    setFilter(key: keyof typeof this.filters, value: any) {
      // @ts-expect-error dynamic assignment
      this.filters[key] = value;
    },
  },
});

