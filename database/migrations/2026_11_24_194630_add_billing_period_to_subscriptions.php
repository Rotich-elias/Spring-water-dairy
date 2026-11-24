<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('canceled_at')->nullable()->after('ends_at');
            $table->timestamp('current_period_end')->nullable()->after('canceled_at');
        });

        // Existing subscriptions never had an explicit period, so derive one
        // from the dates they already carry. This keeps them usable until the
        // date the farm was previously told to expect.
        DB::table('subscriptions')->orderBy('id')->chunkById(100, function (Collection $subscriptions): void {
            foreach ($subscriptions as $subscription) {
                $periodEnd = $subscription->trial_ends_at
                    ? Carbon::parse($subscription->trial_ends_at)
                    : ($subscription->ends_at
                        ? Carbon::parse($subscription->ends_at)
                        : ($subscription->created_at
                            ? Carbon::parse($subscription->created_at)->addMonth()
                            : now()->addMonth()));

                DB::table('subscriptions')->where('id', $subscription->id)->update([
                    'current_period_end' => $periodEnd,
                    'canceled_at' => $subscription->status === 'cancelled'
                        ? ($subscription->ends_at ?? $subscription->updated_at ?? now())
                        : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['canceled_at', 'current_period_end']);
        });
    }
};
