<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\TranslationLoader\LanguageLine;

class LanguageLineSeeder extends Seeder
{
    public function run(): void
    {
        // Only groups seeded into DB for runtime override.
        // validation.php uses nested arrays — skipped by design (see LanguageLine docs).
        $groups = ['messages', 'ui'];
        $locales = config('app.available_locales');

        foreach ($groups as $group) {
            $keys = [];
            foreach ($locales as $locale) {
                $path = lang_path("{$locale}/{$group}.php");
                if (! file_exists($path)) {
                    continue;
                }
                foreach (require $path as $key => $text) {
                    // Laravel's validation.php ships nested keys (e.g. 'between' => ['numeric' => ...]);
                    // storing those as JSON makes getTranslation() return an array -> TypeError.
                    if (is_array($text)) {
                        continue;
                    }
                    $keys[$key][$locale] = $text;
                }
            }
            foreach ($keys as $key => $text) {
                LanguageLine::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['text' => $text]
                );
            }

            // ponytail: drop DB rows whose key no longer exists in lang files (idempotent cleanup)
            $validKeys = array_keys($keys);
            LanguageLine::where('group', $group)
                ->whereNotIn('key', $validKeys)
                ->delete();
        }
    }
}
