<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrinterPrintLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PrintLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PrinterPrintLog::query()->with(['printer.site.province', 'printer.department']);

        // Filtro por tipo (all, color, bw)
        if ($type = $request->string('type')->toString()) {
            if ($type === 'color') {
                $query->where('color_prints', '>', 0);
            } elseif ($type === 'bw') {
                $query->where('bw_prints', '>', 0)->where('color_prints', 0);
            }
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->integer('printer_id'));
        }

        if ($request->filled('province_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('province_id', $request->integer('province_id'));
            });
        }

        if ($request->filled('site_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('site_id', $request->integer('site_id'));
            });
        }

        if ($request->filled('department_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        // Filtro por campo personalizado "proveedor"
        if ($request->filled('proveedor')) {
            $proveedorValue = $request->string('proveedor')->toString();
            $query->whereHas('printer', function ($q) use ($proveedorValue) {
                $q->whereHas('customFieldValues', function ($q2) use ($proveedorValue) {
                    $q2->whereHas('customField', function ($q3) {
                        $q3->where('slug', 'proveedor');
                    })->where('value', $proveedorValue);
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', Carbon::parse($request->input('date_from')));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ended_at', '<=', Carbon::parse($request->input('date_to')));
        }

        $perPage = $request->integer('per_page', 15);

        $logs = (clone $query)
            ->latest('started_at')
            ->paginate($perPage);

        // Obtener todos los logs de las impresoras que aparecen en esta página para calcular diferencias
        $printerIds = $logs->getCollection()->pluck('printer_id')->unique();
        
        // Obtener TODOS los logs de estas impresoras (sin filtros de fecha) para calcular diferencias correctamente
        $allPrinterLogs = PrinterPrintLog::whereIn('printer_id', $printerIds)
            ->orderBy('started_at', 'asc')
            ->get()
            ->groupBy('printer_id');

        // Calcular diferencias entre registros consecutivos para cada impresora
        $logsWithDiff = $logs->getCollection()->map(function ($log) use ($allPrinterLogs) {
            $printerLogs = $allPrinterLogs->get($log->printer_id, collect());
            
            // Buscar el log anterior de la misma impresora (por fecha)
            $previousLog = $printerLogs
                ->where('ended_at', '<', $log->started_at)
                ->sortByDesc('ended_at')
                ->first();
            
            // Calcular diferencias con el registro anterior
            if ($previousLog) {
                $log->diff_total = $log->total_prints;
                $log->diff_color = $log->color_prints;
                $log->diff_bw = $log->bw_prints;
            } else {
                // Es el primer registro, no hay diferencia previa
                $log->diff_total = null;
                $log->diff_color = null;
                $log->diff_bw = null;
            }
            
            return $log;
        });

        $aggregateQuery = clone $query;

        // Los agregados deben sumar las DIFERENCIAS (total_prints, color_prints, bw_prints) dentro del rango de fechas
        $aggregates = [
            'total_prints' => (clone $aggregateQuery)->sum('total_prints'),
            'color_prints' => (clone $aggregateQuery)->sum('color_prints'),
            'bw_prints' => (clone $aggregateQuery)->sum('bw_prints'),
        ];

        $trend = (clone $aggregateQuery)
            ->selectRaw('DATE(ended_at) as day, SUM(total_prints) as total')
            ->whereBetween('ended_at', [now()->subDays(14)->startOfDay(), now()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Reemplazar la colección de items con la que incluye las diferencias
        $logs->setCollection($logsWithDiff);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'aggregates' => $aggregates,
            'trend' => $trend->map(fn ($entry) => [
                'date' => Carbon::parse($entry->day)->format('d/m'),
                'value' => (int) $entry->total,
            ]),
        ]);
    }

    public function export(Request $request)
    {
        $query = PrinterPrintLog::query()->with(['printer.site.province', 'printer.department']);

        // Aplicar los mismos filtros que en index
        if ($type = $request->string('type')->toString()) {
            if ($type === 'color') {
                $query->where('color_prints', '>', 0);
            } elseif ($type === 'bw') {
                $query->where('bw_prints', '>', 0)->where('color_prints', 0);
            }
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->integer('printer_id'));
        }

        if ($request->filled('province_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('province_id', $request->integer('province_id'));
            });
        }

        if ($request->filled('site_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('site_id', $request->integer('site_id'));
            });
        }

        if ($request->filled('department_id')) {
            $query->whereHas('printer', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        // Filtro por campo personalizado "proveedor"
        if ($request->filled('proveedor')) {
            $proveedorValue = $request->string('proveedor')->toString();
            $query->whereHas('printer', function ($q) use ($proveedorValue) {
                $q->whereHas('customFieldValues', function ($q2) use ($proveedorValue) {
                    $q2->whereHas('customField', function ($q3) {
                        $q3->where('slug', 'proveedor');
                    })->where('value', $proveedorValue);
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', Carbon::parse($request->input('date_from')));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ended_at', '<=', Carbon::parse($request->input('date_to')));
        }

        $logs = $query->latest('started_at')->get();

        $aggregates = [
            'total_prints' => $logs->sum('total_prints'),
            'color_prints' => $logs->sum('color_prints'),
            'bw_prints' => $logs->sum('bw_prints'),
        ];

        $html = view('exports.print-log', [
            'logs' => $logs,
            'aggregates' => $aggregates,
            'filters' => $request->only(['type', 'printer_id', 'province_id', 'site_id', 'department_id', 'date_from', 'date_to', 'proveedor']),
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}

