<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

class FeatureController extends Controller
{
    public function index(): View
    {
        $order = ['access', 'monitoring', 'settings', 'billing', 'workspace', 'other'];

        $features = collect(config('pennant.features'))
            ->map(fn ($meta, $slug) => [
                'slug' => $slug,
                'label' => $meta['label'] ?? $slug,
                'group' => $meta['group'] ?? 'other',
                'enabled' => Feature::active($slug),
            ])
            ->sortBy('label')
            ->groupBy('group')
            ->sortBy(fn ($_, $group) => array_search($group, $order, true));

        return view('settings.features.index', compact('features'));
    }

    public function toggle(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, config('pennant.features', [])), 404);

        $request->boolean('enabled')
            ? Feature::activate($slug)
            : Feature::deactivate($slug);

        $label = featureLabel($slug);

        return redirect()->route('features.index')->with(
            'success',
            $request->boolean('enabled')
                ? __('messages.feature_enabled', ['label' => $label])
                : __('messages.feature_disabled', ['label' => $label])
        );
    }
}
