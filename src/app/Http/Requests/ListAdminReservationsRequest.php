<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule>>
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

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }
}
