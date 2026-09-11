<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /**
     * Render the shared admin sidebar view for a given user (no HTTP round-trip).
     * Lets tests assert parent/child menu visibility via permission + feature gate.
     */
    protected function sidebarHtmlFor(User $user): string
    {
        Auth::setUser($user);

        return View::make('partials.layout.sidebar')->render();
    }
}
