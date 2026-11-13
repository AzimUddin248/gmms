<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->decimal('guest_lunch', 3, 1)->default(0)->after('lunch');
            $table->decimal('guest_dinner', 3, 1)->default(0)->after('dinner');
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn(['guest_lunch', 'guest_dinner']);
        });
    }
};
