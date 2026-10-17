<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calf_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained('animals');
            $table->decimal('colostrum_litres', 8, 2)->nullable();
            $table->integer('colostrum_hours')->nullable();
            $table->boolean('navel_dip')->default(false);
            $table->boolean('disbudding')->default(false);
            $table->date('weaning_date')->nullable();
            $table->decimal('weaning_weight', 8, 2)->nullable();
            $table->decimal('birth_weight', 8, 2)->nullable();
            $table->string('outcome')->default('live'); // live, stillborn, deceased
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id']);
            $table->index(['outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calf_records');
    }
};
