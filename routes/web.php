<?php

declare(strict_types=1);

use Focal\Core\Support\RouteGroup;
use Focal\Sales\Http\Controllers\QuoteAcceptanceController;
use Focal\Sales\Http\Controllers\SalesMeetingBookingController;
use Illuminate\Support\Facades\Route;

Route::group(RouteGroup::attributes('focal-sales.routes.web'), function (): void {
    // Quotes public e-sign portal
    Route::get('/quotes/{token}', [QuoteAcceptanceController::class, 'show'])->name('focal.quotes.show');
    Route::post('/quotes/{token}/accept', [QuoteAcceptanceController::class, 'accept'])->name('focal.quotes.accept');

    // Sales reps meeting scheduler
    Route::get('/meet/{slug}', [SalesMeetingBookingController::class, 'show'])->name('focal.meetings.show');
    Route::post('/meet/{slug}/book', [SalesMeetingBookingController::class, 'book'])->name('focal.meetings.book');
});
