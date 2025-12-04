<?php

namespace Database\Seeders;

use App\Models\SnmpOid;
use Illuminate\Database\Seeder;

class SnmpOidSeeder extends Seeder
{
    public function run(): void
    {
        $oids = [
            // Información del sistema
            [
                'oid' => '1.3.6.1.2.1.1.1.0',
                'name' => 'Descripción del sistema',
                'description' => 'sysDescr - Descripción completa del dispositivo',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.1.5.0',
                'name' => 'Nombre del sistema',
                'description' => 'sysName - Nombre del dispositivo',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.1.2.0',
                'name' => 'Object ID del sistema',
                'description' => 'sysObjectID - Identificador único del dispositivo',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.1.3.0',
                'name' => 'Tiempo activo',
                'description' => 'sysUpTime - Tiempo desde el último reinicio',
                'category' => 'system',
                'data_type' => 'integer',
                'unit' => 'centisegundos',
                'is_system' => true,
            ],

            // Contadores de páginas (estándar RFC 3805)
            [
                'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.1',
                'name' => 'Total de páginas impresas',
                'description' => 'Contador total de páginas impresas',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.2',
                'name' => 'Páginas en color',
                'description' => 'Contador de páginas impresas en color',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.10.2.1.4.1.3',
                'name' => 'Páginas en blanco y negro',
                'description' => 'Contador de páginas impresas en B&W',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => true,
            ],

            // Niveles de consumibles (estándar RFC 3805)
            // Negro
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.9.1.1',
                'name' => 'Nivel de toner negro',
                'description' => 'Nivel de toner/cartucho negro',
                'category' => 'consumable',
                'data_type' => 'percentage',
                'unit' => '%',
                'color' => 'black',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.6.1.1',
                'name' => 'Nivel máximo toner negro',
                'description' => 'Nivel máximo del toner negro',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'black',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.8.1.1',
                'name' => 'Nivel actual toner negro',
                'description' => 'Nivel actual del toner negro',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'black',
                'is_system' => true,
            ],

            // Cian
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.9.1.2',
                'name' => 'Nivel de toner cian',
                'description' => 'Nivel de toner/cartucho cian',
                'category' => 'consumable',
                'data_type' => 'percentage',
                'unit' => '%',
                'color' => 'cyan',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.6.1.2',
                'name' => 'Nivel máximo toner cian',
                'description' => 'Nivel máximo del toner cian',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'cyan',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.8.1.2',
                'name' => 'Nivel actual toner cian',
                'description' => 'Nivel actual del toner cian',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'cyan',
                'is_system' => true,
            ],

            // Magenta
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.9.1.3',
                'name' => 'Nivel de toner magenta',
                'description' => 'Nivel de toner/cartucho magenta',
                'category' => 'consumable',
                'data_type' => 'percentage',
                'unit' => '%',
                'color' => 'magenta',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.6.1.3',
                'name' => 'Nivel máximo toner magenta',
                'description' => 'Nivel máximo del toner magenta',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'magenta',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.8.1.3',
                'name' => 'Nivel actual toner magenta',
                'description' => 'Nivel actual del toner magenta',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'magenta',
                'is_system' => true,
            ],

            // Amarillo
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.9.1.4',
                'name' => 'Nivel de toner amarillo',
                'description' => 'Nivel de toner/cartucho amarillo',
                'category' => 'consumable',
                'data_type' => 'percentage',
                'unit' => '%',
                'color' => 'yellow',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.6.1.4',
                'name' => 'Nivel máximo toner amarillo',
                'description' => 'Nivel máximo del toner amarillo',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'yellow',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.43.11.1.1.8.1.4',
                'name' => 'Nivel actual toner amarillo',
                'description' => 'Nivel actual del toner amarillo',
                'category' => 'consumable',
                'data_type' => 'integer',
                'color' => 'yellow',
                'is_system' => true,
            ],

            // HP específicos
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.1.0',
                'name' => 'Marca HP',
                'description' => 'Marca del dispositivo (HP)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.2.0',
                'name' => 'Modelo HP',
                'description' => 'Modelo del dispositivo (HP)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.3.0',
                'name' => 'Número de serie HP',
                'description' => 'Número de serie (HP)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],

            // Canon específicos
            [
                'oid' => '1.3.6.1.4.1.1602.1.2.1.1.1.0',
                'name' => 'Marca Canon',
                'description' => 'Marca del dispositivo (Canon)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],
            [
                'oid' => '1.3.6.1.4.1.1602.1.2.1.1.2.0',
                'name' => 'Modelo Canon',
                'description' => 'Modelo del dispositivo (Canon)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],

            // Epson específicos
            [
                'oid' => '1.3.6.1.4.1.1248.1.2.2.1.1.1.2.1',
                'name' => 'Modelo Epson',
                'description' => 'Modelo del dispositivo (Epson)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],

            // Brother específicos
            [
                'oid' => '1.3.6.1.4.1.2435.2.3.9.4.2.1.5.5.1.0',
                'name' => 'Modelo Brother',
                'description' => 'Modelo del dispositivo (Brother)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],

            // Xerox específicos
            [
                'oid' => '1.3.6.1.4.1.253.8.51.10.2.1.1.1.0',
                'name' => 'Modelo Xerox',
                'description' => 'Modelo del dispositivo (Xerox)',
                'category' => 'system',
                'data_type' => 'string',
                'is_system' => false,
            ],

            // Estado de la impresora
            [
                'oid' => '1.3.6.1.2.1.25.3.2.1.5.1',
                'name' => 'Estado de la impresora',
                'description' => 'Estado actual de la impresora',
                'category' => 'status',
                'data_type' => 'integer',
                'is_system' => true,
            ],
            [
                'oid' => '1.3.6.1.2.1.25.3.5.1.1.1',
                'name' => 'Estado del tóner',
                'description' => 'Estado general del tóner',
                'category' => 'status',
                'data_type' => 'integer',
                'is_system' => true,
            ],

            // Contadores adicionales HP
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.8.0',
                'name' => 'Total páginas HP',
                'description' => 'Contador total de páginas (HP)',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => false,
            ],
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.9.0',
                'name' => 'Páginas color HP',
                'description' => 'Contador de páginas en color (HP)',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => false,
            ],
            [
                'oid' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.4.5.10.0',
                'name' => 'Páginas B&W HP',
                'description' => 'Contador de páginas B&W (HP)',
                'category' => 'counter',
                'data_type' => 'integer',
                'unit' => 'páginas',
                'is_system' => false,
            ],
        ];

        foreach ($oids as $oidData) {
            SnmpOid::updateOrCreate(
                ['oid' => $oidData['oid']],
                $oidData
            );
        }

        $this->command->info('OIDs SNMP sembrados correctamente');
    }
}
