<?php

namespace App\Support\Documents;

final class DocumentFilename
{
    public static function make(string ...$parts): string
    {
        $chunks = [];

        foreach ($parts as $part) {
            $slug = self::slug($part);

            if ($slug !== '') {
                $chunks[] = $slug;
            }
        }

        if ($chunks === []) {
            $chunks[] = 'document';
        }

        return implode('_', $chunks).'.pdf';
    }

    public static function agencySlug(string $agencyName): string
    {
        $words = preg_split('/\s+/', trim($agencyName)) ?: [];
        $words = array_values(array_filter($words, fn (string $word): bool => $word !== ''));

        if (count($words) >= 2) {
            return self::slug($words[0].' '.$words[1]);
        }

        return self::slug($agencyName);
    }

    public static function slug(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['/', '\\'], '-', $value);
        $converted = preg_replace('/[^\pL\pN]+/u', '-', $value) ?? $value;
        $converted = trim($converted, '-');

        return $converted;
    }
}
