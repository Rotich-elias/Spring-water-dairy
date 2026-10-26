<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthController extends Controller
{
    /**
     * GET /system/health - Operational health for the API and its dependencies.
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('system.health');

        $checks = [
            'database' => $this->check(fn () => DB::connection()->getPdo() !== null),
            'cache' => $this->check(function () {
                Cache::put('system-health-check', 'ok', 60);

                return Cache::get('system-health-check') === 'ok';
            }),
            'queue' => $this->check(fn () => Queue::connection()->getConnectionName() !== null),
            'storage' => $this->check(function () {
                $path = 'system-health-check';
                $written = Storage::disk('local')->put($path, 'ok');
                Storage::disk('local')->delete($path);

                return $written;
            }),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['ok']);

        return response()->json([
            'data' => [
                'healthy' => $healthy,
                'app' => [
                    'name' => config('app.name'),
                    'environment' => app()->environment(),
                    'debug' => (bool) config('app.debug'),
                    'timezone' => config('app.timezone'),
                    'url' => config('app.url'),
                ],
                'runtime' => [
                    'php' => PHP_VERSION,
                    'laravel' => app()->version(),
                ],
                'checks' => $checks,
                'checked_at' => now()->toISOString(),
            ],
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function check(callable $callback): array
    {
        try {
            $ok = (bool) $callback();

            return [
                'ok' => $ok,
                'message' => $ok ? 'ok' : 'unavailable',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'unavailable',
            ];
        }
    }
}
