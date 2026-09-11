<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** Get a setting value (string|null). Cached since these are read on every request. */
    public static function get(string $key, ?string $default = null): ?string
    {
        if ($key === 'license_secret') {
            throw new \LogicException('Use config("app.license_secret") — Settings is not a secret store.');
        }

        // ponytail: cache system settings — read on every request via SetLocale/PlanService/RegistrationEnabled
        return cache()->remember('settings:'.$key, 3600, fn () => (static::find($key)?->value) ?? $default);
    }

    /** Set a setting value, insert-or-update. Clears cache so changes are live. */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        // ponytail: invalidate cached value so admin settings update is reflected immediately
        cache()->forget('settings:'.$key);
    }
}
