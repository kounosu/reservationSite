<?php

namespace App\Application\Admin\Reservations;

use App\Domain\Reservations\AdminReservationFilters;

readonly class ListAdminReservationsInput
{
    public function __construct(
        public AdminReservationFilters $filters,
        public string $timezone,
        public int $perPage = 20,
    ) {}
}
