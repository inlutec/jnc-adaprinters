<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PollPrinterSnmp;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\Printer;
use App\Models\Province;
use App\Models\Site;
use App\Models\SnmpOidProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;

class PrinterImportController extends Controller
{
    /**
     * Subir y parsear archivo CSV
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'csv' => ['required', File::types(['csv', 'txt'])->max(10240)], // 10MB max
            ]);

            $file = $request->file('csv');
            
            if (!$file) {
                Log::error('No se recibió archivo CSV');
                return response()->json([
                    'message' => 'No se recibió ningún archivo',
                ], 422);
            }

            $path = $file->getRealPath();
            
            if (!$path) {
                Log::error('No se pudo obtener la ruta del archivo CSV');
                return response()->json([
                    'message' => 'Error al procesar el archivo',
                ], 500);
            }

        // Detectar delimitador (coma o punto y coma)
        $delimiter = $this->detectDelimiter($path);

        // Leer CSV
        $rows = [];
        $columns = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return response()->json([
                'message' => 'Error al leer el archivo CSV',
            ], 500);
        }

        // Leer encabezados
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            return response()->json([
                'message' => 'El archivo CSV está vacío o no tiene encabezados',
            ], 422);
        }

        // Limpiar encabezados (eliminar BOM y espacios)
        $columns = array_map(function ($header) {
            return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header));
        }, $headers);

        // Leer filas
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Saltar filas vacías
            if (empty(array_filter($row))) {
                continue;
            }

            // Asegurar que la fila tenga el mismo número de columnas que los encabezados
            $rowData = [];
            foreach ($columns as $index => $column) {
                $rowData[$column] = $row[$index] ?? '';
            }
            $rows[] = $rowData;
        }

        fclose($handle);

        Log::info('CSV procesado correctamente', [
            'columns_count' => count($columns),
            'rows_count' => count($rows),
        ]);

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
            'total_rows' => count($rows),
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación al subir CSV', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al procesar CSV', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error al procesar el archivo CSV: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar vista previa de la importación
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'column_mapping' => ['required', 'array'],
        ]);

        $rows = $validated['rows'];
        $columnMapping = $validated['column_mapping'];

        // Validar que IP esté mapeada
        if (!in_array('ip_address', $columnMapping)) {
            return response()->json([
                'message' => 'La columna de Dirección IP es obligatoria',
            ], 422);
        }

        $preview = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $mappedData = $this->mapRowData($row, $columnMapping);
            
            // Validar IP
            if (empty($mappedData['ip_address'])) {
                $errors[] = "Fila " . ($index + 1) . ": La dirección IP es obligatoria";
                continue;
            }

            if (!filter_var($mappedData['ip_address'], FILTER_VALIDATE_IP)) {
                $errors[] = "Fila " . ($index + 1) . ": La dirección IP '{$mappedData['ip_address']}' no es válida";
                continue;
            }

            // Verificar si ya existe
            $exists = Printer::where('ip_address', $mappedData['ip_address'])->exists();
            $mappedData['_exists'] = $exists;
            $mappedData['_row_index'] = $index;

            $preview[] = $mappedData;
        }

        return response()->json([
            'preview' => $preview,
            'errors' => $errors,
            'total_rows' => count($preview),
            'existing_count' => count(array_filter($preview, fn($p) => $p['_exists'])),
        ]);
    }

    /**
     * Procesar la importación
     */
    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'column_mapping' => ['required', 'array'],
            'oid_profile_mode' => ['required', 'string', 'in:single,per-row'],
            'default_oid_profile_id' => ['required_if:oid_profile_mode,single', 'nullable', 'exists:snmp_oid_profiles,id'],
            'row_oid_profiles' => ['required_if:oid_profile_mode,per-row', 'nullable', 'array'],
            'row_oid_profiles.*' => ['required_with:row_oid_profiles', 'exists:snmp_oid_profiles,id'],
            'sync_after_import' => ['sometimes', 'boolean'],
        ]);

        $rows = $validated['rows'];
        $columnMapping = $validated['column_mapping'];
        $oidProfileMode = $validated['oid_profile_mode'];
        $defaultOidProfileId = $validated['default_oid_profile_id'] ?? null;
        $rowOidProfiles = $validated['row_oid_profiles'] ?? [];
        $syncAfterImport = $validated['sync_after_import'] ?? true;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        Log::info('Iniciando importación masiva', [
            'total_rows' => count($rows),
            'oid_profile_mode' => $oidProfileMode,
            'default_oid_profile_id' => $defaultOidProfileId,
            'first_row_keys' => !empty($rows[0]) ? array_keys($rows[0]) : [],
        ]);

        // Procesar cada impresora individualmente con su propia transacción
        // Esto evita que un error en una impresora afecte a las demás
        foreach ($rows as $index => $row) {
            try {
                // Si los datos ya vienen mapeados (tienen ip_address directamente), usarlos tal cual
                // Si no, mapearlos usando column_mapping
                if (isset($row['ip_address'])) {
                    // Los datos ya están mapeados desde el preview
                    $mappedData = $row;
                    // Limpiar campos internos del preview
                    unset($mappedData['_exists'], $mappedData['_row_index']);
                } else {
                    // Los datos vienen sin mapear, aplicar mapeo
                    $mappedData = $this->mapRowData($row, $columnMapping);
                }

                // Validar IP
                if (empty($mappedData['ip_address'])) {
                    $errorMsg = "Fila " . ($index + 1) . ": La dirección IP es obligatoria";
                    $errors[] = $errorMsg;
                    $skipped++;
                    Log::warning('Fila omitida: IP faltante', ['row_index' => $index + 1]);
                    continue;
                }

                if (!filter_var($mappedData['ip_address'], FILTER_VALIDATE_IP)) {
                    $errorMsg = "Fila " . ($index + 1) . ": La dirección IP '{$mappedData['ip_address']}' no es válida";
                    $errors[] = $errorMsg;
                    $skipped++;
                    Log::warning('Fila omitida: IP inválida', ['row_index' => $index + 1, 'ip' => $mappedData['ip_address']]);
                    continue;
                }

                // Verificar si ya existe - omitir silenciosamente sin error
                $existing = Printer::where('ip_address', $mappedData['ip_address'])->first();
                if ($existing) {
                    $skipped++;
                    Log::info('Impresora omitida: ya existe en la base de datos', [
                        'row_index' => $index + 1,
                        'ip' => $mappedData['ip_address'],
                        'existing_id' => $existing->id,
                        'existing_name' => $existing->name,
                    ]);
                    continue;
                }

                // Determinar perfil OID
                $oidProfileId = null;
                if ($oidProfileMode === 'single') {
                    $oidProfileId = $defaultOidProfileId;
                } else {
                    $oidProfileId = $rowOidProfiles[$index] ?? null;
                }

                // Procesar cada impresora - intentar sin transacción primero para evitar problemas
                $printer = null;
                
                Log::info('Intentando crear impresora', [
                    'row_index' => $index + 1,
                    'ip' => $mappedData['ip_address'],
                    'name' => $mappedData['name'] ?? null,
                ]);
                
                try {
                    // Preparar datos de creación
                    $printerData = [
                        'name' => $mappedData['name'] ?? "Impresora {$mappedData['ip_address']}",
                        'ip_address' => $mappedData['ip_address'],
                        'hostname' => $mappedData['hostname'] ?? null,
                        'mac_address' => $mappedData['mac_address'] ?? null,
                        'serial_number' => $mappedData['serial_number'] ?? null,
                        'brand' => $mappedData['brand'] ?? null,
                        'model' => $mappedData['model'] ?? null,
                        'firmware_version' => $mappedData['firmware_version'] ?? null,
                        'status' => 'discovered',
                        'supports_snmp' => true,
                        'discovery_source' => 'csv_import',
                        'snmp_oid_profile_id' => $oidProfileId,
                        'province_id' => $mappedData['province_id'] ?? null,
                        'site_id' => $mappedData['site_id'] ?? null,
                        'department_id' => $mappedData['department_id'] ?? null,
                        'notes' => $mappedData['notes'] ?? null,
                        'last_seen_at' => now(),
                    ];
                    
                    Log::debug('Datos de impresora preparados', [
                        'row_index' => $index + 1,
                        'data_keys' => array_keys($printerData),
                    ]);
                    
                    // Crear impresora directamente sin transacción para evitar problemas de estado
                    $printer = Printer::create($printerData);
                    
                    Log::info('Impresora creada exitosamente', [
                        'row_index' => $index + 1,
                        'printer_id' => $printer->id,
                        'ip' => $mappedData['ip_address'],
                    ]);
                } catch (\Exception $createException) {
                    // Si falla, verificar si se creó de todas formas
                    $existing = Printer::where('ip_address', $mappedData['ip_address'])->first();
                    if ($existing) {
                        $printer = $existing;
                        Log::info('Impresora ya existe después de error', [
                            'row_index' => $index + 1,
                            'printer_id' => $existing->id,
                            'ip' => $mappedData['ip_address'],
                            'original_error' => $createException->getMessage(),
                        ]);
                    } else {
                        // Re-lanzar el error para que se maneje en el catch externo
                        throw $createException;
                    }
                }

                // Guardar campos personalizados fuera de la transacción principal
                // para evitar que errores en campos personalizados afecten la creación
                if ($printer && !empty($mappedData['custom_fields'])) {
                    try {
                        foreach ($mappedData['custom_fields'] as $slug => $value) {
                            if (!empty($value)) {
                                try {
                                    $printer->setCustomFieldValue($slug, $value);
                                } catch (\Exception $e) {
                                    Log::warning('Error al guardar campo personalizado', [
                                        'printer_id' => $printer->id,
                                        'slug' => $slug,
                                        'error' => $e->getMessage(),
                                    ]);
                                    // Continuar con el siguiente campo
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error al guardar campos personalizados', [
                            'printer_id' => $printer->id,
                            'error' => $e->getMessage(),
                        ]);
                        // No fallar la importación por campos personalizados
                    }
                }

                $imported++;
                Log::info('Impresora importada', ['row_index' => $index + 1, 'printer_id' => $printer->id, 'ip' => $mappedData['ip_address']]);

                // Encolar sincronización si está habilitada
                // IMPORTANTE: Deshabilitar temporalmente para evitar problemas de serialización
                // Se puede habilitar después de verificar que la importación funciona
                if ($syncAfterImport && $printer) {
                    try {
                        // Usar solo el ID en lugar del objeto completo para evitar problemas de serialización
                        PollPrinterSnmp::dispatch(Printer::find($printer->id));
                        Log::debug('Sincronización encolada', ['printer_id' => $printer->id]);
                    } catch (\Exception $dispatchException) {
                        // No fallar la importación si no se puede encolar la sincronización
                        Log::warning('Error al encolar sincronización (no crítico)', [
                            'printer_id' => $printer->id,
                            'error' => $dispatchException->getMessage(),
                            'error_class' => get_class($dispatchException),
                        ]);
                        // Continuar sin fallar
                    }
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // Manejar errores específicos de base de datos
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                $sqlState = $e->errorInfo[0] ?? null;
                
                $errorInfo = $e->errorInfo ?? [];
                
                Log::error('QueryException al importar fila', [
                    'row_index' => $index + 1,
                    'error_code' => $errorCode,
                    'sql_state' => $sqlState,
                    'error_info' => $errorInfo,
                    'error_message' => $errorMessage,
                    'ip' => $mappedData['ip_address'] ?? 'unknown',
                ]);
                
                // Si es un error de duplicado (23505), omitir silenciosamente
                if (str_contains($errorMessage, '23505') || str_contains($errorMessage, 'duplicate key') || $sqlState === '23505') {
                    $skipped++;
                    Log::info('Impresora omitida: duplicado detectado', [
                        'row_index' => $index + 1,
                        'ip' => $mappedData['ip_address'] ?? 'unknown',
                    ]);
                    continue;
                }
                
                // Si es error de transacción fallida (25P02), reintentar sin transacción
                if (str_contains($errorMessage, '25P02') || str_contains($errorMessage, 'current transaction is aborted') || $sqlState === '25P02') {
                    try {
                        // Reintentar sin transacción
                        $existing = Printer::where('ip_address', $mappedData['ip_address'] ?? '')->first();
                        if ($existing) {
                            $skipped++;
                            Log::info('Impresora omitida: ya existe (reintento)', [
                                'row_index' => $index + 1,
                                'ip' => $mappedData['ip_address'] ?? 'unknown',
                            ]);
                            continue;
                        }
                    } catch (\Exception $retryException) {
                        $errorMsg = "Fila " . ($index + 1) . ": Error de transacción - " . $retryException->getMessage();
                        $errors[] = $errorMsg;
                        $skipped++;
                        Log::error('Error al reintentar importación', [
                            'row_index' => $index + 1,
                            'error' => $retryException->getMessage(),
                        ]);
                        continue;
                    }
                }
                
                // Para "Permission denied", verificar si la impresora se creó de todas formas
                if (stripos($errorMessage, 'Permission denied') !== false || stripos($errorMessage, 'permission denied') !== false) {
                    Log::warning('Error Permission denied detectado, verificando si la impresora se creó', [
                        'row_index' => $index + 1,
                        'ip' => $mappedData['ip_address'] ?? 'unknown',
                    ]);
                    
                    // Verificar si la impresora se creó a pesar del error
                    try {
                        $existing = Printer::where('ip_address', $mappedData['ip_address'] ?? '')->first();
                        if ($existing) {
                            $skipped++;
                            Log::info('Impresora omitida: ya existe (después de Permission denied)', [
                                'row_index' => $index + 1,
                                'ip' => $mappedData['ip_address'] ?? 'unknown',
                                'printer_id' => $existing->id,
                            ]);
                            continue;
                        }
                    } catch (\Exception $checkException) {
                        Log::error('Error al verificar impresora existente', [
                            'row_index' => $index + 1,
                            'error' => $checkException->getMessage(),
                        ]);
                    }
                }
                
                $errorMsg = "Fila " . ($index + 1) . ": Error al crear impresora - " . $errorMessage;
                $errors[] = $errorMsg;
                $skipped++;
                continue;
            } catch (\Throwable $e) {
                $errorMsg = "Fila " . ($index + 1) . ": Error al crear impresora - " . $e->getMessage();
                $errors[] = $errorMsg;
                $skipped++;
                
                // Log detallado
                $logData = [
                    'row_index' => $index + 1,
                    'error_class' => get_class($e),
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                    'ip' => $mappedData['ip_address'] ?? 'unknown',
                ];
                
                // Agregar errorInfo si es QueryException
                if ($e instanceof \Illuminate\Database\QueryException) {
                    $logData['error_info'] = $e->errorInfo ?? [];
                    $logData['sql_state'] = $e->errorInfo[0] ?? null;
                }
                
                // Agregar trace solo si no es muy largo
                $trace = $e->getTraceAsString();
                if (strlen($trace) < 2000) {
                    $logData['trace'] = $trace;
                }
                
                Log::error('Error al importar fila', $logData);
                
                // Verificar si la impresora se creó a pesar del error
                if (isset($mappedData['ip_address'])) {
                    try {
                        $existing = Printer::where('ip_address', $mappedData['ip_address'])->first();
                        if ($existing) {
                            $skipped--; // Ya se contó como skipped, ajustar
                            Log::info('Impresora se creó a pesar del error', [
                                'row_index' => $index + 1,
                                'printer_id' => $existing->id,
                                'ip' => $mappedData['ip_address'],
                            ]);
                            $imported++;
                            continue;
                        }
                    } catch (\Exception $checkException) {
                        // Ignorar errores al verificar
                    }
                }
                
                continue;
            }
        }

        Log::info('Importación masiva completada', [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors_count' => count($errors),
        ]);

        return response()->json([
            'message' => 'Importación completada',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'synced' => $syncAfterImport,
        ]);
    }

    /**
     * Mapear datos de una fila según el mapeo de columnas
     */
    private function mapRowData(array $row, array $columnMapping): array
    {
        $mapped = [
            'custom_fields' => [],
        ];

        foreach ($columnMapping as $csvColumn => $fieldName) {
            if (empty($fieldName) || !isset($row[$csvColumn])) {
                continue;
            }

            $value = trim($row[$csvColumn] ?? '');

            // Si es un campo personalizado
            if (str_starts_with($fieldName, 'custom_field:')) {
                $slug = str_replace('custom_field:', '', $fieldName);
                $mapped['custom_fields'][$slug] = $value;
                continue;
            }

            // Procesar campos especiales (province_id, site_id, department_id)
            if (in_array($fieldName, ['province_id', 'site_id', 'department_id'])) {
                $mapped[$fieldName] = $this->resolveLocationId($fieldName, $value);
            } else {
                $mapped[$fieldName] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Resolver ID de ubicación (provincia, sede, departamento) desde nombre o ID
     */
    private function resolveLocationId(string $fieldName, string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Si es numérico, asumir que es un ID
        if (is_numeric($value)) {
            $id = (int) $value;
            // Verificar que existe
            switch ($fieldName) {
                case 'province_id':
                    return Province::where('id', $id)->exists() ? $id : null;
                case 'site_id':
                    return Site::where('id', $id)->exists() ? $id : null;
                case 'department_id':
                    return Department::where('id', $id)->exists() ? $id : null;
            }
        }

        // Buscar por nombre
        switch ($fieldName) {
            case 'province_id':
                $province = Province::where('name', 'ilike', $value)->first();
                return $province?->id;
            case 'site_id':
                $site = Site::where('name', 'ilike', $value)->first();
                return $site?->id;
            case 'department_id':
                $department = Department::where('name', 'ilike', $value)->first();
                return $department?->id;
        }

        return null;
    }

    /**
     * Detectar delimitador del CSV (coma o punto y coma)
     */
    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ',';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ',';
        }

        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }
}

