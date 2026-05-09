<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminReservationsRequest extends FormRequest
{
    /**
     * 予約一覧検索リクエストの認可可否を判定する。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 予約一覧検索時のバリデーションルールを返す。
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Reservation::statusLabels()))],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ];
    }

    /**
     * 予約一覧の検索条件を整形して返す。
     *
     * @return array{date: string, status: string, q: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date' => (string) ($validated['date'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
            'q' => trim((string) ($validated['q'] ?? '')),
        ];
    }

    /**
     * 予約一覧の1ページあたりの表示件数を返す。
     *
     * @return int
     */
    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
