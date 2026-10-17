<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semen_straws', function (Blueprint $table) {
            $table->id();
            $table->string('bull_code')->unique();
            $table->string('bull_name')->nullable();
            $table->string('breed')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['bull_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semen_straws');
    }
};
