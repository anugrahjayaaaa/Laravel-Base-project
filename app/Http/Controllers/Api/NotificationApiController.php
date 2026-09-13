<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Notifications
 *
 * Authenticated user's notifications.
 */
class NotificationApiController extends Controller
{
    use Auditable;

    /** List notifications (paginated). Marks all as read on view. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        $user->unreadNotifications->markAsRead();

        $this->auditAction('notification_mark_all_read', $user);

        return response()->json(NotificationResource::collection($notifications)->response()->getData(true));
    }

    /** Unread notification count. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    /** Mark all notifications as read. */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        $this->auditAction('notification_mark_all_read', $request->user());

        return response()->json(['message' => __('messages.notifications_marked_read')]);
    }
}
