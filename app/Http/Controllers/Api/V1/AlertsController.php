<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Services\AlertsService;
use App\Http\Controllers\Controller;

class AlertsController extends Controller
{
    public function __construct(private AlertsService $alerts) {}

    public function index()
    {
        $this->authorize('alerts.view');

        return response()->json(['data' => $this->alerts->list()]);
    }

    public function generate()
    {
        $this->authorize('alerts.view');

        $created = $this->alerts->generateFromData();

        return response()->json(['data' => ['created' => $created]]);
    }

    public function dismiss(Alert $alert)
    {
        $this->authorize('alerts.edit', $alert);

        $this->alerts->dismiss($alert);

        return response()->json(null, 204);
    }

    public function read(Alert $alert)
    {
        $this->authorize('alerts.view', $alert);

        $this->alerts->markRead($alert);

        return response()->json(null, 204);
    }
}
