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
        Schema::table('fixed_charges', function (Blueprint $table) {
            $table->decimal('gas_electricity_internet', 10, 2)->default(0)->after('servant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_charges', function (Blueprint $table) {
            $table->dropColumn('gas_electricity_internet');
        });
    }
};

