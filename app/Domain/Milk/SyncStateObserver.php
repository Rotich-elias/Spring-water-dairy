<?php

namespace App\Domain\Milk;

use App\Domain\Animals\Models\Animal;
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
use App\Domain\Milk\Models\SyncState;
use Illuminate\Database\Eloquent\Model;

class SyncStateObserver
{
    /**
     * Models that should be tracked for sync.
     *
     * @var list<string>
     */
    public static $trackedModels = [
        Animal::class,
        MilkRecord::class,
        Treatment::class,
        Vaccination::class,
        Weight::class,
        CalfRecord::class,
        Service::class,
        Heat::class,
        Calving::class,
        PregnancyCheck::class,
        SemenStraw::class,
        BulkTankRecord::class,
        Lactation::class,
    ];

    /**
     * Friendly entity names exposed to offline clients.
     *
     * @var array<class-string, string>
     */
    public static array $entityTypes = [
        Animal::class => 'animals',
        MilkRecord::class => 'milk_records',
        Treatment::class => 'treatments',
        Vaccination::class => 'vaccinations',
        Weight::class => 'weights',
        CalfRecord::class => 'calf_records',
        Service::class => 'services',
        Heat::class => 'heats',
        Calving::class => 'calvings',
        PregnancyCheck::class => 'pregnancy_checks',
        SemenStraw::class => 'semen_straws',
        BulkTankRecord::class => 'bulk_tank_records',
        Lactation::class => 'lactations',
    ];

    public static function typeFor(string $modelClass): string
    {
        return self::$entityTypes[$modelClass] ?? class_basename($modelClass);
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    protected function record(Model $model, string $action): void
    {
        $class = $model::class;
        if (! in_array($class, self::$trackedModels, true)) {
            return;
        }

        SyncState::updateOrCreate(
            [
                'model' => $class,
                'record_id' => $model->getKey(),
                'action' => $action,
            ],
            [
                'changed_at' => now(),
            ]
        );
    }
}
