<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_tank_records', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->date('record_date');
            $table->enum('session', ['morning', 'evening']);
            $table->decimal('litres_collected', 10, 2);
            $table->decimal('litres_sold', 10, 2)->default(0);
            $table->decimal('litres_discarded', 10, 2)->default(0);
            $table->string('buyer')->nullable();
            $table->decimal('price_per_litre', 8, 2)->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['record_date', 'session']);
            $table->index(['record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_tank_records');
    }
};
