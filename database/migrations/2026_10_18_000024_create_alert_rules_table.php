<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "PD due", "Dry off in 7 days", "Calving in 3 days"
            $table->string('type'); // pd_due, dry_off_due, calving_due, heat_window, no_service_day_80, withholding_ending, vaccination_due, calf_behind_target, calf_due_weaning
            $table->text('conditions')->nullable(); // configurable thresholds
            $table->text('channels')->default('["in_app"]'); // in_app, sms, email
            $table->text('recipients')->default('["Owner","Manager"]'); // roles
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
