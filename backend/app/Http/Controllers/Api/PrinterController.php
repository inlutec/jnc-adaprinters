<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PollPrinterSnmp;
use App\Models\Printer;
use App\Models\PrinterPrintLog;
use App\Models\PrinterStatusSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Printer::query()->with(['snmpProfile', 'site.province', 'department']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('ip_address', 'ilike', "%{$search}%")
                    ->orWhere('serial_number', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->integer('province_id'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $perPage = $request->integer('per_page', 15);

        $printers = $query->with(['snapshots' => function ($query) {
            $query->latest('captured_at')->limit(1);
        }, 'customFieldValues.customField'])->orderByDesc('updated_at')->paginate($perPage);

        // Añadir último snapshot y estado online/offline
        $printers->getCollection()->transform(function ($printer) {
            $printer->is_online = $this->isPrinterOnline($printer);
            // Asegurar que obtenemos el snapshot más reciente
            $printer->latest_snapshot = $printer->snapshots()->latest('captured_at')->first() ?? $printer->snapshots->first();
            
            // Cargar valores de campos personalizados como objeto clave-valor
            $customFieldValues = [];
            foreach ($printer->customFieldValues as $value) {
                if ($value->customField) {
                    $customFieldValues[$value->customField->slug] = $value->value;
                }
            }
            // Eliminar la relación customFieldValues para evitar que se serialice
            unset($printer->customFieldValues);
            // Asignar el objeto clave-valor
            $printer->custom_field_values = $customFieldValues;
            
            // Si no hay snapshot, NO crear uno desde snmp_data (dejar que se sincronice primero)
            // Esto evita mostrar datos antiguos o transformados incorrectamente
            
            return $printer;
        });

        return response()->json($printers);
    }

    protected function isPrinterOnline(Printer $printer): bool
    {
        // Si no tiene last_seen_at pero tiene status 'online' o 'discovered', considerar online
        if (!$printer->last_seen_at) {
            return in_array($printer->status, ['online', 'discovered']);
        }

        // Considerar online si se vio en los últimos 15 minutos (aumentado de 5 a 15 para dar más margen)
        return $printer->last_seen_at->isAfter(now()->subMinutes(15));
    }

    protected function extractConsumablesFromSnmpData(array $snmpData): array
    {
        $consumablesMap = [];
        $colorMap = [
            'negro' => 'black',
            'black' => 'black',
            'k' => 'black', // K en CMYK
            'cian' => 'cyan',
            'cyan' => 'cyan',
            'c' => 'cyan', // C en CMYK
            'magenta' => 'magenta',
            'm' => 'magenta', // M en CMYK
            'amarillo' => 'yellow',
            'yellow' => 'yellow',
            'y' => 'yellow', // Y en CMYK
        ];
        
        // Mapa de tipos de consumibles adicionales
        $typeMap = [
            'drum' => ['drum', 'tambor', 'imaging', 'imagen'],
            'waste' => ['waste', 'residuo', 'botella', 'bottle'],
            'fuser' => ['fuser', 'fusor', 'fusing'],
            'transfer' => ['transfer', 'transferencia', 'belt', 'correa'],
            'maintenance' => ['maintenance', 'mantenimiento', 'kit'],
            'paper' => ['paper', 'papel', 'tray', 'bandeja'],
        ];

        // Primero, recopilar todos los valores actuales y máximos por color (toners)
        // Buscar con múltiples patrones: "nivel toner", "toner level", "ink level", "cartridge", etc.
        foreach ($snmpData as $key => $value) {
            $keyLower = strtolower($key);
            
            // Buscar toners/cartuchos con múltiples patrones
            $isToner = (
                (stripos($keyLower, 'nivel') !== false && (stripos($keyLower, 'toner') !== false || stripos($keyLower, 'cartucho') !== false || stripos($keyLower, 'cartridge') !== false || stripos($keyLower, 'ink') !== false)) ||
                (stripos($keyLower, 'toner') !== false && (stripos($keyLower, 'level') !== false || stripos($keyLower, 'porcentaje') !== false || stripos($keyLower, 'percentage') !== false)) ||
                (stripos($keyLower, 'ink') !== false && (stripos($keyLower, 'level') !== false || stripos($keyLower, 'porcentaje') !== false)) ||
                (stripos($keyLower, 'cartridge') !== false && (stripos($keyLower, 'level') !== false || stripos($keyLower, 'porcentaje') !== false)) ||
                // Buscar por OIDs de prtMarkerSuppliesLevel (RFC 3805)
                (stripos($key, '1.3.6.1.2.1.43.11.1.1.9') !== false) ||
                // Buscar por índices comunes de consumibles
                (preg_match('/\b(black|cyan|magenta|yellow|negro|cian|amarillo)\b/i', $keyLower))
            );
            
            if ($isToner) {
                $color = null;
                
                // Detectar color - buscar en cualquier parte de la clave
                foreach ($colorMap as $colorKey => $colorValue) {
                    if (stripos($keyLower, $colorKey) !== false) {
                        $color = $colorValue;
                        break;
                    }
                }
                
                // Si no encontramos color pero hay un valor numérico, podría ser un índice
                // Intentar detectar por posición en el nombre o por OID
                if (!$color) {
                    // Buscar patrones como "1.3.6.1.2.1.43.11.1.1.9.1.X" donde X puede indicar el color
                    if (preg_match('/\.9\.1\.(\d+)/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $indexColorMap = [1 => 'black', 2 => 'cyan', 3 => 'magenta', 4 => 'yellow'];
                        if (isset($indexColorMap[$index])) {
                            $color = $indexColorMap[$index];
                        }
                    }
                }
                
                if (!$color) continue;
                
                // Inicializar si no existe
                if (!isset($consumablesMap[$color])) {
                    $consumablesMap[$color] = [
                        'name' => ucfirst($color),
                        'color' => $color,
                        'type' => 'toner',
                        'actual' => null,
                        'maximo' => null,
                    ];
                }
                
                // Guardar valor actual
                if (stripos($keyLower, 'actual') !== false || stripos($keyLower, 'current') !== false || stripos($keyLower, 'level') !== false) {
                    if (is_numeric($value)) {
                        $consumablesMap[$color]['actual'] = (int)$value;
                    }
                }
                // Guardar valor máximo
                if (stripos($keyLower, 'maximo') !== false || stripos($keyLower, 'máximo') !== false || stripos($keyLower, 'maximum') !== false || stripos($keyLower, 'max') !== false || stripos($keyLower, 'capacity') !== false) {
                    if (is_numeric($value)) {
                        $consumablesMap[$color]['maximo'] = (int)$value;
                    }
                }
                // Si no tiene prefijo pero es numérico y no tenemos actual, asumir que es el nivel actual
                if ($consumablesMap[$color]['actual'] === null && is_numeric($value) && stripos($keyLower, 'max') === false && stripos($keyLower, 'capacity') === false) {
                    $consumablesMap[$color]['actual'] = (int)$value;
                }
            }
            
            // Buscar consumibles adicionales (drum, waste, fuser, etc.)
            foreach ($typeMap as $type => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($keyLower, $keyword) !== false && (stripos($keyLower, 'nivel') !== false || stripos($keyLower, 'level') !== false || stripos($keyLower, 'porcentaje') !== false)) {
                        $typeKey = $type . '_' . $keyword;
                        
                        if (!isset($consumablesMap[$typeKey])) {
                            $consumablesMap[$typeKey] = [
                                'name' => ucfirst($type),
                                'color' => $this->getColorForType($type),
                                'type' => $type,
                                'actual' => null,
                                'maximo' => null,
                            ];
                        }
                        
                        if (is_numeric($value) && $value > 0) {
                            if (stripos($keyLower, 'actual') !== false || stripos($keyLower, 'current') !== false) {
                                $consumablesMap[$typeKey]['actual'] = (int)$value;
                            } elseif (stripos($keyLower, 'maximo') !== false || stripos($keyLower, 'maximum') !== false || stripos($keyLower, 'max') !== false) {
                                $consumablesMap[$typeKey]['maximo'] = (int)$value;
                            } elseif ($consumablesMap[$typeKey]['actual'] === null) {
                                $consumablesMap[$typeKey]['actual'] = (int)$value;
                            }
                        }
                        break;
                    }
                }
            }
        }

        // Si no encontramos valores "actual" para toners, usar valores sin "actual" como fallback
        // También buscar directamente por nombres de color sin "nivel" o "toner"
        foreach ($snmpData as $key => $value) {
            $keyLower = strtolower($key);
            
            // Buscar directamente por nombres de color (black, cyan, magenta, yellow)
            foreach ($colorMap as $colorKey => $colorValue) {
                // Buscar claves que contengan el color pero no sean "actual" o "maximo"
                if (stripos($keyLower, $colorKey) !== false && 
                    stripos($keyLower, 'actual') === false && 
                    stripos($keyLower, 'maximo') === false && 
                    stripos($keyLower, 'máximo') === false &&
                    stripos($keyLower, 'maximum') === false &&
                    stripos($keyLower, 'max') === false &&
                    stripos($keyLower, 'capacity') === false &&
                    is_numeric($value)) {
                    
                    if (!isset($consumablesMap[$colorValue])) {
                        $consumablesMap[$colorValue] = [
                            'name' => ucfirst($colorValue),
                            'color' => $colorValue,
                            'type' => 'toner',
                            'actual' => null,
                            'maximo' => null,
                        ];
                    }
                    
                    if ($consumablesMap[$colorValue]['actual'] === null) {
                        $consumablesMap[$colorValue]['actual'] = (int)$value;
                    }
                }
            }
        }

        // Calcular porcentajes y construir array final
        $consumables = [];
        foreach ($consumablesMap as $key => $data) {
            if ($data['actual'] === null) continue;
            
            $level = null;
            
            // Si tenemos máximo, calcular porcentaje
            if ($data['maximo'] !== null && $data['maximo'] > 0) {
                $level = min(100, (int)(($data['actual'] / $data['maximo']) * 100));
            } else {
                // Si no hay máximo, asumir que el valor está en milésimas o es un porcentaje directo
                if ($data['actual'] > 1000) {
                    // Probablemente en milésimas, convertir a porcentaje (asumiendo máximo de 10000)
                    $level = min(100, (int)($data['actual'] / 100));
                } else {
                    $level = min(100, max(0, $data['actual']));
                }
            }
            
            if ($level !== null) {
                $consumables[] = [
                    'name' => $data['name'],
                    'color' => $data['color'],
                    'type' => $data['type'] ?? 'toner',
                    'nivel_porcentaje' => $level,
                    'label' => $data['name'],
                ];
            }
        }

        return $consumables;
    }
    
    protected function getColorForType(string $type): string
    {
        $colors = [
            'drum' => '#8B7355',      // Marrón
            'waste' => '#964B00',     // Marrón oscuro
            'fuser' => '#FF6B35',    // Naranja
            'transfer' => '#4A90E2',  // Azul
            'maintenance' => '#95A5A6', // Gris
            'paper' => '#ECF0F1',     // Gris claro
        ];
        
        return $colors[$type] ?? '#666666';
    }

    protected function extractCountersFromSnmpData(array $snmpData): ?array
    {
        $counters = [];
        
        // Buscar contadores de páginas con múltiples patrones
        foreach ($snmpData as $key => $value) {
            $keyLower = strtolower($key);
            
            // Total de páginas - múltiples patrones
            if ((stripos($keyLower, 'total') !== false && (stripos($keyLower, 'pagina') !== false || stripos($keyLower, 'page') !== false)) || 
                stripos($keyLower, 'total_de_paginas_impresas') !== false ||
                stripos($keyLower, 'total_pages') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.1') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.2') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.3') !== false) {
                if (is_numeric($value)) {
                    $val = (int)$value;
                    // Si no tenemos total o este valor es mayor, usarlo como total
                    if (!isset($counters['total_pages']) || $val > $counters['total_pages']) {
                        $counters['total_pages'] = $val;
                    }
                }
            }
            
            // Páginas en color - múltiples patrones
            if ((stripos($keyLower, 'color') !== false && (stripos($keyLower, 'pagina') !== false || stripos($keyLower, 'page') !== false)) ||
                stripos($keyLower, 'color_pages') !== false ||
                stripos($keyLower, 'paginas_color') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.2') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.4') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.5') !== false) {
                if (is_numeric($value)) {
                    $val = (int)$value;
                    if (!isset($counters['color_pages']) || $val > $counters['color_pages']) {
                        $counters['color_pages'] = $val;
                    }
                }
            }
            
            // Páginas monocromo/B&W - múltiples patrones
            if (stripos($keyLower, 'monocromo') !== false || 
                stripos($keyLower, 'monochrome') !== false ||
                (stripos($keyLower, 'blanco') !== false && stripos($keyLower, 'negro') !== false) ||
                (stripos($keyLower, 'black') !== false && stripos($keyLower, 'white') !== false) ||
                stripos($keyLower, 'bw_pages') !== false ||
                stripos($keyLower, 'paginas_monocromo') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.3') !== false ||
                stripos($key, '1.3.6.1.2.1.43.10.2.1.4.1.6') !== false) {
                if (is_numeric($value)) {
                    $val = (int)$value;
                    if (!isset($counters['bw_pages']) || $val > $counters['bw_pages']) {
                        $counters['bw_pages'] = $val;
                    }
                }
            }
        }
        
        // Si tenemos total pero no color ni bw, intentar calcular
        if (isset($counters['total_pages']) && $counters['total_pages'] > 0) {
            // Si tenemos total y color, calcular bw
            if (isset($counters['color_pages']) && !isset($counters['bw_pages'])) {
                $counters['bw_pages'] = max(0, $counters['total_pages'] - $counters['color_pages']);
            }
            
            // Si tenemos total y bw, calcular color
            if (isset($counters['bw_pages']) && !isset($counters['color_pages'])) {
                $counters['color_pages'] = max(0, $counters['total_pages'] - $counters['bw_pages']);
            }
        }

        return !empty($counters) ? $counters : null;
    }

    protected function parsePercentage($value): ?int
    {
        if (is_numeric($value)) {
            $value = (int)$value;
            // Si el valor es muy grande (probablemente en milésimas), convertir a porcentaje
            if ($value > 1000) {
                return min(100, (int)($value / 10));
            }
            return min(100, max(0, $value));
        }
        
        // Intentar extraer número de string
        if (preg_match('/(\d+)/', (string)$value, $matches)) {
            $value = (int)$matches[1];
            if ($value > 1000) {
                return min(100, (int)($value / 10));
            }
            return min(100, max(0, $value));
        }
        
        return null;
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $printer = Printer::create($data);

        return response()->json($printer->fresh(['snmpProfile', 'site', 'department']), 201);
    }

    public function show(Printer $printer): JsonResponse
    {
        $printer->load([
            'snmpProfile',
            'site.province',
            'department',
            'installations.stock.consumable',
            'installations.installer',
            'installations.photos',
            'customFieldValues.customField',
        ]);
        $printer->load(['snmpProfile', 'site', 'department', 'province']);
        $printer->is_online = $this->isPrinterOnline($printer);
        $printer->latest_snapshot = $printer->snapshots()->latest('captured_at')->first();
        $printer->all_snapshots = $printer->snapshots()->latest('captured_at')->take(10)->get();
        
        // Cargar valores de campos personalizados como objeto clave-valor
        $customFieldValues = [];
        foreach ($printer->customFieldValues as $value) {
            if ($value->customField) {
                $customFieldValues[$value->customField->slug] = $value->value;
            }
        }
        // Eliminar la relación customFieldValues para evitar que se serialice
        unset($printer->customFieldValues);
        // Asignar el objeto clave-valor
        $printer->custom_field_values = $customFieldValues;
        
        // Si no hay snapshot, NO crear uno desde snmp_data (dejar que se sincronice primero)
        // Esto evita mostrar datos antiguos o transformados incorrectamente

        return response()->json($printer);
    }

    public function update(Request $request, Printer $printer): JsonResponse
    {
        $data = $this->validatedData($request, update: true);
        
        // Separar campos personalizados del resto de datos
        $customFieldValues = $data['custom_field_values'] ?? null;
        unset($data['custom_field_values']);

        $printer->update($data);
        
        // Actualizar valores de campos personalizados si se proporcionaron
        if ($customFieldValues !== null && is_array($customFieldValues)) {
            \Log::info('Guardando campos personalizados', ['printer_id' => $printer->id, 'values' => $customFieldValues]);
            foreach ($customFieldValues as $slug => $value) {
                \Log::info('Guardando campo', ['slug' => $slug, 'value' => $value, 'type' => gettype($value)]);
                try {
                    $printer->setCustomFieldValue($slug, $value);
                    \Log::info('Campo guardado exitosamente', ['slug' => $slug]);
                } catch (\Exception $e) {
                    \Log::error('Error al guardar campo personalizado', [
                        'slug' => $slug,
                        'value' => $value,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        $printer = $printer->fresh(['snmpProfile', 'site', 'department', 'customFieldValues.customField']);
        
        // Cargar valores de campos personalizados como objeto clave-valor
        $customFieldValues = [];
        foreach ($printer->customFieldValues as $value) {
            if ($value->customField) {
                $customFieldValues[$value->customField->slug] = $value->value;
            }
        }
        $printer->custom_field_values = $customFieldValues;
        
        return response()->json($printer);
    }

    public function destroy(Printer $printer): JsonResponse
    {
        $printer->delete();

        return response()->json([
            'message' => __('Printer removed'),
        ]);
    }

    public function sync(Printer $printer): JsonResponse
    {
        PollPrinterSnmp::dispatch($printer);

        return response()->json([
            'message' => __('Sync encolada'),
            'printer_id' => $printer->id,
        ], 202);
    }

    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip_range' => ['required', 'string'],
        ]);

        $snmpClient = app(\App\Services\Snmp\SnmpClient::class);
        $ipAddress = $validated['ip_range'];
        
        // Si es un rango, solo procesamos la primera IP por ahora
        // En el futuro se puede expandir para procesar rangos
        if (str_contains($ipAddress, '-') || str_contains($ipAddress, '/')) {
            return response()->json([
                'error' => __('Por ahora solo se admite una IP única. Ejemplo: 10.64.130.12'),
            ], 400);
        }

        try {
            $discoveredData = $snmpClient->discover($ipAddress);
            
            if (!$discoveredData) {
                return response()->json([
                    'error' => __('No se pudo conectar con la impresora en la IP proporcionada. Verifica que SNMP esté habilitado.'),
                    'ip_address' => $ipAddress,
                ], 404);
            }

            // Verificar si ya existe una impresora con esta IP
            $existing = Printer::where('ip_address', $ipAddress)->first();
            
            return response()->json([
                'message' => __('Impresora descubierta correctamente'),
                'data' => [
                    'ip_address' => $ipAddress,
                    'sys_descr' => $discoveredData['sys_descr'] ?? null,
                    'oids' => $discoveredData['oids'] ?? [],
                    'exists' => $existing !== null,
                    'existing_printer_id' => $existing?->id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Error al descubrir impresora: :message', ['message' => $e->getMessage()]),
                'ip_address' => $ipAddress,
            ], 500);
        }
    }

    public function importDiscovered(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip'],
            'sys_descr' => ['sometimes', 'nullable', 'string'],
            'oids' => ['sometimes', 'nullable', 'array'],
            'name' => ['sometimes', 'nullable', 'string'],
            'province_id' => ['sometimes', 'nullable', 'exists:provinces,id'],
            'site_id' => ['sometimes', 'nullable', 'exists:sites,id'],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'custom_field_values' => ['sometimes', 'nullable', 'array'],
        ]);

        // Verificar si ya existe
        $existing = Printer::where('ip_address', $validated['ip_address'])->first();
        if ($existing) {
            return response()->json([
                'error' => __('Ya existe una impresora con esta IP'),
                'printer' => $existing,
            ], 409);
        }

        // Extraer información de los OIDs descubiertos
        $oids = $validated['oids'] ?? [];
        $snmpData = $oids;

        // Extraer consumibles y contadores si están en los OIDs
        $consumables = $oids['consumables'] ?? [];
        $counters = $oids['counters'] ?? [];
        
        // Si no hay consumibles en los OIDs, intentar extraerlos del snmp_data
        if (empty($consumables) && !empty($snmpData)) {
            $consumables = $this->extractConsumablesFromSnmpData($snmpData);
        }
        
        // Si no hay contadores en los OIDs, intentar extraerlos del snmp_data
        if (empty($counters) && !empty($snmpData)) {
            $counters = $this->extractCountersFromSnmpData($snmpData);
        }
        
        // Añadir consumibles y contadores al snmp_data
        if (!empty($consumables)) {
            $snmpData['consumables'] = $consumables;
        }
        if (!empty($counters)) {
            $snmpData['counters'] = $counters;
        }

        // Intentar extraer marca y modelo del sys_descr
        $sysDescr = $validated['sys_descr'] ?? '';
        $brand = $oids['brand'] ?? null;
        $model = $oids['model'] ?? null;
        
        // Si no hay en OIDs, intentar extraer del sys_descr
        if (!$brand || !$model) {
            // Patrones comunes: "HP LaserJet Pro M404dn", "Canon imageRUNNER ADVANCE C5535i", etc.
            if (preg_match('/^(HP|Canon|Epson|Brother|Xerox|Lexmark|Kyocera|Ricoh|Konica|Sharp)/i', $sysDescr, $matches)) {
                $brand = $brand ?? $matches[1];
            }
            if (preg_match('/([A-Z][0-9A-Z]+(?:[-\s][0-9A-Z]+)+)/', $sysDescr, $matches)) {
                $model = $model ?? trim($matches[1]);
            }
        }
        
        // Detectar si es a color basándose en los consumibles
        $isColor = (bool) ($oids['is_color'] ?? false);
        if (!$isColor && !empty($consumables)) {
            $isColor = !empty(array_filter($consumables, fn($c) => in_array($c['color'] ?? null, ['cyan', 'magenta', 'yellow'])));
        }

        $printer = Printer::create([
            'name' => $validated['name'] ?? $sysDescr ?? "Impresora {$validated['ip_address']}",
            'ip_address' => $validated['ip_address'],
            'brand' => $brand,
            'model' => $model,
            'serial_number' => $oids['serial_number'] ?? null,
            'status' => 'online', // Marcar como online al importar
            'supports_snmp' => true,
            'discovery_source' => 'snmp_discovery',
            'snmp_data' => $snmpData,
            'is_color' => $isColor,
            'province_id' => $validated['province_id'] ?? null,
            'site_id' => $validated['site_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'last_seen_at' => now(), // Actualizar last_seen_at al importar
            'last_sync_at' => now(), // También actualizar last_sync_at
            'metrics' => !empty($counters) ? [
                'total_pages' => $counters['total_pages'] ?? 0,
                'color_pages' => $counters['color_pages'] ?? 0,
                'bw_pages' => $counters['bw_pages'] ?? 0,
            ] : null,
        ]);

        // Guardar valores de campos personalizados si se proporcionaron
        if (isset($validated['custom_field_values']) && is_array($validated['custom_field_values'])) {
            foreach ($validated['custom_field_values'] as $slug => $value) {
                $printer->setCustomFieldValue($slug, $value);
            }
        }

        return response()->json([
            'message' => __('Impresora importada correctamente'),
            'data' => $printer->load(['site', 'department', 'province']),
        ], 201);
    }

    public function uploadPhoto(Request $request, Printer $printer): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB máximo
        ]);

        $file = $request->file('photo');
        $tempPath = $file->getRealPath();
        
        // Obtener información de la imagen original
        $imageInfo = getimagesize($tempPath);
        if (!$imageInfo) {
            return response()->json(['error' => __('Formato de imagen no válido')], 400);
        }
        
        [$originalWidth, $originalHeight, $imageType] = $imageInfo;
        
        // Tamaño máximo para redimensionar (mantener proporción)
        $maxWidth = 800;
        $maxHeight = 800;
        
        // Calcular nuevas dimensiones manteniendo la proporción
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) ($originalWidth * $ratio);
        $newHeight = (int) ($originalHeight * $ratio);
        
        // Crear imagen desde el archivo original según su tipo
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($tempPath),
            IMAGETYPE_PNG => imagecreatefrompng($tempPath),
            IMAGETYPE_GIF => imagecreatefromgif($tempPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($tempPath),
            default => null,
        };
        
        if (!$sourceImage) {
            return response()->json(['error' => __('Formato de imagen no soportado. Use JPEG, PNG, GIF o WebP.')], 400);
        }
        
        // Crear nueva imagen redimensionada
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Mantener transparencia para PNG y GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionar con alta calidad
        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );
        
        // Generar nombre único para el archivo
        $filename = 'printer-' . $printer->id . '-' . time() . '.jpg';
        $path = 'printer-photos/' . $filename;
        
        // Guardar imagen redimensionada como JPEG con calidad 85 usando Storage
        // Crear un archivo temporal para imagejpeg
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        imagejpeg($resizedImage, $tempPath, 85);
        
        // Guardar usando Storage
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($tempPath));
        
        // Cerrar y eliminar archivo temporal
        fclose($tempFile);
        
        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        // Obtener el nombre exacto de la impresora
        $printerName = $printer->name;
        
        // Buscar todas las impresoras con el mismo nombre exacto
        $printersWithSameName = Printer::where('name', $printerName)->get();
        
        // Eliminar fotos anteriores de todas las impresoras con el mismo nombre
        foreach ($printersWithSameName as $p) {
            if ($p->photo_path && $p->photo_path !== $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($p->photo_path);
            }
        }
        
        // Aplicar la nueva foto a todas las impresoras con el mismo nombre
        Printer::where('name', $printerName)->update(['photo_path' => $path]);

        // Usar URL relativa para evitar problemas con diferentes dominios
        $photoUrl = '/storage/' . $path;

        return response()->json([
            'message' => __('Foto subida correctamente. Se aplicó a :count impresoras con el mismo nombre.', ['count' => $printersWithSameName->count()]),
            'photo_url' => $photoUrl,
            'photo_path' => $path,
            'applied_to_count' => $printersWithSameName->count(),
        ]);
    }

    public function logs(Printer $printer): JsonResponse
    {
        $logs = PrinterPrintLog::where('printer_id', $printer->id)
            ->latest('started_at')
            ->paginate(20);

        return response()->json($logs);
    }

    public function snapshots(Printer $printer): JsonResponse
    {
        $snapshots = PrinterStatusSnapshot::where('printer_id', $printer->id)
            ->latest('captured_at')
            ->paginate(20);

        return response()->json($snapshots);
    }

    public function groupsByName(): JsonResponse
    {
        // Agrupar solo por nombre (no por modelo) para mostrar todos los nombres únicos
        $groups = Printer::select('name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MAX(model) as model')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                $printers = Printer::where('name', $group->name)
                    ->select('id', 'name', 'ip_address', 'photo_path', 'model')
                    ->get();
                
                return [
                    'name' => $group->name,
                    'model' => $group->model,
                    'count' => (int) $group->count,
                    'printers' => $printers,
                ];
            });

        return response()->json($groups->values());
    }

    public function uploadMassivePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB máximo
            'printer_name' => ['required', 'string', 'max:255'],
        ]);

        $printerName = $validated['printer_name'];
        
        // Verificar que existe al menos una impresora con ese nombre
        $printersWithSameName = Printer::where('name', $printerName)->get();
        if ($printersWithSameName->isEmpty()) {
            return response()->json(['error' => __('No se encontraron impresoras con el nombre especificado')], 404);
        }

        $file = $request->file('photo');
        $tempPath = $file->getRealPath();
        
        // Obtener información de la imagen original
        $imageInfo = getimagesize($tempPath);
        if (!$imageInfo) {
            return response()->json(['error' => __('Formato de imagen no válido')], 400);
        }
        
        [$originalWidth, $originalHeight, $imageType] = $imageInfo;
        
        // Tamaño máximo para redimensionar (mantener proporción)
        $maxWidth = 800;
        $maxHeight = 800;
        
        // Calcular nuevas dimensiones manteniendo la proporción
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) ($originalWidth * $ratio);
        $newHeight = (int) ($originalHeight * $ratio);
        
        // Crear imagen desde el archivo original según su tipo
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($tempPath),
            IMAGETYPE_PNG => imagecreatefrompng($tempPath),
            IMAGETYPE_GIF => imagecreatefromgif($tempPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($tempPath),
            default => null,
        };
        
        if (!$sourceImage) {
            return response()->json(['error' => __('Formato de imagen no soportado. Use JPEG, PNG, GIF o WebP.')], 400);
        }
        
        // Crear nueva imagen redimensionada
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Mantener transparencia para PNG y GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionar con alta calidad
        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );
        
        // Generar nombre único para el archivo
        $filename = 'printer-massive-' . str_replace([' ', '/', '\\'], '-', $printerName) . '-' . time() . '.jpg';
        $path = 'printer-photos/' . $filename;
        
        // Guardar imagen redimensionada como JPEG con calidad 85 usando Storage
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        imagejpeg($resizedImage, $tempPath, 85);
        
        // Guardar usando Storage
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($tempPath));
        
        // Cerrar y eliminar archivo temporal
        fclose($tempFile);
        
        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        // Eliminar fotos anteriores de todas las impresoras con el mismo nombre
        foreach ($printersWithSameName as $printer) {
            if ($printer->photo_path && $printer->photo_path !== $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($printer->photo_path);
            }
        }
        
        // Aplicar la nueva foto a todas las impresoras con el mismo nombre
        Printer::where('name', $printerName)->update(['photo_path' => $path]);

        return response()->json([
            'message' => __('Foto subida correctamente. Se aplicó a :count impresoras con el mismo nombre.', ['count' => $printersWithSameName->count()]),
            'photo_path' => $path,
            'applied_to_count' => $printersWithSameName->count(),
        ]);
    }

    private function validatedData(Request $request, bool $update = false): array
    {
        $rules = [
            'snmp_profile_id' => ['nullable', 'exists:snmp_profiles,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'ip_address' => [$update ? 'sometimes' : 'required', 'ip'],
            'mac_address' => ['nullable', 'string', 'max:32'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'is_color' => ['boolean'],
            'supports_snmp' => ['boolean'],
            'installed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'custom_field_values' => ['sometimes', 'nullable', 'array'],
        ];

        $data = $request->validate($rules);

        return $data;
    }
}

