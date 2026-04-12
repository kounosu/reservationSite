<?php

namespace App\Infrastructure\Reservations;

use App\Domain\Reservations\AdminReservationFilters;
use App\Domain\Reservations\AdminReservationRepository;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentAdminReservationRepository implements AdminReservationRepository
{
    public function paginate(AdminReservationFilters $filters, int $perPage): LengthAwarePaginator
    {
        return Reservation::query()
            ->select('reservations.*')
            ->join('reservation_slots', 'reservation_slots.id', '=', 'reservations.reservation_slot_id')
            ->with('slot')
            ->when($filters->date !== '', function (Builder $query) use ($filters): void {
                $query->whereDate('reservation_slots.slot_start', $filters->date);
            })
            ->when($filters->status !== '', function (Builder $query) use ($filters): void {
                $query->where('reservations.status', $filters->status);
            })
            ->when($filters->keyword !== '', function (Builder $query) use ($filters): void {
                $search = $filters->keyword;

                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('reservations.reservation_code', 'like', "%{$search}%")
                        ->orWhere('reservations.guest_name', 'like', "%{$search}%")
                        ->orWhere('reservations.guest_email', 'like', "%{$search}%")
                        ->orWhere('reservations.guest_phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('reservation_slots.slot_start')
            ->orderByDesc('reservations.created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summarize(string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();

        $baseQuery = Reservation::query()
            ->join('reservation_slots', 'reservation_slots.id', '=', 'reservations.reservation_slot_id');

        return [
            'todayReservations' => (clone $baseQuery)
                ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                ->whereDate('reservation_slots.slot_start', $today)
                ->count('reservations.id'),
            'upcomingGuests' => (int) (clone $baseQuery)
                ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                ->where('reservation_slots.slot_start', '>=', $now->toDateTimeString())
                ->sum('reservations.party_size'),
            'confirmedReservations' => (clone $baseQuery)
                ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                ->count('reservations.id'),
            'cancelledReservations' => (clone $baseQuery)
                ->where('reservations.status', Reservation::STATUS_CANCELLED)
                ->count('reservations.id'),
        ];
    }
}
