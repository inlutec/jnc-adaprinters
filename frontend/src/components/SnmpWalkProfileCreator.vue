<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useAppStore } from '@/stores/app';
import { createProfileFromWalk, executeSnmpWalk, testOid } from '@/services/snmpProfileService';

type Category = 'consumable' | 'counter' | 'system' | 'other';
type Color = 'black' | 'cyan' | 'magenta' | 'yellow' | null;

type WalkItem = {
  oid: string;
  raw_value: string;
  clean_value: string;
  snmp_type: string | null;
  numeric_value: number | null;
  suggested_category: Category | string;
  suggested_unit: string | null;
  suggested_color: Color | string | null;
  suggested_name: string;
  is_percentage_suspect: boolean;
};

type EditableOid = {
  selected: boolean;
  oid: string;
  name: string;
  description: string;
  category: Category;
  data_type: string;
  unit: string;
  color: Color;
  is_required: boolean;
  order: number;
  preview_value: string;
};

const props = defineProps<{
  modelValue: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void;
  (e: 'created', profile: any): void;
}>();

const appStore = useAppStore();

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
});

const step = ref<1 | 2 | 3 | 4 | 5>(1);
const discovering = ref(false);
const creating = ref(false);

const connection = ref({
  ip: '',
  community: 'public',
  oid_base: '1.3.6.1.2.1',
});

const search = ref('');
const rawWalk = ref<WalkItem[]>([]);
const oids = ref<EditableOid[]>([]);

const profile = ref({
  name: '',
  brand: '',
  model: '',
  description: '',
  is_default: false,
  is_active: true,
});

const selectedCount = computed(() => oids.value.filter((o) => o.selected).length);
const selectedConsumablesMissingColor = computed(() => {
  return oids.value.filter((o) => o.selected && o.category === 'consumable' && !o.color).length;
});

const grouped = computed(() => {
  const q = search.value.trim().toLowerCase();
  const filtered = !q
    ? oids.value
    : oids.value.filter((o) => {
        return (
          o.oid.toLowerCase().includes(q) ||
          o.name.toLowerCase().includes(q) ||
          o.description.toLowerCase().includes(q) ||
          o.preview_value.toLowerCase().includes(q)
        );
      });

  const groups: Record<Category, EditableOid[]> = {
    consumable: [],
    counter: [],
    system: [],
    other: [],
  };

  for (const item of filtered) {
    groups[item.category].push(item);
  }

  return groups;
});

const close = () => {
  open.value = false;
};

const reset = () => {
  step.value = 1;
  discovering.value = false;
  creating.value = false;
  search.value = '';
  rawWalk.value = [];
  oids.value = [];
  profile.value = {
    name: '',
    brand: '',
    model: '',
    description: '',
    is_default: false,
    is_active: true,
  };
};

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      reset();
    }
  }
);

const inferDataType = (item: WalkItem): string => {
  if (typeof item.numeric_value === 'number') {
    return Number.isInteger(item.numeric_value) ? 'integer' : 'float';
  }
  const t = (item.snmp_type || '').toLowerCase();
  if (t.includes('integer') || t.includes('counter') || t.includes('gauge')) return 'integer';
  return 'string';
};

const normalizeCategory = (c: any): Category => {
  if (c === 'consumable' || c === 'counter' || c === 'system' || c === 'other') return c;
  return 'other';
};

const normalizeColor = (c: any): Color => {
  if (c === 'black' || c === 'cyan' || c === 'magenta' || c === 'yellow') return c;
  return null;
};

const runWalk = async () => {
  if (!connection.value.ip.trim()) {
    appStore.notify('La IP es obligatoria', 'error');
    return;
  }

  discovering.value = true;
  rawWalk.value = [];
  oids.value = [];

  try {
    const data = await executeSnmpWalk({
      ip: connection.value.ip.trim(),
      community: connection.value.community.trim() || null,
      oid_base: connection.value.oid_base.trim() || null,
    });

    if (!data?.success) {
      appStore.notify(data?.error || 'Error al ejecutar SNMP walk', 'error');
      return;
    }

    rawWalk.value = data.items || [];

    oids.value = (rawWalk.value as WalkItem[]).map((it, idx) => {
      const category = normalizeCategory(it.suggested_category);
      const unit = it.suggested_unit || '';
      const name = it.suggested_name || 'OID';
      const color = category === 'consumable' ? normalizeColor(it.suggested_color) : null;
      return {
        selected: false,
        oid: it.oid,
        name,
        description: '',
        category,
        data_type: inferDataType(it),
        unit,
        color,
        is_required: false,
        order: idx,
        preview_value: it.clean_value ?? '',
      };
    });

    step.value = 2;
    appStore.notify(`SNMP walk completado: ${oids.value.length} OIDs`, 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al ejecutar SNMP walk', 'error');
  } finally {
    discovering.value = false;
  }
};

const toggleGroup = (category: Category, value: boolean) => {
  for (const item of oids.value) {
    if (item.category === category) {
      item.selected = value;
    }
  }
};

const onCategoryChange = (item: EditableOid) => {
  if (item.category !== 'consumable') {
    item.color = null;
  }
  if (item.category === 'counter' && !item.unit) {
    item.unit = 'pages';
  }
  if ((item.category === 'system' || item.category === 'other') && item.unit === 'pages') {
    item.unit = '';
  }
};

const testOne = async (item: EditableOid) => {
  try {
    const data = await testOid({
      ip: connection.value.ip.trim(),
      community: connection.value.community.trim() || null,
      oid: item.oid,
    });
    if (data?.success) {
      item.preview_value = data.clean_value ?? data.formatted_value ?? data.raw_value ?? item.preview_value;
      appStore.notify('OID probado correctamente', 'success');
    } else {
      appStore.notify(data?.error || 'Error al probar OID', 'error');
    }
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al probar OID', 'error');
  }
};

const goNextFromSelection = () => {
  if (selectedCount.value < 1) {
    appStore.notify('Selecciona al menos 1 OID para el perfil', 'error');
    return;
  }
  step.value = 4;
};

const goToReview = () => {
  if (!profile.value.name.trim()) {
    appStore.notify('El nombre del perfil es obligatorio', 'error');
    return;
  }
  step.value = 5;
};

const createProfile = async () => {
  if (!profile.value.name.trim()) {
    appStore.notify('El nombre del perfil es obligatorio', 'error');
    return;
  }
  const selected = oids.value
    .filter((o) => o.selected)
    .sort((a, b) => a.order - b.order)
    .map((o, idx) => ({
      oid: o.oid,
      name: o.name,
      description: o.description || null,
      category: o.category,
      data_type: o.data_type,
      unit: o.unit || null,
      color: o.category === 'consumable' ? o.color : null,
      is_active: true,
      is_required: o.is_required,
      order: idx,
    }));

  if (selected.length < 1) {
    appStore.notify('Selecciona al menos 1 OID para el perfil', 'error');
    return;
  }

  creating.value = true;
  try {
    const data = await createProfileFromWalk({
      profile: {
        name: profile.value.name.trim(),
        brand: profile.value.brand.trim() || null,
        model: profile.value.model.trim() || null,
        description: profile.value.description.trim() || null,
        is_default: profile.value.is_default,
        is_active: profile.value.is_active,
      },
      oids: selected,
    });

    appStore.notify('Perfil creado correctamente', 'success');
    emit('created', data);
    close();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al crear el perfil', 'error');
  } finally {
    creating.value = false;
  }
};
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    @click.self="close"
  >
    <div class="bg-white rounded-xl p-6 max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-4 mb-4">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Crear perfil con SNMP walk</h3>
          <p class="text-sm text-slate-600 mt-1">
            Descubre OIDs, configúralos desde cero y crea un perfil totalmente personalizado.
          </p>
        </div>
        <button
          class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
          @click="close"
        >
          Cerrar
        </button>
      </div>

      <!-- Steps -->
      <div class="flex items-center gap-2 text-sm mb-6">
        <span
          class="px-2 py-1 rounded font-semibold"
          :class="step === 1 ? 'bg-ada-primary text-white' : 'bg-slate-100 text-slate-700'"
        >
          1. Conexión
        </span>
        <span class="text-slate-400">/</span>
        <span
          class="px-2 py-1 rounded font-semibold"
          :class="step === 2 || step === 3 ? 'bg-ada-primary text-white' : 'bg-slate-100 text-slate-700'"
        >
          2. OIDs
        </span>
        <span class="text-slate-400">/</span>
        <span
          class="px-2 py-1 rounded font-semibold"
          :class="step === 4 ? 'bg-ada-primary text-white' : 'bg-slate-100 text-slate-700'"
        >
          3. Perfil
        </span>
        <span class="text-slate-400">/</span>
        <span
          class="px-2 py-1 rounded font-semibold"
          :class="step === 5 ? 'bg-ada-primary text-white' : 'bg-slate-100 text-slate-700'"
        >
          4. Resumen
        </span>
      </div>

      <!-- Step 1 -->
      <div v-if="step === 1" class="space-y-4">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">IP *</label>
            <input
              v-model="connection.ip"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="10.66.129.61"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Community</label>
            <input
              v-model="connection.community"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="public"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">OID base</label>
            <input
              v-model="connection.oid_base"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="1.3.6.1.2.1"
            />
            <p class="text-xs text-slate-500 mt-1">Por defecto: todo el árbol estándar (puede tardar).</p>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="text-sm text-slate-600">
            Consejo: para impresoras suele ser útil `1.3.6.1.2.1.43` (Printer-MIB), pero aquí vamos a “todo”.
          </div>
          <button
            @click="runWalk"
            :disabled="discovering"
            class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-60"
          >
            {{ discovering ? 'Ejecutando...' : 'Ejecutar SNMP walk' }}
          </button>
        </div>
      </div>

      <!-- Step 2/3 -->
      <div v-else-if="step === 2 || step === 3" class="space-y-4">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="text-sm text-slate-700">
              OIDs descubiertos: <span class="font-semibold">{{ oids.length }}</span>
              · Seleccionados: <span class="font-semibold">{{ selectedCount }}</span>
            </div>
            <input
              v-model="search"
              type="text"
              class="w-80 rounded-lg border border-slate-300 px-3 py-2 text-sm"
              placeholder="Buscar por OID, nombre o valor…"
            />
          </div>
          <div class="flex items-center gap-2">
            <button
              class="px-3 py-2 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
              @click="step = 1"
            >
              Volver
            </button>
            <button
              class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
              @click="goNextFromSelection"
            >
              Continuar
            </button>
          </div>
        </div>

        <div class="grid grid-cols-4 gap-3">
          <button
            class="px-3 py-2 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-left"
            @click="toggleGroup('consumable', true)"
          >
            Seleccionar consumibles
          </button>
          <button
            class="px-3 py-2 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-left"
            @click="toggleGroup('counter', true)"
          >
            Seleccionar contadores
          </button>
          <button
            class="px-3 py-2 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-left"
            @click="toggleGroup('system', true)"
          >
            Seleccionar sistema
          </button>
          <button
            class="px-3 py-2 text-sm rounded-lg border border-slate-200 hover:bg-slate-50 text-left"
            @click="oids.forEach(o => (o.selected = false))"
          >
            Limpiar selección
          </button>
        </div>

        <div class="space-y-6">
          <div v-for="(items, cat) in grouped" :key="cat" class="border border-slate-200 rounded-xl">
            <div class="flex items-center justify-between px-4 py-3 bg-slate-50 rounded-t-xl">
              <div class="font-semibold text-slate-800 capitalize">
                {{ cat }} <span class="text-slate-500 text-sm font-normal">({{ items.length }})</span>
              </div>
              <div class="flex items-center gap-2">
                <button
                  class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
                  @click="toggleGroup(cat as any, true)"
                >
                  Seleccionar todo
                </button>
                <button
                  class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
                  @click="toggleGroup(cat as any, false)"
                >
                  Deseleccionar
                </button>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="text-left text-slate-600">
                  <tr class="border-t border-slate-200">
                    <th class="px-4 py-2">Usar</th>
                    <th class="px-4 py-2">OID</th>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Categoría</th>
                    <th class="px-4 py-2">Color</th>
                    <th class="px-4 py-2">Unidad</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Valor</th>
                    <th class="px-4 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in items" :key="item.oid" class="border-t border-slate-100">
                    <td class="px-4 py-2">
                      <input v-model="item.selected" type="checkbox" class="rounded border-slate-300" />
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ item.oid }}</td>
                    <td class="px-4 py-2">
                      <input
                        v-model="item.name"
                        type="text"
                        class="w-64 rounded-lg border border-slate-300 px-3 py-1.5"
                      />
                    </td>
                    <td class="px-4 py-2">
                      <select
                        v-model="item.category"
                        class="rounded-lg border border-slate-300 px-3 py-1.5"
                        @change="onCategoryChange(item)"
                      >
                        <option value="consumable">consumable</option>
                        <option value="counter">counter</option>
                        <option value="system">system</option>
                        <option value="other">other</option>
                      </select>
                    </td>
                    <td class="px-4 py-2">
                      <select
                        v-model="item.color"
                        class="rounded-lg border border-slate-300 px-3 py-1.5"
                        :disabled="item.category !== 'consumable'"
                      >
                        <option :value="null">—</option>
                        <option value="black">black</option>
                        <option value="cyan">cyan</option>
                        <option value="magenta">magenta</option>
                        <option value="yellow">yellow</option>
                      </select>
                    </td>
                    <td class="px-4 py-2">
                      <input
                        v-model="item.unit"
                        type="text"
                        class="w-24 rounded-lg border border-slate-300 px-3 py-1.5"
                        placeholder="%"
                      />
                    </td>
                    <td class="px-4 py-2">
                      <input
                        v-model="item.data_type"
                        type="text"
                        class="w-24 rounded-lg border border-slate-300 px-3 py-1.5"
                      />
                    </td>
                    <td class="px-4 py-2 text-slate-700">
                      <span class="font-mono text-xs">{{ item.preview_value }}</span>
                    </td>
                    <td class="px-4 py-2">
                      <button
                        class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
                        @click="testOne(item)"
                        :disabled="!connection.ip.trim()"
                      >
                        Probar
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 4 -->
      <div v-else-if="step === 4" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nombre *</label>
            <input
              v-model="profile.name"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Ej: Lexmark MXxxxx (Custom)"
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Marca</label>
              <input v-model="profile.brand" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Modelo</label>
              <input v-model="profile.model" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Descripción</label>
          <textarea v-model="profile.description" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2">
            <input v-model="profile.is_default" type="checkbox" class="rounded border-slate-300" />
            <span class="text-sm text-slate-700">Perfil por defecto</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="profile.is_active" type="checkbox" class="rounded border-slate-300" />
            <span class="text-sm text-slate-700">Activo</span>
          </label>
        </div>
        <div class="flex items-center justify-between">
          <button class="px-3 py-2 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200" @click="step = 2">
            Volver
          </button>
          <button class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark" @click="goToReview">
            Continuar
          </button>
        </div>
      </div>

      <!-- Step 5 -->
      <div v-else class="space-y-4">
        <div class="border border-slate-200 rounded-xl p-4">
          <div class="font-semibold text-slate-900 mb-2">Resumen</div>
          <div class="text-sm text-slate-700">
            Perfil: <span class="font-semibold">{{ profile.name }}</span>
            · OIDs seleccionados: <span class="font-semibold">{{ selectedCount }}</span>
          </div>
          <div v-if="selectedConsumablesMissingColor > 0" class="text-xs text-amber-700 mt-2">
            Aviso: hay {{ selectedConsumablesMissingColor }} consumibles seleccionados sin color. Si quieres que se muestren como toner
            (negro/cian/magenta/amarillo), asigna el color en la tabla.
          </div>
          <div class="text-xs text-slate-500 mt-1">Puedes volver atrás para ajustar categorías/unidades/colores.</div>
        </div>

        <div class="flex items-center justify-between">
          <button class="px-3 py-2 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200" @click="step = 4">
            Volver
          </button>
          <button
            class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700 disabled:opacity-60"
            @click="createProfile"
            :disabled="creating"
          >
            {{ creating ? 'Creando...' : 'Crear perfil' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>


