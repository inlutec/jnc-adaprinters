import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function usePermissions() {
  const authStore = useAuthStore();

  const user = computed(() => authStore.user);

  /**
   * Verifica si el usuario tiene acceso a una página específica
   * Si no tiene page_permissions definido, tiene acceso a todas las páginas
   */
  const canAccessPage = (pageName: string): boolean => {
    if (!user.value) return false;
    if (!user.value.page_permissions || user.value.page_permissions.length === 0) {
      return true; // Sin restricciones = acceso a todo
    }
    return user.value.page_permissions.includes(pageName);
  };

  /**
   * Verifica si el usuario puede ver una provincia específica
   * Si no tiene location_permissions definido, puede ver todas
   */
  const canViewProvince = (provinceId: number): boolean => {
    if (!user.value) return false;
    if (!user.value.location_permissions) return true; // Sin restricciones = ver todo
    if (!user.value.location_permissions.provinces || user.value.location_permissions.provinces.length === 0) {
      return true;
    }
    return user.value.location_permissions.provinces.includes(provinceId);
  };

  /**
   * Verifica si el usuario puede ver una sede específica
   */
  const canViewSite = (siteId: number): boolean => {
    if (!user.value) return false;
    if (!user.value.location_permissions) return true;
    if (!user.value.location_permissions.sites || user.value.location_permissions.sites.length === 0) {
      // Si no tiene restricciones de sedes, verificar por provincia
      return true;
    }
    return user.value.location_permissions.sites.includes(siteId);
  };

  /**
   * Verifica si el usuario puede ver un departamento específico
   */
  const canViewDepartment = (departmentId: number): boolean => {
    if (!user.value) return false;
    if (!user.value.location_permissions) return true;
    if (!user.value.location_permissions.departments || user.value.location_permissions.departments.length === 0) {
      return true;
    }
    return user.value.location_permissions.departments.includes(departmentId);
  };

  /**
   * Verifica si el usuario tiene permisos de escritura en un módulo
   * Si no tiene read_write_permissions definido, solo tiene lectura
   */
  const canWrite = (module: string): boolean => {
    if (!user.value) return false;
    if (!user.value.read_write_permissions || user.value.read_write_permissions.length === 0) {
      return false; // Sin permisos de escritura = solo lectura
    }
    return user.value.read_write_permissions.includes(module);
  };

  return {
    user,
    canAccessPage,
    canViewProvince,
    canViewSite,
    canViewDepartment,
    canWrite,
  };
}

