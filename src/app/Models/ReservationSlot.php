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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_start' => 'immutable_datetime',
            'slot_end' => 'immutable_datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->reserved_count);
    }
}
