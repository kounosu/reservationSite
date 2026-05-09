<?php

namespace App\Domain\Reservations;

use Illuminate\Pagination\LengthAwarePaginator;

interface AdminReservationRepository
{
    /**
     * 検索条件に一致する予約をページネーション付きで取得する。
     */
    public function paginate(AdminReservationFilters $filters, int $perPage): LengthAwarePaginator;

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
    public function summarize(string $timezone): array;
}
