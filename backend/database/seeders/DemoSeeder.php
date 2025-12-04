<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Consumable;
use App\Models\Printer;
use App\Models\PrinterPrintLog;
use App\Models\PrinterStatusSnapshot;
use App\Models\Province;
use App\Models\Site;
use App\Models\SnmpProfile;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@jnc-adaprinters.local'],
            [
                'name' => 'Administrador ADA',
                'password' => bcrypt('admin123'),
            ]
        );

        $profiles = collect([
            [
                'name' => 'HP V2C',
                'version' => 'v2c',
                'community' => 'public',
                'is_default' => true,
                'description' => 'Perfil genérico para HP LaserJet',
            ],
            [
                'name' => 'Kyocera Secure',
                'version' => 'v3',
                'security_level' => 'authPriv',
                'security_username' => 'snmpadmin',
            ],
        ])->map(fn ($payload) => SnmpProfile::create($payload));

        $provinces = collect([
            ['name' => 'Sevilla', 'code' => 'SE'],
            ['name' => 'Málaga', 'code' => 'MA'],
            ['name' => 'Cádiz', 'code' => 'CA'],
        ])->map(function ($data) {
            return Province::create([
                ...$data,
                'region' => 'Andalucía',
            ]);
        });

        $sites = collect();
        $departments = collect();

        $provinces->each(function (Province $province) use (&$sites, &$departments) {
            $site = $province->sites()->create([
                'name' => "Sede Central {$province->name}",
                'code' => Str::upper(Str::substr($province->code, 0, 2)).'01',
                'address' => 'Av. Innovación s/n',
                'city' => $province->name,
                'postal_code' => '41000',
                'contact_email' => 'sede@ada.es',
            ]);

            $department = $site->departments()->create([
                'name' => 'Departamento TIC',
                'code' => "{$site->code}-TIC",
                'floor' => '3A',
                'contact_email' => 'tic@ada.es',
            ]);

            $sites->push($site);
            $departments->push($department);
        });

        $consumables = collect([
            [
                'name' => 'Tóner Negro 90A',
                'sku' => 'ADA-TN-90A',
                'type' => 'toner',
                'brand' => 'HP',
                'color' => 'black',
                'is_color' => false,
                'average_yield' => 10000,
                'unit_cost' => 120.50,
            ],
            [
                'name' => 'Kit Color CMYK 655A',
                'sku' => 'ADA-CLR-655A',
                'type' => 'toner',
                'brand' => 'HP',
                'color' => 'pack',
                'is_color' => true,
                'average_yield' => 8500,
                'unit_cost' => 410.00,
            ],
        ])->map(fn ($payload) => Consumable::create($payload));

        $printers = collect();
        foreach ($sites as $index => $site) {
            foreach (['mono', 'color'] as $variant) {
                $printer = Printer::create([
                    'snmp_profile_id' => $profiles[$index % $profiles->count()]->id,
                    'province_id' => $site->province_id,
                    'site_id' => $site->id,
                    'department_id' => $departments[$index]->id,
                    'name' => ($variant === 'mono' ? 'HP M607' : 'HP Color 776') . "-{$site->code}-{$variant}",
                    'hostname' => Str::slug(($variant === 'mono' ? 'hp-m607' : 'hp-776') . "-{$site->code}-{$variant}"),
                    'ip_address' => "10.10.{$index}.2" . ($variant === 'mono' ? '5' : '6'),
                    'serial_number' => "SN-ADA-{$site->code}-{$variant}",
                    'brand' => $variant === 'mono' ? 'HP' : 'Kyocera',
                    'model' => $variant === 'mono' ? 'LaserJet Enterprise M607' : 'TASKalfa 406ci',
                    'status' => collect(['online', 'online', 'warning', 'offline'])->random(),
                    'is_color' => $variant === 'color',
                    'last_sync_at' => now()->subMinutes(rand(2, 90)),
                    'last_seen_at' => now()->subMinutes(rand(1, 45)),
                    'metrics' => [
                        'cpu' => rand(18, 65),
                        'memory' => rand(25, 78),
                        'duty_cycle' => rand(50, 95),
                    ],
                    'snmp_data' => [
                        'consumables' => [
                            ['name' => 'Black', 'nivel_porcentaje' => rand(20, 90)],
                            ['name' => 'Cyan', 'nivel_porcentaje' => rand(15, 85)],
                            ['name' => 'Magenta', 'nivel_porcentaje' => rand(10, 80)],
                            ['name' => 'Yellow', 'nivel_porcentaje' => rand(25, 90)],
                        ],
                    ],
                ]);

                $printers->push($printer);

                $period = CarbonPeriod::create(now()->subDays(6), '1 day', now());
                foreach ($period as $day) {
                    $snapshot = PrinterStatusSnapshot::create([
                        'printer_id' => $printer->id,
                        'status' => $printer->status,
                        'total_pages' => rand(5000, 9000),
                        'color_pages' => $variant === 'color' ? rand(500, 2500) : 0,
                        'bw_pages' => rand(1000, 4000),
                        'lifetime_pages' => rand(15000, 60000),
                        'uptime_seconds' => rand(80_000, 200_000),
                        'consumables' => $printer->snmp_data['consumables'],
                        'captured_at' => $day->copy()->setTime(rand(6, 20), rand(0, 59)),
                    ]);

                    PrinterPrintLog::create([
                        'printer_id' => $printer->id,
                        'snapshot_id' => $snapshot->id,
                        'start_counter' => rand(10_000, 20_000),
                        'end_counter' => rand(20_001, 40_000),
                        'total_prints' => rand(600, 1400),
                        'color_prints' => $variant === 'color' ? rand(120, 600) : 0,
                        'bw_prints' => rand(300, 900),
                        'started_at' => $day->copy()->startOfDay(),
                        'ended_at' => $day->copy()->endOfDay(),
                        'source' => 'snmp',
                    ]);
                }
            }
        }

        foreach ($consumables as $consumable) {
            $stock = Stock::create([
                'consumable_id' => $consumable->id,
                'site_id' => $sites->first()->id,
                'department_id' => null,
                'quantity' => rand(5, 20),
                'minimum_quantity' => 5,
                'average_cost' => $consumable->unit_cost,
                'managed_by' => $admin->id,
            ]);

            StockMovement::create([
                'stock_id' => $stock->id,
                'movement_type' => 'in',
                'quantity' => $stock->quantity,
                'note' => 'Stock inicial',
                'performed_by' => $admin->id,
            ]);
        }

        $alertsPayload = [
            [
                'type' => 'LOW_TONER',
                'severity' => 'high',
                'status' => 'open',
                'source' => 'snmp',
                'title' => 'Tóner negro bajo',
                'message' => 'Nivel por debajo del 15% en la impresora principal',
                'printer' => $printers->first(),
                'payload' => ['current_level' => 12, 'threshold' => 15],
            ],
            [
                'type' => 'SERVICE',
                'severity' => 'medium',
                'status' => 'acknowledged',
                'source' => 'monitoring',
                'title' => 'Mantenimiento preventivo',
                'message' => 'Revisión programada tras 250.000 páginas',
                'printer' => $printers->last(),
                'payload' => ['pages' => 248000],
            ],
            [
                'type' => 'JAM',
                'severity' => 'critical',
                'status' => 'open',
                'source' => 'snmp',
                'title' => 'Atasco detectado en bandeja 2',
                'message' => 'Se requiere intervención manual',
                'printer' => $printers->get(2),
                'payload' => ['tray' => 2],
            ],
        ];

        foreach ($alertsPayload as $alertData) {
            Alert::create([
                'type' => $alertData['type'],
                'severity' => $alertData['severity'],
                'status' => $alertData['status'],
                'source' => $alertData['source'],
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'printer_id' => $alertData['printer']->id,
                'site_id' => $alertData['printer']->site_id,
                'payload' => $alertData['payload'],
            ]);
        }
    }
}
