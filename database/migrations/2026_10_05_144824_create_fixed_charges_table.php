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
        Schema::create('fixed_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('month_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('rent', 10, 2)->default(0);
            $table->decimal('water', 10, 2)->default(0);
            $table->decimal('servant', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['month_session_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_charges');
    }
};
