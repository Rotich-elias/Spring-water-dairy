<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid')->nullable()->unique(); // idempotency for offline creates
            $table->string('ear_tag')->unique();
            $table->string('name')->nullable();
            $table->foreignId('category_id')->constrained('lookups');
            $table->foreignId('pen_id')->nullable()->constrained('lookups');
            $table->foreignId('status_id')->nullable()->constrained('lookups');
            $table->foreignId('breed_id')->nullable()->constrained('lookups');
            $table->enum('sex', ['M', 'F']);
            $table->date('birth_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->string('exit_reason')->nullable();
            $table->decimal('exit_price', 12, 2)->nullable();
            $table->foreignId('dam_id')->nullable()->constrained('animals');
            $table->foreignId('sire_id')->nullable()->constrained('animals');
            $table->integer('version')->default(1); // optimistic locking
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status_id', 'pen_id']);
            $table->index(['breed_id']);
            $table->index(['sex']);
            $table->index(['exit_date']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
