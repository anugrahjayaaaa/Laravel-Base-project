<?php

namespace App\Http\Requests\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Validasi untuk create + update translation line.
 * - store:  group, key, + tiap locale diperlukan
 * - update: hanya tiap locale (group/key tidak berubah)
 */
class TranslationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = collect(Config::get('app.available_locales', ['en', 'id']))
            ->mapWithKeys(fn ($locale) => [$locale => 'required|string'])->toArray();

        if ($this->route()->named('translations.store')) {
            return [
                'group' => 'required|string',
                'key' => 'required|string',
                ...$locales,
            ];
        }

        return $locales;
    }
}
