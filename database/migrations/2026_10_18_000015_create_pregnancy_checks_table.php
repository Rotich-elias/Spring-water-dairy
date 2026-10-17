<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pregnancy_checks', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('animal_id')->constrained('animals');
            $table->date('check_date');
            $table->foreignId('pd_result_id')->nullable()->constrained('lookups');
            $table->enum('result', ['pregnant', 'open', 'recheck']);
            $table->text('notes')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['animal_id', 'check_date']);
            $table->index(['result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pregnancy_checks');
    }
};
