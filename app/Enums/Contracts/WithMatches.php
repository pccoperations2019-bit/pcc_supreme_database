<?php

namespace App\Enums\Contracts;

interface WithMatches
{
    /**
     * Get the match variants for all enum cases.
     *
     * @return array<string, array<int, string|int>>
     */
    public static function matches(): array;

    /**
     * Get an enum case from its match variant.
     *
     * @return static|null
     */
    public static function tryFroMatches(?string $label): ?self;
}
