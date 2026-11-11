<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Folds the gender values the imports wrote ("M", "F", "N/A") into the
 * vocabulary the forms and the `in:` rule accept.
 *
 * Without this, every one of those records fails validation on the way back
 * in, so a service record that arrived from a spreadsheet can be read but never
 * saved again. The values are only rewritten when the record is submitted, and
 * "N/A" - which the importer already treats as "not recorded" - becomes null.
 *
 * The consuming request calls `normalizeGender()` from its
 * `prepareForValidation()`.
 *
 * @mixin FormRequest
 */
trait NormalizesGender
{
    /**
     * @var array<string, string>
     */
    private const GENDER_ALIASES = [
        'm' => 'Male',
        'male' => 'Male',
        'f' => 'Female',
        'female' => 'Female',
        'o' => 'Other',
        'other' => 'Other',
    ];

    /**
     * @var array<int, string>
     */
    private const GENDER_BLANKS = ['n/a', 'na', '-', 'none', 'unknown'];

    protected function normalizeGender(): void
    {
        if (! $this->has('gender')) {
            return;
        }

        $gender = $this->input('gender');

        if (! is_string($gender)) {
            return;
        }

        $normalized = trim($gender);

        if ($normalized === '') {
            $this->merge(['gender' => null]);

            return;
        }

        $normalized = self::GENDER_ALIASES[strtolower($normalized)]
            ?? (in_array(strtolower($normalized), self::GENDER_BLANKS, true) ? '' : $normalized);

        $this->merge(['gender' => $normalized === '' ? null : $normalized]);
    }
}
