<?php

namespace App\Enums\Casts;

use App\Enums\Concerns\HasBackedEnumString;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a scalar column to a backed enum when possible, otherwise returns the raw string.
 *
 * Usage: BackedEnumStringCast::class.':'.SomeBackedEnum::class
 */
class BackedEnumStringCast implements CastsAttributes
{
    use HasBackedEnumString;

    /** @var class-string<BackedEnum> */
    private string $enumClass;

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public function __construct(string $enumClass)
    {
        if (! enum_exists($enumClass) || ! is_subclass_of($enumClass, BackedEnum::class)) {
            throw new InvalidArgumentException("{$enumClass} must be a backed enum.");
        }

        $this->enumClass = $enumClass;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): BackedEnum|string|null
    {
        $normalized = self::normalizeBackedEnumString($value);
        if ($normalized === null) {
            return null;
        }

        return $this->enumClass::tryFrom($normalized) ?? $normalized;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $normalized = self::normalizeBackedEnumString($value);

        return [$key => $normalized];
    }
}
