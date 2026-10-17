<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lactations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained('animals');
            $table->integer('lactation_number');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->decimal('total_litres', 12, 2)->default(0);
            $table->decimal('peak_yield', 8, 2)->nullable();
            $table->integer('peak_day')->nullable();
            $table->date('drying_off_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'started_at']);
            $table->index(['ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lactations');
    }
};
