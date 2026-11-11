<?php

namespace App\Enums\Concerns;

trait HasMatches
{
    public static function tryFroMatches(?string $label): ?self
    {
        if (! $label) {
            return null;
        }

        $label = trim(strtolower($label));

        foreach (self::cases() as $case) {
            $matches = self::matches()[$case->value] ?? [];
            foreach ($matches as $variant) {
                if (strtolower((string) $variant) === $label) {
                    return $case;
                }
            }
        }

        return null;
    }
}
