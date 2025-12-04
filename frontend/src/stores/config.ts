import { defineStore } from 'pinia';
import { api } from '@/services/httpClient';

type Logo = {
  id: number;
  type: string;
  path: string;
  mime_type: string;
  size?: number;
  width?: number;
  height?: number;
  is_active: boolean;
};

type CustomField = {
  id: number;
  entity_type: string;
  name: string;
  slug: string;
  type: string;
  options?: string[];
  is_required: boolean;
  order: number;
  help_text?: string;
  is_active: boolean;
};

type SnmpOid = {
  id: number;
  oid: string;
  name: string;
  description?: string;
  category: string;
  data_type: string;
  unit?: string;
  color?: string;
  is_system: boolean;
};

type NotificationConfig = {
  id: number;
  type: string;
  name: string;
  smtp_host?: string;
  smtp_port?: number;
  smtp_username?: string;
  smtp_encryption?: string;
  from_address: string;
  from_name?: string;
  alert_thresholds?: any;
  recipients?: string[];
  is_active: boolean;
};

type Province = {
  id: number;
  name: string;
  code?: string;
  sites_count?: number;
};

type Site = {
  id: number;
  province_id: number;
  name: string;
  code?: string;
  address?: string;
  city?: string;
  province?: Province;
};

type Department = {
  id: number;
  site_id: number;
  name: string;
  code?: string;
  floor?: string;
  is_warehouse: boolean;
  is_active: boolean;
  site?: Site;
};

type User = {
  id: number;
  name: string;
  email: string;
  role?: string;
  page_permissions?: string[];
  location_permissions?: {
    provinces?: number[];
    sites?: number[];
    departments?: number[];
  };
  read_write_permissions?: string[];
};

export const useConfigStore = defineStore('config', {
  state: () => ({
    logos: [] as Logo[],
    customFields: [] as CustomField[],
    snmpOids: [] as SnmpOid[],
    notificationConfigs: [] as NotificationConfig[],
    provinces: [] as Province[],
    sites: [] as Site[],
    departments: [] as Department[],
    users: [] as User[],
    snmpSyncConfig: {
      auto_sync_enabled: false,
      auto_sync_frequency: 15, // minutos por defecto
    } as { auto_sync_enabled: boolean; auto_sync_frequency: number },
    loading: false,
  }),
  actions: {
    // Logos
    async fetchLogos() {
      const { data } = await api.get('/config/logos');
      this.logos = data;
    },
    async uploadLogo(formData: FormData) {
      const { data } = await api.post('/config/logos', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchLogos();
      return data;
    },
    async updateLogo(id: number, updates: Partial<Logo>) {
      const { data } = await api.put(`/config/logos/${id}`, updates);
      await this.fetchLogos();
      return data;
    },
    async deleteLogo(id: number) {
      await api.delete(`/config/logos/${id}`);
      await this.fetchLogos();
    },
    getActiveLogo(type: string = 'web'): Logo | null {
      return this.logos.find((logo) => logo.type === type && logo.is_active) || null;
    },

    // Custom Fields
    async fetchCustomFields(entityType?: string) {
      const params = entityType ? { entity_type: entityType } : {};
      const { data } = await api.get('/config/custom-fields', { params });
      this.customFields = data;
    },
    async createCustomField(field: Partial<CustomField>) {
      const { data } = await api.post('/config/custom-fields', field);
      await this.fetchCustomFields();
      return data;
    },
    async updateCustomField(id: number, updates: Partial<CustomField>) {
      const { data } = await api.put(`/config/custom-fields/${id}`, updates);
      await this.fetchCustomFields();
      return data;
    },
    async deleteCustomField(id: number) {
      await api.delete(`/config/custom-fields/${id}`);
      await this.fetchCustomFields();
    },

    // SNMP OIDs
    async fetchSnmpOids(category?: string) {
      const params = category ? { category } : {};
      const { data } = await api.get('/config/snmp-oids', { params });
      this.snmpOids = data;
    },
    async createSnmpOid(oid: Partial<SnmpOid>) {
      const { data } = await api.post('/config/snmp-oids', oid);
      await this.fetchSnmpOids();
      return data;
    },
    async updateSnmpOid(id: number, updates: Partial<SnmpOid>) {
      const { data } = await api.put(`/config/snmp-oids/${id}`, updates);
      await this.fetchSnmpOids();
      return data;
    },
    async deleteSnmpOid(id: number) {
      await api.delete(`/config/snmp-oids/${id}`);
      await this.fetchSnmpOids();
    },

    // Notification Configs
    async fetchNotificationConfigs() {
      const { data } = await api.get('/config/notification-configs');
      this.notificationConfigs = data;
    },
    async createNotificationConfig(config: Partial<NotificationConfig>) {
      const { data } = await api.post('/config/notification-configs', config);
      await this.fetchNotificationConfigs();
      return data;
    },
    async updateNotificationConfig(id: number, updates: Partial<NotificationConfig>) {
      const { data } = await api.put(`/config/notification-configs/${id}`, updates);
      await this.fetchNotificationConfigs();
      return data;
    },
    async testNotificationConfig(id: number) {
      const { data } = await api.post(`/config/notification-configs/${id}/test`);
      return data;
    },

    // Provinces
    async fetchProvinces() {
      const { data } = await api.get('/config/provinces');
      this.provinces = data;
    },
    async createProvince(province: Partial<Province>) {
      const { data } = await api.post('/config/provinces', province);
      await this.fetchProvinces();
      return data;
    },
    async updateProvince(id: number, updates: Partial<Province>) {
      const { data } = await api.put(`/config/provinces/${id}`, updates);
      await this.fetchProvinces();
      return data;
    },
    async deleteProvince(id: number) {
      await api.delete(`/config/provinces/${id}`);
      await this.fetchProvinces();
    },

    // Sites
    async fetchSites(provinceId?: number) {
      const params = provinceId ? { province_id: provinceId } : {};
      const { data } = await api.get('/config/sites', { params });
      this.sites = data;
    },
    async createSite(site: Partial<Site>) {
      const { data } = await api.post('/config/sites', site);
      await this.fetchSites();
      return data;
    },
    async updateSite(id: number, updates: Partial<Site>) {
      const { data } = await api.put(`/config/sites/${id}`, updates);
      await this.fetchSites();
      return data;
    },
    async deleteSite(id: number) {
      await api.delete(`/config/sites/${id}`);
      await this.fetchSites();
    },

    // Departments
    async fetchDepartments(siteId?: number) {
      const params = siteId ? { site_id: siteId } : {};
      const { data } = await api.get('/config/departments', { params });
      this.departments = data;
    },
    async createDepartment(department: Partial<Department>) {
      const { data } = await api.post('/config/departments', department);
      await this.fetchDepartments();
      return data;
    },
    async updateDepartment(id: number, updates: Partial<Department>) {
      const { data } = await api.put(`/config/departments/${id}`, updates);
      await this.fetchDepartments();
      return data;
    },
    async deleteDepartment(id: number) {
      await api.delete(`/config/departments/${id}`);
      await this.fetchDepartments();
    },

    // Users
    async fetchUsers() {
      const { data } = await api.get('/config/users');
      this.users = data.data || data;
    },
    async createUser(user: Partial<User> & { password: string; password_confirmation: string }) {
      const { data } = await api.post('/config/users', user);
      await this.fetchUsers();
      return data;
    },
    async updateUser(id: number, updates: Partial<User>) {
      const { data } = await api.put(`/config/users/${id}`, updates);
      await this.fetchUsers();
      return data;
    },
    async deleteUser(id: number) {
      await api.delete(`/config/users/${id}`);
      await this.fetchUsers();
    },

    // SNMP Sync Config
    async fetchSnmpSyncConfig() {
      const { data } = await api.get('/config/snmp-sync/config');
      this.snmpSyncConfig = {
        auto_sync_enabled: data.auto_sync_enabled || false,
        auto_sync_frequency: data.auto_sync_frequency || 15,
      };
      return this.snmpSyncConfig;
    },
  },
});

