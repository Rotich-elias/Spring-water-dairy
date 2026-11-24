<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('tenant_name');
            $table->unsignedBigInteger('plan_id');
            $table->string('plan_code');
            $table->string('plan_name');
            $table->unsignedBigInteger('current_plan_id')->nullable();
            $table->string('current_plan_code')->nullable();
            $table->string('current_plan_name')->nullable();
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('requester_name')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->text('note')->nullable();
            $table->string('invoice_number')->nullable()->unique();
            $table->timestamp('invoiced_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('approver_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'plan_request_tenant_status_idx');
            $table->index(['status', 'created_at'], 'plan_request_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_change_requests');
    }
};
