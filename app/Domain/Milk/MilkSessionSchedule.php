<?php

namespace App\Domain\Milk;

use App\Models\Setting;

/**
 * Session keys supported by the milk domain and the farm's configured
 * sessions-per-day. All three keys exist in the schema so records stay
 * valid if the schedule is reduced and later expanded again.
 */
class MilkSessionSchedule
{
    /** @var list<string> */
    public const ALL = ['morning', 'midday', 'evening'];

    public const DEFAULT_PER_DAY = 2;

    public static function perDay(): int
    {
        $value = Setting::query()
            ->where('key', 'milk_sessions_per_day')
            ->value('value');

        $count = (int) ($value ?? self::DEFAULT_PER_DAY);

        return max(1, min(count(self::ALL), $count));
    }

    /**
     * @return list<string>
     */
    public static function enabled(): array
    {
        return match (self::perDay()) {
            1 => ['morning'],
            3 => ['morning', 'midday', 'evening'],
            default => ['morning', 'evening'],
        };
    }

    /**
     * Pick the session that best matches the time of day for the given farm.
     *
     * @param  list<string>|null  $enabled
     */
    public static function defaultForHour(int $hour, ?array $enabled = null): string
    {
        $enabled ??= self::enabled();

        $preferred = match (true) {
            $hour < 11 => 'morning',
            $hour < 16 => 'midday',
            default => 'evening',
        };

        if (in_array($preferred, $enabled, true)) {
            return $preferred;
        }

        return $hour < 16 ? $enabled[0] : $enabled[count($enabled) - 1];
    }
}
