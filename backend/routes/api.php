<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsumableController;
use App\Http\Controllers\Api\ConsumableInstallationController;
use App\Http\Controllers\Api\ConsumableReferenceController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\LogoController;
use App\Http\Controllers\Api\NotificationConfigController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderEntryController;
use App\Http\Controllers\Api\PrintLogController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SnmpOidController;
use App\Http\Controllers\Api\SnmpProfileController;
use App\Http\Controllers\Api\SnmpSyncController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('dashboard', DashboardController::class);

        // Rutas específicas de impresoras (deben ir antes de apiResource para evitar conflictos)
        Route::get('printers/groups-by-name', [PrinterController::class, 'groupsByName']);
        Route::post('printers/upload-massive-photo', [PrinterController::class, 'uploadMassivePhoto']);
        Route::post('printers/discover', [PrinterController::class, 'discover']);
        Route::post('printers/import-discovered', [PrinterController::class, 'importDiscovered']);
        
        Route::apiResource('printers', PrinterController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('printers/{printer}/sync', [PrinterController::class, 'sync']);
        Route::post('printers/{printer}/photo', [PrinterController::class, 'uploadPhoto']);
        Route::get('printers/{printer}/logs', [PrinterController::class, 'logs']);
        Route::get('printers/{printer}/snapshots', [PrinterController::class, 'snapshots']);
        Route::get('print-logs', [PrintLogController::class, 'index']);
        Route::get('print-logs/export', [PrintLogController::class, 'export']);

        Route::apiResource('snmp-profiles', SnmpProfileController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('snmp-profiles/{snmp_profile}/test', [SnmpProfileController::class, 'test']);

        Route::apiResource('consumables', ConsumableController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('stocks', [StockController::class, 'index']);
        Route::post('stocks/{stock}/adjust', [StockController::class, 'adjust']);
        Route::post('stocks/{stock}/regularize', [StockController::class, 'regularize']);
        Route::post('stocks/{stock}/movements', [StockController::class, 'storeMovement']);
        Route::put('stocks/{stock}/minimum-quantity', [StockController::class, 'updateMinimumQuantity']);

        Route::apiResource('alerts', AlertController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
        Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve']);
        Route::post('alerts/{alert}/dismiss', [AlertController::class, 'dismiss']);

        // Configuración
        Route::prefix('config')->group(function () {
            // SNMP Sync routes (deben ir antes de apiResource para evitar conflictos)
            Route::get('snmp-sync/config', [SnmpSyncController::class, 'getConfig']);
            Route::put('snmp-sync/config', [SnmpSyncController::class, 'updateConfig']);
            Route::post('snmp-sync/sync-all', [SnmpSyncController::class, 'syncAll']);
            Route::get('snmp-sync/history', [SnmpSyncController::class, 'getHistory']);
            
            Route::apiResource('logos', LogoController::class);
            Route::apiResource('custom-fields', CustomFieldController::class);
            Route::get('custom-fields/{slug}/values', [CustomFieldController::class, 'getFieldValues']);
            Route::apiResource('snmp-oids', SnmpOidController::class);
            Route::apiResource('notification-configs', NotificationConfigController::class);
            Route::post('notification-configs/{notification_config}/test', [NotificationConfigController::class, 'test']);
            Route::apiResource('provinces', ProvinceController::class);
            Route::apiResource('sites', SiteController::class);
            Route::apiResource('departments', DepartmentController::class);
            Route::apiResource('users', UserController::class);
        });

        // Referencias
        Route::apiResource('references', ConsumableReferenceController::class);
        Route::get('references/{consumableReference}/movements', [ConsumableReferenceController::class, 'movements']);

        // Instalaciones de consumibles
        Route::apiResource('installations', ConsumableInstallationController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        // Pedidos
        Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show', 'update']);
        Route::post('orders/{order}/comments', [OrderController::class, 'addComment']);
        Route::get('orders/{order}/comments', [OrderController::class, 'getComments']);
        Route::get('order-entries', [OrderEntryController::class, 'index']);
        Route::post('order-entries', [OrderEntryController::class, 'store']);
        Route::get('order-entries/{orderEntry}', [OrderEntryController::class, 'show']);
        Route::put('order-entries/{orderEntry}', [OrderEntryController::class, 'update']);
        Route::delete('order-entries/{orderEntry}', [OrderEntryController::class, 'destroy']);
    });
});

