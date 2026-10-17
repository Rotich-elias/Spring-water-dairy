<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calvings', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('dam_id')->constrained('animals');
            $table->dateTime('calving_date');
            $table->foreignId('ease_score_id')->nullable()->constrained('lookups');
            $table->boolean('retained_placenta')->default(false);
            $table->text('notes')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dam_id', 'calving_date']);
            $table->index(['calving_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calvings');
    }
};
