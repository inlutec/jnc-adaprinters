<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Impresiones - JNC-AdaPrinters</title>
    <style>
        @media print {
            @page { margin: 1cm; }
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #00A859;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #00A859;
            margin: 0;
        }
        .info {
            margin-bottom: 20px;
        }
        .info-row {
            margin: 5px 0;
        }
        .aggregates {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .aggregate-box {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .aggregate-box .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        .aggregate-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #00A859;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #00A859;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .no-print {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <p><strong>Informe generado:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
        <p><em>Use Ctrl+P o Cmd+P para imprimir este informe</em></p>
    </div>

    <div class="header">
        <h1>Informe de Impresiones</h1>
        <p>JNC-AdaPrinters - Agencia Digital de Andalucía</p>
    </div>

    <div class="info">
        <div class="info-row"><strong>Fecha de generación:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
        @if(!empty($filters))
            <div class="info-row"><strong>Filtros aplicados:</strong></div>
            <ul>
                @if(isset($filters['type']) && $filters['type'] !== 'all')
                    <li>Tipo: {{ $filters['type'] === 'color' ? 'Color' : 'Monocromo' }}</li>
                @endif
                @if(isset($filters['date_from']))
                    <li>Desde: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}</li>
                @endif
                @if(isset($filters['date_to']))
                    <li>Hasta: {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</li>
                @endif
            </ul>
        @endif
    </div>

    <div class="aggregates">
        <div class="aggregate-box">
            <div class="label">Total Trabajos</div>
            <div class="value">{{ number_format($aggregates['total_jobs']) }}</div>
        </div>
        <div class="aggregate-box">
            <div class="label">Total Impresiones</div>
            <div class="value">{{ number_format($aggregates['total_prints']) }}</div>
        </div>
        <div class="aggregate-box">
            <div class="label">Color</div>
            <div class="value">{{ number_format($aggregates['color_prints']) }}</div>
        </div>
        <div class="aggregate-box">
            <div class="label">B/N</div>
            <div class="value">{{ number_format($aggregates['bw_prints']) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Impresora</th>
                <th>Ubicación</th>
                <th>Total</th>
                <th>Color</th>
                <th>B/N</th>
                <th>Período</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->ended_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->printer->name }}</td>
                    <td>
                        {{ $log->printer->site?->name }}
                        @if($log->printer->department)
                            - {{ $log->printer->department->name }}
                        @endif
                    </td>
                    <td>{{ number_format($log->total_prints) }}</td>
                    <td>{{ number_format($log->color_prints) }}</td>
                    <td>{{ number_format($log->bw_prints) }}</td>
                    <td>
                        {{ $log->started_at->format('d/m H:i') }} - {{ $log->ended_at->format('d/m H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        No hay registros que mostrar con los filtros aplicados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Generado por JNC-AdaPrinters - {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

