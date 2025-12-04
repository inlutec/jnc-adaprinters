<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class BackupController extends Controller
{
    public function index()
    {
        // Obtener lista de backups disponibles
        $backups = $this->getBackupList();
        
        return view('backup.index', [
            'backups' => $backups,
            'formatBytes' => [$this, 'formatBytes'],
        ]);
    }
    
    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function createBackup(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $dbName = config('database.connections.pgsql.database');
            $dbUser = config('database.connections.pgsql.username');
            $dbPassword = config('database.connections.pgsql.password');
            $dbHost = config('database.connections.pgsql.host');
            $dbPort = config('database.connections.pgsql.port', 5432);

            $backupName = $request->input('name') 
                ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->input('name'))
                : 'backup_' . Carbon::now()->format('Y-m-d_H-i-s');
            
            $backupFileName = $backupName . '.sql';
            $backupPath = storage_path('app/backups');
            
            // Crear directorio si no existe
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $fullPath = $backupPath . '/' . $backupFileName;

            // Ejecutar pg_dump desde el contenedor de postgres usando formato SQL plano
            // Usamos docker exec con redirección de salida
            $env = ['PGPASSWORD' => $dbPassword];
            
            $command = sprintf(
                'docker exec -e PGPASSWORD=%s jnc-postgres pg_dump -U %s -d %s',
                escapeshellarg($dbPassword),
                escapeshellarg($dbUser),
                escapeshellarg($dbName)
            );

            $process = Process::fromShellCommandline($command);
            $process->setEnv($env);
            $process->setTimeout(300); // 5 minutos
            
            // Ejecutar y capturar la salida
            $process->run();
            
            if (!$process->isSuccessful()) {
                return redirect()->route('backup.index')
                    ->with('error', 'Error al crear backup: ' . $process->getErrorOutput());
            }

            // Guardar el output del proceso en el archivo
            $output = $process->getOutput();
            file_put_contents($fullPath, $output);

            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                return redirect()->route('backup.index')
                    ->with('error', 'El backup se creó pero está vacío o no se encontró el archivo.');
            }

            return redirect()->route('backup.index')
                ->with('success', "Backup creado exitosamente: {$backupFileName}");
        } catch (\Exception $e) {
            return redirect()->route('backup.index')
                ->with('error', 'Error al crear backup: ' . $e->getMessage());
        }
    }

    public function restoreBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        try {
            $backupFileName = basename($request->input('backup_file'));
            $backupPath = storage_path('app/backups/' . $backupFileName);

            if (!file_exists($backupPath)) {
                return redirect()->route('backup.index')
                    ->with('error', 'El archivo de backup no existe.');
            }

            $dbName = config('database.connections.pgsql.database');
            $dbUser = config('database.connections.pgsql.username');
            $dbPassword = config('database.connections.pgsql.password');
            $dbHost = config('database.connections.pgsql.host');
            $dbPort = config('database.connections.pgsql.port', 5432);

            // Primero, terminar todas las conexiones a la base de datos
            $this->terminateConnections($dbName, $dbUser, $dbPassword, $dbHost, $dbPort);

            // Copiar el archivo SQL al contenedor de postgres
            $copyCommand = sprintf(
                'docker cp %s jnc-postgres:/tmp/%s',
                escapeshellarg($backupPath),
                escapeshellarg($backupFileName)
            );
            
            $copyProcess = Process::fromShellCommandline($copyCommand);
            $copyProcess->setTimeout(60);
            $copyProcess->run();

            if (!$copyProcess->isSuccessful()) {
                return redirect()->route('backup.index')
                    ->with('error', 'Error al copiar archivo al contenedor: ' . $copyProcess->getErrorOutput());
            }

            // Ejecutar psql para restaurar el backup SQL
            $env = ['PGPASSWORD' => $dbPassword];
            $command = sprintf(
                'docker exec -e PGPASSWORD=%s jnc-postgres psql -U %s -d %s -f /tmp/%s',
                escapeshellarg($dbPassword),
                escapeshellarg($dbUser),
                escapeshellarg($dbName),
                escapeshellarg($backupFileName)
            );

            $process = Process::fromShellCommandline($command);
            $process->setEnv($env);
            $process->setTimeout(600); // 10 minutos
            $process->run();

            if (!$process->isSuccessful()) {
                return redirect()->route('backup.index')
                    ->with('error', 'Error al restaurar backup: ' . $process->getErrorOutput());
            }

            // Limpiar cache
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            return redirect()->route('backup.index')
                ->with('success', "Backup restaurado exitosamente: {$backupFileName}");
        } catch (\Exception $e) {
            return redirect()->route('backup.index')
                ->with('error', 'Error al restaurar backup: ' . $e->getMessage());
        }
    }

    public function deleteBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|string',
        ]);

        try {
            $backupFileName = basename($request->input('backup_file'));
            $backupPath = storage_path('app/backups/' . $backupFileName);

            if (!file_exists($backupPath)) {
                return redirect()->route('backup.index')
                    ->with('error', 'El archivo de backup no existe.');
            }

            unlink($backupPath);

            return redirect()->route('backup.index')
                ->with('success', "Backup eliminado: {$backupFileName}");
        } catch (\Exception $e) {
            return redirect()->route('backup.index')
                ->with('error', 'Error al eliminar backup: ' . $e->getMessage());
        }
    }

    public function downloadBackup($filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));

        if (!file_exists($backupPath)) {
            abort(404, 'Backup no encontrado');
        }

        return response()->download($backupPath);
    }

    public function cleanData(Request $request)
    {
        try {
            DB::beginTransaction();

            // Tablas a borrar (manteniendo usuarios, logos, configuraciones SNMP y estructura geográfica)
            // IMPORTANTE: NO se borran snmp_profiles, snmp_oids, snmp_sync_configs (son configuraciones)
            $tablesToTruncate = [
                // Impresoras y sus datos
                'printer_print_logs',
                'printer_status_snapshots',
                'printers',
                // Consumibles e inventario
                'consumable_installation_photos',
                'consumable_installations',
                'stock_movements',
                'stocks',
                'consumable_references',
                'consumables',
                // Pedidos y entradas
                'order_comments',
                'order_entry_items',
                'order_entries',
                'order_items',
                'orders',
                // Alertas e historial (solo historial, NO configuraciones SNMP)
                'alerts',
                'snmp_sync_history', // Solo historial de sincronizaciones, NO las configuraciones
            ];

            // Desactivar foreign keys temporalmente para poder truncar en cualquier orden
            DB::statement('SET session_replication_role = replica;');

            $deletedCounts = [];
            foreach ($tablesToTruncate as $table) {
                try {
                    // Verificar si la tabla existe antes de truncarla
                    $exists = DB::select("SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name = ?
                    )", [$table]);
                    
                    if ($exists && $exists[0]->exists) {
                        $count = DB::table($table)->count();
                        DB::table($table)->truncate();
                        $deletedCounts[$table] = $count;
                    }
                } catch (\Exception $e) {
                    // Si la tabla no existe o hay error, continuar
                    continue;
                }
            }

            // Reactivar foreign keys
            DB::statement('SET session_replication_role = DEFAULT;');

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
            foreach ($deletedCounts as $table => $count) {
                if ($count > 0) {
                    $summary[] = "$table: $count registros";
                }
            }

            $message = 'Datos limpiados exitosamente. Se mantuvieron usuarios, logos y configuraciones.';
            if (!empty($summary)) {
                $message .= ' Borrados: ' . implode(', ', $summary);
            }

            return redirect()->route('backup.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('backup.index')
                ->with('error', 'Error al limpiar datos: ' . $e->getMessage());
        }
    }

    protected function getBackupList(): array
    {
        $backupPath = storage_path('app/backups');
        
        if (!file_exists($backupPath)) {
            return [];
        }

        $files = glob($backupPath . '/*.sql');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'created_at' => Carbon::createFromTimestamp(filemtime($file)),
            ];
        }

        // Ordenar por fecha (más reciente primero)
        usort($backups, function ($a, $b) {
            return $b['created_at']->timestamp <=> $a['created_at']->timestamp;
        });

        return $backups;
    }

    protected function terminateConnections($dbName, $dbUser, $dbPassword, $dbHost, $dbPort): void
    {
        try {
            // Conectar a la base de datos postgres para terminar conexiones
            $pdo = new \PDO(
                "pgsql:host={$dbHost};port={$dbPort};dbname=postgres",
                $dbUser,
                $dbPassword
            );

            $stmt = $pdo->prepare("
                SELECT pg_terminate_backend(pid)
                FROM pg_stat_activity
                WHERE datname = :dbname
                AND pid <> pg_backend_pid()
            ");
            $stmt->execute(['dbname' => $dbName]);
        } catch (\Exception $e) {
            // Si falla, continuar de todos modos
        }
    }
}

