<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('condition_id')->nullable()->constrained('lookups');
            $table->date('vaccination_date');
            $table->string('vaccine_name');
            $table->string('dose');
            $table->string('route');
            $table->foreignId('animal_id')->nullable()->constrained('animals');
            $table->foreignId('pen_id')->nullable()->constrained('lookups');
            $table->date('next_due_date')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'vaccination_date']);
            $table->index(['pen_id', 'vaccination_date']);
            $table->index(['next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
