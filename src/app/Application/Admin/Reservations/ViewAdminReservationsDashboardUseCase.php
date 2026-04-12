<?php

namespace App\Application\Admin\Reservations;

use App\Domain\Reservations\AdminReservationRepository;
use Carbon\CarbonImmutable;

class ViewAdminReservationsDashboardUseCase
{
    public function __construct(
        private readonly AdminReservationRepository $reservations,
    ) {}

    public function handle(string $timezone): AdminReservationsDashboardViewData
    {
        $summary = $this->reservations->summarize($timezone);

        return new AdminReservationsDashboardViewData(
            summary: [
                [
                    'label' => '本日の予約',
                    'value' => $summary['todayReservations'],
                    'tone' => 'accent',
                    'hint' => '本日来店予定の確定予約',
                ],
                [
                    'label' => '今後の来店人数',
                    'value' => $summary['upcomingGuests'],
                    'tone' => 'ink',
                    'hint' => '現在時刻以降の総人数',
                ],
                [
                    'label' => '確定済み予約',
                    'value' => $summary['confirmedReservations'],
                    'tone' => 'success',
                    'hint' => '全期間の確定予約件数',
                ],
                [
                    'label' => 'キャンセル済み',
                    'value' => $summary['cancelledReservations'],
                    'tone' => 'danger',
                    'hint' => '履歴として残っている件数',
                ],
            ],
            today: CarbonImmutable::now($timezone)->toDateString(),
        );
    }
}
