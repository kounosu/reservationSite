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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            AdminBasicAuth::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_admin_page_lists_and_filters_reservations(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(10, 0), 4, 2);
        $visibleReservation = $this->createReservation($slot, 'Hanako Yamada', 'hanako@example.com', 2, Reservation::STATUS_CONFIRMED);

        $cancelledSlot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDays(2)->setTime(11, 0), 4, 0);
        $hiddenReservation = $this->createReservation($cancelledSlot, 'Taro Sato', 'taro@example.com', 1, Reservation::STATUS_CANCELLED);

        $response = $this->get(route('admin.reservations.index', [
            'status' => Reservation::STATUS_CONFIRMED,
            'q' => 'Hanako',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('予約管理')
            ->assertSeeText($visibleReservation->reservation_code)
            ->assertSeeText('Hanako Yamada')
            ->assertDontSeeText($hiddenReservation->reservation_code);
    }

    public function test_admin_can_cancel_a_confirmed_reservation(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(10, 0), 4, 2);
        $reservation = $this->createReservation($slot, 'Hanako Yamada', 'hanako@example.com', 2, Reservation::STATUS_CONFIRMED);

        $response = $this->from(route('admin.reservations.index'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CANCELLED,
            ]);

        $response
            ->assertRedirect(route('admin.reservations.index'))
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

    public function test_admin_can_reconfirm_a_cancelled_reservation_when_capacity_is_available(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(13, 0), 4, 1);
        $reservation = $this->createReservation($slot, 'Riko Arai', 'riko@example.com', 2, Reservation::STATUS_CANCELLED);

        $response = $this->from(route('admin.reservations.index'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CONFIRMED,
            ]);

        $response->assertRedirect(route('admin.reservations.index'));

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('reservation_slots', [
            'id' => $slot->id,
            'reserved_count' => 3,
        ]);
    }

    public function test_admin_cannot_reconfirm_a_cancelled_reservation_when_the_slot_is_full(): void
    {
        $slot = $this->createSlot(CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(15, 0), 4, 4);
        $reservation = $this->createReservation($slot, 'Mika Ito', 'mika@example.com', 1, Reservation::STATUS_CANCELLED);

        $response = $this->from(route('admin.reservations.index'))
            ->patch(route('admin.reservations.update', $reservation), [
                'status' => Reservation::STATUS_CONFIRMED,
            ]);

        $response
            ->assertRedirect(route('admin.reservations.index'))
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

    private function createSlot(CarbonImmutable $slotStart, int $capacity, int $reservedCount): ReservationSlot
    {
        return ReservationSlot::create([
            'slot_start' => $slotStart,
            'slot_end' => $slotStart->addHour(),
            'capacity' => $capacity,
            'reserved_count' => $reservedCount,
        ]);
    }

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
