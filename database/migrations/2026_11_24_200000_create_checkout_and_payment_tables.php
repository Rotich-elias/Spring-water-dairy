<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('provider_price_id')->nullable()->after('price_monthly');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider')->nullable()->after('current_period_end');
            $table->string('provider_customer_id')->nullable()->after('provider');
            $table->string('provider_subscription_id')->nullable()->after('provider_customer_id');
            $table->string('provider_email_token')->nullable()->after('provider_subscription_id');
            $table->string('provider_plan_code')->nullable()->after('provider_email_token');
            $table->string('payment_status')->default('none')->after('provider_plan_code');
            $table->timestamp('last_payment_at')->nullable()->after('payment_status');
        });

        Schema::create('payment_customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('provider', 32);
            $table->string('provider_customer_id');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_customer_id'], 'payment_customer_provider_id_unique');
            $table->index(['tenant_id', 'provider'], 'payment_customer_tenant_idx');
        });

        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('plan_id');
            $table->string('provider', 32);
            $table->string('reference')->unique();
            $table->string('provider_session_id')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->text('authorization_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'expires_at'], 'checkout_tenant_status_idx');
            $table->index(['plan_id', 'status'], 'checkout_plan_status_idx');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('checkout_session_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('provider', 32);
            $table->string('provider_reference')->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_invoice_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('receipt_url')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'payment_tenant_status_idx');
            $table->index(['subscription_id', 'created_at'], 'payment_subscription_idx');
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id');
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'webhook_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('checkout_sessions');
        Schema::dropIfExists('payment_customers');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'provider',
                'provider_customer_id',
                'provider_subscription_id',
                'provider_email_token',
                'provider_plan_code',
                'payment_status',
                'last_payment_at',
            ]);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('provider_price_id');
        });
    }
};
