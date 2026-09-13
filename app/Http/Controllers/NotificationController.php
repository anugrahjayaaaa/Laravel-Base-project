<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use Auditable;

    public function index(Request $request): View
    {
        // ponytail: native Laravel notifications; mark all visible as read on view
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        $user->unreadNotifications->markAsRead();

        $this->auditAction('notification_mark_all_read', $user);

        return view('notifications.index', compact('notifications'));
    }
}
