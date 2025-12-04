<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Backups - JNC AdaPrinters</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h1 class="text-3xl font-black text-slate-900 mb-2">Administración de Backups</h1>
            <p class="text-slate-600">Gestiona copias de seguridad de la base de datos, restaura backups y limpia datos para empezar desde cero.</p>
        </div>

        <!-- Mensajes de éxito/error -->
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Crear Backup -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Crear Copia de Seguridad</h2>
                <p class="text-sm text-slate-600 mb-4">Crea un backup completo de la base de datos PostgreSQL.</p>
                
                <form method="POST" action="{{ route('backup.create') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Nombre del backup (opcional)</label>
                        <input 
                            type="text" 
                            name="name" 
                            placeholder="backup_manual"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        <p class="text-xs text-slate-500 mt-1">Si se deja vacío, se usará la fecha y hora actual</p>
                    </div>
                    <button 
                        type="submit"
                        class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg font-semibold hover:bg-blue-700 transition"
                    >
                        Crear Backup
                    </button>
                </form>
            </div>

            <!-- Limpiar Datos -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Limpiar Todos los Datos</h2>
                <p class="text-sm text-slate-600 mb-4">
                    Borra todos los datos operativos manteniendo solo lo esencial:
                </p>
                <ul class="text-xs text-slate-600 mb-4 space-y-1 list-disc list-inside">
                    <li>✓ Usuarios y permisos</li>
                    <li>✓ Configuraciones (SNMP, notificaciones, logos)</li>
                    <li>✓ Estructura geográfica (provincias, sedes, departamentos)</li>
                    <li>✗ Impresoras y snapshots</li>
                    <li>✗ Inventario y stocks</li>
                    <li>✗ Pedidos y entradas</li>
                    <li>✗ Alertas e historial</li>
                </ul>
                
                <form method="POST" action="{{ route('backup.clean') }}" 
                      onsubmit="return confirm('¿Estás SEGURO de que quieres borrar TODOS los datos? Esta acción NO se puede deshacer.');">
                    @csrf
                    <button 
                        type="submit"
                        class="w-full bg-rose-600 text-white px-4 py-3 rounded-lg font-semibold hover:bg-rose-700 transition"
                    >
                        🗑️ Limpiar Todos los Datos
                    </button>
                </form>
            </div>
        </div>

        <!-- Lista de Backups -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
            <h2 class="text-xl font-bold text-slate-900 mb-4">Backups Disponibles</h2>
            
            @if(count($backups) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Tamaño</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach($backups as $backup)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $backup['name'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $formatBytes($backup['size']) }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $backup['created_at']->format('d/m/Y H:i:s') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a 
                                                href="{{ route('backup.download', ['filename' => $backup['name']]) }}"
                                                class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold hover:bg-blue-200"
                                            >
                                                Descargar
                                            </a>
                                            <form 
                                                method="POST" 
                                                action="{{ route('backup.restore') }}" 
                                                onsubmit="return confirm('¿Restaurar este backup? Esto reemplazará TODOS los datos actuales.');"
                                                class="inline"
                                            >
                                                @csrf
                                                <input type="hidden" name="backup_file" value="{{ $backup['name'] }}">
                                                <button 
                                                    type="submit"
                                                    class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-200"
                                                >
                                                    Restaurar
                                                </button>
                                            </form>
                                            <form 
                                                method="POST" 
                                                action="{{ route('backup.delete') }}"
                                                onsubmit="return confirm('¿Eliminar este backup?');"
                                                class="inline"
                                            >
                                                @csrf
                                                <input type="hidden" name="backup_file" value="{{ $backup['name'] }}">
                                                <button 
                                                    type="submit"
                                                    class="px-3 py-1 bg-rose-100 text-rose-700 rounded-lg text-xs font-semibold hover:bg-rose-200"
                                                >
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-500 text-center py-8">No hay backups disponibles. Crea uno para comenzar.</p>
            @endif
        </div>
    </div>

</body>
</html>

