<?php

namespace App\Domain\Reservations;

use Illuminate\Pagination\LengthAwarePaginator;

interface AdminReservationRepository
{
    public function paginate(AdminReservationFilters $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array{
     *     todayReservations: int,
     *     upcomingGuests: int,
     *     confirmedReservations: int,
     *     cancelledReservations: int
     * }
     */
    public function summarize(string $timezone): array;
}
