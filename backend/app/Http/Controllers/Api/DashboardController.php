<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Printer;
use App\Models\PrinterPrintLog;
use App\Models\Stock;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totalPrinters = Printer::count();
        $onlinePrinters = Printer::where('status', 'online')->count();
        $offlinePrinters = Printer::where('status', 'offline')->count();
        $warningPrinters = Printer::where('status', 'warning')->count();

        $statusBreakdown = [
            'online' => $onlinePrinters,
            'offline' => $offlinePrinters,
            'warning' => $warningPrinters,
        ];

        $printTrendRaw = PrinterPrintLog::selectRaw('DATE(ended_at) as day, SUM(total_prints) as total, SUM(color_prints) as color, SUM(bw_prints) as bw')
            ->whereBetween('ended_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $printTrend = collect(range(6, 0))->map(function ($daysAgo) use ($printTrendRaw) {
            $date = Carbon::today()->subDays($daysAgo);
            $record = $printTrendRaw->get($date->toDateString());

            return [
                'date' => $date->format('d/m'),
                'value' => (int) ($record->total ?? 0),
                'color' => (int) ($record->color ?? 0),
                'bw' => (int) ($record->bw ?? 0),
            ];
        });

        $topPrinters = Printer::with(['site'])
            ->withSum(['printLogs as weekly_prints' => function ($query) {
                $query->where('ended_at', '>=', now()->subDays(7));
            }], 'total_prints')
            ->orderByDesc('weekly_prints')
            ->limit(5)
            ->get()
            ->map(fn (Printer $printer) => [
                'id' => $printer->id,
                'name' => $printer->name,
                'site' => $printer->site?->name,
                'status' => $printer->status,
                'weekly_prints' => (int) $printer->weekly_prints,
                'last_sync_at' => $printer->last_sync_at,
            ]);

        $latestAlerts = Alert::with('printer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'printer' => $alert->printer?->name,
                'created_at' => $alert->created_at,
            ]);

        $lowStock = Stock::with(['consumable', 'site'])
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->orderByRaw('(minimum_quantity - quantity) DESC')
            ->limit(5)
            ->get()
            ->map(fn (Stock $stock) => [
                'id' => $stock->id,
                'consumable' => $stock->consumable?->name,
                'site' => $stock->site?->name,
                'quantity' => $stock->quantity,
                'minimum' => $stock->minimum_quantity,
            ]);

        $siteHealth = Site::with('province')
            ->withCount(['printers', 'printers as online_printers_count' => function ($query) {
                $query->where('status', 'online');
            }])
            ->limit(4)
            ->get()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'province' => $site->province?->name,
                'printers' => $site->printers_count,
                'online' => $site->online_printers_count,
            ]);

        $alertsOpen = Alert::whereIn('status', ['open', 'acknowledged'])->count();
        $criticalAlerts = Alert::where('severity', 'critical')->where('status', 'open')->count();

        $inventoryTotal = Stock::sum('quantity');

        $printToday = PrinterPrintLog::whereDate('ended_at', today())->sum('total_prints');
        $printWeek = PrinterPrintLog::whereBetween('ended_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_prints');

        return response()->json([
            'data' => [
                'summary' => [
                    'printers' => [
                        'total' => $totalPrinters,
                        'online' => $onlinePrinters,
                        'color' => Printer::where('is_color', true)->count(),
                    ],
                    'alerts' => [
                        'open' => $alertsOpen,
                        'critical' => $criticalAlerts,
                    ],
                    'inventory' => [
                        'low_stock' => $lowStock->count(),
                        'total_items' => $inventoryTotal,
                    ],
                    'printing' => [
                        'today' => $printToday,
                        'week' => $printWeek,
                    ],
                ],
                'status_breakdown' => $statusBreakdown,
                'print_trend' => $printTrend,
                'top_printers' => $topPrinters,
                'latest_alerts' => $latestAlerts,
                'low_stock' => $lowStock,
                'site_health' => $siteHealth,
            ],
        ]);
    }
}

