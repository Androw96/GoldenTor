<?php

use App\Http\Controllers\AdminSetupController;
use Illuminate\Support\Facades\Route;

// Only /admin/* and /livewire/* reach this app (see the site's root .htaccess).
// One-time creation of the first Filament admin; returns 404 once an admin exists.
Route::get('/admin/setup', [AdminSetupController::class, 'show'])->name('admin.setup');
Route::post('/admin/setup', [AdminSetupController::class, 'store'])->middleware('throttle:5,1');
