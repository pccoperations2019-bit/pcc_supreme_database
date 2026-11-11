<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class Longitude implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_null($value) ? null : (float) $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return self::normalize($value);
    }

    public static function normalize($value): ?float
    {
        $v = self::coerce($value, leadingDigits: 3); // keep 3 leading digits for lon
        if ($v === null || $v < -180 || $v > 180) {
            return null;
        }

        return $v;
    }

    private static function coerce($value, int $leadingDigits): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $s = trim(str_replace(',', '.', $value));
            if ($s === '' || $s[0] === '=') {
                return null;
            } // unresolved formula
            if (! is_numeric($s)) {
                return null;
            }
            if (str_contains($s, '.')) {
                return (float) $s;
            }

            $sign = $s[0] === '-' ? -1 : 1;
            $digits = ltrim($s, '-');
            if (ctype_digit($digits)) {
                $len = strlen($digits);
                if ($len > $leadingDigits) {
                    $scale = 10 ** ($len - $leadingDigits);

                    return $sign * ((int) $digits / $scale);
                }

                return $sign * (float) $digits;
            }

            return null;
        }

        if (is_numeric($value)) {
            $f = (float) $value;
            $abs = abs($f);
            if ($abs > 180 && $abs < 1e12) {
                $digits = strlen((string) (int) $abs);
                if ($digits > $leadingDigits) {
                    $scale = 10 ** ($digits - $leadingDigits);

                    return ($f < 0 ? -1 : 1) * ((int) $abs / $scale);
                }
            }

            return $f;
        }

        return null;
    }
}
