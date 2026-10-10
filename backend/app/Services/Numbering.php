<?php

namespace App\Services;

/** Human-readable, tenant-unique document numbers (e.g. RSV-202610-00042). */
class Numbering
{
    public static function next(string $prefix, string $modelClass, string $column = 'number'): string
    {
        $stem = $prefix.'-'.now()->format('Ym').'-';
        $n = $modelClass::withTrashed()->where($column, 'like', $stem.'%')->count() + 1;
        do {
            $candidate = $stem.str_pad((string) $n++, 5, '0', STR_PAD_LEFT);
        } while ($modelClass::withTrashed()->where($column, $candidate)->exists());

        return $candidate;
    }
}
