<?php

namespace App\Domain\Reservations;

readonly class AdminReservationFilters
{
    /**
     * 予約一覧の検索条件を生成する。
     */
    public function __construct(
        public string $date = '',
        public string $status = '',
        public string $keyword = '',
    ) {}

    /**
     * リクエスト配列から検索条件を生成する。
     *
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
     * 検索条件をリクエスト互換の配列へ変換する。
     *
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
