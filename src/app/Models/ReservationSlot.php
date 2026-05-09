<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationSlot extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationSlotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slot_start',
        'slot_end',
        'capacity',
        'reserved_count',
    ];

    /**
     * 型変換する属性を返す。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_start' => 'immutable_datetime',
            'slot_end' => 'immutable_datetime',
        ];
    }

    /**
     * この予約枠に紐づく予約一覧を返す。
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * 残席数を算出する。
     */
    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->reserved_count);
    }
}
