<?php

namespace App\Http\Controllers;

use App\Application\Admin\Reservations\ViewAdminReservationsDashboardUseCase;
use App\Application\Admin\Reservations\ListAdminReservationsInput;
use App\Application\Admin\Reservations\ListAdminReservationsUseCase;
use App\Domain\Reservations\AdminReservationFilters;
use App\Http\Requests\ListAdminReservationsRequest;
use App\Http\Requests\UpdateAdminReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function index(
        ReservationCalendarService $calendar,
        ViewAdminReservationsDashboardUseCase $useCase,
    ): View
    {
        $viewData = $useCase->handle($calendar->timezone());

        return view('admin.reservations.index', $viewData->toArray());
    }

    public function list(
        ListAdminReservationsRequest $request,
        ReservationCalendarService $calendar,
        ListAdminReservationsUseCase $useCase
    ): View
    {
        $viewData = $useCase->handle(new ListAdminReservationsInput(
            filters: AdminReservationFilters::fromArray($request->filters()),
            timezone: $calendar->timezone(),
            perPage: $request->perPage(),
        ));

        return view('admin.reservations.list', $viewData->toArray());
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
}
