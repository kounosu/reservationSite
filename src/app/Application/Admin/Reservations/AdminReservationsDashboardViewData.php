<?php

namespace App\Application\Admin\Reservations;

readonly class AdminReservationsDashboardViewData
{
    /**
     * @param  list<array{label: string, value: int, tone: string, hint: string}>  $summary
     */
    public function __construct(
        public array $summary,
        public string $today,
    ) {}

    /**
     * @return array{
     *     summary: list<array{label: string, value: int, tone: string, hint: string}>,
     *     today: string
     * }
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'today' => $this->today,
        ];
    }
}
