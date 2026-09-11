<?php

function flattenTranslationKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys += flattenTranslationKeys($value, $fullKey);

            continue;
        }

        $keys[$fullKey] = true;
    }

    return $keys;
}

it('keeps English and Vietnamese PHP translation files structurally aligned', function (): void {
    foreach (glob(lang_path('en/*.php')) as $englishFile) {
        $filename = basename($englishFile);
        $vietnameseFile = lang_path('vi/'.$filename);

        expect($vietnameseFile)->toBeFile();

        $englishKeys = flattenTranslationKeys(require $englishFile);
        $vietnameseKeys = flattenTranslationKeys(require $vietnameseFile);

        expect(array_diff_key($englishKeys, $vietnameseKeys))
            ->toBe([]);
    }
});
