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
    /**
     * 検索条件に一致する予約をページネーション付きで取得する。
     */
    public function paginate(AdminReservationFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->reservationQuery()
            ->select('reservations.*')
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

    /**
     * 管理画面ダッシュボード用の予約集計値を取得する。
     *
     * @return array{
     *     todayReservations: int,
     *     upcomingGuests: int,
     *     confirmedReservations: int,
     *     cancelledReservations: int
     * }
     */
    public function summarize(string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $baseQuery = $this->reservationQuery();
        $confirmedQuery = $this->reservationsWithStatus($baseQuery, Reservation::STATUS_CONFIRMED);

        return [
            'todayReservations' => (clone $confirmedQuery)
                ->whereDate('reservation_slots.slot_start', $today)
                ->count('reservations.id'),
            'upcomingGuests' => (int) (clone $confirmedQuery)
                ->where('reservation_slots.slot_start', '>=', $now->toDateTimeString())
                ->sum('reservations.party_size'),
            'confirmedReservations' => (clone $confirmedQuery)->count('reservations.id'),
            'cancelledReservations' => $this
                ->reservationsWithStatus($baseQuery, Reservation::STATUS_CANCELLED)
                ->count('reservations.id'),
        ];
    }

    /**
     * 予約枠を結合した予約クエリを生成する。
     */
    private function reservationQuery(): Builder
    {
        return Reservation::query()
            ->join('reservation_slots', 'reservation_slots.id', '=', 'reservations.reservation_slot_id');
    }

    /**
     * 指定ステータスの予約に絞り込んだクエリを生成する。
     */
    private function reservationsWithStatus(Builder $query, string $status): Builder
    {
        return (clone $query)->where('reservations.status', $status);
    }
}
