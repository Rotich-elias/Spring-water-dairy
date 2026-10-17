<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weights', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('weight_date');
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('adg', 8, 3)->nullable(); // average daily gain
            $table->boolean('on_target')->default(false);
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'weight_date']);
            $table->index(['weight_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weights');
    }
};
