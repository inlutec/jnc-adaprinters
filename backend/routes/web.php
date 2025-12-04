<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('backup')->name('backup.')->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('index');
    Route::post('/create', [BackupController::class, 'createBackup'])->name('create');
    Route::post('/restore', [BackupController::class, 'restoreBackup'])->name('restore');
    Route::post('/delete', [BackupController::class, 'deleteBackup'])->name('delete');
    Route::post('/clean', [BackupController::class, 'cleanData'])->name('clean');
    Route::get('/download/{filename}', [BackupController::class, 'downloadBackup'])->name('download');
});
