<?php

use App\Http\Controllers\AdminSetupController;
use App\Http\Controllers\InlineTextController;
use Illuminate\Support\Facades\Route;

// Only /admin/* and /livewire/* reach this app (see the site's root .htaccess).
// One-time creation of the first Filament admin; returns 404 once an admin exists.
Route::get('/admin/setup', [AdminSetupController::class, 'show'])->name('admin.setup');
Route::post('/admin/setup', [AdminSetupController::class, 'store'])->middleware('throttle:5,1');

// Editing texts directly on the public pages (site-text.js); requires a logged-in admin.
Route::get('/admin/inline-editor/session', [InlineTextController::class, 'session']);
Route::post('/admin/inline-editor/texts', [InlineTextController::class, 'save'])->middleware('throttle:60,1');
Route::post('/admin/inline-editor/layout', [InlineTextController::class, 'saveLayout'])->middleware('throttle:60,1');
Route::post('/admin/inline-editor/upload', [InlineTextController::class, 'upload'])->middleware('throttle:30,1');
