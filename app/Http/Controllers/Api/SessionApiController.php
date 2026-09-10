<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\Auditable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Sessions
 *
 * Active sessions for the authenticated user.
 */
class SessionApiController extends Controller
{
    use Auditable;

    /** List active sessions. */
    public function index(Request $request): JsonResponse
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return response()->json(['sessions' => $sessions]);
    }

    /** Log out all other sessions. */
    public function logoutOthers(Request $request): JsonResponse
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->delete();

        $this->audit($request->user(), 'session_logout_others', $request->user());

        return response()->json(['message' => __('messages.sessions_logged_out')]);
    }
}
