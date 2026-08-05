<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Src\Modules\Support\Infrastructure\Http\Controllers\SupportTicketAttachmentController;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get(
        'support-tickets/{ticket}/attachments/{attachment}/download',
        [SupportTicketAttachmentController::class, 'download'],
    )->name('support-tickets.attachments.download');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/client.php';
