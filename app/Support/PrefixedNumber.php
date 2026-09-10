<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PrefixedNumber
{
    /**
     * @param  Builder<covariant Model>  $query
     */
    public static function next(Builder $query, string $column, string $prefix, int $pad = 4): string
    {
        $max = 0;

        foreach ($query->pluck($column) as $value) {
            if (! is_string($value) || ! str_starts_with($value, $prefix)) {
                continue;
            }

            $numeric = (int) ltrim(substr($value, strlen($prefix)), '0');
            $max = max($max, $numeric);
        }

        return $prefix.str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
