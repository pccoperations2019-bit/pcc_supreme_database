<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Recursive deep-merge for the `metadata` JSON columns written by the
 * CBIN/CCDP registry import.
 *
 * New import data lives ONLY under `metadata.registry_import`; every other
 * key (written by the old import) is preserved untouched. The overlay wins
 * on scalar conflicts and nested assoc arrays merge recursively.
 * Numerically-indexed lists are REPLACED, not appended, so re-imports stay
 * idempotent.
 */
final class RegistryMetadata
{
    /**
     * @param  array<string, mixed>|null  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    public static function deepMerge(?array $base, array $overlay): array
    {
        $merged = $base ?? [];

        foreach ($overlay as $key => $value) {
            if (is_array($value)
                && array_key_exists($key, $merged)
                && is_array($merged[$key])
                && ! array_is_list($value)
                && ! array_is_list($merged[$key])
            ) {
                $merged[$key] = self::deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
