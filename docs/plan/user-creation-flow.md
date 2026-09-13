# User Creation & First Login Flow

## Status
PLAN — planning phase. Not yet implemented.

## Scope

Two separate flows:
1. **Admin creates user** — admin fills username/email, system generates random password, sends email containing credentials + verification link
2. **User self-registers** — user fills own data, system sends verification email

Both flows require email verification. Admin-created user additionally requires **password change on first login** before accessing any other page.

## Current State

- `UserController::store` — exists, uses `UserService::create` + `sendEmailVerificationNotification` + `AdminUserCreated` notification
- `RegisterController` — exists via auth scaffold
- Mail: `MAIL_MAILER=array` in `.env.testing`
- Queue: no worker/Redis yet, using `sync` driver
- Session: `SESSION_DRIVER=array` in testing, `database` in production

## Plan: Admin-Created User Flow

```
Admin POST /users
  → UserController::store
  → UserService::create (random password via Str::random(16))
  → User::sendEmailVerificationNotification()
  → AdminUserCreated notification (dispatched as queued job)
  → Set user.must_change_password = true
  → Audit log: user_created
  → Redirect to users.index
```

Email to user (admin-created):
- Subject: "Your account has been created by an administrator"
- Body: username, temporary password, email verification link, login link
- Call-to-action button: "Verify Email"

Email to user (self-register):
- Subject: "Your account has been registered"
- Body: username, email verification link
- Password NOT included (user already knows it)

## Plan: First Login Enforcement

### Middleware: `ForcePasswordChange`

```php
class ForcePasswordChange
{
    public function handle($request, Closure $next)
    {
        if (auth()->check() && auth()->user()->must_change_password) {
            $whitelist = ['profile.password', 'profile.show', 'logout'];
            if (!in_array($request->route()->getName(), $whitelist)) {
                return redirect(route('profile.show'))
                    ->with('force_password_change', true);
            }
        }
        return $next($request);
    }
}
```

**Pros:**
- Global enforcement — all requests checked
- Single point of truth
- Whitelist is smaller attack surface (safer than blacklist)

**Cons:**
- Minor overhead on each request (flag check)
- Must whitelist the password change route

**Decision:** Middleware. Security requirement — user should not access anything with a temporary password.

### User model
```php
// app/Models/User.php
protected $casts = [
    'must_change_password' => 'boolean',
];

// Migration
Schema::table('users', function (Blueprint $table) {
    $table->boolean('must_change_password')->default(false);
});
```

### ProfileController::changePassword update
Clear flag after successful password change:
```php
$user->update([
    'password' => Hash::make($request->validated()['password']),
    'must_change_password' => false,
]);
```

## Plan: Redis + Queue Implementation

### Environment variables
```
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis
```

`config/queue.php` — default Laravel Redis driver is already available.

### Jobs

1. **`SendAdminUserCreatedNotification`**
   - Queued in `UserController::store` after user creation
   - Sends `AdminUserCreated` notification via mail channel

2. **`SendVerificationEmailJob`**
   - Wraps `sendEmailVerificationNotification`
   - Queued in `UserService::create` and `RegisterController`

3. **`SendPasswordChangedNotification`**
   - Queued in `ProfileController::changePassword`
   - Email confirmation: "Your password has been successfully changed"

### Routes
```php
// Re-send verification email (for users who haven't verified)
Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->name('verification.resend')
    ->middleware(['auth', 'throttle:6,1']);
```

## Plan: Queue Worker / Supervisor

### Local Development
`scripts/dev.sh` — run worker + serve in one command:
```bash
#!/bin/bash
php artisan queue:work --sleep=1 --tries=1 --timeout=60 &
worker_pid=$!
php artisan serve
kill $worker_pid
```
Run: `bash scripts/dev.sh` — worker runs in background, serve in foreground. Ctrl+C kills both.

### Production
Supervisor config (same concept: persistent worker process):
```ini
; config/supervisor.d/laravel-worker.ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=1 --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=35
```

**Concept is the same:** persistent worker process. Local uses shell background, production uses Supervisor — differser only in orchestrator.

## Plan: Internationalization

Email templates use Laravel lang files:
- `lang/en/messages.php`: `admin_user_created_subject`, `admin_user_created_body`, `password_changed_notification`
- `lang/id/messages.php`: Indonesian translations

Notification class uses lang helpers:
```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject(__('messages.admin_user_created_subject'))
        ->line(__('messages.admin_user_created_body', [
            'username' => $notifiable->username,
            'password' => $this->plainPassword,
        ]))
        ->action(__('messages.verify_email'), $this->verificationUrl($notifiable));
}
```

## Plan: File Structure

```
app/
  Http/
    Middleware/
      ForcePasswordChange.php
    Controllers/
      ProfileController.php       # update changePassword
    Notifications/
      AdminUserCreated.php        # 2 templates: admin-created vs self-register
      PasswordChanged.php         # new
  Jobs/
    SendAdminUserCreatedNotification.php
    SendVerificationEmailJob.php
    SendPasswordChangedNotification.php
resources/views/vendor/mail/html/
  admin-user-created.blade.php
  user-registered.blade.php
  password-changed.blade.php
```

## Implementation Order

1. Migration: `must_change_password` column on users table
2. Update `UserStoreRequest` — validate password confirmation (already present, remove password from input)
3. Update `UserService::create` — generate random password, set flag
4. Update `UserController::store` — pass plain password to notification, dispatch queued job
5. Create `ForcePasswordChange` middleware — register in Kernel
6. Update `ProfileController::changePassword` — clear flag
7. Update `AdminUserCreated` notification — conditional email content
8. Create 3 Job classes
9. Update `.env.example` + `.env.testing` — Redis config
10. Email templates + lang files

## Testing Plan

- Unit: `MustChangePasswordTest` — middleware redirect logic
- Feature: `AdminUserCreationTest` — email content, random password, flag set
- Feature: `FirstLoginTest` — blocked from dashboard, can access profile.show, can access after password change
- Queue: `Queue::fake()` assertions on job dispatch

## Open Questions

- Q: Should `AdminUserCreated` notification include plaintext password in email?
  A: Yes, common pattern for initial credentials. Password only usable once because forced change is required on first login.