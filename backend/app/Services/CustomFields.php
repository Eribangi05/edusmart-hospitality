<?php

namespace App\Services;

use App\Models\CustomField;
use Illuminate\Validation\ValidationException;

/** Validates the `meta` payload against the tenant's custom field definitions for an entity. */
class CustomFields
{
    public static function validate(string $entity, ?array $meta, bool $isCreate): ?array
    {
        $defs = CustomField::where('entity', $entity)->where('is_active', true)->get();
        if ($defs->isEmpty()) {
            return $meta;
        }
        $errors = [];
        $meta ??= [];
        foreach ($defs as $f) {
            $v = $meta[$f->key] ?? null;
            if (($v === null || $v === '') && $f->required && $isCreate) {
                $errors["meta.{$f->key}"] = ['This field is required.'];

                continue;
            }
            if ($v === null) {
                continue;
            }
            $ok = match ($f->type) {
                'number' => is_numeric($v),
                'boolean' => is_bool($v),
                'date' => is_string($v) && strtotime($v) !== false,
                'select' => in_array($v, $f->options ?? [], true),
                'multiselect' => is_array($v) && ! array_diff($v, $f->options ?? []),
                default => is_string($v) && mb_strlen($v) <= 5000,
            };
            if (! $ok) {
                $errors["meta.{$f->key}"] = ['Invalid value.'];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $meta;
    }
}
