<?php

namespace App\Casts;

use App\Enums\ProjectProgramEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ProjectProgramEnumCast implements CastsAttributes
{
    /**
     * Always return a list of canonical program values.
     *
     * Stored as a comma-separated string (e.g. "cbed,pl480") so legacy
     * single-value rows keep working. Also accepts JSON arrays.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [];
        }

        if ($value instanceof ProjectProgramEnum) {
            return [$value->value];
        }

        if (is_array($value)) {
            return $this->normalizeList($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $this->normalizeList($decoded);
                }
            }

            return $this->normalizeList(explode(',', $trimmed));
        }

        return [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof ProjectProgramEnum) {
            return $value->value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $list = $this->normalizeList($decoded);

                    return $list === [] ? null : implode(',', $list);
                }
            }
            $list = $this->normalizeList(explode(',', $trimmed));

            return $list === [] ? null : implode(',', $list);
        }

        if (is_array($value)) {
            $list = $this->normalizeList($value);

            return $list === [] ? null : implode(',', $list);
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if ($value instanceof ProjectProgramEnum) {
                $normalized[] = $value->value;

                continue;
            }
            if (is_null($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $canonical = ProjectProgramEnum::tryFrom(strtolower($text))?->value
                ?? ProjectProgramEnum::tryFroMatches($text)?->value
                ?? $text;
            $normalized[] = $canonical;
        }

        return array_values(array_unique($normalized));
    }
}
