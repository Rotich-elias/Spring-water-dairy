<?php

namespace Database\Factories;

use App\Domain\Alerts\Models\AlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertRuleFactory extends Factory
{
    protected $model = AlertRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement([
                'pd_due', 'dry_off_due', 'calving_due', 'heat_window',
                'no_service_day_80', 'withholding_ending', 'vaccination_due',
                'calf_behind_target', 'calf_due_weaning',
            ]),
            'conditions' => [],
            'channels' => ['in_app'],
            'recipients' => ['Owner', 'Manager'],
            'is_active' => true,
        ];
    }
}
