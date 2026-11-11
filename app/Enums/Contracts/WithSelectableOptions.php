<?php

namespace App\Enums\Contracts;

interface WithSelectableOptions
{
    /**
     * Get the selectable options for all enum cases, including an "All" option.
     *
     * @return array<string, string>
     */
    public static function selectableOptions(): array;
}
