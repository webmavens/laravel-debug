<?php

use Illuminate\Support\Facades\Route;
use Webmavens\DebugMonitor\Http\Controllers\DebugRuleController;

Route::middleware(['web', 'debug-monitor.auth'])
    ->prefix('debug-monitor')
    ->name('debug-monitor.')
    ->group(function () {
        Route::get('/dashboard', [DebugRuleController::class, 'dashboard'])->name('dashboard');
        Route::get('/rules', [DebugRuleController::class, 'index'])->name('rules.index');
        Route::get('/rules/create', [DebugRuleController::class, 'create'])->name('rules.create');
        Route::post('/rules', [DebugRuleController::class, 'store'])->name('rules.store');
        Route::get('/rules/{rule}/edit', [DebugRuleController::class, 'edit'])->name('rules.edit');
        Route::put('/rules/{rule}', [DebugRuleController::class, 'update'])->name('rules.update');
        Route::get('/rules/{rule}', [DebugRuleController::class, 'show'])->name('rules.show');
        Route::delete('/rules/{id}', [DebugRuleController::class, 'destroy'])->name('rules.destroy');

        Route::post('/rules/{id}/suppress', [DebugRuleController::class, 'suppress'])->name('rules.suppress');
        Route::delete('/logs/{id}', [DebugRuleController::class, 'destroyLog'])->name('logs.destroy');
    });
