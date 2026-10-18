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
