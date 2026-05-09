<?php

namespace App\Application\Admin\Reservations;

use Illuminate\Pagination\LengthAwarePaginator;

readonly class AdminReservationsIndexViewData
{
    /**
     * 予約一覧画面の表示データを生成する。
     *
     * @param  array{date: string, status: string, q: string}  $filters
     * @param  array<string, string>  $statusLabels
     * @param  list<string>  $statusOptions
     * @param  list<array{label: string, value: string}>  $activeFilters
     * @param  list<int>  $perPageOptions
     */
    public function __construct(
        public array $filters,
        public LengthAwarePaginator $reservations,
        public array $statusLabels,
        public array $statusOptions,
        public array $activeFilters,
        public int $perPage,
        public array $perPageOptions,
        public string $timezone,
        public string $today,
    ) {}

    /**
     * ビューへ渡す配列へ変換する。
     *
     * @return array{
     *     filters: array{date: string, status: string, q: string},
     *     reservations: LengthAwarePaginator,
     *     statusLabels: array<string, string>,
     *     statusOptions: list<string>,
     *     activeFilters: list<array{label: string, value: string}>,
     *     perPage: int,
     *     perPageOptions: list<int>,
     *     timezone: string,
     *     today: string
     * }
     */
    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'reservations' => $this->reservations,
            'statusLabels' => $this->statusLabels,
            'statusOptions' => $this->statusOptions,
            'activeFilters' => $this->activeFilters,
            'perPage' => $this->perPage,
            'perPageOptions' => $this->perPageOptions,
            'timezone' => $this->timezone,
            'today' => $this->today,
        ];
    }
}
