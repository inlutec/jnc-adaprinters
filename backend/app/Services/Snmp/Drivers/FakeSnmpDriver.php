<?php

namespace App\Services\Snmp\Drivers;

use App\Models\Printer;
use Illuminate\Support\Arr;

class FakeSnmpDriver
{
    public function poll(Printer $printer): array
    {
        $metrics = $printer->metrics ?? [];
        $previousTotal = (int) ($metrics['total_pages'] ?? 15000);

        $delta = rand(0, 75);
        $totalPages = $previousTotal + $delta;
        $colorPages = $printer->is_color
            ? max(0, (int) (($totalPages) * rand(15, 60) / 100))
            : 0;
        $bwPages = max(0, $totalPages - $colorPages);

        $consumables = collect([
            ['slot' => 'K', 'label' => 'Negro'],
            ['slot' => 'C', 'label' => 'Cian'],
            ['slot' => 'M', 'label' => 'Magenta'],
            ['slot' => 'Y', 'label' => 'Amarillo'],
        ])
            ->take($printer->is_color ? 4 : 1)
            ->map(function ($item) {
                return [
                    'slot' => $item['slot'],
                    'label' => $item['label'],
                    'level' => rand(10, 100),
                ];
            })
            ->values()
            ->all();

        return [
            'status' => $delta === 0 ? 'idle' : 'online',
            'error_code' => null,
            'uptime_seconds' => rand(1_000, 120_000),
            'consumables' => $consumables,
            'environment' => [
                'temperature_c' => rand(18, 32),
                'humidity' => rand(35, 70),
            ],
            'counters' => [
                'total_pages' => $totalPages,
                'color_pages' => $colorPages,
                'bw_pages' => $bwPages,
            ],
            'raw' => [
                'driver' => 'fake',
                'sampled_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function discover(string $ip): ?array
    {
        // Simular descubrimiento de impresora
        $isPrinter = rand(0, 10) > 2; // 80% de probabilidad de ser impresora

        if (! $isPrinter) {
            return null;
        }

        $brands = ['HP', 'Canon', 'Epson', 'Brother'];
        $brand = $brands[array_rand($brands)];
        $model = $brand . ' ' . rand(100, 999);

        $isColor = rand(0, 1) === 1;
        $consumables = collect([
            ['name' => 'Negro', 'color' => 'black', 'nivel_porcentaje' => rand(10, 100)],
        ]);

        if ($isColor) {
            $consumables->push(
                ['name' => 'Cian', 'color' => 'cyan', 'nivel_porcentaje' => rand(10, 100)],
                ['name' => 'Magenta', 'color' => 'magenta', 'nivel_porcentaje' => rand(10, 100)],
                ['name' => 'Amarillo', 'color' => 'yellow', 'nivel_porcentaje' => rand(10, 100)],
            );
        }

        return [
            'ip_address' => $ip,
            'hostname' => "printer-{$ip}",
            'description' => "{$brand} {$model} Multifunction Printer",
            'object_id' => '1.3.6.1.4.1.11.2.3.9.1',
            'consumables' => $consumables->toArray(),
            'counters' => [
                'total_pages' => rand(1000, 50000),
                'color_pages' => $isColor ? rand(500, 20000) : 0,
            ],
            'is_color' => $isColor,
        ];
    }
}

