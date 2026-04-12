<?php

namespace App\Providers;

use App\Domain\Reservations\AdminReservationRepository;
use App\Infrastructure\Reservations\EloquentAdminReservationRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AdminReservationRepository::class, EloquentAdminReservationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
