<?php

namespace App\Providers;

use App\Domain\Reservations\AdminReservationRepository;
use App\Infrastructure\Reservations\EloquentAdminReservationRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * アプリケーションサービスを登録する。
     */
    public function register(): void
    {
        $this->app->bind(AdminReservationRepository::class, EloquentAdminReservationRepository::class);
    }

    /**
     * アプリケーションサービスを起動する。
     */
    public function boot(): void
    {
        //
    }
}
