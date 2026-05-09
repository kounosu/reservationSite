<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminBasicAuth;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 各テストの前処理を実行する。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            AdminBasicAuth::class,
            ValidateCsrfToken::class,
        ]);
    }

    /**
     * 管理画面で予約一覧を表示し、条件で絞り込めることを検証する。
     */
    public function test_admin_page_lists_and_filters_reservations(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(10, 0), 4, 2);
        $visibleReservation = $this->createReservation($slot, 'Hanako Yamada', 'hanako@example.com', 2, Reservation::STATUS_CONFIRMED);

        $cancelledSlot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDays(2)->setTime(11, 0), 4, 0);
        $hiddenReservation = $this->createReservation($cancelledSlot, 'Taro Sato', 'taro@example.com', 1, Reservation::STATUS_CANCELLED);

        $response = $this->get(route('admin.reservations.list', [
            'status' => Reservation::STATUS_CONFIRMED,
            'q' => 'Hanako',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('予約一覧')
            ->assertSeeText($visibleReservation->reservation_code)
            ->assertSeeText('Hanako Yamada')
            ->assertDontSeeText($hiddenReservation->reservation_code);
    }

    /**
     * 管理ダッシュボードから予約一覧ページへ遷移できることを検証する。
     */
    public function test_admin_dashboard_links_to_dedicated_reservation_list_page(): void
    {
        $response = $this->get(route('admin.reservations.index'));

        $response
            ->assertOk()
            ->assertSeeText('予約管理')
            ->assertSeeText('予約一覧を開く')
            ->assertSee(route('admin.reservations.list'), false);
    }

    /**
     * 管理画面の予約一覧がページネーションされることを検証する。
     */
    public function test_admin_page_is_paginated(): void
    {
        $latestReservation = null;
        $oldestReservation = null;

        for ($index = 0; $index < 25; $index++) {
            $slot = $this->createSlot(
                CarbonImmutable::now(config('app.timezone'))->addDays($index + 1)->setTime(10, 0),
                4,
                1
            );

            $reservation = $this->createReservation(
                $slot,
                'Guest '.$index,
                "guest{$index}@example.com",
                1,
                Reservation::STATUS_CONFIRMED
            );

            if ($index === 0) {
                $oldestReservation = $reservation;
            }

            if ($index === 24) {
                $latestReservation = $reservation;
            }
        }

        $firstPageResponse = $this->get(route('admin.reservations.list'));

        $firstPageResponse
            ->assertOk()
            ->assertSeeText($latestReservation?->reservation_code ?? '')
            ->assertDontSeeText($oldestReservation?->reservation_code ?? '');

        $secondPageResponse = $this->get(route('admin.reservations.list', [
            'page' => 2,
        ]));

        $secondPageResponse
            ->assertOk()
            ->assertSeeText($oldestReservation?->reservation_code ?? '')
            ->assertDontSeeText($latestReservation?->reservation_code ?? '');
    }

    /**
     * 管理者が1ページあたりの表示件数を変更できることを検証する。
     */
    public function test_admin_can_change_items_per_page(): void
    {
        $latestReservation = null;
        $eleventhReservation = null;

        for ($index = 0; $index < 15; $index++) {
            $slot = $this->createSlot(
                CarbonImmutable::now(config('app.timezone'))->addDays($index + 1)->setTime(12, 0),
                4,
                1
            );

            $reservation = $this->createReservation(
                $slot,
                'Per Page Guest '.$index,
                "per-page-{$index}@example.com",
                1,
                Reservation::STATUS_CONFIRMED
            );

            if ($index === 14) {
                $latestReservation = $reservation;
            }

            if ($index === 4) {
                $eleventhReservation = $reservation;
            }
        }

        $response = $this->get(route('admin.reservations.list', [
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertSeeText($latestReservation?->reservation_code ?? '')
            ->assertDontSeeText($eleventhReservation?->reservation_code ?? '')
            ->assertSee('option value="10" selected', false);
    }

    /**
     * 管理者が確定済み予約をキャンセルできることを検証する。
     */
    public function test_admin_can_cancel_a_confirmed_reservation(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(10, 0), 4, 2);
        $reservation = $this->createReservation($slot, 'Hanako Yamada', 'hanako@example.com', 2, Reservation::STATUS_CONFIRMED);

        $response = $this->from(route('admin.reservations.list'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CANCELLED,
            ]);

        $response
            ->assertRedirect(route('admin.reservations.list'))
            ->assertSessionHas('status', '予約ステータスを更新しました。');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('reservation_slots', [
            'id' => $slot->id,
            'reserved_count' => 0,
        ]);
    }

    /**
     * 空席がある場合にキャンセル済み予約を再確定できることを検証する。
     */
    public function test_admin_can_reconfirm_a_cancelled_reservation_when_capacity_is_available(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(13, 0), 4, 1);
        $reservation = $this->createReservation($slot, 'Riko Arai', 'riko@example.com', 2, Reservation::STATUS_CANCELLED);

        $response = $this->from(route('admin.reservations.list'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CONFIRMED,
            ]);

        $response->assertRedirect(route('admin.reservations.list'));

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('reservation_slots', [
            'id' => $slot->id,
            'reserved_count' => 3,
        ]);
    }

    /**
     * 満席の予約枠ではキャンセル済み予約を再確定できないことを検証する。
     */
    public function test_admin_cannot_reconfirm_a_cancelled_reservation_when_the_slot_is_full(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(15, 0), 4, 4);
        $reservation = $this->createReservation($slot, 'Mika Ito', 'mika@example.com', 1, Reservation::STATUS_CANCELLED);

        $response = $this->from(route('admin.reservations.list'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CONFIRMED,
            ]);

        $response
            ->assertRedirect(route('admin.reservations.list'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('reservation_slots', [
            'id' => $slot->id,
            'reserved_count' => 4,
        ]);
    }

    /**
     * テスト用の予約枠を作成する。
     */
    private function createSlot(CarbonImmutable $slotStart, int $capacity, int $reservedCount): ReservationSlot
    {
        return ReservationSlot::create([
            'slot_start' => $slotStart,
            'slot_end' => $slotStart->addHour(),
            'capacity' => $capacity,
            'reserved_count' => $reservedCount,
        ]);
    }

    /**
     * テスト用の予約を作成する。
     */
    private function createReservation(
        ReservationSlot $slot,
        string $guestName,
        string $guestEmail,
        int $partySize,
        string $status
    ): Reservation {
        return Reservation::create([
            'reservation_slot_id' => $slot->id,
            'reservation_code' => (string) Str::ulid(),
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => '09012345678',
            'party_size' => $partySize,
            'notes' => 'Admin test note',
            'status' => $status,
        ]);
    }
}
