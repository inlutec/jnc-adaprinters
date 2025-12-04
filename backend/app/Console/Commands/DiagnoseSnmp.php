<?php

namespace App\Console\Commands;

use App\Models\Printer;
use App\Services\Snmp\SnmpClient;
use Illuminate\Console\Command;

class DiagnoseSnmp extends Command
{
    protected $signature = 'snmp:diagnose {ip} {--community=public}';
    protected $description = 'Diagnostica valores SNMP crudos de una impresora';

    public function handle(SnmpClient $snmpClient)
    {
        $ip = $this->argument('ip');
        $community = $this->option('community') ?? 'public';

        $this->info("Diagnosticando impresora en {$ip}...");
        $this->newLine();

        // Buscar impresora o crear una temporal
        $printer = Printer::where('ip_address', $ip)->first();
        if (!$printer) {
            $printer = new Printer(['ip_address' => $ip, 'name' => 'Diagnóstico']);
        }

        // Leer valores SNMP crudos usando el driver real
        $this->readRawSnmpValues($ip, $community);

        // Probar con el servicio
        $result = $snmpClient->poll($printer);
        if ($result) {
            $this->info("Resultado del servicio SNMP:");
            $this->displayResult($result);
        }
    }

    protected function readRawSnmpValues(string $ip, string $community)
    {
        $this->info("=== Valores SNMP Crudos ===");
        
        // OIDs importantes para consumibles (RFC 3805)
        $this->info("Consultando OIDs estándar RFC 3805...");
        
        for ($index = 1; $index <= 10; $index++) {
            $typeOid = "1.3.6.1.2.1.43.11.1.1.2.1.{$index}";
            $descOid = "1.3.6.1.2.1.43.11.1.1.3.1.{$index}";
            $levelOid = "1.3.6.1.2.1.43.11.1.1.9.1.{$index}";
            $maxOid = "1.3.6.1.2.1.43.11.1.1.8.1.{$index}";

            $typeValue = @snmpget($ip, $community, $typeOid, 2000000, 1);
            $descValue = @snmpget($ip, $community, $descOid, 2000000, 1);
            $levelValue = @snmpget($ip, $community, $levelOid, 2000000, 1);
            $maxValue = @snmpget($ip, $community, $maxOid, 2000000, 1);

            if ($typeValue !== false || $levelValue !== false) {
                $this->line("--- Índice {$index} ---");
                if ($typeValue !== false) {
                    $this->line("Tipo: " . trim(preg_replace('/^[^:]*:\s*/', '', $typeValue), ' "'));
                }
                if ($descValue !== false) {
                    $this->line("Descripción: " . trim(preg_replace('/^[^:]*:\s*/', '', $descValue), ' "'));
                }
                if ($levelValue !== false) {
                    $levelRaw = trim(preg_replace('/^[^:]*:\s*/', '', $levelValue), ' "');
                    $level = (int)filter_var($levelRaw, FILTER_VALIDATE_INT);
                    $this->line("Nivel (crudo): {$levelRaw} ({$level})");
                } else {
                    $level = null;
                }
                if ($maxValue !== false) {
                    $maxRaw = trim(preg_replace('/^[^:]*:\s*/', '', $maxValue), ' "');
                    $max = (int)filter_var($maxRaw, FILTER_VALIDATE_INT);
                    $this->line("Máximo (crudo): {$maxRaw} ({$max})");
                    
                    if ($level !== null && $max > 0) {
                        $percentage = ($level / $max) * 100;
                        $this->line("→ Porcentaje calculado: " . round($percentage, 2) . "%");
                        $this->line("  (Nivel: {$level}, Máximo: {$max}, Ratio: " . round($level/$max, 4) . ")");
                        
                        // Mostrar diferentes interpretaciones posibles
                        if ($level > $max) {
                            $this->warn("  ⚠️  Nivel > Máximo - posible interpretación incorrecta");
                        }
                        if ($percentage > 100) {
                            $this->warn("  ⚠️  Porcentaje > 100%");
                        }
                    }
                }
                $this->newLine();
            }
        }

        // OIDs para contadores
        $this->info("=== Contadores ===");
        $counterOids = [
            'Total' => '1.3.6.1.2.1.43.10.2.1.4.1.1',
            'Color' => '1.3.6.1.2.1.43.10.2.1.4.1.2',
            'B&W' => '1.3.6.1.2.1.43.10.2.1.4.1.3',
        ];

        foreach ($counterOids as $name => $oid) {
            $value = @snmpget($ip, $community, $oid, 2000000, 1);
            if ($value !== false) {
                $clean = trim(preg_replace('/^[^:]*:\s*/', '', $value), ' "');
                $this->line("{$name}: {$clean}");
            }
        }
        
        // OIDs específicos de Lexmark para consumibles
        $this->newLine();
        $this->info("=== OIDs Específicos Lexmark ===");
        $lexmarkOids = [
            'Lexmark Black Level' => '1.3.6.1.4.1.641.2.1.2.1.1.4.1',
            'Lexmark Cyan Level' => '1.3.6.1.4.1.641.2.1.2.1.1.4.2',
            'Lexmark Magenta Level' => '1.3.6.1.4.1.641.2.1.2.1.1.4.3',
            'Lexmark Yellow Level' => '1.3.6.1.4.1.641.2.1.2.1.1.4.4',
        ];
        
        foreach ($lexmarkOids as $name => $oid) {
            $value = @snmpget($ip, $community, $oid, 2000000, 1);
            if ($value !== false) {
                $clean = trim(preg_replace('/^[^:]*:\s*/', '', $value), ' "');
                $this->line("{$name}: {$clean}");
            }
        }
    }

    protected function displayResult(array $result)
    {
        if (isset($result['consumables'])) {
            $this->newLine();
            $this->info("Consumibles detectados:");
            foreach ($result['consumables'] as $consumable) {
                $this->line(sprintf(
                    "  - %s (%s): %s%% | Raw: %s",
                    $consumable['name'] ?? 'Desconocido',
                    $consumable['color'] ?? 'N/A',
                    $consumable['nivel_porcentaje'] ?? 'N/A',
                    $consumable['raw_value'] ?? 'N/A'
                ));
            }
        }

        if (isset($result['counters'])) {
            $this->newLine();
            $this->info("Contadores:");
            foreach ($result['counters'] as $key => $value) {
                $this->line("  - {$key}: {$value}");
            }
        }
    }
}
