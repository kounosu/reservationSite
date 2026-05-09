<?php

namespace App\Application\Admin\Reservations;

use App\Domain\Reservations\AdminReservationFilters;

readonly class ListAdminReservationsInput
{
    /**
     * 予約一覧取得ユースケースの入力値を生成する。
     */
    public function __construct(
        public AdminReservationFilters $filters,
        public string $timezone,
        public int $perPage = 20,
    ) {}
}
