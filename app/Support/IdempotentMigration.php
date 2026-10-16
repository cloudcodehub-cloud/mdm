<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narrow helpers so October migrations can safely resume against a partially
 * applied SQLite demo database without dropping existing objects or data.
 */
final class IdempotentMigration
{
    public static function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    /**
     * @param  callable(Blueprint): void  $callback
     */
    public static function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
}
