<?php

use App\Domain\Breeding\Services\BreedingService;
use App\Http\Controllers\Api\V1\AlertRulesController;
use App\Http\Controllers\Api\V1\AlertsController;
use App\Http\Controllers\Api\V1\AnimalsController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BulkTankRecordsController;
use App\Http\Controllers\Api\V1\CalfRecordsController;
use App\Http\Controllers\Api\V1\CalvingsController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HeatsController;
use App\Http\Controllers\Api\V1\LactationsController;
use App\Http\Controllers\Api\V1\LookupsController;
use App\Http\Controllers\Api\V1\MilkRecordsController;
use App\Http\Controllers\Api\V1\MilkSessionsController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\PdfReportController;
use App\Http\Controllers\Api\V1\PregnancyChecksController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\SemenStrawsController;
use App\Http\Controllers\Api\V1\ServicesController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SetupController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Http\Controllers\Api\V1\TasksController;
use App\Http\Controllers\Api\V1\TreatmentsController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\Api\V1\VaccinationsController;
use App\Http\Controllers\Api\V1\WeightsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// All Phase 1 endpoints live under /api/v1.
Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::middleware('throttle:login')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UsersController::class, 'index'])->name('index');
            Route::post('/', [UsersController::class, 'store'])->name('store');
            Route::get('/{user}', [UsersController::class, 'show'])->name('show');
            Route::patch('/{user}', [UsersController::class, 'update'])->name('update');
            Route::delete('/{user}', [UsersController::class, 'destroy'])->name('destroy');
        });

        Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('/public', [SettingsController::class, 'publicIndex'])->name('public');
            Route::patch('/', [SettingsController::class, 'update'])->name('update');
        });

        Route::prefix('lookups')->name('lookups.')->group(function () {
            Route::get('/all', [LookupsController::class, 'all'])->name('all');
            Route::post('/', [LookupsController::class, 'store'])->name('store');
            Route::get('/{type}', [LookupsController::class, 'byType'])->name('byType');
            Route::patch('/{lookup}', [LookupsController::class, 'update'])->name('update');
            Route::delete('/{lookup}', [LookupsController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('setup')->name('setup.')->group(function () {
            Route::get('/status', [SetupController::class, 'status'])->name('status');
            Route::post('/complete', [SetupController::class, 'complete'])->name('complete');
        });

        Route::prefix('animals')->name('animals.')->group(function () {
            Route::get('/', [AnimalsController::class, 'index'])->name('index');
            Route::post('/', [AnimalsController::class, 'store'])->name('store');
            Route::post('/bulk', [AnimalsController::class, 'bulk'])->name('bulk');
            Route::post('/import', [AnimalsController::class, 'import'])->name('import');
            Route::get('/lookup', [AnimalsController::class, 'lookup'])->name('lookup');
            Route::get('/{animal}', [AnimalsController::class, 'show'])->name('show');
            Route::patch('/{animal}', [AnimalsController::class, 'update'])->name('update');
            Route::post('/{animal}/move', [AnimalsController::class, 'move'])->name('move');
            Route::post('/{animal}/exit', [AnimalsController::class, 'exit'])->name('exit');
            Route::get('/{animal}/timeline', [AnimalsController::class, 'timeline'])->name('timeline');
            Route::get('/{animal}/lineage', [AnimalsController::class, 'lineage'])->name('lineage');
            Route::get('/{animal}/milk', [LactationsController::class, 'animalMilk'])->name('milk');
        });

        Route::prefix('milk')->name('milk.')->group(function () {
            Route::get('/sessions', [MilkSessionsController::class, 'index'])->name('sessions.index');
            Route::post('/sessions', [MilkSessionsController::class, 'store'])->name('sessions.store');

            Route::prefix('milk-records')->name('milk-records.')->group(function () {
                Route::get('/', [MilkRecordsController::class, 'index'])->name('index');
                Route::post('/', [MilkRecordsController::class, 'store'])->name('store');
                Route::get('/{record}', [MilkRecordsController::class, 'show'])->name('show');
                Route::patch('/{record}', [MilkRecordsController::class, 'update'])->name('update');
                Route::delete('/{record}', [MilkRecordsController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('bulk-tank-records')->name('bulk-tank.')->group(function () {
                Route::get('/', [BulkTankRecordsController::class, 'index'])->name('index');
                Route::post('/', [BulkTankRecordsController::class, 'store'])->name('store');
                Route::get('/{record}', [BulkTankRecordsController::class, 'show'])->name('show');
            });

            Route::prefix('lactations')->name('lactations.')->group(function () {
                Route::get('/', [LactationsController::class, 'index'])->name('index');
                Route::get('/{lactation}', [LactationsController::class, 'show'])->name('show');
                Route::post('/{lactation}/dry-off', [LactationsController::class, 'dryOff'])->name('dry-off');
            });
        });

        Route::prefix('sync')->name('sync.')->group(function () {
            Route::get('/changes', [SyncController::class, 'changes'])->name('changes');
        });

        // ---------------------------------------------------------------
        // Phase 4: Breeding and calves
        // ---------------------------------------------------------------
        Route::prefix('breeding')->name('breeding.')->group(function () {
            Route::prefix('heats')->name('heats.')->group(function () {
                Route::get('/', [HeatsController::class, 'index'])->name('index');
                Route::post('/', [HeatsController::class, 'store'])->name('store');
                Route::get('/{heat}', [HeatsController::class, 'show'])->name('show');
                Route::patch('/{heat}', [HeatsController::class, 'update'])->name('update');
                Route::delete('/{heat}', [HeatsController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('services')->name('services.')->group(function () {
                Route::get('/', [ServicesController::class, 'index'])->name('index');
                Route::post('/', [ServicesController::class, 'store'])->name('store');
                Route::get('/{service}', [ServicesController::class, 'show'])->name('show');
                Route::patch('/{service}', [ServicesController::class, 'update'])->name('update');
                Route::delete('/{service}', [ServicesController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('pregnancy-checks')->name('pregnancy-checks.')->group(function () {
                Route::get('/', [PregnancyChecksController::class, 'index'])->name('index');
                Route::post('/', [PregnancyChecksController::class, 'store'])->name('store');
                Route::get('/{check}', [PregnancyChecksController::class, 'show'])->name('show');
                Route::patch('/{check}', [PregnancyChecksController::class, 'update'])->name('update');
                Route::delete('/{check}', [PregnancyChecksController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('calvings')->name('calvings.')->group(function () {
                Route::get('/', [CalvingsController::class, 'index'])->name('index');
                Route::post('/', [CalvingsController::class, 'store'])->name('store');
                Route::get('/{calving}', [CalvingsController::class, 'show'])->name('show');
                Route::patch('/{calving}', [CalvingsController::class, 'update'])->name('update');
                Route::delete('/{calving}', [CalvingsController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('semen-straws')->name('semen-straws.')->group(function () {
                Route::get('/', [SemenStrawsController::class, 'index'])->name('index');
                Route::post('/', [SemenStrawsController::class, 'store'])->name('store');
                Route::get('/{straw}', [SemenStrawsController::class, 'show'])->name('show');
                Route::patch('/{straw}', [SemenStrawsController::class, 'update'])->name('update');
                Route::delete('/{straw}', [SemenStrawsController::class, 'destroy'])->name('destroy');
            });

            Route::get('/calendar', function (Request $request) {
                Gate::authorize('breeding.view');

                $range = $request->validate([
                    'from' => ['required', 'date'],
                    'to' => ['required', 'date', 'after_or_equal:from'],
                ]);

                return response()->json([
                    'data' => app(BreedingService::class)->calendar($range['from'], $range['to']),
                ]);
            })->name('calendar');

            Route::get('/kpis', function (Request $request) {
                Gate::authorize('breeding.view');

                $range = $request->validate([
                    'from' => ['required', 'date'],
                    'to' => ['required', 'date', 'after_or_equal:from'],
                ]);

                return response()->json([
                    'data' => app(BreedingService::class)->kpis($range['from'], $range['to']),
                ]);
            })->name('kpis');
        });

        Route::prefix('calf-records')->name('calf-records.')->group(function () {
            Route::get('/', [CalfRecordsController::class, 'index'])->name('index');
            Route::post('/', [CalfRecordsController::class, 'store'])->name('store');
            Route::get('/{record}', [CalfRecordsController::class, 'show'])->name('show');
            Route::patch('/{record}', [CalfRecordsController::class, 'update'])->name('update');
            Route::delete('/{record}', [CalfRecordsController::class, 'destroy'])->name('destroy');
            Route::post('/{record}/wean', [CalfRecordsController::class, 'wean'])->name('wean');
        });

        Route::get('/calves/nursery', [CalfRecordsController::class, 'nursery'])->name('calves.nursery');

        Route::prefix('weights')->name('weights.')->group(function () {
            Route::get('/', [WeightsController::class, 'index'])->name('index');
            Route::post('/', [WeightsController::class, 'store'])->name('store');
            Route::get('/{weight}', [WeightsController::class, 'show'])->name('show');
            Route::delete('/{weight}', [WeightsController::class, 'destroy'])->name('destroy');
        });

        Route::get('/animals/{animal}/growth', [WeightsController::class, 'growth'])->name('animals.growth');

        // Phase 5: Health
        Route::prefix('treatments')->name('treatments.')->group(function () {
            Route::get('/', [TreatmentsController::class, 'index'])->name('index');
            Route::post('/', [TreatmentsController::class, 'store'])->name('store');
            Route::get('/{treatment}', [TreatmentsController::class, 'show'])->name('show');
            Route::patch('/{treatment}', [TreatmentsController::class, 'update'])->name('update');
            Route::delete('/{treatment}', [TreatmentsController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('vaccinations')->name('vaccinations.')->group(function () {
            Route::get('/', [VaccinationsController::class, 'index'])->name('index');
            Route::post('/', [VaccinationsController::class, 'store'])->name('store');
            Route::get('/{vaccination}', [VaccinationsController::class, 'show'])->name('show');
            Route::delete('/{vaccination}', [VaccinationsController::class, 'destroy'])->name('destroy');
        });

        Route::get('/health/sick-bay', [TreatmentsController::class, 'sickBay'])->name('sick-bay');

        Route::get('/animals/{animal}/health', [TreatmentsController::class, 'animalHealth'])->name('animals.health');

        // Phase 6: Dashboard & Alerts
        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('/dashboard/stats', [DashboardController::class, 'summary'])->name('dashboard.stats');
        Route::get('/dashboard/charts/milk-trend', [DashboardController::class, 'milkTrend'])->name('dashboard.milk-trend');
        Route::get('/dashboard/charts/breed-share', [DashboardController::class, 'breedShare'])->name('dashboard.breed-share');
        Route::get('/dashboard/charts/composition', [DashboardController::class, 'composition'])->name('dashboard.composition');
        Route::get('/dashboard/upcoming', [DashboardController::class, 'upcoming'])->name('dashboard.upcoming');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationsController::class, 'index'])->name('index');
            Route::patch('/{notification}/read', [NotificationsController::class, 'markRead'])->name('read');
            Route::post('/read-all', [NotificationsController::class, 'readAll'])->name('read-all');
        });

        Route::prefix('alerts')->name('alerts.')->group(function () {
            Route::get('/', [AlertsController::class, 'index'])->name('index');
            Route::post('/generate', [AlertsController::class, 'generate'])->name('generate');
            Route::patch('/{alert}/read', [AlertsController::class, 'read'])->name('read');
            Route::patch('/{alert}/dismiss', [AlertsController::class, 'dismiss'])->name('dismiss');
        });

        Route::prefix('alert-rules')->name('alert-rules.')->group(function () {
            Route::get('/', [AlertRulesController::class, 'index'])->name('index');
            Route::post('/', [AlertRulesController::class, 'store'])->name('store');
            Route::post('/generate', [AlertRulesController::class, 'generate'])->name('generate');
            Route::get('/{alertRule}', [AlertRulesController::class, 'show'])->name('show');
            Route::patch('/{alertRule}', [AlertRulesController::class, 'update'])->name('update');
            Route::delete('/{alertRule}', [AlertRulesController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('tasks')->name('tasks.')->group(function () {
            Route::get('/', [TasksController::class, 'index'])->name('index');
            Route::post('/', [TasksController::class, 'store'])->name('store');
            Route::get('/{task}', [TasksController::class, 'show'])->name('show');
            Route::patch('/{task}', [TasksController::class, 'update'])->name('update');
            Route::delete('/{task}', [TasksController::class, 'destroy'])->name('destroy');
        });

        // Phase 7: Reports & Audit
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportsController::class, 'index'])->name('index');
            Route::get('/{type}', [ReportsController::class, 'show'])->name('show');
            Route::get('/{type}/pdf', [PdfReportController::class, 'generate'])->name('pdf');
        });

        Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
            Route::get('/', [AuditController::class, 'index'])->name('index');
            Route::get('/actions', [AuditController::class, 'actions'])->name('actions');
        });

        Route::get('/system/health', SystemHealthController::class)->name('system.health');
    });
});
