<?php

namespace App\Enums\Contracts;

interface WithLabels
{
    /**
     * Get the labels for all enum cases.
     *
     * @return array<string, string>
     */
    public static function labels(): array;

    /**
     * Get an enum case from its label.
     *
     * @return static|null
     */
    public static function fromLabel(string $label): ?self;
}
