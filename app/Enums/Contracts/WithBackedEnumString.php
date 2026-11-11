<?php

namespace App\Enums\Contracts;

interface WithBackedEnumString
{
    /**
     * Normalize any "enum-or-string" value into a scalar backing value string.
     *
     * Examples:
     * - null / '' -> null
     * - SomeEnum::FOO -> 'foo' (backing value as string)
     * - ' foo ' -> 'foo'
     * - 123 -> '123'
     */
    public static function normalizeBackedEnumString(string|int|\BackedEnum|null $value): ?string;
}
