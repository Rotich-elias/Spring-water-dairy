<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support modifying ENUM/CHECK constraints directly;
        // recreate the check constraint by dropping and re-adding via raw SQL.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite CHECK constraints cannot be altered; recreate the table.
            Schema::table('services', function (Blueprint $table) {
                // No-op for SQLite; the constraint was created inline in migration.
            });
        }
    }

    public function down(): void
    {
        //
    }
};
