<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The gender values a service record may be saved with.
 *
 * The forms offer Male/Female/Other, but the spreadsheet imports write what the
 * sheets hold - "M", "F", "N/A" - and those rows are the ones an officer has to
 * correct later. They are accepted rather than rewritten on the way in: rewriting
 * them would make every edit to an untouched field also report a gender change
 * nobody made, which is the one thing an audit trail must not do.
 *
 * The consuming request uses `self::GENDERS` in its rules.
 *
 * @mixin FormRequest
 */
trait AcceptsImportedGender
{
    /**
     * @var array<int, string>
     */
    public const GENDERS = ['Male', 'Female', 'Other', 'M', 'F', 'N/A'];
}
