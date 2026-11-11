<?php

namespace App\Exports\Directory\Support;

/**
 * Safe metadata normalization that handles arrays, objects, and null values.
 */
final class DirectoryMetadata
{
    /**
     * Normalize metadata to an array, handling already-decoded arrays and JSON strings.
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            return (array) $metadata;
        }

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Get a metadata value with a fallback.
     */
    public static function get(array $metadata, string $key, mixed $default = null): mixed
    {
        return $metadata[$key] ?? $default;
    }
}
