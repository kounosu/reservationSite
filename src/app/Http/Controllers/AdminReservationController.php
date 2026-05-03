<?php

namespace App\Http\Controllers;

use App\Application\Admin\Reservations\ListAdminReservationsInput;
use App\Application\Admin\Reservations\ListAdminReservationsUseCase;
use App\Application\Admin\Reservations\ViewAdminReservationsDashboardUseCase;
use App\Domain\Reservations\AdminReservationFilters;
use App\Http\Requests\ListAdminReservationsRequest;
use App\Http\Requests\UpdateAdminReservationRequest;
use App\Models\Reservation;
use App\Services\ReservationCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 管理者向け予約管理画面を扱うコントローラ。
 */
class AdminReservationController extends Controller
{
    /**
     * 予約管理ダッシュボードを表示する。
     *
     * @param \App\Services\ReservationCalendarService $calendar カレンダー設定を取得するサービス
     * @param \App\Application\Admin\Reservations\ViewAdminReservationsDashboardUseCase $useCase ダッシュボード表示データを生成するユースケース
     * @return \Illuminate\View\View
     */
    public function index(
        ReservationCalendarService $calendar,
        ViewAdminReservationsDashboardUseCase $useCase,
    ): View {
        $viewData = $useCase->handle($calendar->timezone());

        return view('admin.reservations.index', $viewData->toArray());
    }

    /**
     * 検索条件に一致する予約一覧を表示する。
     *
     * @param \App\Http\Requests\ListAdminReservationsRequest $request 予約一覧の検索条件を含むリクエスト
     * @param \App\Services\ReservationCalendarService $calendar カレンダー設定を取得するサービス
     * @param \App\Application\Admin\Reservations\ListAdminReservationsUseCase $useCase 予約一覧表示データを生成するユースケース
     * @return \Illuminate\View\View
     */
    public function list(
        ListAdminReservationsRequest $request,
        ReservationCalendarService $calendar,
        ListAdminReservationsUseCase $useCase
    ): View {
        $viewData = $useCase->handle(new ListAdminReservationsInput(
            filters: AdminReservationFilters::fromArray($request->filters()),
            timezone: $calendar->timezone(),
            perPage: $request->perPage(),
        ));

        return view('admin.reservations.list', $viewData->toArray());
    }

    /**
     * 予約ステータスを更新する。
     *
     * @param \App\Http\Requests\UpdateAdminReservationRequest $request ステータス更新用の検証済みリクエスト
     * @param \App\Models\Reservation $reservation 更新対象の予約
     * @param \App\Services\ReservationCalendarService $calendar 予約ステータス更新処理を行うサービス
     * @return \Illuminate\Http\RedirectResponse
     */
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
