<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('treatment_date');
            $table->foreignId('condition_id')->nullable()->constrained('lookups');
            $table->string('drug_name');
            $table->string('dose');
            $table->string('route'); // IM, IV, SC, Oral, Topical
            $table->integer('milk_withholding_days')->default(0);
            $table->integer('meat_withholding_days')->default(0);
            $table->date('milk_safe_date')->nullable();
            $table->date('meat_safe_date')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'treatment_date']);
            $table->index(['milk_safe_date']);
            $table->index(['meat_safe_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
