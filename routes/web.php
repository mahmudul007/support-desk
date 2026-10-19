<?php

declare(strict_types=1);

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TicketAssignmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('tickets', TicketController::class)->except(['destroy', 'edit']);
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
    Route::post('tickets/{ticket}/accept', [TicketAssignmentController::class, 'accept'])->name('tickets.accept');
    Route::post('tickets/{ticket}/assign', [TicketAssignmentController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->name('tickets.replies.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    // Admin CSV Import routes
    Route::middleware('role:admin')->group(function (): void {
        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::get('imports/{import}/errors', [ImportController::class, 'downloadErrors'])->name('imports.errors');
    });
});

require __DIR__.'/auth.php';
