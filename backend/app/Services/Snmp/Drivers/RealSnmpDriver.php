<?php

namespace App\Services\Snmp\Drivers;

use App\Models\Printer;
use App\Models\SnmpOid;
use Illuminate\Support\Facades\Log;

class RealSnmpDriver
{
    protected string $community = 'public';
    protected int $timeout = 2;
    protected int $retries = 1;

    public function __construct()
    {
        $this->community = config('snmp.community', 'public');
        $this->timeout = (int) (config('snmp.timeout_ms', 2000) / 1000);
        $this->retries = config('snmp.retries', 1);
    }

    public function poll(Printer $printer): ?array
    {
        if (! function_exists('snmpget')) {
            Log::warning('SNMP extension not available, falling back to fake driver');
            return null;
        }

        try {
            $ip = $printer->ip_address;
            $community = $printer->snmpProfile?->community ?? $this->community;

            // Obtener información básica del sistema
            $sysDescr = @snmpget($ip, $community, '1.3.6.1.2.1.1.1.0', $this->timeout * 1000000, $this->retries);
            $sysName = @snmpget($ip, $community, '1.3.6.1.2.1.1.5.0', $this->timeout * 1000000, $this->retries);
            $sysUpTime = @snmpget($ip, $community, '1.3.6.1.2.1.1.3.0', $this->timeout * 1000000, $this->retries);

            if ($sysDescr === false) {
                return null;
            }

            $data = [
                'status' => 'online',
                'error_code' => null,
                'uptime_seconds' => $this->parseUptime($sysUpTime),
                'consumables' => $this->getConsumables($ip, $community),
                'environment' => $this->getEnvironment($ip, $community),
                'counters' => $this->getCounters($ip, $community),
                'raw' => [
                    'sysDescr' => $this->cleanSnmpValue($sysDescr),
                    'sysName' => $this->cleanSnmpValue($sysName),
                    'sysUpTime' => $sysUpTime,
                    'sampled_at' => now()->toIso8601String(),
                ],
            ];

            // Calcular total_pages, color_pages, bw_pages
            $data['total_pages'] = $data['counters']['total_pages'] ?? 0;
            $data['color_pages'] = $data['counters']['color_pages'] ?? 0;
            $data['bw_pages'] = $data['counters']['bw_pages'] ?? 0;

            return $data;
        } catch (\Exception $e) {
            Log::error("SNMP poll error for {$printer->ip_address}: {$e->getMessage()}");
            return null;
        }
    }

    public function discover(string $ip): ?array
    {
        if (! function_exists('snmpget')) {
            return null;
        }

        try {
            $community = $this->community;

            // Intentar obtener información básica
            $sysDescr = @snmpget($ip, $community, '1.3.6.1.2.1.1.1.0', $this->timeout * 1000000, $this->retries);
            
            if ($sysDescr === false) {
                return null;
            }

            $description = $this->cleanSnmpValue($sysDescr);
            
            // No filtrar por tipo de dispositivo - intentar descubrir todo lo que responda SNMP
            // El usuario decidirá si es una impresora o no

            // Consultar TODOS los OIDs de la base de datos
            $allOids = SnmpOid::where('is_active', true)->get();
            $discoveredOids = [];
            
            foreach ($allOids as $oidConfig) {
                try {
                    $value = @snmpget($ip, $community, $oidConfig->oid, $this->timeout * 1000000, $this->retries);
                    if ($value !== false) {
                        $cleanValue = $this->cleanSnmpValue($value);
                        $formattedValue = $this->formatValue($cleanValue, $oidConfig->data_type, $oidConfig->unit);
                        
                        // Crear una clave única para el OID usando el nombre normalizado
                        $key = \Illuminate\Support\Str::slug($oidConfig->name, '_');
                        $discoveredOids[$key] = $formattedValue;
                        
                        // También añadir con el nombre del OID como clave alternativa
                        $discoveredOids[$oidConfig->name] = $formattedValue;
                        
                        // Y con el OID como clave
                        $discoveredOids[$oidConfig->oid] = $formattedValue;
                    }
                } catch (\Exception $e) {
                    // Continuar con el siguiente OID
                    Log::debug("Failed to get OID {$oidConfig->oid} for {$ip}: {$e->getMessage()}");
                }
            }

            // Obtener información básica del sistema
            $sysName = @snmpget($ip, $community, '1.3.6.1.2.1.1.5.0', $this->timeout * 1000000, $this->retries);
            $sysObjectId = @snmpget($ip, $community, '1.3.6.1.2.1.1.2.0', $this->timeout * 1000000, $this->retries);
            $sysUpTime = @snmpget($ip, $community, '1.3.6.1.2.1.1.3.0', $this->timeout * 1000000, $this->retries);

            // Añadir información básica a los OIDs descubiertos
            $discoveredOids['sys_descr'] = $description;
            $discoveredOids['sys_name'] = $this->cleanSnmpValue($sysName);
            $discoveredOids['sys_object_id'] = $this->cleanSnmpValue($sysObjectId);
            $discoveredOids['sys_uptime'] = $this->cleanSnmpValue($sysUpTime);

            // Intentar extraer marca y modelo
            $brand = $this->extractBrand($description, $discoveredOids);
            $model = $this->extractModel($description, $discoveredOids);
            $serialNumber = $this->extractSerialNumber($discoveredOids);

            // Obtener consumibles y contadores durante el descubrimiento
            $consumables = $this->getConsumables($ip, $community);
            $counters = $this->getCounters($ip, $community);
            
            // Añadir consumibles y contadores a los OIDs descubiertos
            $discoveredOids['consumables'] = $consumables;
            $discoveredOids['counters'] = $counters;

            return [
                'ip_address' => $ip,
                'sys_descr' => $description,
                'oids' => $discoveredOids,
                'brand' => $brand,
                'model' => $model,
                'serial_number' => $serialNumber,
                'is_color' => $this->detectColorCapability($discoveredOids) || !empty(array_filter($consumables, fn($c) => in_array($c['color'] ?? null, ['cyan', 'magenta', 'yellow']))),
                'consumables' => $consumables,
                'counters' => $counters,
            ];
        } catch (\Exception $e) {
            Log::debug("SNMP discovery failed for {$ip}: {$e->getMessage()}");
            return null;
        }
    }

    protected function formatValue(string $value, string $dataType, ?string $unit = null): mixed
    {
        $value = trim($value);
        
        return match ($dataType) {
            'integer' => (int) $value,
            'percentage' => (float) str_replace('%', '', $value),
            'boolean' => (bool) $value,
            default => $value,
        };
    }

    protected function extractBrand(string $description, array $oids): ?string
    {
        // Buscar en los OIDs descubiertos
        foreach (['Marca HP', 'Marca Canon', 'brand', 'manufacturer'] as $key) {
            if (isset($oids[$key]) && !empty($oids[$key])) {
                return $oids[$key];
            }
        }

        // Buscar en la descripción
        $brands = ['HP', 'Canon', 'Epson', 'Brother', 'Xerox', 'Lexmark', 'Kyocera', 'Ricoh', 'Konica', 'Sharp', 'Samsung'];
        foreach ($brands as $brand) {
            if (stripos($description, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    protected function extractModel(string $description, array $oids): ?string
    {
        // Buscar en los OIDs descubiertos
        foreach (['Modelo HP', 'Modelo Canon', 'Modelo Epson', 'Modelo Brother', 'Modelo Xerox', 'model', 'model_name'] as $key) {
            if (isset($oids[$key]) && !empty($oids[$key])) {
                return $oids[$key];
            }
        }

        // Intentar extraer de la descripción
        if (preg_match('/([A-Z][0-9A-Z]+(?:[-\s][0-9A-Z]+)+)/', $description, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractSerialNumber(array $oids): ?string
    {
        foreach (['Número de serie HP', 'serial_number', 'serial', 'serialNumber'] as $key) {
            if (isset($oids[$key]) && !empty($oids[$key])) {
                return $oids[$key];
            }
        }

        return null;
    }

    protected function detectColorCapability(array $oids): bool
    {
        // Si hay toners de color, es una impresora a color
        $colorKeys = ['cyan', 'magenta', 'yellow', 'Cian', 'Magenta', 'Amarillo'];
        foreach ($colorKeys as $key) {
            if (isset($oids[$key]) || isset($oids["Nivel de toner {$key}"])) {
                return true;
            }
        }

        return false;
    }

    protected function getConsumables(string $ip, string $community): array
    {
        $consumables = [];
        
        // PRIMERO: Intentar descubrir consumibles usando OIDs estándar RFC 3805 (más confiables)
        // Esto nos da datos más precisos con descripciones y niveles correctos
        $additionalConsumables = $this->discoverAdditionalConsumables($ip, $community);
        $consumables = array_merge($consumables, $additionalConsumables);
        
        // SEGUNDO: Consultar OIDs configurados en la base de datos (pueden tener datos menos precisos)
        // Solo añadir si no tenemos ya ese color detectado
        $foundColors = array_unique(array_column($consumables, 'color'));
        $oids = SnmpOid::where('category', 'consumable')->where('is_active', true)->get();

        foreach ($oids as $oid) {
            // Si ya tenemos este color con un porcentaje realista, saltar
            if ($oid->color && in_array($oid->color, $foundColors)) {
                // Verificar si el consumible existente tiene porcentaje realista
                $existing = null;
                foreach ($consumables as $c) {
                    if (($c['color'] ?? null) === $oid->color) {
                        $existing = $c;
                        break;
                    }
                }
                // Si el existente tiene porcentaje realista (0-100), no añadir este
                if ($existing && ($existing['nivel_porcentaje'] ?? 0) <= 100) {
                    continue;
                }
            }
            
            try {
                $value = @snmpget($ip, $community, $oid->oid, $this->timeout * 1000000, $this->retries);
                if ($value !== false) {
                    $level = $this->parseConsumableLevel($value, $oid);
                    // CRÍTICO: Solo añadir si el porcentaje es válido (0-100%)
                    if ($level !== null && $level >= 0 && $level <= 100) {
                        $consumables[] = [
                            'oid' => $oid->oid,
                            'name' => $oid->name,
                            'color' => $oid->color,
                            'nivel_porcentaje' => $level,
                            'raw_value' => $this->cleanSnmpValue($value),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Continuar con el siguiente OID
            }
        }

        // Si no hay consumibles descubiertos, intentar OIDs estándar comunes
        if (empty($consumables)) {
            $consumables = $this->getStandardConsumables($ip, $community);
        }
        
        // FILTRAR consumibles con porcentajes inválidos (> 100% o < 0%)
        $consumables = array_filter($consumables, function ($c) {
            $percentage = $c['nivel_porcentaje'] ?? null;
            // Solo mantener consumibles con porcentajes válidos (0-100%) o sin porcentaje (para kits, drums, etc)
            if ($percentage === null) {
                return true; // Mantener si no tiene porcentaje (kits, drums, etc)
            }
            return $percentage >= 0 && $percentage <= 100;
        });
        
        // Eliminar duplicados basándose en color y tipo
        // Prioridad: porcentaje realista (0-100) > mejor nombre > cualquier otro
        $uniqueConsumables = [];
        foreach ($consumables as $c) {
            $color = $c['color'] ?? 'unknown';
            $type = $c['type'] ?? 'unknown';
            $key = $color . '_' . $type;
            
            // Solo procesar si tiene porcentaje válido o es un tipo especial (kit, drum, etc)
            $percentage = $c['nivel_porcentaje'] ?? null;
            if ($percentage !== null && ($percentage < 0 || $percentage > 100)) {
                continue; // Saltar consumibles con porcentajes inválidos
            }
            
            if (!isset($uniqueConsumables[$key])) {
                $uniqueConsumables[$key] = $c;
            } else {
                $existing = $uniqueConsumables[$key];
                $existingPercentage = $existing['nivel_porcentaje'] ?? null;
                $newPercentage = $c['nivel_porcentaje'] ?? null;
                
                $shouldReplace = false;
                
                // Si el existente tiene porcentaje inválido y el nuevo es válido, reemplazar
                if ($newPercentage !== null && $newPercentage >= 0 && $newPercentage <= 100) {
                    if ($existingPercentage === null || $existingPercentage < 0 || $existingPercentage > 100) {
                        $shouldReplace = true;
                    }
                    // Si ambos tienen porcentajes válidos, usar lógica de priorización normal
                    elseif ($existingPercentage !== null && $existingPercentage >= 0 && $existingPercentage <= 100) {
                        // Si el nuevo está al 100% y el existente no, mantener el existente (más preciso)
                        if ($newPercentage >= 100 && $existingPercentage < 100) {
                            $shouldReplace = false;
                        }
                        // Si el existente está al 100% y el nuevo no, mantener el existente (más preciso)
                        elseif ($existingPercentage >= 100 && $newPercentage < 100) {
                            $shouldReplace = false;
                        }
                        // Si ambos están en rango válido, priorizar el que tenga mejor nombre descriptivo
                        else {
                            $existingName = trim($existing['name'] ?? '');
                            $newName = trim($c['name'] ?? '');
                            // Priorizar nombres descriptivos sobre genéricos
                            if (!empty($newName) && (empty($existingName) || is_numeric($existingName) || 
                                (!is_numeric($newName) && strlen($newName) > strlen($existingName)))) {
                                $shouldReplace = true;
                            }
                        }
                    }
                }
                
                if ($shouldReplace) {
                    $uniqueConsumables[$key] = $c;
                }
            }
        }
        $consumables = array_values($uniqueConsumables);
        
        // Verificar qué colores tenemos y cuáles faltan
        $foundColors = [];
        foreach ($consumables as $c) {
            $color = $c['color'] ?? null;
            if ($color && in_array($color, ['black', 'cyan', 'magenta', 'yellow'])) {
                $foundColors[] = $color;
            }
        }
        $foundColors = array_unique($foundColors);
        
        // Si tenemos black, cyan, magenta pero no yellow, intentar encontrarlo
        if (in_array('black', $foundColors) && in_array('cyan', $foundColors) && in_array('magenta', $foundColors) && !in_array('yellow', $foundColors)) {
            $yellowFound = false;
            
            // Intentar OIDs específicos de Lexmark para yellow
            $lexmarkYellowOids = [
                '1.3.6.1.4.1.641.2.1.2.1.1.4', // Lexmark toner level yellow
                '1.3.6.1.4.1.641.6.2.1.1.1.4', // Lexmark alternate yellow OID
            ];
            
            foreach ($lexmarkYellowOids as $yellowOid) {
                try {
                    $value = @snmpget($ip, $community, $yellowOid, $this->timeout * 1000000, $this->retries);
                    if ($value !== false) {
                        $level = $this->parseNumericValue($this->cleanSnmpValue($value));
                        if ($level !== null && $level >= 0) {
                            $percentage = $level > 1000 ? min(100, (int)($level / 100)) : min(100, max(0, $level));
                            $consumables[] = [
                                'oid' => $yellowOid,
                                'name' => 'Amarillo',
                                'color' => 'yellow',
                                'type' => 'toner',
                                'nivel_porcentaje' => $percentage,
                                'raw_value' => $this->cleanSnmpValue($value),
                            ];
                            $yellowFound = true;
                            break; // Solo añadir una vez
                        }
                    }
                } catch (\Exception $e) {
                    // Continuar
                }
            }
            
            // Si aún no encontramos yellow, consultar más índices de prtMarkerSuppliesLevel
            // Algunas impresoras Lexmark pueden tener el yellow en un índice más alto
            if (!$yellowFound) {
                for ($index = 4; $index <= 30; $index++) {
                    try {
                        $levelOid = "1.3.6.1.2.1.43.11.1.1.9.1.{$index}";
                        $levelValue = @snmpget($ip, $community, $levelOid, $this->timeout * 1000000, $this->retries);
                        
                        if ($levelValue !== false) {
                            $level = $this->parseNumericValue($this->cleanSnmpValue($levelValue));
                            if ($level !== null && $level >= 0) {
                                // Si encontramos un nivel válido en un índice más alto y no tenemos yellow, asumir que es yellow
                                $maxOid = "1.3.6.1.2.1.43.11.1.1.8.1.{$index}";
                                $maxValue = @snmpget($ip, $community, $maxOid, $this->timeout * 1000000, $this->retries);
                                $max = $maxValue !== false ? $this->parseNumericValue($this->cleanSnmpValue($maxValue)) : null;
                                
                                $percentage = $level;
                                if ($max !== null && $max > 0) {
                                    $percentage = $level > $max ? min(100, (int)(($level / ($max * 10)) * 100)) : min(100, (int)(($level / $max) * 100));
                                } elseif ($level > 1000) {
                                    $percentage = min(100, (int)($level / 100));
                                } elseif ($level > 100) {
                                    $percentage = min(100, $level);
                                }
                                
                                $consumables[] = [
                                    'oid' => $levelOid,
                                    'name' => 'Amarillo',
                                    'color' => 'yellow',
                                    'type' => 'toner',
                                    'nivel_porcentaje' => $percentage,
                                    'raw_value' => $this->cleanSnmpValue($levelValue),
                                ];
                                $yellowFound = true;
                                break; // Salir del bucle for
                            }
                        }
                    } catch (\Exception $e) {
                        // Continuar
                    }
                }
            }
            
            // Si aún no encontramos yellow después de buscar en todos los índices,
            // y tenemos black, cyan, magenta, asumir que yellow existe pero no se reporta por SNMP
            // Añadir un yellow con nivel desconocido (0% o null) para que aparezca en la interfaz
            // Esto es común en algunas impresoras Lexmark que no reportan todos los colores por SNMP
            if (!$yellowFound) {
                $consumables[] = [
                    'oid' => null,
                    'name' => 'Amarillo',
                    'color' => 'yellow',
                    'type' => 'toner',
                    'nivel_porcentaje' => 0, // Nivel desconocido
                    'raw_value' => null,
                    'note' => 'No reportado por SNMP - puede requerir verificación manual',
                ];
            }
        }

        return $consumables;
    }
    
    protected function discoverAdditionalConsumables(string $ip, string $community): array
    {
        $consumables = [];
        $foundIndices = [];
        
        // PRIMER PASO: Recopilar TODOS los índices y sus tipos ANTES de asignar colores
        // Esto nos permite detectar qué índices son kits/tambores y ajustar la asignación de colores
        $indexTypes = []; // Almacenar: índice => tipo (kit, drum, toner)
        
        // Primera pasada: identificar tipos sin asignar colores
        for ($preIndex = 1; $preIndex <= 30; $preIndex++) {
            try {
                $typeOid = "1.3.6.1.2.1.43.11.1.1.2.1.{$preIndex}";
                $typeValue = @snmpget($ip, $community, $typeOid, $this->timeout * 1000000, $this->retries);
                if ($typeValue === false) continue;
                
                $levelOid = "1.3.6.1.2.1.43.11.1.1.9.1.{$preIndex}";
                $maxOid = "1.3.6.1.2.1.43.11.1.1.8.1.{$preIndex}";
                $levelValue = @snmpget($ip, $community, $levelOid, $this->timeout * 1000000, $this->retries);
                $maxValue = @snmpget($ip, $community, $maxOid, $this->timeout * 1000000, $this->retries);
                
                if ($levelValue !== false) {
                    $level = $this->parseNumericValue($levelValue);
                    $max = $maxValue !== false ? $this->parseNumericValue($maxValue) : null;
                    
                    if ($level !== null && $level >= 0) {
                        // Clasificar el tipo sin procesar completamente
                        if ($max !== null && ($max > 100000 || $level > 100000)) {
                            $indexTypes[$preIndex] = 'kit';
                        } elseif ($max !== null && $max >= 50000 && $max <= 100000) {
                            $indexTypes[$preIndex] = 'drum';
                        } elseif ($max !== null && $max > 30000 && $max < 50000) {
                            // Valores entre 30000-50000 son tambores (kit de imagen)
                            $indexTypes[$preIndex] = 'drum';
                        } elseif ($max !== null && $max > 10000 && $max < 30000) {
                            // Verificar porcentaje
                            $pct = ($level / $max) * 100;
                            // CORRECCIÓN: En impresoras monocromas (índice 1 es tambor), valores 20000-30000 son toner negro
                            $index1IsDrum = isset($indexTypes[1]) && $indexTypes[1] === 'drum';
                            $isMonochromeCandidate = $index1IsDrum && $preIndex > 1 && $max >= 20000 && $max <= 30000;
                            
                            if ($isMonochromeCandidate) {
                                // En impresora monocroma, estos valores son toner negro
                                $indexTypes[$preIndex] = 'toner';
                            } elseif ($pct >= 80) {
                                $indexTypes[$preIndex] = 'drum'; // Tambor o kit de imagen
                            } else {
                                $indexTypes[$preIndex] = 'toner';
                            }
                        } elseif ($max !== null && $max <= 10000) {
                            $indexTypes[$preIndex] = 'toner';
                        } else {
                            $indexTypes[$preIndex] = 'unknown';
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Contar toners reales (no kits/tambores) para asignar colores correctamente
        $tonerIndices = [];
        foreach ($indexTypes as $idx => $type) {
            if ($type === 'toner') {
                $tonerIndices[] = $idx;
            }
        }
        sort($tonerIndices); // Ordenar índices de toners
        
        // Mapear toners a colores según su orden: 1=black, 2=cyan, 3=magenta, 4=yellow
        $tonerColorMap = [];
        $colorOrder = ['black', 'cyan', 'magenta', 'yellow'];
        foreach ($tonerIndices as $pos => $idx) {
            if ($pos < count($colorOrder)) {
                $tonerColorMap[$idx] = $colorOrder[$pos];
            }
        }
        
        $foundDrums = []; // Rastrear tambores detectados
        $foundToners = []; // Rastrear toners detectados
        
        // OIDs estándar RFC 3805 para descubrir todos los suministros
        // prtMarkerSuppliesTable (1.3.6.1.2.1.43.11.1.1)
        // Intentar descubrir hasta 30 suministros (índices 1-30) para asegurar que encontramos todos los colores
        for ($index = 1; $index <= 30; $index++) {
            try {
                // prtMarkerSuppliesType (1.3.6.1.2.1.43.11.1.1.2.x) - Tipo de suministro (INTEGER)
                $typeOid = "1.3.6.1.2.1.43.11.1.1.2.1.{$index}";
                $typeValue = @snmpget($ip, $community, $typeOid, $this->timeout * 1000000, $this->retries);
                
                if ($typeValue === false) continue;
                
                $supplyType = (int)$this->cleanSnmpValue($typeValue);
                
                // prtMarkerSuppliesDescription (1.3.6.1.2.1.43.11.1.1.3.x) - Descripción
                $descOid = "1.3.6.1.2.1.43.11.1.1.3.1.{$index}";
                $descValue = @snmpget($ip, $community, $descOid, $this->timeout * 1000000, $this->retries);
                $description = $descValue !== false ? $this->cleanSnmpValue($descValue) : null;
                
                // Para Lexmark: Intentar obtener descripción más detallada usando OIDs específicos del fabricante
                // Lexmark usa diferentes OIDs para descripciones más detalladas
                if (empty($description) || is_numeric($description)) {
                    // Intentar OIDs específicos de Lexmark para obtener descripciones
                    $lexmarkDescOids = [
                        "1.3.6.1.4.1.641.2.1.2.1.1.3.{$index}", // Lexmark toner description
                        "1.3.6.1.4.1.641.6.2.1.1.1.3.{$index}", // Lexmark alternate description OID
                    ];
                    foreach ($lexmarkDescOids as $lexOid) {
                        $lexValue = @snmpget($ip, $community, $lexOid, $this->timeout * 1000000, $this->retries);
                        if ($lexValue !== false) {
                            $lexDesc = $this->cleanSnmpValue($lexValue);
                            if (!empty($lexDesc) && !is_numeric($lexDesc)) {
                                $description = $lexDesc;
                                break;
                            }
                        }
                    }
                }
                
                // prtMarkerSuppliesLevel (1.3.6.1.2.1.43.11.1.1.9.x) - Nivel actual
                $levelOid = "1.3.6.1.2.1.43.11.1.1.9.1.{$index}";
                $levelValue = @snmpget($ip, $community, $levelOid, $this->timeout * 1000000, $this->retries);
                
                // prtMarkerSuppliesMaxCapacity (1.3.6.1.2.1.43.11.1.1.8.x) - Capacidad máxima
                $maxOid = "1.3.6.1.2.1.43.11.1.1.8.1.{$index}";
                $maxValue = @snmpget($ip, $community, $maxOid, $this->timeout * 1000000, $this->retries);
                
                if ($levelValue !== false) {
                    $level = $this->parseNumericValue($levelValue);
                    $max = $maxValue !== false ? $this->parseNumericValue($maxValue) : null;
                    
                    if ($level !== null && $level >= 0) {
                        // ===== FILTRO 1: DISTINGUIR ENTRE TONERS, TAMBORES Y KITS =====
                        // Los toners reales típicamente tienen valores entre 1000-5000
                        // Los tambores/unidades de imagen tienen valores entre 10000-100000
                        // Los kits de mantenimiento tienen valores > 100000
                        $isLikelyKit = false;
                        $isLikelyDrum = false;
                        
                        if ($max !== null && $max > 0) {
                            // Kits de mantenimiento: valores > 100000
                            if ($max > 100000 || $level > 100000) {
                                $isLikelyKit = true;
                            }
                            // Tambores/unidades de imagen: valores entre 50000-100000 (valores más altos)
                            // Toners pueden tener valores más bajos incluso si están en rango 10000-50000
                            elseif ($max >= 50000 && $max <= 100000) {
                                $isLikelyDrum = true;
                            }
                            // Para valores entre 10000-50000, usar porcentaje para distinguir:
                            // - Si el porcentaje es bajo (< 80%), probablemente es un toner real
                            // - Si el porcentaje es alto (> 80%), podría ser tambor O toner en impresora monocroma
                            elseif ($max >= 10000 && $max < 50000) {
                                $percentage = ($level / $max) * 100;
                                
                                // CORRECCIÓN: Verificar si es impresora monocroma
                                // Si el índice 1 es tambor (50000-100000), es monocroma
                                $index1Type = $indexTypes[1] ?? null;
                                $isMonochromePrinter = ($index1Type === 'drum' && $index > 1);
                                
                                // En impresoras monocromas, valores 20000-30000 son toner negro, no tambor
                                if ($isMonochromePrinter && $max >= 20000 && $max <= 30000) {
                                    // NO marcar como tambor - es toner negro
                                    $isLikelyDrum = false;
                                } elseif ($percentage >= 80 && $max >= 30000) {
                                    // Solo marcar como tambor si el max es >= 30000 y porcentaje alto
                                    $isLikelyDrum = true;
                                }
                                // Si el porcentaje es bajo, es probablemente un toner (no marcar como tambor)
                            }
                            // Si el nivel es muy alto (50000+) pero max está en rango, también es tambor
                            elseif ($level >= 50000 && $level <= 100000 && $max >= 50000) {
                                $isLikelyDrum = true;
                            }
                        } elseif ($level > 100000) {
                            // Si el nivel sin máximo es muy alto (> 100000), es kit de mantenimiento
                            $isLikelyKit = true;
                        } elseif ($level >= 50000 && $level <= 100000) {
                            // Si el nivel está en rango alto de tambor, probablemente es tambor
                            $isLikelyDrum = true;
                        }
                        
                        // Determinar tipo y color basado en el tipo de suministro y descripción
                        $type = $this->detectConsumableTypeFromSupplyType($supplyType, $description);
                        
                        // Si es probablemente un kit o tambor, cambiar el tipo ANTES de detectar color
                        if ($isLikelyKit) {
                            $type = 'maintenance';
                        } elseif ($isLikelyDrum) {
                            $type = 'drum'; // Tambor/unidad de imagen
                        }
                        
                        // Detectar color SOLO si no es un kit ni tambor (para evitar detectar kits/tambores como toners)
                        if (!$isLikelyKit && !$isLikelyDrum) {
                            // PRIORIDAD 1: Si tenemos mapeo de toners basado en orden, usarlo
                            if (isset($tonerColorMap[$index]) && $type === 'toner') {
                                $color = $tonerColorMap[$index]; // Usar color según orden de toners
                            } else {
                                // Detectar color normalmente
                                $color = $this->detectConsumableColor($description, $type, $index, $consumables, $indexTypes);
                                
                                // CORRECCIÓN: Si el índice 1 es kit/tambor y estamos en índice 2, debe ser negro
                                if ($index === 2 && $type === 'toner') {
                                    $index1Type = $indexTypes[1] ?? null;
                                    if (in_array($index1Type, ['kit', 'drum'])) {
                                        $color = 'black'; // El índice 2 es el primer toner real, debe ser negro
                                    }
                                }
                                
                                // CORRECCIÓN: Si es impresora monocroma (índice 1 es tambor), el índice 2 debe ser negro
                                $index1Type = $indexTypes[1] ?? null;
                                if ($index1Type === 'drum' && $index === 2 && $type === 'toner' && $max !== null && $max > 10000 && $max < 30000) {
                                    $color = 'black'; // Forzar negro para impresoras monocromas
                                }
                            }
                        } else {
                            $color = null; // Los kits y tambores no tienen color
                        }
                        
                        // Solo añadir si no es un toner ya detectado (evitar duplicados)
                        $isToner = !$isLikelyKit && !$isLikelyDrum && in_array($type, ['toner', 'ink']) && in_array($color, ['black', 'cyan', 'magenta', 'yellow']);
                        $key = $isToner ? $color : (($isLikelyKit || $isLikelyDrum) ? $type . '_' . $index : ($type . '_' . $index));
                        
                        // Calcular porcentaje SIEMPRE primero para poder comparar
                        $percentage = $level;
                        if ($max !== null && $max > 0) {
                            // SIEMPRE calcular porcentaje usando nivel/máximo cuando ambos están disponibles
                            $rawPercentage = ($level / $max) * 100;
                            // MEJORA: Redondear porcentajes cercanos a 100% (99%+ se muestra como 100%)
                            if ($rawPercentage >= 99.0 && $rawPercentage <= 100.0) {
                                $percentage = 100; // Redondear 99-100% a 100%
                            } else {
                                $percentage = min(100, max(0, (int)$rawPercentage));
                            }
                        } elseif ($level > 1000 && $level <= 10000) {
                            // Probablemente en milésimas, convertir a porcentaje (solo para valores realistas)
                            $percentage = min(100, (int)($level / 100));
                        } elseif ($level > 100 && $level <= 1000) {
                            // Si es mayor que 100 pero menor que 1000, podría ser un valor directo
                            $percentage = min(100, $level);
                        } elseif ($level > 10000) {
                            // Valores muy altos probablemente son kits - usar cálculo diferente
                            $percentage = null; // No calcular porcentaje para kits
                        }
                        
                        // Para toners: FILTRAR kits, tambores y priorizar valores realistas
                        if ($isToner) {
                            // CRÍTICO: Si es un toner y los valores son muy altos, es un kit o tambor mal identificado - FILTRAR COMPLETAMENTE
                            if ($isLikelyKit || $isLikelyDrum) {
                                continue; // Saltar este - es un kit o tambor, no un toner
                            }
                            
                            // PERMITIR toners con valores hasta 30000 si tienen porcentajes realistas (< 80%)
                            // Esto cubre casos donde el toner tiene valores más altos pero es válido
                            // Ejemplo: índice 2 con 22436/28400 = 79% es un toner válido
                            // Pero si el porcentaje es alto (> 80%), probablemente es un tambor
                            if ($max !== null && $max > 30000) {
                                continue; // Saltar - valores demasiado altos para ser toner real
                            }
                            if ($level > 30000) {
                                continue; // Saltar - nivel demasiado alto
                            }
                            
                            // Si tenemos max y porcentaje, verificar que el porcentaje sea válido
                            if ($max !== null && $percentage !== null) {
                                // Si el porcentaje es > 100%, definitivamente no es válido
                                if ($percentage > 100) {
                                    continue;
                                }
                                // CORRECCIÓN: En impresoras monocromas, valores 20000-30000 son toner negro, no tambor
                                // Verificar si es impresora monocroma (índice 1 es tambor)
                                $index1Type = $indexTypes[1] ?? null;
                                $isMonochromePrinter = ($index1Type === 'drum' && $index > 1);
                                
                                // Si el porcentaje es > 80% y el max está en rango de tambor (10000-30000), probablemente es tambor, no toner
                                // EXCEPTO en impresoras monocromas donde valores 20000-30000 son toner negro
                                if ($percentage > 80 && $max >= 10000 && $max <= 30000 && !($isMonochromePrinter && $max >= 20000 && $max <= 30000)) {
                                    continue; // Probablemente es tambor (pero no en impresoras monocromas)
                                }
                            }
                            
                            // Si ya encontramos este color, verificar si el nuevo valor es mejor
                            if (isset($foundIndices[$key])) {
                                // Buscar el consumible existente para comparar
                                $existingIdx = null;
                                $existingPercentage = null;
                                $existingMax = null;
                                foreach ($consumables as $idx => $existing) {
                                    if (($existing['color'] ?? null) === $color && ($existing['type'] ?? null) === $type) {
                                        $existingIdx = $idx;
                                        $existingPercentage = $existing['nivel_porcentaje'] ?? null;
                                        $existingMax = $existing['raw_max'] ?? null;
                                        break;
                                    }
                                }
                                
                                $shouldReplace = false;
                                $existingIsKit = ($existingMax !== null && $existingMax > 10000);
                                
                                // PRIORIDAD 1: Si el existente es un kit (valores altos) y el nuevo tiene valores realistas, reemplazar
                                if ($existingIsKit && !$isLikelyKit) {
                                    $shouldReplace = true;
                                }
                                // PRIORIDAD 2: Si el nuevo es un kit y el existente tiene valores realistas, mantener el existente
                                elseif (!$existingIsKit && $isLikelyKit) {
                                    $shouldReplace = false;
                                }
                                // PRIORIDAD 3: Si ambos son toners reales (no kits), priorizar valores más precisos (no al 100%)
                                elseif (!$existingIsKit && !$isLikelyKit && $existingPercentage !== null && $percentage !== null) {
                                    // CRÍTICO: Priorizar valores que NO estén al 100% (más precisos) sobre valores al 100%
                                    // Los valores al 100% pueden ser de kits o valores incorrectos
                                    // Los valores < 100% son más realistas y precisos
                                    
                                    // Si el existente está al 100% y el nuevo NO, reemplazar (el nuevo es más preciso)
                                    if ($existingPercentage >= 100 && $percentage < 100 && $max !== null && $max <= 5000) {
                                        $shouldReplace = true;
                                    }
                                    // Si el nuevo está al 100% y el existente NO, mantener el existente (más preciso)
                                    elseif ($percentage >= 100 && $existingPercentage < 100) {
                                        $shouldReplace = false;
                                    }
                                    // Si ambos están al 100%, priorizar valores más realistas (max más bajo = más realista)
                                    elseif ($existingPercentage >= 100 && $percentage >= 100) {
                                        $existingMaxValue = $existingMax ?? 9999;
                                        $newMaxValue = $max ?? 9999;
                                        // Priorizar el que tenga max más bajo (más realista para toner)
                                        if ($newMaxValue < $existingMaxValue && $newMaxValue <= 5000) {
                                            $shouldReplace = true;
                                        } elseif ($existingMaxValue > 5000 && $newMaxValue <= 5000) {
                                            $shouldReplace = true;
                                        } else {
                                            // Si ambos tienen valores similares, priorizar índice más bajo
                                            $existingIndex = $consumables[$existingIdx]['index'] ?? 999;
                                            $newIndex = $index ?? 999;
                                            if ($newIndex < $existingIndex) {
                                                $shouldReplace = true;
                                            }
                                        }
                                    }
                                    // Si ambos están en rango válido (<100%), priorizar el más bajo (más preciso)
                                    elseif ($existingPercentage < 100 && $percentage < 100) {
                                        // Priorizar el que tenga valores más realistas (max < 5000)
                                        $existingMaxValue = $existingMax ?? 9999;
                                        $newMaxValue = $max ?? 9999;
                                        if ($newMaxValue < $existingMaxValue && $newMaxValue <= 5000) {
                                            $shouldReplace = true;
                                        } elseif ($existingMaxValue > 5000 && $newMaxValue <= 5000) {
                                            $shouldReplace = true;
                                        } else {
                                            // Si ambos tienen valores realistas, priorizar el porcentaje más bajo (más preciso)
                                            if ($percentage < $existingPercentage) {
                                                $shouldReplace = true;
                                            } else {
                                                // Si tienen el mismo porcentaje, priorizar índice más bajo
                                                $existingIndex = $consumables[$existingIdx]['index'] ?? 999;
                                                $newIndex = $index ?? 999;
                                                if ($newIndex < $existingIndex) {
                                                    $shouldReplace = true;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                if ($shouldReplace && $existingIdx !== null) {
                                    unset($consumables[$existingIdx]);
                                } else {
                                    continue; // Ya tenemos uno mejor, no reemplazar
                                }
                            }
                        } else {
                            // Para no-toners (kits, drums, etc), solo evitar duplicados exactos
                            if (isset($foundIndices[$key])) {
                                continue;
                            }
                        }
                        $foundIndices[$key] = true;
                        
                        // Generar nombre si no hay descripción
                        $name = $description;
                        if (empty($name) || is_numeric($name)) {
                            // Si la descripción es solo un número, generar nombre basado en tipo y color
                            if ($color && $type === 'toner') {
                                $colorNames = [
                                    'black' => 'Negro',
                                    'cyan' => 'Cian',
                                    'magenta' => 'Magenta',
                                    'yellow' => 'Amarillo',
                                ];
                                $name = $colorNames[$color] ?? ucfirst($color);
                            } else {
                                // MEJORA: Detectar nombres correctos basados en valores y tipo de impresora
                                // CORRECCIÓN ESPECIAL: En impresoras monocromas, índice 1 con valores 50000-100000 es "Unidad Imagen"
                                if ($type === 'drum' && $index === 1 && $max !== null && $max >= 50000 && $max <= 100000) {
                                    $name = 'Unidad Imagen';
                                } else {
                                    $index1Type = $indexTypes[1] ?? null;
                                    $isMonochromePrinter = ($index1Type === 'drum' && $index > 1);
                                    
                                    if ($isMonochromePrinter && $type === 'drum' && $max !== null && $max >= 50000 && $max <= 100000) {
                                        // En impresora monocroma, tambor con valores 50000-100000 es "Unidad Imagen"
                                        $name = 'Unidad Imagen';
                                    } elseif (($type === 'drum' && $max !== null && $max >= 24000 && $max <= 30000 && !$isMonochromePrinter) || 
                                        ($type === 'maintenance' && $max !== null && ($max === 150000 || $level === 150000) && $index !== 1)) {
                                        // Kits de imagen en impresoras color
                                        $name = 'Kit de imagen';
                                    } else {
                                        $name = $this->getConsumableNameFromType($type, $color, $index, $description, $max, $indexTypes);
                                    }
                                }
                            }
                        }
                        
                        // Si no tenemos color pero es un toner y tenemos índice, intentar inferir el color
                        if (!$color && $type === 'toner') {
                            // Si ya tenemos black, cyan, magenta, el siguiente probablemente sea yellow
                            $foundColors = array_column($consumables, 'color');
                            if (in_array('black', $foundColors) && in_array('cyan', $foundColors) && in_array('magenta', $foundColors) && !in_array('yellow', $foundColors)) {
                                $color = 'yellow';
                            } elseif (in_array('black', $foundColors) && !in_array('cyan', $foundColors) && $index === 2) {
                                $color = 'cyan';
                            } elseif (in_array('black', $foundColors) && in_array('cyan', $foundColors) && !in_array('magenta', $foundColors) && $index === 3) {
                                $color = 'magenta';
                            } elseif (in_array('black', $foundColors) && in_array('cyan', $foundColors) && in_array('magenta', $foundColors) && $index === 4) {
                                $color = 'yellow';
                            }
                        }
                        
                        // NO añadir si es un kit y estamos buscando toners (a menos que sea explícitamente un consumible de tipo kit)
                        if ($isLikelyKit && $isToner) {
                            continue; // Saltar kits cuando esperamos toners
                        }
                        
                        // Validar que el porcentaje sea válido antes de añadir
                        // Si no tenemos max, no podemos calcular un porcentaje válido - necesitamos ambos valores
                        if ($max === null && $isToner) {
                            continue; // No añadir toners sin máximo (no podemos calcular porcentaje)
                        }
                        
                        // Solo añadir si tenemos un porcentaje válido (0-100%) o si es un tipo diferente (kits, drums, etc)
                        if ($percentage === null && $isToner) {
                            continue; // No añadir toners sin porcentaje válido
                        }
                        
                        // Validar que el porcentaje esté en rango válido (0-100%)
                        if ($percentage !== null && ($percentage < 0 || $percentage > 100)) {
                            continue; // No añadir consumibles con porcentajes inválidos
                        }
                        
                        // Si el porcentaje se calculó incorrectamente (usando level directamente sin max), no añadir
                        if ($percentage !== null && $max === null && $level > 100) {
                            continue; // No añadir si el porcentaje es > 100 sin tener max válido
                        }
                        
                        $consumables[] = [
                            'oid' => $levelOid,
                            'name' => $name,
                            'color' => $color ?? $this->getColorForType($type),
                            'type' => $type,
                            'nivel_porcentaje' => $percentage,
                            'raw_value' => $this->cleanSnmpValue($levelValue),
                            'raw_level' => $level,
                            'raw_max' => $max,
                            'index' => $index,
                            'description' => $description,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Continuar con el siguiente índice
            }
        }
        
        // ===== DETECTAR SI ES IMPRESORA MONOCROMA Y FILTRAR TONERS DE COLOR =====
        // Una impresora monocroma solo tiene toner negro, no tiene toners de color
        $toners = array_filter($consumables, function ($c) {
            return ($c['type'] ?? null) === 'toner' && in_array($c['color'] ?? null, ['black', 'cyan', 'magenta', 'yellow']);
        });
        
        $blackToner = array_filter($toners, function ($c) {
            return ($c['color'] ?? null) === 'black';
        });
        
        $colorToners = array_filter($toners, function ($c) {
            return in_array($c['color'] ?? null, ['cyan', 'magenta', 'yellow']);
        });
        
        // Si solo hay toner negro y no hay toners de color, es una impresora monocroma
        $isMonochrome = count($blackToner) > 0 && count($colorToners) === 0;
        
        // Si es monocroma, eliminar cualquier toner de color que haya sido detectado incorrectamente
        if ($isMonochrome) {
            $consumables = array_filter($consumables, function ($c) {
                $type = $c['type'] ?? null;
                $color = $c['color'] ?? null;
                // Mantener todos los consumibles excepto toners de color
                if ($type === 'toner' && in_array($color, ['cyan', 'magenta', 'yellow'])) {
                    return false; // Eliminar toners de color
                }
                return true; // Mantener todo lo demás
            });
            // Re-indexar el array
            $consumables = array_values($consumables);
        }
        
        return $consumables;
    }
    
    protected function detectConsumableTypeFromSupplyType(int $supplyType, ?string $description): string
    {
        // Tipos de suministro según RFC 3805
        // 3 = toner, 4 = wasteToner, 5 = wasteInk, 6 = ink, 7 = ribbon, etc.
        $typeMap = [
            3 => 'toner',
            4 => 'waste',
            5 => 'waste',
            6 => 'ink',
            7 => 'ribbon',
        ];
        
        if (isset($typeMap[$supplyType])) {
            return $typeMap[$supplyType];
        }
        
        // Si hay descripción, usar detección por texto
        if ($description) {
            return $this->detectConsumableType($description);
        }
        
        return 'toner'; // Por defecto
    }
    
    protected function getConsumableNameFromType(string $type, ?string $color, int $index, ?string $description = null, ?int $max = null, ?array $indexTypes = null): string
    {
        // Si hay descripción, verificar si menciona "imagen" o "imaging"
        if ($description) {
            $descLower = strtolower($description);
            if (stripos($descLower, 'imagen') !== false || stripos($descLower, 'imaging') !== false) {
                return 'Kit de imagen';
            }
        }
        
        // CORRECCIÓN: En impresoras monocromas, tambores con valores 50000-100000 son "Unidad Imagen"
        if ($type === 'drum') {
            // Si es índice 1 con valores 50000-100000, es Unidad Imagen (impresora monocroma)
            if ($index === 1 && $max !== null && $max >= 50000 && $max <= 100000) {
                return 'Unidad Imagen';
            }
            
            // Si el índice 1 es tambor y estamos en otro índice con valores 50000-100000, también es Unidad Imagen
            if ($indexTypes !== null) {
                $index1Type = $indexTypes[1] ?? null;
                $isMonochromePrinter = ($index1Type === 'drum' && $index > 1);
                
                if ($isMonochromePrinter && $max !== null && $max >= 50000 && $max <= 100000) {
                    return 'Unidad Imagen';
                }
            }
        }
        
        // Para tambores con valores en rango 24000-30000 (en impresoras color), probablemente es kit de imagen
        if ($type === 'drum' && $index === 8) {
            return 'Kit de imagen';
        }
        
        $names = [
            'drum' => 'Tambor',
            'waste' => 'Botella de residuos',
            'fuser' => 'Fusor',
            'transfer' => 'Correa de transferencia',
            'maintenance' => 'Kit de mantenimiento',
            'paper' => 'Bandeja de papel',
            'toner' => $color ? ucfirst($color) : "Toner {$index}",
        ];
        
        return $names[$type] ?? "Consumible {$index}";
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
    
    protected function detectConsumableType(string $description): string
    {
        $descLower = strtolower($description);
        
        if (stripos($descLower, 'drum') !== false || stripos($descLower, 'tambor') !== false || stripos($descLower, 'imaging') !== false) {
            return 'drum';
        }
        if (stripos($descLower, 'waste') !== false || stripos($descLower, 'residuo') !== false || stripos($descLower, 'bottle') !== false || stripos($descLower, 'botella') !== false) {
            return 'waste';
        }
        if (stripos($descLower, 'fuser') !== false || stripos($descLower, 'fusor') !== false || stripos($descLower, 'fusing') !== false) {
            return 'fuser';
        }
        if (stripos($descLower, 'transfer') !== false || stripos($descLower, 'transferencia') !== false || stripos($descLower, 'belt') !== false || stripos($descLower, 'correa') !== false) {
            return 'transfer';
        }
        if (stripos($descLower, 'maintenance') !== false || stripos($descLower, 'mantenimiento') !== false || stripos($descLower, 'kit') !== false) {
            return 'maintenance';
        }
        if (stripos($descLower, 'paper') !== false || stripos($descLower, 'papel') !== false || stripos($descLower, 'tray') !== false || stripos($descLower, 'bandeja') !== false) {
            return 'paper';
        }
        
        return 'toner';
    }
    
    protected function detectConsumableColor(?string $description, string $type, int $index = 0, array $existingConsumables = [], array $indexTypes = []): ?string
    {
        if ($type !== 'toner' && $type !== 'ink') {
            return null; // Los consumibles adicionales no tienen color
        }
        
        // Mapeo de índices comunes a colores (para impresoras estándar)
        // Nota: El orden puede variar según el fabricante, pero este es el más común
        // Algunas impresoras Lexmark/HP usan: 1=black, 2=cyan, 3=magenta, 4=yellow
        // Otras pueden usar: 1=black, 2=yellow, 3=magenta, 4=cyan
        // Otras: 1=black, 2=cyan, 3=yellow, 4=magenta
        $indexColorMap = [
            1 => 'black',
            2 => 'cyan',   // Puede ser cyan o yellow dependiendo del fabricante
            3 => 'magenta', // Puede ser magenta o yellow
            4 => 'yellow',  // Puede ser yellow o cyan
            5 => 'black',   // Algunas impresoras tienen múltiples negros
        ];
        
        // Si tenemos descripción, priorizar la detección por texto
        if ($description) {
            $descLower = strtolower($description);
            $colorMap = [
                'black' => 'black',
                'negro' => 'black',
                'k' => 'black', // K en CMYK
                'cyan' => 'cyan',
                'cian' => 'cyan',
                'c' => 'cyan', // C en CMYK
                'magenta' => 'magenta',
                'm' => 'magenta', // M en CMYK
                'yellow' => 'yellow',
                'amarillo' => 'yellow',
                'amar' => 'yellow', // Abreviación de amarillo
                'yel' => 'yellow', // Abreviación de yellow
                'y' => 'yellow', // Y en CMYK
                'jaune' => 'yellow', // Francés
                'giallo' => 'yellow', // Italiano
                'gelb' => 'yellow', // Alemán
            ];
            
            foreach ($colorMap as $key => $color) {
                if (stripos($descLower, $key) !== false) {
                    return $color;
                }
            }
        }
        
        // Si no encontramos por descripción, usar índice
        // Pero primero intentar detectar por el índice si ya tenemos otros colores detectados
        if (isset($indexColorMap[$index])) {
            $mappedColor = $indexColorMap[$index];
            
            // MEJORA: Si el índice 1 fue un kit (filtrado) y estamos en índice 2, 
            // probablemente sea el toner negro (el primero real)
            // Verificar si hay kits o tambores en índices anteriores que fueron filtrados
            $hasKitOrDrumBefore = false;
            foreach ($existingConsumables as $c) {
                $cIndex = $c['index'] ?? null;
                $cType = $c['type'] ?? null;
                if ($cIndex !== null && $cIndex < $index && in_array($cType, ['maintenance', 'drum'])) {
                    $hasKitOrDrumBefore = true;
                    break;
                }
            }
            
            // Si el índice 1 es kit/tambor y estamos en índice 2, asumir que es negro (primer toner real)
            if ($index === 2 && $hasKitOrDrumBefore) {
                // Verificar si hay algún consumible con índice 1 que sea kit o tambor
                foreach ($existingConsumables as $c) {
                    if (($c['index'] ?? null) === 1 && in_array($c['type'] ?? null, ['maintenance', 'drum'])) {
                        return 'black'; // El índice 2 es el primer toner real, debe ser negro
                    }
                }
            }
            
            return $mappedColor;
        }
        
        // Si el índice es mayor a 4 y es un toner, podría ser un color adicional
        // Algunas impresoras tienen múltiples toners del mismo color o colores adicionales
        if ($index > 4 && $type === 'toner') {
            // Intentar detectar por el índice relativo
            // Si ya tenemos black, cyan, magenta, el siguiente probablemente sea yellow
            $remainingColors = ['yellow']; // Colores que aún no hemos encontrado
            if (!empty($remainingColors)) {
                return $remainingColors[0];
            }
        }
        
        return 'black'; // Por defecto
    }

    protected function getStandardConsumables(string $ip, string $community): array
    {
        $standardOids = [
            ['oid' => '1.3.6.1.2.1.43.11.1.1.9.1.1', 'name' => 'Negro', 'color' => 'black'],
            ['oid' => '1.3.6.1.2.1.43.11.1.1.9.1.2', 'name' => 'Cian', 'color' => 'cyan'],
            ['oid' => '1.3.6.1.2.1.43.11.1.1.9.1.3', 'name' => 'Magenta', 'color' => 'magenta'],
            ['oid' => '1.3.6.1.2.1.43.11.1.1.9.1.4', 'name' => 'Amarillo', 'color' => 'yellow'],
        ];

        $consumables = [];
        foreach ($standardOids as $item) {
            try {
                $value = @snmpget($ip, $community, $item['oid'], $this->timeout * 1000000, $this->retries);
                if ($value !== false) {
                    $level = $this->parseConsumableLevel($value);
                    if ($level !== null) {
                        $consumables[] = [
                            'oid' => $item['oid'],
                            'name' => $item['name'],
                            'color' => $item['color'],
                            'nivel_porcentaje' => $level,
                            'raw_value' => $this->cleanSnmpValue($value),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Continuar
            }
        }

        return $consumables;
    }

    protected function getCounters(string $ip, string $community): array
    {
        $counters = [];
        $oids = SnmpOid::where('category', 'counter')->where('is_active', true)->get();

        foreach ($oids as $oid) {
            try {
                $value = @snmpget($ip, $community, $oid->oid, $this->timeout * 1000000, $this->retries);
                if ($value !== false) {
                    $numericValue = $this->parseNumericValue($value);
                    if ($numericValue !== null) {
                        $counters[$oid->name] = $numericValue;
                    }
                }
            } catch (\Exception $e) {
                // Continuar
            }
        }

        // OIDs estándar para contadores - múltiples variantes para diferentes fabricantes
        $standardCounters = [
            // RFC 3805 - prtMarkerLifeCount (total pages)
            'total_pages' => [
                '1.3.6.1.2.1.43.10.2.1.4.1.1', // prtMarkerLifeCount.1.1
                '1.3.6.1.2.1.43.10.2.1.4.1.2', // Alternativa
                '1.3.6.1.2.1.43.10.2.1.4.1.3', // Alternativa
            ],
            // Páginas en color - múltiples OIDs posibles
            'color_pages' => [
                '1.3.6.1.2.1.43.10.2.1.4.1.2', // Algunas impresoras usan este para color
                '1.3.6.1.2.1.43.10.2.1.4.1.4', // Alternativa
                '1.3.6.1.2.1.43.10.2.1.4.1.5', // Alternativa
            ],
            // Páginas monocromo/B&W
            'bw_pages' => [
                '1.3.6.1.2.1.43.10.2.1.4.1.3', // Algunas impresoras usan este para B&W
                '1.3.6.1.2.1.43.10.2.1.4.1.6', // Alternativa
            ],
        ];

        // Intentar múltiples OIDs para cada tipo de contador
        foreach ($standardCounters as $key => $oidList) {
            if (!isset($counters[$key]) || $counters[$key] === 0) {
                foreach ($oidList as $oid) {
                    try {
                        $value = @snmpget($ip, $community, $oid, $this->timeout * 1000000, $this->retries);
                        if ($value !== false) {
                            $numericValue = $this->parseNumericValue($value);
                            if ($numericValue !== null && $numericValue > 0) {
                                $counters[$key] = $numericValue;
                                break; // Usar el primer valor válido encontrado
                            }
                        }
                    } catch (\Exception $e) {
                        // Continuar con el siguiente OID
                    }
                }
            }
        }

        // MEJORA: Buscar también en prtMarkerCounterLife (1.3.6.1.2.1.43.10.2.1.5)
        // Este OID a menudo contiene contadores adicionales de color/B&W
        // Recopilar todos los valores disponibles primero para decidir cuál es cuál
        $foundCounterValues = [];
        if (!isset($counters['color_pages']) || !isset($counters['bw_pages']) || $counters['color_pages'] === 0) {
            for ($index = 1; $index <= 10; $index++) {
                try {
                    $oid = "1.3.6.1.2.1.43.10.2.1.5.1.{$index}";
                    $value = @snmpget($ip, $community, $oid, $this->timeout * 1000000, $this->retries);
                    if ($value !== false) {
                        $numericValue = $this->parseNumericValue($value);
                        if ($numericValue !== null && $numericValue > 0 && isset($counters['total_pages']) && $numericValue < $counters['total_pages']) {
                            $foundCounterValues[] = $numericValue;
                        }
                    }
                } catch (\Exception $e) {
                    // Continuar
                }
            }
            
            // Si encontramos valores, determinar cuál es color y cuál es B/N
            if (!empty($foundCounterValues) && isset($counters['total_pages'])) {
                // Eliminar duplicados
                $foundCounterValues = array_unique($foundCounterValues);
                sort($foundCounterValues);
                
                // Si tenemos un solo valor, asumir que es el mayor (B/N) y calcular el menor (color)
                if (count($foundCounterValues) === 1) {
                    $value = $foundCounterValues[0];
                    // Generalmente hay más páginas B/N que color, así que el valor más grande probablemente es B/N
                    if ($value > ($counters['total_pages'] / 2)) {
                        // Si es más de la mitad del total, probablemente es B/N
                        $counters['bw_pages'] = $value;
                        $counters['color_pages'] = max(0, $counters['total_pages'] - $value);
                    } else {
                        // Si es menos de la mitad, probablemente es color
                        $counters['color_pages'] = $value;
                        $counters['bw_pages'] = max(0, $counters['total_pages'] - $value);
                    }
                } elseif (count($foundCounterValues) >= 2) {
                    // Si tenemos múltiples valores, el mayor es B/N y el menor es color
                    $counters['color_pages'] = $foundCounterValues[0]; // Menor
                    $counters['bw_pages'] = $foundCounterValues[count($foundCounterValues) - 1]; // Mayor
                }
            }
        }
        
        // Si tenemos total pero no color ni bw, intentar calcular
        if (isset($counters['total_pages']) && $counters['total_pages'] > 0) {
            if (!isset($counters['color_pages']) || $counters['color_pages'] === 0) {
                // Intentar buscar en otros índices de prtMarkerLifeCount
                $foundValues = [];
                for ($index = 2; $index <= 10; $index++) {
                    try {
                        $oid = "1.3.6.1.2.1.43.10.2.1.4.1.{$index}";
                        $value = @snmpget($ip, $community, $oid, $this->timeout * 1000000, $this->retries);
                        if ($value !== false) {
                            $numericValue = $this->parseNumericValue($value);
                            if ($numericValue !== null && $numericValue > 0 && $numericValue < $counters['total_pages']) {
                                $foundValues[] = $numericValue;
                            }
                        }
                    } catch (\Exception $e) {
                        // Continuar
                    }
                }
                
                // Si encontramos valores, usar el mayor como color (generalmente el color es menor que B&W)
                if (!empty($foundValues)) {
                    sort($foundValues); // Ordenar de menor a mayor
                    // El valor más pequeño probablemente es color (típicamente hay menos páginas a color)
                    if (!isset($counters['color_pages'])) {
                        $counters['color_pages'] = $foundValues[0];
                    }
                }
            }
            
            // Si tenemos total y color, calcular bw
            if (isset($counters['total_pages']) && isset($counters['color_pages']) && (!isset($counters['bw_pages']) || $counters['bw_pages'] === 0)) {
                $counters['bw_pages'] = max(0, $counters['total_pages'] - $counters['color_pages']);
            }
            
            // Si tenemos total y bw, calcular color
            if (isset($counters['total_pages']) && isset($counters['bw_pages']) && (!isset($counters['color_pages']) || $counters['color_pages'] === 0)) {
                $counters['color_pages'] = max(0, $counters['total_pages'] - $counters['bw_pages']);
            }
        }

        return $counters;
    }

    protected function getEnvironment(string $ip, string $community): array
    {
        $environment = [];
        $oids = SnmpOid::where('category', 'environment')->get();

        foreach ($oids as $oid) {
            try {
                $value = @snmpget($ip, $community, $oid->oid, $this->timeout * 1000000, $this->retries);
                if ($value !== false) {
                    $environment[$oid->name] = $this->parseNumericValue($value);
                }
            } catch (\Exception $e) {
                // Continuar
            }
        }

        return $environment;
    }

    protected function parseConsumableLevel(string $value, ?SnmpOid $oid = null): ?int
    {
        $value = $this->cleanSnmpValue($value);
        $numeric = $this->parseNumericValue($value);

        if ($numeric === null) {
            return null;
        }

        // Si el OID tiene información de unidad, usarla
        if ($oid && $oid->unit === '%') {
            return (int) $numeric;
        }

        // Intentar detectar si ya es un porcentaje (0-100)
        if ($numeric >= 0 && $numeric <= 100) {
            return (int) $numeric;
        }

        // Si es un valor grande, podría ser un nivel máximo, intentar calcular porcentaje
        // Esto es heurístico y puede necesitar ajustes según el fabricante
        return null;
    }

    protected function parseNumericValue(string $value): ?int
    {
        $value = $this->cleanSnmpValue($value);
        $numeric = filter_var($value, FILTER_VALIDATE_INT);
        return $numeric !== false ? $numeric : null;
    }

    protected function parseUptime(string $value): int
    {
        $value = $this->cleanSnmpValue($value);
        // SNMP uptime viene en centésimas de segundo
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) ($matches[1] / 100);
        }
        return 0;
    }

    protected function cleanSnmpValue(string $value): string
    {
        // Limpiar el valor SNMP (remover tipo y comillas)
        return trim(preg_replace('/^[^:]*:\s*/', '', $value), ' "');
    }
}

