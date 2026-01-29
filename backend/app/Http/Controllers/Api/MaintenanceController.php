<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class MaintenanceController extends Controller
{
    /**
     * Crear backup completo de la base de datos
     */
    public function createBackup(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $dbName = config('database.connections.pgsql.database');
            $dbUser = config('database.connections.pgsql.username');
            $dbPassword = config('database.connections.pgsql.password');

            $backupName = $request->input('name') 
                ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->input('name'))
                : 'backup_' . Carbon::now()->format('Y-m-d_H-i-s');
            
            $backupFileName = $backupName . '.sql.gz';
            $backupPath = storage_path('app/backups');
            
            // Crear directorio si no existe
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $fullPath = $backupPath . '/' . $backupFileName;

            // Ejecutar pg_dump comprimido
            $env = ['PGPASSWORD' => $dbPassword];
            
            // Intentar con docker primero, luego sin docker
            $command = sprintf(
                'docker exec -e PGPASSWORD=%s jnc-postgres pg_dump -U %s -d %s | gzip',
                escapeshellarg($dbPassword),
                escapeshellarg($dbUser),
                escapeshellarg($dbName)
            );

            $process = Process::fromShellCommandline($command);
            $process->setEnv($env);
            $process->setTimeout(600); // 10 minutos
            
            $process->run();
            
            if (!$process->isSuccessful()) {
                // Intentar sin docker (instalación nativa)
                $command = sprintf(
                    'PGPASSWORD=%s pg_dump -U %s -d %s -h %s | gzip > %s',
                    escapeshellarg($dbPassword),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbName),
                    escapeshellarg(config('database.connections.pgsql.host', 'localhost')),
                    escapeshellarg($fullPath)
                );
                
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(600);
                $process->run();
                
                if (!$process->isSuccessful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear backup: ' . $process->getErrorOutput()
                    ], 500);
                }
            } else {
                // Guardar el output comprimido
                file_put_contents($fullPath, $process->getOutput());
            }

            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El backup se creó pero está vacío o no se encontró el archivo.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup creado exitosamente',
                'filename' => $backupFileName,
                'size' => filesize($fullPath),
                'path' => 'backups/' . $backupFileName,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar backup
     */
    public function downloadBackup($filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));

        if (!file_exists($backupPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup no encontrado'
            ], 404);
        }

        return response()->download($backupPath);
    }

    /**
     * Listar backups disponibles
     */
    public function listBackups()
    {
        $backupPath = storage_path('app/backups');
        
        if (!file_exists($backupPath)) {
            return response()->json([
                'success' => true,
                'backups' => []
            ]);
        }

        $files = glob($backupPath . '/*.{sql,sql.gz}', GLOB_BRACE);
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'created_at' => Carbon::createFromTimestamp(filemtime($file))->toISOString(),
            ];
        }

        // Ordenar por fecha (más reciente primero)
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return response()->json([
            'success' => true,
            'backups' => $backups
        ]);
    }

    /**
     * Eliminar backup
     */
    public function deleteBackup(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
        ]);

        try {
            $backupFileName = basename($request->input('filename'));
            $backupPath = storage_path('app/backups/' . $backupFileName);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo de backup no existe.'
                ], 404);
            }

            unlink($backupPath);

            return response()->json([
                'success' => true,
                'message' => "Backup eliminado: {$backupFileName}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar todos los datos (manteniendo usuarios, logos y configuraciones)
     */
    public function cleanAllData(Request $request)
    {
        $request->validate([
            'confirm' => 'required|boolean',
        ]);

        if (!$request->boolean('confirm')) {
            return response()->json([
                'success' => false,
                'message' => 'Debes confirmar la acción'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Tablas a borrar en orden (primero las hijas, luego las padres)
            // Orden importante para respetar foreign keys sin necesidad de desactivarlas
            $tablesToDelete = [
                // Primero: Tablas hijas (sin dependencias)
                'printer_print_logs',
                'printer_status_snapshots',
                'consumable_installation_photos',
                'stock_movements',
                'order_comments',
                'order_entry_items',
                'order_items',
                'snmp_sync_history',
                // Segundo: Tablas intermedias
                'consumable_installations',
                'order_entries',
                'alerts',
                // Tercero: Tablas principales
                'printers',
                'stocks',
                'consumable_references',
                'consumables',
                'orders',
            ];

            $deletedCounts = [];
            foreach ($tablesToDelete as $table) {
                try {
                    // Verificar si la tabla existe antes de borrarla
                    $exists = DB::select("SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    )", [$table]);
                    
                    if ($exists && $exists[0]->exists) {
                        $count = DB::table($table)->count();
                        // Usar DELETE en lugar de TRUNCATE para evitar problemas de permisos
                        // DELETE respeta foreign keys y no requiere permisos especiales
                        DB::table($table)->delete();
                        $deletedCounts[$table] = $count;
                    }
                } catch (\Exception $e) {
                    // Si hay error con foreign keys, intentar truncar con CASCADE
                    try {
                        if ($exists && $exists[0]->exists) {
                            $count = DB::table($table)->count();
                            // Intentar TRUNCATE CASCADE si DELETE falla
                            DB::statement("TRUNCATE TABLE {$table} CASCADE");
                            $deletedCounts[$table] = $count;
                        }
                    } catch (\Exception $e2) {
                        // Si también falla, registrar el error pero continuar
                        \Log::warning("No se pudo borrar la tabla {$table}: " . $e2->getMessage());
                        continue;
                    }
                }
            }

            // Limpiar storage de fotos de impresoras
            $printerPhotosPath = storage_path('app/public/printer-photos');
            if (file_exists($printerPhotosPath)) {
                $files = glob($printerPhotosPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Limpiar storage de fotos de instalaciones
            $installationPhotosPath = storage_path('app/public/installation-photos');
            if (file_exists($installationPhotosPath)) {
                $files = glob($installationPhotosPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            DB::commit();

            // Limpiar cache y colas
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('queue:clear');

            // Generar resumen de lo borrado
            $summary = [];
            $totalDeleted = 0;
            foreach ($deletedCounts as $table => $count) {
                if ($count > 0) {
                    $summary[] = "$table: $count registros";
                    $totalDeleted += $count;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Datos limpiados exitosamente. Se mantuvieron usuarios, logos y configuraciones.',
                'summary' => $summary,
                'total_deleted' => $totalDeleted,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar datos: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

