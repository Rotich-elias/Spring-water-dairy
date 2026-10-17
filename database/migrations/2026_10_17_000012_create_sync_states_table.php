<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('model')->index();
            $table->unsignedBigInteger('record_id')->index();
            $table->timestamp('changed_at')->index();
            $table->string('action'); // created, updated, deleted
            $table->timestamps();

            $table->unique(['model', 'record_id', 'action']);
            $table->index(['changed_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
