<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milk can be recorded in a midday session, and farms choose how many
     * sessions they run per day (1 = morning, 2 = morning/evening,
     * 3 = morning/midday/evening). All three keys stay valid in the schema
     * so records remain intact if the farm later reduces its schedule.
     */
    public function up(): void
    {
        $sessions = ['morning', 'midday', 'evening'];

        Schema::table('milk_records', function (Blueprint $table) use ($sessions) {
            $table->enum('session', $sessions)->change();
        });

        Schema::table('bulk_tank_records', function (Blueprint $table) use ($sessions) {
            $table->enum('session', $sessions)->change();
        });

        if (! DB::table('settings')->where('key', 'milk_sessions_per_day')->exists()) {
            DB::table('settings')->insert([
                'key' => 'milk_sessions_per_day',
                'value' => '2',
                'type' => 'integer',
                'group' => 'milk',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('milk_records')->where('session', 'midday')->delete();
        DB::table('bulk_tank_records')->where('session', 'midday')->delete();

        $sessions = ['morning', 'evening'];

        Schema::table('milk_records', function (Blueprint $table) use ($sessions) {
            $table->enum('session', $sessions)->change();
        });

        Schema::table('bulk_tank_records', function (Blueprint $table) use ($sessions) {
            $table->enum('session', $sessions)->change();
        });

        DB::table('settings')->where('key', 'milk_sessions_per_day')->delete();
    }
};
