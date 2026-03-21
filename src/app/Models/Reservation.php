<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reservation_slot_id',
        'reservation_code',
        'guest_name',
        'guest_email',
        'guest_phone',
        'party_size',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ReservationSlot::class, 'reservation_slot_id');
    }
}
