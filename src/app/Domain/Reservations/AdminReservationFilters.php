<?php

namespace App\Domain\Reservations;

readonly class AdminReservationFilters
{
    public function __construct(
        public string $date = '',
        public string $status = '',
        public string $keyword = '',
    ) {}

    /**
     * @param  array{date?: string, status?: string, q?: string}  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self(
            date: trim((string) ($filters['date'] ?? '')),
            status: trim((string) ($filters['status'] ?? '')),
            keyword: trim((string) ($filters['q'] ?? '')),
        );
    }

    /**
     * @return array{date: string, status: string, q: string}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'status' => $this->status,
            'q' => $this->keyword,
        ];
    }
}
