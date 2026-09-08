<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Controllers\Concerns\Sortable;
use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminUserCreated;
use App\Services\BulkDeleteService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    use Sortable, Auditable;

    public function __construct(private UserService $users, private BulkDeleteService $bulk) {}

    public function index(Request $request): View
    {
        $users = User::withTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('username', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%');
            }))
            ->with('roles')
            ->when(true, fn ($q) => $this->sortIndex($q, $request, 'name', ['name', 'username', 'email']))
            ->paginate(10)
            ->withQueryString();

        return view('access.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('access.users.create', compact('roles'));
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $plain = $request->validated()['password'];
        $user = $this->users->create($request->validated());

        $this->audit($user, 'user_created', $request->user(), [
            'username' => $user->username,
            'email' => $user->email,
        ]);

        if ($user) {
            $user->sendEmailVerificationNotification();
            $user->notify(new AdminUserCreated(
                plainPassword: $plain,
                loginUrl: route('login'),
            ));
        }

        return redirect()->route('users.index')->with('success', __('messages.user_created'));
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
        $user->load('roles');

        return view('access.users.edit', compact('user', 'roles'));
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated());

        $this->audit($user, 'user_updated', $request->user());

        return redirect()->route('users.index')->with('success', __('messages.user_updated'));
    }

    /**
     * Unlock a locked account (clear locked_until). Admin action.
     */
    public function unlock(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->users->unlock($user);

        $this->audit($user, 'user_unlocked', auth()->user());

        return redirect()->route('users.index')->with('success', __('messages.user_unlocked'));
    }

    /**
     * Permanently lock an account (admin action). Only unlock() clears it.
     */
    public function lock(int $id): RedirectResponse
    {
        if ($id === auth()->id()) {
            return redirect()->route('users.index')->with('error', __('messages.cannot_lock_self'));
        }
        $user = User::withTrashed()->findOrFail($id);
        $this->users->lock($user);

        $this->audit($user, 'user_locked', auth()->user());

        return redirect()->route('users.index')->with('success', __('messages.user_locked'));
    }

    /**
     * Send a password reset link to the user's email (admin-triggered).
     * Requires MAIL_* to be configured for actual delivery.
     */
    public function sendResetPassword(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $status = $this->users->sendResetPassword($user);

        if ($status === Password::RESET_LINK_SENT) {
            $this->audit($user, 'user_reset_link_sent', auth()->user());

            return redirect()->route('users.index')->with('success', __('messages.reset_link_sent', ['email' => $user->email]));
        }

        return redirect()->route('users.index')->with('error', __('messages.could_not_send_reset_link', ['status' => __($status)]));
    }

    public function bulk(BulkActionRequest $request): RedirectResponse
    {
        $force = $request->input('action') === 'force';
        $done = $this->bulk->run(
            User::class,
            $request->input('ids'),
            $force,
            'user',
            fn (User $user) => $user->id === auth()->id(), // never bulk-delete yourself
        );

        $key = $force ? 'users_permanently_deleted_count' : 'users_deleted_count';

        return redirect()->route('users.index')->with('success', __('messages.'.$key, ['count' => $done]));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', __('messages.cannot_delete_self'));
        }

        $this->audit($user, 'user_deleted', $request->user());

        $user->delete();

        return redirect()->route('users.index')->with('success', __('messages.user_deleted'));
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->audit($user, 'user_restored', $request->user());

        $user->restore();

        return redirect()->route('users.index')->with('success', __('messages.user_restored'));
    }

    public function forceDelete(int $id): RedirectResponse
    {
        if ($id === auth()->id()) {
            return redirect()->route('users.index')->with('error', __('messages.cannot_delete_self_permanently'));
        }

        $user = User::withTrashed()->findOrFail($id);

        $this->audit($user, 'user_force_deleted', $request->user());

        $user->forceDelete();

        return redirect()->route('users.index')->with('success', __('messages.user_permanently_deleted'));
    }
}
