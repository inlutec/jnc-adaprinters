import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type PrinterItem = {
  id: number;
  name: string;
  ip_address: string;
  status: string;
  brand?: string;
  model?: string;
  site?: { name: string };
  department?: { name: string };
  province_id?: number;
  site_id?: number;
  department_id?: number;
  photo_path?: string;
  last_sync_at?: string;
  last_seen_at?: string;
  is_color?: boolean;
  is_online?: boolean;
  custom_field_values?: Record<string, any>;
  latest_snapshot?: {
    consumables?: Array<{
      level?: number;
      nivel_porcentaje?: number;
      color?: string;
      slot?: string;
      label?: string;
      name?: string;
    }>;
  };
  snmp_data?: Record<string, any>;
};

type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const usePrintersStore = defineStore('printers', {
  state: () => ({
    loading: false,
    printers: [] as PrinterItem[],
    items: [] as PrinterItem[],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
    } as PaginationMeta,
    filters: {
      search: '',
      status: '',
      perPage: 10,
    },
  }),
  actions: {
    async fetch(page = 1) {
      this.loading = true;
      try {
        const { data } = await api.get('/printers', {
          params: {
            page,
            per_page: this.filters.perPage,
            search: this.filters.search || undefined,
            status: this.filters.status || undefined,
          },
        });

        const printersList = data.data || data;
        // Asegurar que custom_field_values esté disponible en cada impresora
        if (Array.isArray(printersList)) {
          printersList.forEach((printer: any) => {
            // Si custom_field_values no existe o no es un objeto, inicializarlo
            if (!printer.custom_field_values || typeof printer.custom_field_values !== 'object' || Array.isArray(printer.custom_field_values)) {
              printer.custom_field_values = {};
            }
            // Log para debugging (solo en desarrollo)
            if (printer.id === 1) {
              console.log('Printer 1 loaded:', {
                id: printer.id,
                name: printer.name,
                custom_field_values: printer.custom_field_values,
                type: typeof printer.custom_field_values,
                isArray: Array.isArray(printer.custom_field_values),
                keys: Object.keys(printer.custom_field_values || {})
              });
            }
          });
        }
        this.printers = printersList;
        this.items = printersList;
        this.meta = {
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          per_page: data.per_page || 10,
          total: data.total || data.data?.length || 0,
        };
      } finally {
        this.loading = false;
      }
    },
    setFilter(key: keyof typeof this.filters, value: string | number) {
      // @ts-expect-error dynamic assignment
      this.filters[key] = value;
    },
  },
});

