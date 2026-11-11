<?php

namespace App\Support;

use App\Enums\ProgramEnum;
use Illuminate\Http\Request;

/**
 * Seeds a blank entry form from a monthly task's own axes.
 *
 * The production and sales create forms each open on an empty object, so a link
 * that says "go record this month" has to carry the month with it - otherwise the
 * center retypes the year, the month, the program and the center it was just
 * looking at, and a mistyped month is a month nobody notices was mistyped.
 *
 * This is that carrying, done server-side. Reading the defaults in the browser
 * would mean every form re-implementing the same "use mine, or else use the
 * blank one" precedence against its own empty-object default, and six forms would
 * drift from each other the first time one of them was touched. Seeding the object
 * the form already reads means the existing Form components need no change at all.
 *
 * Every value is validated here rather than trusted, because it arrives in a
 * query string a person can edit. An unvalidated year or program from a hand-built
 * URL would otherwise be written straight into the form and from there into the
 * record.
 */
class EntryFormDefaults
{
    /**
     * The axes a deep link may pre-fill.
     *
     * Declared rather than read off the model's columns: these are the four things
     * a monthly task knows and an entry form recognises, and a schema lookup per
     * form view would cost a database round trip to answer a question that is the
     * same for all six forms.
     *
     * @var list<string>
     */
    private const AXES = ['center_id', 'program', 'year', 'month'];

    /**
     * The blank record a create form starts from, pre-filled from the request.
     *
     * @return array<string, mixed>
     */
    public static function for(Request $request): array
    {
        $defaults = [
            'center_id' => self::centerId($request),
            'program' => self::program($request),
            'year' => self::year($request),
            'month' => self::month($request),
        ];

        return array_filter(
            array_intersect_key($defaults, array_flip(self::AXES)),
            fn ($value) => $value !== null
        );
    }

    /**
     * The center the form should open on.
     *
     * A center-scoped viewer always gets its own center, whatever the query says.
     * This is the same scope rule the queries apply, and it matters most here: the
     * form is a page that will `store()` whatever it is holding, so honouring a
     * hand-crafted center_id would hand a center-scoped user a form already filled
     * with someone else's center.
     */
    private static function centerId(Request $request): ?int
    {
        $user = $request->user();

        if ($user?->isCenterScoped()) {
            return $user->center_id;
        }

        $requested = $request->integer('center_id');

        return $requested > 0 ? $requested : $user?->center_id;
    }

    private static function program(Request $request): ?string
    {
        $program = $request->string('program')->trim()->value();

        // Validated against the enum rather than passed through: an unrecognised
        // program would render a form whose select shows nothing selected, and the
        // center would have no way to tell that from "not set yet".
        return ProgramEnum::tryFrom($program)?->value;
    }

    private static function year(Request $request): ?int
    {
        $year = $request->integer('year');

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }

    private static function month(Request $request): ?int
    {
        $month = $request->integer('month');

        return $month >= 1 && $month <= 12 ? $month : null;
    }
}
