<?php

namespace App\Application\Admin\Reservations;

use App\Domain\Reservations\AdminReservationFilters;
use App\Domain\Reservations\AdminReservationRepository;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ListAdminReservationsUseCase
{
    /**
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    /**
     * 予約一覧表示ユースケースを生成する。
     */
    public function __construct(
        private readonly AdminReservationRepository $reservations,
    ) {}

    /**
     * 予約一覧画面の表示データを生成する。
     */
    public function handle(ListAdminReservationsInput $input): AdminReservationsIndexViewData
    {
        $statusLabels = Reservation::statusLabels();

        return new AdminReservationsIndexViewData(
            filters: $input->filters->toArray(),
            reservations: $this->reservations->paginate($input->filters, $input->perPage),
            statusLabels: $statusLabels,
            statusOptions: array_keys($statusLabels),
            activeFilters: $this->buildActiveFilters($input->filters, $statusLabels),
            perPage: $input->perPage,
            perPageOptions: self::PER_PAGE_OPTIONS,
            timezone: $input->timezone,
            today: CarbonImmutable::now($input->timezone)->toDateString(),
        );
    }

    /**
     * 現在適用中の検索条件を表示用ラベルへ変換する。
     *
     * @param  array<string, string>  $statusLabels
     * @return list<array{label: string, value: string}>
     */
    private function buildActiveFilters(AdminReservationFilters $filters, array $statusLabels): array
    {
        $activeFilters = [
            [
                'label' => '来店日',
                'value' => $filters->date,
            ],
            [
                'label' => '状態',
                'value' => $filters->status === '' ? '' : ($statusLabels[$filters->status] ?? $filters->status),
            ],
            [
                'label' => '検索',
                'value' => $filters->keyword === '' ? '' : Str::limit($filters->keyword, 24),
            ],
        ];

        return array_values(array_filter(
            $activeFilters,
            fn (array $filter): bool => $filter['value'] !== ''
        ));
    }
}
