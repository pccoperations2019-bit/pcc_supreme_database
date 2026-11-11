<?php

namespace App\Enums\Concerns;

trait HasSelectableOptions
{
    /**
     * Get the selectable options for all enum cases, including an "All" option.
     *
     * @param  string|null  $allLabel  The label for the "all" option. Defaults to "All Options".
     * @return array<string, string>
     */
    public static function selectableOptions(?string $allLabel = null): array
    {
        return ['all' => $allLabel ?? 'All Options'] + static::labels();
    }
}
