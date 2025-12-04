<?php

namespace App\Services\Snmp;

use App\Models\Consumable;
use App\Models\Printer;
use App\Services\Snmp\Drivers\FakeSnmpDriver;
use App\Services\Snmp\Drivers\RealSnmpDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SnmpDiscoveryService
{
    public function __construct(
        protected RealSnmpDriver $realDriver,
        protected FakeSnmpDriver $fakeDriver,
    ) {
    }

    /**
     * Descubre impresoras en un rango de IPs
     *
     * @param string $ipRange IP única o rango (ej: "10.64.130.12" o "10.64.130.0/24")
     * @return Collection<array{ip: string, data: array|null}>
     */
    public function discover(string $ipRange): Collection
    {
        $ips = $this->parseIpRange($ipRange);
        $results = collect();

        foreach ($ips as $ip) {
            $data = $this->discoverSingle($ip);
            $results->push([
                'ip' => $ip,
                'data' => $data,
            ]);
        }

        return $results;
    }

    /**
     * Descubre una IP individual
     */
    public function discoverSingle(string $ip): ?array
    {
        // Intentar con driver real primero
        $data = $this->realDriver->discover($ip);

        // Si falla y estamos en modo desarrollo, usar fake
        if (! $data && config('snmp.driver') === 'fake') {
            $data = $this->fakeDriver->discover($ip);
        }

        return $data;
    }

    /**
     * Crea una impresora desde datos de descubrimiento
     */
    public function createPrinterFromDiscovery(array $discoveryData, ?int $provinceId = null, ?int $siteId = null, ?int $departmentId = null): Printer
    {
        $description = $discoveryData['description'] ?? '';
        $name = $discoveryData['hostname'] ?? $discoveryData['ip_address'] ?? 'Impresora Desconocida';

        // Extraer marca y modelo de la descripción
        $brand = $this->extractBrand($description);
        $model = $this->extractModel($description);

        $printer = Printer::create([
            'name' => $name,
            'hostname' => $discoveryData['hostname'] ?? null,
            'ip_address' => $discoveryData['ip_address'],
            'brand' => $brand,
            'model' => $model,
            'province_id' => $provinceId,
            'site_id' => $siteId,
            'department_id' => $departmentId,
            'status' => 'online',
            'is_color' => $discoveryData['is_color'] ?? false,
            'supports_snmp' => true,
            'discovery_source' => 'snmp_scan',
            'snmp_data' => $discoveryData,
            'last_seen_at' => now(),
        ]);

        // Crear consumibles automáticamente desde los datos de descubrimiento
        $this->createConsumablesFromDiscovery($printer, $discoveryData);

        return $printer;
    }

    /**
     * Crea consumibles desde datos de descubrimiento
     */
    protected function createConsumablesFromDiscovery(Printer $printer, array $discoveryData): void
    {
        $consumables = $discoveryData['consumables'] ?? [];

        foreach ($consumables as $consumableData) {
            $name = $consumableData['name'] ?? 'Consumible Desconocido';
            $color = $consumableData['color'] ?? null;
            $type = $this->detectConsumableType($name, $color);

            Consumable::firstOrCreate(
                [
                    'sku' => $this->generateSku($printer->brand, $printer->model, $type, $color),
                ],
                [
                    'name' => "{$name} - {$printer->brand} {$printer->model}",
                    'type' => $type,
                    'brand' => $printer->brand,
                    'color' => $color,
                    'is_color' => $color !== null && $color !== 'black',
                    'compatible_models' => [$printer->model],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Parsea un rango de IPs
     */
    protected function parseIpRange(string $ipRange): array
    {
        // Si es una IP única
        if (filter_var($ipRange, FILTER_VALIDATE_IP)) {
            return [$ipRange];
        }

        // Si es un rango CIDR (ej: 10.64.130.0/24)
        if (str_contains($ipRange, '/')) {
            return $this->parseCidr($ipRange);
        }

        // Si es un rango simple (ej: 10.64.130.1-10.64.130.50)
        if (str_contains($ipRange, '-')) {
            return $this->parseRange($ipRange);
        }

        return [];
    }

    /**
     * Parsea un rango CIDR
     */
    protected function parseCidr(string $cidr): array
    {
        [$ip, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        if ($mask < 24 || $mask > 30) {
            // Limitar a /24 a /30 para evitar escaneos masivos
            Log::warning("CIDR mask {$mask} is too large, limiting to /24");
            $mask = 24;
        }

        $ips = [];
        $ipLong = ip2long($ip);
        $network = $ipLong & ((-1 << (32 - $mask)));
        $broadcast = $network | ((1 << (32 - $mask)) - 1);

        for ($i = $network + 1; $i < $broadcast; $i++) {
            $ips[] = long2ip($i);
        }

        return $ips;
    }

    /**
     * Parsea un rango simple
     */
    protected function parseRange(string $range): array
    {
        [$start, $end] = explode('-', $range, 2);
        $start = trim($start);
        $end = trim($end);

        if (! filter_var($start, FILTER_VALIDATE_IP) || ! filter_var($end, FILTER_VALIDATE_IP)) {
            return [];
        }

        $ips = [];
        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong > $endLong) {
            [$startLong, $endLong] = [$endLong, $startLong];
        }

        // Limitar a 256 IPs máximo
        if (($endLong - $startLong) > 256) {
            $endLong = $startLong + 256;
        }

        for ($i = $startLong; $i <= $endLong; $i++) {
            $ips[] = long2ip($i);
        }

        return $ips;
    }

    /**
     * Extrae la marca de una descripción
     */
    protected function extractBrand(string $description): ?string
    {
        $brands = ['HP', 'Canon', 'Epson', 'Brother', 'Lexmark', 'Xerox', 'Ricoh', 'Kyocera', 'Samsung'];
        $description = strtoupper($description);

        foreach ($brands as $brand) {
            if (stripos($description, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Extrae el modelo de una descripción
     */
    protected function extractModel(string $description): ?string
    {
        // Buscar patrones comunes de modelos (números y letras)
        if (preg_match('/([A-Z]{1,3}[\s\-]?[\d]{3,5}[a-z]?)/i', $description, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Detecta el tipo de consumible
     */
    protected function detectConsumableType(string $name, ?string $color): string
    {
        $name = strtolower($name);
        if (stripos($name, 'toner') !== false) {
            return 'toner';
        }
        if (stripos($name, 'cartridge') !== false || stripos($name, 'cartucho') !== false) {
            return 'cartridge';
        }
        if (stripos($name, 'drum') !== false) {
            return 'drum';
        }
        return 'consumable';
    }

    /**
     * Genera un SKU único
     */
    protected function generateSku(?string $brand, ?string $model, string $type, ?string $color): string
    {
        $parts = array_filter([$brand, $model, $type, $color]);
        $base = strtoupper(implode('-', $parts));
        return Str::slug($base);
    }
}

