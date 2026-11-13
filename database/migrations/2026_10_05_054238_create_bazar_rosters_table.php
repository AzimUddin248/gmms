<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazar_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('month_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            
            // One person per date per session, or maybe multiple? Let's not strict unique just in case.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bazar_rosters');
    }
};
