<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Models\Notification;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('notifications.view');

        $query = $request->user()->appNotifications()->with('user');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        Gate::authorize('notifications.view');

        $canManageOthers = $request->user()->can('notifications.update');
        abort_unless($notification->user_id === $request->user()->id || $canManageOthers, 403);

        $notification->update(['read_at' => now()]);

        return response()->json([
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    public function readAll(Request $request)
    {
        Gate::authorize('notifications.view');

        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'data' => ['read_all' => true],
        ]);
    }
}
