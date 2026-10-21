<?php

namespace App\Support;

/**
 * MDM 11-07 — searchable matching for Agency IANA timezone options.
 * Mirrors the client-side filter in TimezoneSearchSelect.
 */
final class TimezoneOptionSearch
{
    /**
     * @param  list<array{value: string, label: string, group?: string|null}>  $options
     * @return list<array{value: string, label: string, group?: string|null}>
     */
    public static function filter(array $options, string $query): array
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return array_values($options);
        }

        return array_values(array_filter(
            $options,
            fn (array $option): bool => self::matches($option, $needle),
        ));
    }

    /**
     * @param  array{value: string, label: string, group?: string|null}  $option
     */
    public static function matches(array $option, string $normalizedQuery): bool
    {
        if ($normalizedQuery === '') {
            return true;
        }

        $value = (string) ($option['value'] ?? '');
        $haystacks = [
            mb_strtolower($value),
            mb_strtolower((string) ($option['label'] ?? '')),
            mb_strtolower((string) ($option['group'] ?? '')),
            mb_strtolower(str_replace(['_', '/'], ' ', $value)),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $normalizedQuery)) {
                return true;
            }
        }

        return false;
    }
}
