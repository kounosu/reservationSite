<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * 予約登録リクエストの認可可否を判定する。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 予約登録時のバリデーションルールを返す。
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'slot_start' => ['required', 'date_format:Y-m-d H:i'],
            'guest_name' => ['required', 'string', 'max:80'],
            'guest_email' => ['required', 'email', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'party_size' => ['required', 'integer', 'min:1', 'max:'.config('reservations.max_party_size', 4)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * バリデーションエラー表示用の項目名を返す。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slot_start' => '予約枠',
            'guest_name' => 'お名前',
            'guest_email' => 'メールアドレス',
            'guest_phone' => '電話番号',
            'party_size' => '人数',
            'notes' => 'ご要望',
        ];
    }
}
