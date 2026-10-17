<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_records', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique(); // idempotency for offline saves
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('record_date');
            $table->enum('session', ['morning', 'evening']);
            $table->decimal('litres', 8, 2);
            $table->tinyInteger('cmt_score')->nullable(); // 0-5 California Mastitis Test
            $table->integer('scc')->nullable(); // somatic cell count (x1000)
            $table->boolean('in_withholding')->default(false);
            $table->boolean('excluded_from_sales')->default(false);
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['animal_id', 'record_date', 'session']);
            $table->index(['record_date', 'session']);
            $table->index(['animal_id', 'record_date']);
            $table->index(['in_withholding']);
            $table->index(['excluded_from_sales']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_records');
    }
};
