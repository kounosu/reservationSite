<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminReservationRequest extends FormRequest
{
    /**
     * 予約ステータス更新リクエストの認可可否を判定する。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 予約ステータス更新時のバリデーションルールを返す。
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(Reservation::statusLabels()))],
        ];
    }

    /**
     * バリデーションエラー表示用の項目名を返す。
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'status' => 'ステータス',
        ];
    }
}
