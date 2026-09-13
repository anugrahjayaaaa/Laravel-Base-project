#!/usr/bin/env php
<?php

/**
 * Lightweight i18n audit — checks locale parity and placeholder consistency
 * across lang/{en,id}/{ui,messages}.php without a Laravel app context.
 *
 * Usage: php scripts/i18n_audit.php
 */
$root = dirname(__DIR__);
$langRoot = "$root/lang";

$locales = ['en', 'id'];
$groups = ['ui', 'messages'];

$exit = 0;

foreach ($groups as $group) {
    $data = [];
    foreach ($locales as $locale) {
        $path = "$langRoot/$locale/$group.php";
        if (! file_exists($path)) {
            echo "SKIP: $path not found\n";

            continue;
        }
        $data[$locale] = require $path;
    }

    $base = $data['en'] ?? [];
    $other = $data['id'] ?? [];

    // missing in id
    foreach ($base as $key => $val) {
        if (is_array($val)) {
            continue;
        } // skip validation sub-arrays
        if (! isset($other[$key])) {
            echo "MISSING id/$group.$key\n";
            $exit = 1;
        }
    }

    // extra in id
    foreach ($other as $key => $val) {
        if (is_array($val)) {
            continue;
        }
        if (! isset($base[$key])) {
            echo "EXTRA id/$group.$key\n";
            $exit = 1;
        }
    }

    // placeholder parity
    foreach ($base as $key => $val) {
        if (is_array($val)) {
            continue;
        }
        if (! isset($other[$key])) {
            continue;
        }
        $enPh = preg_match_all('/:(\w+)/', (string) $val);
        $idPh = preg_match_all('/:(\w+)/', (string) $other[$key]);
        if ($enPh !== $idPh) {
            echo "PH MISMATCH $group.$key (en=$enPh, id=$idPh)\n";
            $exit = 1;
        }
    }
}

echo $exit === 0 ? "i18n audit: PASS (all locales consistent)\n" : "i18n audit: FAIL\n";
exit($exit);
