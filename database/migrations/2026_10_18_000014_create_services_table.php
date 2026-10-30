<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('service_date');
            // The semen_straws table is created in a later migration, so the
            // foreign key is added there once the table exists.
            $table->foreignId('bull_id')->nullable();
            $table->string('inseminator')->nullable();
            $table->enum('service_type', ['AI', 'Natural'])->default('AI');
            $table->date('expected_calving_date');
            $table->date('target_dry_date');
            $table->date('pd_window_start');
            $table->date('pd_window_end');
            $table->enum('reproductive_status', ['open', 'pregnant', 'recheck'])->default('open');
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'service_date']);
            $table->index(['reproductive_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
