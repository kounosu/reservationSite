<?php

namespace App\Http\Controllers;

use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreReservationRequest;
use App\Services\ReservationCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(ReservationCalendarService $calendar): View
    {
        $initialMonth = $calendar->currentMonth();
        $initialDate = $calendar->parseDate(null, $initialMonth);

        return view('reservations.index', [
            'frontendConfig' => [
                'calendarEndpoint' => route('reservations.calendar'),
                'reservationEndpoint' => route('reservations.store'),
                'initialMonth' => $initialMonth->format('Y-m'),
                'initialDate' => $initialDate->toDateString(),
                'locale' => 'ja-JP',
                'settings' => $calendar->settings(),
            ],
        ]);
    }

    public function calendar(Request $request, ReservationCalendarService $calendar): JsonResponse
    {
        $month = $calendar->parseMonth($request->string('month')->toString());
        $selectedDate = $calendar->parseDate($request->string('date')->toString(), $month);

        return response()->json($calendar->buildCalendar($month, $selectedDate));
    }

    public function store(StoreReservationRequest $request, ReservationCalendarService $calendar): JsonResponse
    {
        try {
            $reservation = $calendar->reserve($request->validated());
        } catch (SlotUnavailableException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        $slot = $reservation->slot;

        return response()->json([
            'message' => '予約が確定しました。',
            'reservation' => [
                'code' => $reservation->reservation_code,
                'guestName' => $reservation->guest_name,
                'partySize' => $reservation->party_size,
                'slotStart' => $slot->slot_start->format('Y-m-d H:i'),
                'slotEnd' => $slot->slot_end->format('Y-m-d H:i'),
            ],
        ], 201);
    }
}
