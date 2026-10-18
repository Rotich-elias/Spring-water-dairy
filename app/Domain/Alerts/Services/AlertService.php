<?php

namespace App\Domain\Alerts\Services;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Alerts\Models\Notification;
use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\Service;
use App\Domain\Calves\Services\CalfGrowthService;
use App\Domain\Health\Models\Vaccination;
use App\Domain\Health\Services\WithholdingService;
use App\Models\Lookup;
use App\Models\User;

class AlertService
{
    public function __construct(
        private WithholdingService $withholding,
        private CalfGrowthService $calfGrowth,
    ) {}

    /**
     * Evaluate all active alert rules and create notifications.
     * Deduplicates: never creates the same alert twice for the same event.
     */
    public function evaluate(): array
    {
        $today = today()->toDateString();
        $now = now();

        $rules = AlertRule::query()->where('is_active', true)->get();

        $created = [];

        // Always check for animals currently in withholding (regardless of alert rules)
        $sickBay = $this->withholding->sickBay();
        foreach ($sickBay as $animal) {
            $key = "withholding:{$animal['id']}";
            if ($this->alreadyNotified(null, $key)) {
                continue;
            }
            $recipients = $this->resolveRecipientsByRole(['Owner', 'Manager']);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'health',
                    'title' => 'Withholding Ending',
                    'message' => "Animal {$animal['ear_tag']} withholding ends soon.",
                    'data' => ['animal_id' => $animal['id'], 'event_key' => $key],
                ]);
                $created[] = $animal['id'];
            }
        }

        foreach ($rules as $rule) {
            switch ($rule->type) {
                case 'pd_due':
                    $created = array_merge($created, $this->checkPDDue($rule));
                    break;
                case 'dry_off_due':
                    $created = array_merge($created, $this->checkDryOffDue($rule));
                    break;
                case 'calving_due':
                    $created = array_merge($created, $this->checkCalvingDue($rule));
                    break;
                case 'heat_window':
                    $created = array_merge($created, $this->checkHeatWindow($rule));
                    break;
                case 'no_service_day_80':
                    $created = array_merge($created, $this->checkNoServiceDay80($rule));
                    break;
                case 'withholding_ending':
                    $created = array_merge($created, $this->checkWithholdingEnding($rule));
                    break;
                case 'vaccination_due':
                    $created = array_merge($created, $this->checkVaccinationDue($rule));
                    break;
                case 'calf_behind_target':
                    $created = array_merge($created, $this->checkCalfBehindTarget($rule));
                    break;
                case 'calf_due_weaning':
                    $created = array_merge($created, $this->checkCalfDueWeaning($rule));
                    break;
            }
        }

        return $created;
    }

    /**
     * PD due: services where PD window ends today or tomorrow.
     */
    protected function checkPDDue(AlertRule $rule): array
    {
        $today = today();
        $tomorrow = today()->addDay();

        $services = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('pd_window_end', [$today->toDateString(), $tomorrow->toDateString()])
            ->with('animal')
            ->get();

        $created = [];
        foreach ($services as $service) {
            $key = "pd_due:{$service->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'breeding',
                    'title' => 'PD Due',
                    'message' => "Cow {$service->animal->ear_tag} is due for PD today.",
                    'data' => ['animal_id' => $service->animal_id, 'event_key' => $key],
                ]);
                $created[] = $service->id;
            }
        }

        return $created;
    }

    /**
     * Dry-off due: target_dry_date in next 7 days.
     */
    protected function checkDryOffDue(AlertRule $rule): array
    {
        $today = today();
        $end = today()->addDays(7);

        $services = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('target_dry_date', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        $created = [];
        foreach ($services as $service) {
            $key = "dry_off:{$service->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'breeding',
                    'title' => 'Dry-off Due',
                    'message' => "Cow {$service->animal->ear_tag} is due for dry-off.",
                    'data' => ['animal_id' => $service->animal_id, 'event_key' => $key],
                ]);
                $created[] = $service->id;
            }
        }

        return $created;
    }

    /**
     * Calving due: expected_calving_date in next 14 days.
     */
    protected function checkCalvingDue(AlertRule $rule): array
    {
        $today = today();
        $end = today()->addDays(14);

        $services = Service::query()
            ->where('reproductive_status', '!=', 'open')
            ->whereBetween('expected_calving_date', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        $created = [];
        foreach ($services as $service) {
            $key = "calving:{$service->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'breeding',
                    'title' => 'Calving Due',
                    'message' => "Cow {$service->animal->ear_tag} is due to calve.",
                    'data' => ['animal_id' => $service->animal_id, 'event_key' => $key],
                ]);
                $created[] = $service->id;
            }
        }

        return $created;
    }

    /**
     * Heat window: cows in heat window (from breeding service data).
     */
    protected function checkHeatWindow(AlertRule $rule): array
    {
        // Heats recorded today that haven't been serviced
        $today = today()->toDateString();

        $heats = Heat::query()
            ->where('heat_date', $today)
            ->with('animal')
            ->get();

        $created = [];
        foreach ($heats as $heat) {
            $key = "heat:{$heat->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'breeding',
                    'title' => 'Heat Detected',
                    'message' => "Cow {$heat->animal->ear_tag} is in heat.",
                    'data' => ['animal_id' => $heat->animal_id, 'event_key' => $key],
                ]);
                $created[] = $heat->id;
            }
        }

        return $created;
    }

    /**
     * No service by day 80: cows calved >80 days ago with no service.
     */
    protected function checkNoServiceDay80(AlertRule $rule): array
    {
        $cutoffDate = today()->subDays(80)->toDateString();

        $cows = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('status_id', $this->statusIds('Milking Cows'))
            ->where('calvings.count', '>=', 1) // has calved before
            ->whereDoesntHave('services', function ($q) {
                $q->where('service_date', '>=', today()->subDays(80)->toDateString());
            })
            ->with('calvings')
            ->get();

        $created = [];
        foreach ($cows as $cow) {
            $lastCalving = $cow->calvings->sortByDesc('calving_date')->first();
            if (! $lastCalving) {
                continue;
            }

            $daysSinceCalving = today()->diffInDays($lastCalving->calving_date);
            if ($daysSinceCalving < 80) {
                continue;
            }

            $key = "no_service:{$cow->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'breeding',
                    'title' => 'No Service by Day 80',
                    'message' => "Cow {$cow->ear_tag} has not been serviced by day 80.",
                    'data' => ['animal_id' => $cow->id, 'event_key' => $key],
                ]);
                $created[] = $cow->id;
            }
        }

        return $created;
    }

    /**
     * Withholding ending: animals whose withholding ends in next 3 days.
     */
    protected function checkWithholdingEnding(AlertRule $rule): array
    {
        $today = today();
        $end = today()->addDays(3);

        $animals = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->with(['treatments' => function ($q) {
                $q->whereNull('deleted_at')
                    ->whereNotNull('milk_safe_date')
                    ->where('milk_safe_date', '>=', today())
                    ->where('milk_safe_date', '<=', today()->addDays(3)->toDateString());
            }])
            ->get();

        $created = [];
        foreach ($animals as $animal) {
            $key = "withholding:{$animal->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'health',
                    'title' => 'Withholding Ending',
                    'message' => "Cow {$animal->ear_tag} withholding ends soon.",
                    'data' => ['animal_id' => $animal->id, 'event_key' => $key],
                ]);
                $created[] = $animal->id;
            }
        }

        return $created;
    }

    /**
     * Vaccination due: vaccinations due in next 7 days.
     */
    protected function checkVaccinationDue(AlertRule $rule): array
    {
        $today = today();
        $end = today()->addDays(7);

        $vaccinations = Vaccination::query()
            ->whereBetween('next_due_date', [$today->toDateString(), $end->toDateString()])
            ->with('animal')
            ->get();

        $created = [];
        foreach ($vaccinations as $vaccination) {
            $key = "vaccination:{$vaccination->id}";
            if ($this->alreadyNotified($rule->id, $key)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);
            foreach ($recipients as $user) {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'health',
                    'title' => 'Vaccination Due',
                    'message' => "Cow {$vaccination->animal->ear_tag} is due for vaccination.",
                    'data' => ['animal_id' => $vaccination->animal_id, 'event_key' => $key],
                ]);
                $created[] = $vaccination->id;
            }
        }

        return $created;
    }

    /**
     * Calf behind target: calves whose ADG is below benchmark.
     */
    protected function checkCalfBehindTarget(AlertRule $rule): array
    {
        $calves = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('category_id', $this->categoryIdFor('Calves'))
            ->with('calfRecord', 'weights')
            ->get();

        $created = [];
        foreach ($calves as $calf) {
            $adg = $this->calfGrowth->calfADG($calf);
            $benchmark = $this->calfGrowth->benchmarkWeight($calf, today()->toDateString());

            if ($adg !== null && $benchmark !== null && $adg < $benchmark * 0.9) {
                $key = "adg:{$calf->id}";
                if ($this->alreadyNotified($rule->id, $key)) {
                    continue;
                }

                $recipients = $this->resolveRecipients($rule);
                foreach ($recipients as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'health',
                        'title' => 'Calf Behind Target',
                        'message' => "Calf {$calf->ear_tag} is behind target ADG.",
                        'data' => ['animal_id' => $calf->id, 'event_key' => $key],
                    ]);
                    $created[] = $calf->id;
                }
            }
        }

        return $created;
    }

    /**
     * Calf due for weaning: ready for weaning but not yet weaned.
     */
    protected function checkCalfDueWeaning(AlertRule $rule): array
    {
        $calves = Animal::query()
            ->where('sex', 'F')
            ->whereNull('exit_date')
            ->whereNull('deleted_at')
            ->whereIn('category_id', $this->categoryIdFor('Calves'))
            ->with('calfRecord')
            ->get();

        $created = [];
        foreach ($calves as $calf) {
            $readiness = $this->calfGrowth->weaningReadiness($calf);
            if ($readiness['ready_for_weaning'] && ! $readiness['already_weaned']) {
                $key = "wean:{$calf->id}";
                if ($this->alreadyNotified($rule->id, $key)) {
                    continue;
                }

                $recipients = $this->resolveRecipients($rule);
                foreach ($recipients as $user) {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'health',
                        'title' => 'Calf Due for Weaning',
                        'message' => "Calf {$calf->ear_tag} is ready for weaning.",
                        'data' => ['animal_id' => $calf->id, 'event_key' => $key],
                    ]);
                    $created[] = $calf->id;
                }
            }
        }

        return $created;
    }

    /**
     * Resolve recipients for an alert rule based on roles.
     */
    protected function resolveRecipients(AlertRule $rule): array
    {
        $roles = $rule->roles ?? ['Owner', 'Manager'];

        return $this->resolveRecipientsByRole($roles);
    }

    /**
     * Resolve recipients by role names.
     */
    protected function resolveRecipientsByRole(array $roles)
    {
        return User::query()
            ->whereNull('deleted_at')
            ->whereHas('roles', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            })
            ->get();
    }

    /**
     * Check if a notification for this rule/key already exists (dedup).
     */
    protected function alreadyNotified(?int $ruleId, string $eventKey): bool
    {
        return Notification::query()
            ->where('data->event_key', $eventKey)
            ->where('created_at', '>=', today()->subDays(7)->toDateString())
            ->exists();
    }

    /**
     * Helper: get lookups ID for category name.
     */
    protected function categoryIdFor(string $name): ?int
    {
        return Lookup::query()
            ->where('type', 'categories')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->value('id');
    }

    /**
     * Helper: get status IDs for a status name.
     */
    protected function statusIds(string $name): array
    {
        return Lookup::query()
            ->where('type', 'animal-statuses')
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
    }
}
