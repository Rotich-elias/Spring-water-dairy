<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'settings',
        'lookups',
        'audit_logs',
        'animals',
        'milk_records',
        'bulk_tank_records',
        'lactations',
        'sync_states',
        'heats',
        'services',
        'pregnancy_checks',
        'calvings',
        'semen_straws',
        'weights',
        'calf_records',
        'treatments',
        'vaccinations',
        'alerts',
        'notifications',
        'alert_rules',
        'tasks',
        'images',
    ];

    /**
     * Unique indexes that must be scoped per tenant: [table, old columns, new columns].
     *
     * @var array<string, array{list<string>, list<string>}>
     */
    private const UNIQUE_INDEXES = [
        'animals' => [['ear_tag'], ['tenant_id', 'ear_tag']],
        'milk_records' => [['animal_id', 'record_date', 'session'], ['tenant_id', 'animal_id', 'record_date', 'session']],
        'bulk_tank_records' => [['record_date', 'session'], ['tenant_id', 'record_date', 'session']],
        'sync_states' => [['model', 'record_id', 'action'], ['tenant_id', 'model', 'record_id', 'action']],
        'settings' => [['key'], ['tenant_id', 'key']],
        'lookups' => [['type', 'name'], ['tenant_id', 'type', 'name']],
        'semen_straws' => [['bull_code'], ['tenant_id', 'bull_code']],
    ];

    /**
     * Single-column client_uuid uniques that become tenant-scoped.
     *
     * @var list<string>
     */
    private const CLIENT_UUID_TABLES = [
        'animals',
        'milk_records',
        'bulk_tank_records',
        'heats',
        'services',
        'pregnancy_checks',
        'calvings',
        'weights',
        'treatments',
        'vaccinations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        foreach (self::uniqueIndexMap() as $tableName => [$oldColumns, $newColumns]) {
            Schema::table($tableName, function (Blueprint $table) use ($oldColumns, $newColumns): void {
                $table->dropUnique($oldColumns);
                $table->unique($newColumns);
            });
        }
    }

    public function down(): void
    {
        foreach (self::uniqueIndexMap() as $tableName => [$oldColumns, $newColumns]) {
            Schema::table($tableName, function (Blueprint $table) use ($oldColumns, $newColumns): void {
                $table->dropUnique($newColumns);
                $table->unique($oldColumns);
            });
        }

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }

    /**
     * @return array<string, array{list<string>, list<string>}>
     */
    private static function uniqueIndexMap(): array
    {
        $map = self::UNIQUE_INDEXES;

        foreach (self::CLIENT_UUID_TABLES as $tableName) {
            $map[$tableName] = [['client_uuid'], ['tenant_id', 'client_uuid']];
        }

        ksort($map);

        return $map;
    }
};
