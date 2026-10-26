<?php

namespace App\Providers;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Animals\Models\Animal;
use App\Domain\Audit\AuditObserver;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Breeding\Models\SemenStraw;
use App\Domain\Breeding\Models\Service;
use App\Domain\Calves\Models\CalfRecord;
use App\Domain\Calves\Models\Weight;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Milk\Models\BulkTankRecord;
use App\Domain\Milk\Models\Lactation;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Milk\SyncStateObserver;
use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Business models that should keep a full audit trail.
     *
     * @var list<class-string>
     */
    protected array $auditedModels = [
        Animal::class,
        MilkRecord::class,
        BulkTankRecord::class,
        Lactation::class,
        Heat::class,
        Service::class,
        PregnancyCheck::class,
        Calving::class,
        SemenStraw::class,
        Weight::class,
        CalfRecord::class,
        Treatment::class,
        Vaccination::class,
        Lookup::class,
        AlertRule::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatic audit trail — every write to audited models is recorded.
        User::observe(AuditObserver::class);
        Setting::observe(AuditObserver::class);

        foreach ($this->auditedModels as $model) {
            $model::observe(AuditObserver::class);
        }

        // Sync state tracking — every write to synced models is recorded for offline sync.
        foreach (SyncStateObserver::$trackedModels as $model) {
            $model::observe(SyncStateObserver::class);
        }
    }
}
