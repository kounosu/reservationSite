<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function index(Request $request, ReservationCalendarService $calendar): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Reservation::statusLabels()))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $timezone = $calendar->timezone();
        $reservations = $this->buildReservationQuery($filters)
            ->orderByDesc('reservation_slots.slot_start')
            ->orderByDesc('reservations.created_at')
            ->paginate(12)
            ->withQueryString();

        return view('admin.reservations.index', [
            'filters' => [
                'date' => $filters['date'] ?? '',
                'status' => $filters['status'] ?? '',
                'q' => trim((string) ($filters['q'] ?? '')),
            ],
            'reservations' => $reservations,
            'statusLabels' => Reservation::statusLabels(),
            'statusOptions' => array_keys(Reservation::statusLabels()),
            'summary' => $this->buildSummary($timezone),
            'timezone' => $timezone,
            'today' => CarbonImmutable::now($timezone)->toDateString(),
        ]);
    }

    public function update(
        UpdateAdminReservationRequest $request,
        Reservation $reservation,
        ReservationCalendarService $calendar
    ): RedirectResponse {
        $status = (string) $request->validated('status');

        try {
            $calendar->updateReservationStatus($reservation, $status);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', '予約ステータスを更新しました。');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildReservationQuery(array $filters): Builder
    {
        $query = Reservation::query()
            ->select('reservations.*')
            ->join('reservation_slots', 'reservation_slots.id', '=', 'reservations.reservation_slot_id')
            ->with('slot');

        if (($filters['date'] ?? null) !== null && $filters['date'] !== '') {
            $query->whereDate('reservation_slots.slot_start', $filters['date']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('reservations.status', $filters['status']);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('reservations.reservation_code', 'like', "%{$search}%")
                    ->orWhere('reservations.guest_name', 'like', "%{$search}%")
                    ->orWhere('reservations.guest_email', 'like', "%{$search}%")
                    ->orWhere('reservations.guest_phone', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @return list<array{label: string, value: int, tone: string, hint: string}>
     */
    private function buildSummary(string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();

        $baseQuery = Reservation::query()
            ->join('reservation_slots', 'reservation_slots.id', '=', 'reservations.reservation_slot_id');

        return [
            [
                'label' => '本日の予約',
                'value' => (clone $baseQuery)
                    ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                    ->whereDate('reservation_slots.slot_start', $today)
                    ->count('reservations.id'),
                'tone' => 'accent',
                'hint' => '本日来店予定の確定予約',
            ],
            [
                'label' => '今後の来店人数',
                'value' => (int) (clone $baseQuery)
                    ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                    ->where('reservation_slots.slot_start', '>=', $now->toDateTimeString())
                    ->sum('reservations.party_size'),
                'tone' => 'ink',
                'hint' => '現在時刻以降の総人数',
            ],
            [
                'label' => '確定済み予約',
                'value' => (clone $baseQuery)
                    ->where('reservations.status', Reservation::STATUS_CONFIRMED)
                    ->count('reservations.id'),
                'tone' => 'success',
                'hint' => '全期間の確定予約件数',
            ],
            [
                'label' => 'キャンセル済み',
                'value' => (clone $baseQuery)
                    ->where('reservations.status', Reservation::STATUS_CANCELLED)
                    ->count('reservations.id'),
                'tone' => 'danger',
                'hint' => '履歴として残っている件数',
            ],
        ];
    }
}
