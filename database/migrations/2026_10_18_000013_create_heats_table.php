<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heats', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('heat_date');
            $table->string('observed_by')->nullable();
            $table->text('notes')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'heat_date']);
            $table->index(['heat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heats');
    }
};
