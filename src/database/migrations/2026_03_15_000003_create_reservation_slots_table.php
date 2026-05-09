<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 予約枠テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('reservation_slots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('slot_start')->unique();
            $table->dateTime('slot_end');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->unsignedTinyInteger('reserved_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * 予約枠テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_slots');
    }
};
