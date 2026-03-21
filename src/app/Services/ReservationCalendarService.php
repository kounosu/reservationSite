<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationCalendarService
{
    private string $timezone;

    private int $bookingWindowDays;

    private int $openHour;

    private int $closeHour;

    private int $slotMinutes;

    private int $slotCapacity;

    private int $maxPartySize;

    public function __construct()
    {
        $this->timezone = (string) config('reservations.timezone', config('app.timezone'));
        $this->bookingWindowDays = (int) config('reservations.booking_window_days', 90);
        $this->openHour = (int) config('reservations.open_hour', 10);
        $this->closeHour = (int) config('reservations.close_hour', 18);
        $this->slotMinutes = (int) config('reservations.slot_minutes', 60);
        $this->slotCapacity = (int) config('reservations.slot_capacity', 4);
        $this->maxPartySize = (int) config('reservations.max_party_size', 4);
    }

    /**
     * @return array<string, int|string>
     */
    public function settings(): array
    {
        return [
            'timezone' => $this->timezone,
            'bookingWindowDays' => $this->bookingWindowDays,
            'openHour' => $this->openHour,
            'closeHour' => $this->closeHour,
            'slotMinutes' => $this->slotMinutes,
            'slotCapacity' => $this->slotCapacity,
            'maxPartySize' => $this->maxPartySize,
        ];
    }

    public function currentMonth(): CarbonImmutable
    {
        return $this->now()->startOfMonth();
    }

    public function parseMonth(?string $value): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $this->currentMonth();
        }

        $month = $this->fromFormat('Y-m', $value);

        return $month?->startOfMonth() ?? $this->currentMonth();
    }

    public function parseDate(?string $value, CarbonImmutable $month): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $this->defaultDateForMonth($month);
        }

        $date = $this->fromFormat('Y-m-d', $value);

        if (! $date || ! $date->isSameMonth($month)) {
            return $this->defaultDateForMonth($month);
        }

        return $date->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCalendar(CarbonImmutable $month, CarbonImmutable $selectedDate): array
    {
        $this->ensureSlotsForMonth($month);

        $rangeStart = $month->startOfMonth()->startOfDay();
        $rangeEnd = $month->endOfMonth()->endOfDay();
        $slots = ReservationSlot::query()
            ->whereBetween('slot_start', [$rangeStart->toDateTimeString(), $rangeEnd->toDateTimeString()])
            ->orderBy('slot_start')
            ->get();

        $groupedByDate = $slots->groupBy(fn (ReservationSlot $slot) => $slot->slot_start->timezone($this->timezone)->toDateString());
        $days = [];
        $cursor = $month->startOfMonth();
        $today = $this->now()->startOfDay();

        while ($cursor->lte($month->endOfMonth())) {
            $dateKey = $cursor->toDateString();
            /** @var Collection<int, ReservationSlot> $daySlots */
            $daySlots = $groupedByDate->get($dateKey, collect());

            $availableSlots = $daySlots->filter(fn (ReservationSlot $slot) => $this->isBookableSlot($slot->slot_start) && $slot->remaining_capacity > 0)->count();
            $remainingSeats = $daySlots->filter(fn (ReservationSlot $slot) => $this->isBookableSlot($slot->slot_start))->sum(fn (ReservationSlot $slot) => $slot->remaining_capacity);

            $days[] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'availableSlots' => $availableSlots,
                'totalSlots' => $daySlots->count(),
                'remainingSeats' => $remainingSeats,
                'isPast' => $cursor->endOfDay()->lt($today),
                'isToday' => $cursor->isSameDay($today),
            ];

            $cursor = $cursor->addDay();
        }

        /** @var Collection<int, ReservationSlot> $selectedSlots */
        $selectedSlots = $groupedByDate->get($selectedDate->toDateString(), collect());

        return [
            'month' => $month->format('Y-m'),
            'selectedDate' => $selectedDate->toDateString(),
            'today' => $today->toDateString(),
            'days' => $days,
            'selectedSlots' => $selectedSlots
                ->map(fn (ReservationSlot $slot) => $this->serializeSlot($slot))
                ->values(),
            'settings' => $this->settings(),
        ];
    }

    public function reserve(array $payload): Reservation
    {
        $slotStart = $this->parseSlotStart((string) $payload['slot_start']);
        $partySize = (int) $payload['party_size'];

        $this->assertSlotIsReservable($slotStart, $partySize);

        /** @var Reservation $reservation */
        $reservation = DB::transaction(function () use ($payload, $slotStart, $partySize) {
            // Create the inventory row first so concurrent writes all wait on one slot record.
            DB::table('reservation_slots')->insertOrIgnore([$this->makeSlotRecord($slotStart)]);

            $slot = ReservationSlot::query()
                ->where('slot_start', $slotStart->toDateTimeString())
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->remaining_capacity < $partySize) {
                throw new SlotUnavailableException('The selected slot just sold out. Please choose another time.');
            }

            $reservation = $slot->reservations()->create([
                'reservation_code' => (string) Str::ulid(),
                'guest_name' => $payload['guest_name'],
                'guest_email' => $payload['guest_email'],
                'guest_phone' => $payload['guest_phone'] ?: null,
                'party_size' => $partySize,
                'notes' => $payload['notes'] ?: null,
                'status' => 'confirmed',
            ]);

            $slot->forceFill([
                'reserved_count' => $slot->reserved_count + $partySize,
            ])->save();

            return $reservation;
        });

        return $reservation->load('slot');
    }

    private function defaultDateForMonth(CarbonImmutable $month): CarbonImmutable
    {
        $today = $this->now()->startOfDay();

        if ($today->between($month->startOfMonth(), $month->endOfMonth(), true)) {
            return $today;
        }

        return $month->startOfMonth();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    private function fromFormat(string $format, string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat($format, $value, $this->timezone);
        } catch (\Throwable) {
            return null;
        }

        if (! $date || $date->format($format) !== $value) {
            return null;
        }

        return $date;
    }

    private function parseSlotStart(string $value): CarbonImmutable
    {
        $slotStart = $this->fromFormat('Y-m-d H:i', $value);

        if (! $slotStart) {
            throw ValidationException::withMessages([
                'slot_start' => 'The slot format is invalid.',
            ]);
        }

        return $slotStart;
    }

    private function assertSlotIsReservable(CarbonImmutable $slotStart, int $partySize): void
    {
        $opensAt = $slotStart->setTime($this->openHour, 0);
        $closesAt = $slotStart->setTime($this->closeHour, 0);
        $slotEnd = $slotStart->addMinutes($this->slotMinutes);
        $latestBookableDate = $this->now()->startOfDay()->addDays($this->bookingWindowDays);

        if ($slotStart->lt($this->now()->startOfMinute())) {
            throw ValidationException::withMessages([
                'slot_start' => 'Past slots cannot be reserved.',
            ]);
        }

        if ($slotStart->startOfDay()->gt($latestBookableDate)) {
            throw ValidationException::withMessages([
                'slot_start' => 'This slot is outside the booking window.',
            ]);
        }

        if (
            $slotStart->lt($opensAt)
            || $slotEnd->gt($closesAt)
            || $slotStart->minute % $this->slotMinutes !== 0
        ) {
            throw ValidationException::withMessages([
                'slot_start' => 'This slot is outside business hours.',
            ]);
        }

        if ($partySize > $this->maxPartySize) {
            throw ValidationException::withMessages([
                'party_size' => 'Party size exceeds the maximum allowed.',
            ]);
        }
    }

    private function ensureSlotsForMonth(CarbonImmutable $month): void
    {
        $records = [];
        $cursor = $month->startOfMonth();

        while ($cursor->lte($month->endOfMonth())) {
            $slotStart = $cursor->setTime($this->openHour, 0);
            $closesAt = $cursor->setTime($this->closeHour, 0);

            while ($slotStart->addMinutes($this->slotMinutes)->lte($closesAt)) {
                $records[] = $this->makeSlotRecord($slotStart);
                $slotStart = $slotStart->addMinutes($this->slotMinutes);
            }

            $cursor = $cursor->addDay();
        }

        if ($records !== []) {
            DB::table('reservation_slots')->insertOrIgnore($records);
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function makeSlotRecord(CarbonImmutable $slotStart): array
    {
        $now = $this->now()->toDateTimeString();

        return [
            'slot_start' => $slotStart->toDateTimeString(),
            'slot_end' => $slotStart->addMinutes($this->slotMinutes)->toDateTimeString(),
            'capacity' => $this->slotCapacity,
            'reserved_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function isBookableSlot(CarbonImmutable $slotStart): bool
    {
        return $slotStart->gte($this->now()->startOfMinute())
            && $slotStart->startOfDay()->lte($this->now()->startOfDay()->addDays($this->bookingWindowDays));
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function serializeSlot(ReservationSlot $slot): array
    {
        $slotStart = $slot->slot_start->timezone($this->timezone);
        $slotEnd = $slot->slot_end->timezone($this->timezone);

        return [
            'slotStart' => $slotStart->format('Y-m-d H:i'),
            'startTime' => $slotStart->format('H:i'),
            'endTime' => $slotEnd->format('H:i'),
            'capacity' => $slot->capacity,
            'reservedCount' => $slot->reserved_count,
            'remainingSeats' => $slot->remaining_capacity,
            'isBookable' => $this->isBookableSlot($slotStart) && $slot->remaining_capacity > 0,
        ];
    }
}
