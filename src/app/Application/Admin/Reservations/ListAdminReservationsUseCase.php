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

    public function __construct(
        private readonly AdminReservationRepository $reservations,
    ) {}

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
     * @param  array<string, string>  $statusLabels
     * @return list<array{label: string, value: string}>
     */
    private function buildActiveFilters(AdminReservationFilters $filters, array $statusLabels): array
    {
        $activeFilters = [];

        if ($filters->date !== '') {
            $activeFilters[] = [
                'label' => '来店日',
                'value' => $filters->date,
            ];
        }

        if ($filters->status !== '') {
            $activeFilters[] = [
                'label' => '状態',
                'value' => $statusLabels[$filters->status] ?? $filters->status,
            ];
        }

        if ($filters->keyword !== '') {
            $activeFilters[] = [
                'label' => '検索',
                'value' => Str::limit($filters->keyword, 24),
            ];
        }

        return $activeFilters;
    }
}
