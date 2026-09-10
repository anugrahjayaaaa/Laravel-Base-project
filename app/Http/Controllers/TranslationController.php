<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Translation\TranslationUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\TranslationLoader\LanguageLine;

class TranslationController extends Controller
{
    use Auditable;

    protected array $locales = ['en', 'id'];

    public function index(): View
    {
        $lines = \Spatie\TranslationLoader\LanguageLine::query()
            ->when(request('q'), fn ($q, $s) => $q->where(function ($sq) use ($s) {
                $sq->where('group', 'like', "%$s%")
                    ->orWhere('key', 'like', "%$s%")
                    ->orWhereJsonContains('text->en', $s);
            }))
            ->orderBy(request('sort') ?: 'group', request('dir') ?: 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('settings.translations.index', [
            'lines' => $lines,
            'locales' => $this->locales,
        ]);
    }

    public function create(): View
    {
        return view('settings.translations.create', [
            'locales' => $this->locales,
        ]);
    }

    public function store(TranslationUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $line = LanguageLine::create([
            'group' => $data['group'],
            'key' => $data['key'],
            'text' => collect($this->locales)->mapWithKeys(fn ($loc) => [$loc => $data[$loc]])->toArray(),
        ]);

        $this->auditAction('translation_created', request()->user(), [
            'group' => $line->group,
            'key' => $line->key,
        ]);

        return redirect()->route('translations.edit', $line)
            ->with('status', __('messages.translation_created'));
    }

    public function edit(LanguageLine $languageLine): View
    {
        return view('settings.translations.edit', [
            'line' => $languageLine,
            'locales' => $this->locales,
        ]);
    }

    public function update(TranslationUpdateRequest $request, LanguageLine $languageLine): RedirectResponse
    {
        $data = $request->validated();

        foreach ($this->locales as $locale) {
            $languageLine->setTranslation($locale, $data[$locale]);
        }
        $languageLine->save();

        $this->auditAction('translation_updated', request()->user(), [
            'group' => $languageLine->group,
            'key' => $languageLine->key,
        ]);

        return redirect()->route('translations.index')
            ->with('status', __('messages.saved'));
    }
}
