<?php

namespace App\Enums\Concerns;

trait HasLabels
{
    public static function fromLabel(?string $label): ?self
    {
        if (is_null($label)) {
            return null;
        }

        $labels = array_flip(self::labels());

        return isset($labels[$label]) ? self::from($labels[$label]) : null;
    }

    public function label(): string
    {
        return static::labels()[$this->value] ?? $this->value;
    }
}
