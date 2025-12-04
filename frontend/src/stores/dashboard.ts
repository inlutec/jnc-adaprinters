import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type TrendPoint = {
  date: string;
  value: number;
  color: number;
  bw: number;
};

type PrinterHighlight = {
  id: number;
  name: string;
  site?: string;
  status: string;
  weekly_prints: number;
  last_sync_at?: string;
};

type AlertHighlight = {
  id: number;
  title: string;
  severity: string;
  status: string;
  printer?: string;
  created_at: string;
};

type StockHighlight = {
  id: number;
  consumable?: string;
  site?: string;
  quantity: number;
  minimum: number;
};

type SiteHealthCard = {
  id: number;
  name: string;
  province?: string;
  printers: number;
  online: number;
};

type SummaryBlock = {
  printers: { total: number; online: number; color: number };
  alerts: { open: number; critical: number };
  inventory: { low_stock: number; total_items: number };
  printing: { today: number; week: number };
};

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    loading: false,
    summary: null as SummaryBlock | null,
    statusBreakdown: {} as Record<string, number>,
    printTrend: [] as TrendPoint[],
    topPrinters: [] as PrinterHighlight[],
    latestAlerts: [] as AlertHighlight[],
    lowStock: [] as StockHighlight[],
    siteHealth: [] as SiteHealthCard[],
    lastUpdated: null as string | null,
  }),
  actions: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await api.get('/dashboard');
        const payload = data.data;
        this.summary = payload.summary;
        this.statusBreakdown = payload.status_breakdown ?? {};
        this.printTrend = payload.print_trend ?? [];
        this.topPrinters = payload.top_printers ?? [];
        this.latestAlerts = payload.latest_alerts ?? [];
        this.lowStock = payload.low_stock ?? [];
        this.siteHealth = payload.site_health ?? [];
        this.lastUpdated = new Date().toISOString();
      } finally {
        this.loading = false;
      }
    },
  },
});

