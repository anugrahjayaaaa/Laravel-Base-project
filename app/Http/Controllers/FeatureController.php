<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

class FeatureController extends Controller
{
    use Auditable;

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

        $enabled = $request->boolean('enabled');
        $enabled
            ? Feature::activate($slug)
            : Feature::deactivate($slug);

        $this->auditAction($enabled ? 'feature_enabled' : 'feature_disabled', $request->user(), [
            'feature' => $slug,
            'label' => featureLabel($slug),
        ]);

        return redirect()->route('features.index')->with(
            'success',
            $enabled
                ? __('messages.feature_enabled', ['label' => featureLabel($slug)])
                : __('messages.feature_disabled', ['label' => featureLabel($slug)])
        );
    }
}
