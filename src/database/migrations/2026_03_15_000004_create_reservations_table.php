<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_slot_id')->constrained()->cascadeOnDelete();
            $table->char('reservation_code', 26)->unique();
            $table->string('guest_name', 80);
            $table->string('guest_email', 120);
            $table->string('guest_phone', 30)->nullable();
            $table->unsignedTinyInteger('party_size');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
