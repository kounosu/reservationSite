<?php

use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('reservations.index');
Route::get('/reservation-calendar', [ReservationController::class, 'calendar'])->name('reservations.calendar');
Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
