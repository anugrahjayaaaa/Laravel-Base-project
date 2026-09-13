<?php

namespace App\Enums;

enum LicenseMode: string
{
    case GLOBAL = 'global';
    case PER_USER = 'per_user';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global (Instance)',
            self::PER_USER => 'Per-User',
        };
    }
}
