<?php

namespace Tests\Feature;

use App\Models\ReservationSlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_calendar_endpoint_returns_month_data(): void
    {
        $monthDate = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        $month = $monthDate->format('Y-m');
        $date = $monthDate->format('Y-m-d');

        $response = $this->getJson(route('reservations.calendar', [
            'month' => $month,
            'date' => $date,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('month', $month)
            ->assertJsonCount($monthDate->daysInMonth, 'days');
    }

    public function test_user_can_create_a_reservation_and_inventory_is_updated(): void
    {
        $slotStart = CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(10, 0);
        $slot = $this->createSlot($slotStart, capacity: 4, reservedCount: 1);

        $response = $this->postJson(route('reservations.store'), [
            'slot_start' => $slotStart->format('Y-m-d H:i'),
            'guest_name' => 'Hanako Yamada',
            'guest_email' => 'hanako@example.com',
            'guest_phone' => '09012345678',
            'party_size' => 2,
            'notes' => 'Window seat preferred',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('reservation.partySize', 2)
            ->assertJsonPath('reservation.slotStart', $slotStart->format('Y-m-d H:i'));

        $this->assertDatabaseHas('reservations', [
            'reservation_slot_id' => $slot->id,
            'guest_email' => 'hanako@example.com',
            'party_size' => 2,
        ]);

        $this->assertDatabaseHas('reservation_slots', [
            'id' => $slot->id,
            'reserved_count' => 3,
        ]);
    }

    public function test_full_slot_returns_conflict(): void
    {
        $slotStart = CarbonImmutable::now(config('app.timezone'))->addDay()->setTime(11, 0);
        $this->createSlot($slotStart, capacity: 2, reservedCount: 2);

        $response = $this->postJson(route('reservations.store'), [
            'slot_start' => $slotStart->format('Y-m-d H:i'),
            'guest_name' => 'Taro Sato',
            'guest_email' => 'taro@example.com',
            'guest_phone' => null,
            'party_size' => 1,
            'notes' => null,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'The selected slot just sold out. Please choose another time.');

        $this->assertDatabaseCount('reservations', 0);
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
}
