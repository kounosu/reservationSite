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
    private const MONTH_FORMAT = 'Y-m';

    private const DATE_FORMAT = 'Y-m-d';

    private const SLOT_FORMAT = 'Y-m-d H:i';

    private readonly string $timezone;

    private readonly int $bookingWindowDays;

    private readonly int $openHour;

    private readonly int $closeHour;

    private readonly int $slotMinutes;

    private readonly int $slotCapacity;

    private readonly int $maxPartySize;

    /**
     * アプリケーション設定から予約カレンダーサービスを初期化する。
     */
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
     * クライアントへ公開する予約カレンダー設定を返す。
     *
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

    /**
     * 予約タイムゾーンにおける現在月の初日を返す。
     */
    public function currentMonth(): CarbonImmutable
    {
        return $this->now()->startOfMonth();
    }

    /**
     * 予約タイムゾーンを返す。
     */
    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * 年月文字列を解析し、不正な場合は現在月を返す。
     */
    public function parseMonth(?string $value): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $this->currentMonth();
        }

        $month = $this->fromFormat(self::MONTH_FORMAT, $value);

        return $month?->startOfMonth() ?? $this->currentMonth();
    }

    /**
     * 指定月内の選択日を解析し、不正な場合は既定日を返す。
     */
    public function parseDate(?string $value, CarbonImmutable $month): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $this->defaultDateForMonth($month);
        }

        $date = $this->fromFormat(self::DATE_FORMAT, $value);

        if (! $date || ! $date->isSameMonth($month)) {
            return $this->defaultDateForMonth($month);
        }

        return $date->startOfDay();
    }

    /**
     * 指定月と選択日に対応するカレンダー表示データを構築する。
     *
     * @return array{
     *     month: string,
     *     selectedDate: string,
     *     today: string,
     *     days: array<int, array{
     *         date: string,
     *         day: int,
     *         availableSlots: int,
     *         totalSlots: int,
     *         remainingSeats: int,
     *         isPast: bool,
     *         isToday: bool
     *     }>,
     *     selectedSlots: Collection<int, array<string, int|string|bool>>,
     *     settings: array<string, int|string>
     * }
     */
    public function buildCalendar(CarbonImmutable $month, CarbonImmutable $selectedDate): array
    {
        $this->ensureSlotsForMonth($month);

        $slotsByDate = $this->slotsForMonth($month)
            ->groupBy(fn (ReservationSlot $slot) => $slot->slot_start->timezone($this->timezone)->toDateString());
        $today = $this->now()->startOfDay();

        return [
            'month' => $month->format(self::MONTH_FORMAT),
            'selectedDate' => $selectedDate->toDateString(),
            'today' => $today->toDateString(),
            'days' => $this->serializeDays($month, $slotsByDate, $today),
            'selectedSlots' => $this->slotsForDate($slotsByDate, $selectedDate)
                ->map(fn (ReservationSlot $slot) => $this->serializeSlot($slot))
                ->values(),
            'settings' => $this->settings(),
        ];
    }

    /**
     * 指定された予約枠に確定予約を作成する。
     *
     * @param  array{
     *     slot_start: string,
     *     party_size: int|string,
     *     guest_name: string,
     *     guest_email: string,
     *     guest_phone?: string|null,
     *     notes?: string|null
     * }  $payload
     *
     * @throws SlotUnavailableException
     * @throws ValidationException
     */
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
                throw new SlotUnavailableException('選択した時間枠は満席になりました。別の時間を選択してください。');
            }

            $reservation = $slot->reservations()->create([
                'reservation_code' => (string) Str::ulid(),
                'guest_name' => $payload['guest_name'],
                'guest_email' => $payload['guest_email'],
                'guest_phone' => $payload['guest_phone'] ?: null,
                'party_size' => $partySize,
                'notes' => $payload['notes'] ?: null,
                'status' => Reservation::STATUS_CONFIRMED,
            ]);

            $slot->forceFill([
                'reserved_count' => $slot->reserved_count + $partySize,
            ])->save();

            return $reservation;
        });

        return $reservation->load('slot');
    }

    /**
     * 予約ステータスを更新し、必要に応じて予約枠の在庫を調整する。
     *
     * @throws ValidationException
     */
    public function updateReservationStatus(Reservation $reservation, string $status): Reservation
    {
        /** @var Reservation $managedReservation */
        $managedReservation = DB::transaction(function () use ($reservation, $status) {
            /** @var Reservation $managedReservation */
            $managedReservation = Reservation::query()
                ->lockForUpdate()
                ->findOrFail($reservation->getKey());

            /** @var ReservationSlot $slot */
            $slot = ReservationSlot::query()
                ->whereKey($managedReservation->reservation_slot_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($managedReservation->status === $status) {
                return $managedReservation->setRelation('slot', $slot);
            }

            $reservedCountDelta = $this->reservedCountDelta($managedReservation, $status, $slot);
            if ($reservedCountDelta !== 0) {
                $slot->forceFill([
                    'reserved_count' => $slot->reserved_count + $reservedCountDelta,
                ])->save();
            }

            $managedReservation->forceFill(['status' => $status])->save();

            return $managedReservation->setRelation('slot', $slot);
        });

        return $managedReservation;
    }

    /**
     * 指定月に含まれる予約枠を取得する。
     *
     * @return Collection<int, ReservationSlot>
     */
    private function slotsForMonth(CarbonImmutable $month): Collection
    {
        return ReservationSlot::query()
            ->whereBetween('slot_start', [
                $month->startOfMonth()->startOfDay()->toDateTimeString(),
                $month->endOfMonth()->endOfDay()->toDateTimeString(),
            ])
            ->orderBy('slot_start')
            ->get();
    }

    /**
     * 月内の日別カレンダー表示データを生成する。
     *
     * @param  Collection<string, Collection<int, ReservationSlot>>  $slotsByDate
     * @return array<int, array{
     *     date: string,
     *     day: int,
     *     availableSlots: int,
     *     totalSlots: int,
     *     remainingSeats: int,
     *     isPast: bool,
     *     isToday: bool
     * }>
     */
    private function serializeDays(CarbonImmutable $month, Collection $slotsByDate, CarbonImmutable $today): array
    {
        $days = [];
        $cursor = $month->startOfMonth();

        while ($cursor->lte($month->endOfMonth())) {
            $days[] = $this->serializeDay($cursor, $this->slotsForDate($slotsByDate, $cursor), $today);
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * 指定日の予約枠コレクションを取得する。
     *
     * @param  Collection<string, Collection<int, ReservationSlot>>  $slotsByDate
     * @return Collection<int, ReservationSlot>
     */
    private function slotsForDate(Collection $slotsByDate, CarbonImmutable $date): Collection
    {
        /** @var Collection<int, ReservationSlot> $slots */
        $slots = $slotsByDate->get($date->toDateString(), collect());

        return $slots;
    }

    /**
     * 日別カレンダー表示データを生成する。
     *
     * @param  Collection<int, ReservationSlot>  $slots
     * @return array{
     *     date: string,
     *     day: int,
     *     availableSlots: int,
     *     totalSlots: int,
     *     remainingSeats: int,
     *     isPast: bool,
     *     isToday: bool
     * }
     */
    private function serializeDay(CarbonImmutable $date, Collection $slots, CarbonImmutable $today): array
    {
        $bookableSlots = $this->bookableSlots($slots);

        return [
            'date' => $date->toDateString(),
            'day' => $date->day,
            'availableSlots' => $bookableSlots->filter(fn (ReservationSlot $slot) => $slot->remaining_capacity > 0)->count(),
            'totalSlots' => $slots->count(),
            'remainingSeats' => $bookableSlots->sum(fn (ReservationSlot $slot) => $slot->remaining_capacity),
            'isPast' => $date->endOfDay()->lt($today),
            'isToday' => $date->isSameDay($today),
        ];
    }

    /**
     * 予約可能期間内の予約枠だけを抽出する。
     *
     * @param  Collection<int, ReservationSlot>  $slots
     * @return Collection<int, ReservationSlot>
     */
    private function bookableSlots(Collection $slots): Collection
    {
        return $slots->filter(fn (ReservationSlot $slot) => $this->isBookableSlot($slot->slot_start));
    }

    /**
     * 予約ステータス変更時に加減算する予約済み人数を算出する。
     *
     * @throws ValidationException
     */
    private function reservedCountDelta(Reservation $reservation, string $status, ReservationSlot $slot): int
    {
        if ($reservation->status === Reservation::STATUS_CONFIRMED && $status === Reservation::STATUS_CANCELLED) {
            if ($slot->reserved_count < $reservation->party_size) {
                $this->validationError('status', '予約枠の在庫情報が不整合のため更新できません。');
            }

            return -$reservation->party_size;
        }

        if ($reservation->status === Reservation::STATUS_CANCELLED && $status === Reservation::STATUS_CONFIRMED) {
            if ($slot->remaining_capacity < $reservation->party_size) {
                $this->validationError('status', '空席が足りないため、この予約を再確定できません。');
            }

            return $reservation->party_size;
        }

        return 0;
    }

    /**
     * 指定月の既定選択日を決定する。
     */
    private function defaultDateForMonth(CarbonImmutable $month): CarbonImmutable
    {
        $today = $this->now()->startOfDay();

        if ($today->between($month->startOfMonth(), $month->endOfMonth(), true)) {
            return $today;
        }

        return $month->startOfMonth();
    }

    /**
     * 予約タイムゾーンにおける現在時刻を返す。
     */
    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /**
     * 予約タイムゾーンで厳密な日付または日時文字列を解析する。
     */
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

    /**
     * リクエスト入力から予約枠の開始日時を解析する。
     *
     * @throws ValidationException
     */
    private function parseSlotStart(string $value): CarbonImmutable
    {
        $slotStart = $this->fromFormat(self::SLOT_FORMAT, $value);

        if (! $slotStart) {
            $this->validationError('slot_start', 'The slot format is invalid.');
        }

        return $slotStart;
    }

    /**
     * 指定された予約枠と人数が予約可能か検証する。
     *
     * @throws ValidationException
     */
    private function assertSlotIsReservable(CarbonImmutable $slotStart, int $partySize): void
    {
        $opensAt = $slotStart->setTime($this->openHour, 0);
        $closesAt = $slotStart->setTime($this->closeHour, 0);
        $slotEnd = $slotStart->addMinutes($this->slotMinutes);
        $latestBookableDate = $this->now()->startOfDay()->addDays($this->bookingWindowDays);

        if ($slotStart->lt($this->now()->startOfMinute())) {
            $this->validationError('slot_start', 'Past slots cannot be reserved.');
        }

        if ($slotStart->startOfDay()->gt($latestBookableDate)) {
            $this->validationError('slot_start', 'This slot is outside the booking window.');
        }

        if (
            $slotStart->lt($opensAt)
            || $slotEnd->gt($closesAt)
            || $slotStart->minute % $this->slotMinutes !== 0
        ) {
            $this->validationError('slot_start', 'This slot is outside business hours.');
        }

        if ($partySize > $this->maxPartySize) {
            $this->validationError('party_size', 'Party size exceeds the maximum allowed.');
        }
    }

    /**
     * 指定項目のバリデーション例外を送出する。
     *
     * @throws ValidationException
     */
    private function validationError(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    /**
     * 指定月の予約枠在庫レコードが存在するように補完する。
     */
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
     * 一括登録用の予約枠在庫レコードを構築する。
     *
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

    /**
     * 予約枠が現在予約可能な期間内にあるか判定する。
     */
    private function isBookableSlot(CarbonImmutable $slotStart): bool
    {
        return $slotStart->gte($this->now()->startOfMinute())
            && $slotStart->startOfDay()->lte($this->now()->startOfDay()->addDays($this->bookingWindowDays));
    }

    /**
     * カレンダー応答用に予約枠を配列へ変換する。
     *
     * @return array{
     *     slotStart: string,
     *     startTime: string,
     *     endTime: string,
     *     capacity: int,
     *     reservedCount: int,
     *     remainingSeats: int,
     *     isBookable: bool
     * }
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
