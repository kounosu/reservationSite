<?php

use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('reservations.index');
Route::get('/reservation-calendar', [ReservationController::class, 'calendar'])->name('reservations.calendar');
Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');

Route::middleware('admin.basic')
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::get('/reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
    });
