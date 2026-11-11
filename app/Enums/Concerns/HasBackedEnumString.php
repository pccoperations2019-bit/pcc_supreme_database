<?php

namespace App\Enums\Concerns;

trait HasBackedEnumString
{
    public static function normalizeBackedEnumString(string|int|\BackedEnum|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
