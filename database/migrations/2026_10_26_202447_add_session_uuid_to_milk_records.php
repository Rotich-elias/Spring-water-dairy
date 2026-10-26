<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_records', function (Blueprint $table) {
            // Session-level idempotency key. Each record keeps its own unique
            // client_uuid, so a session with multiple cows no longer collides.
            $table->uuid('session_uuid')->nullable()->after('client_uuid')->index();
        });

        // Backfill: before this column existed the session UUID was written to
        // client_uuid on every record, so it is the best available value.
        DB::table('milk_records')->update(['session_uuid' => DB::raw('client_uuid')]);
    }

    public function down(): void
    {
        Schema::table('milk_records', function (Blueprint $table) {
            $table->dropIndex(['session_uuid']);
            $table->dropColumn('session_uuid');
        });
    }
};
