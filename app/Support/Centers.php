<?php

namespace App\Support;

use App\Enums\CenterEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Centers
{
    /**
     * Map a program filter to the national center (GP / NIZ) shown for it.
     *
     * IH   → GP   (NIZ is CBED-only, hidden under IH)
     * CBED → NIZ  (GP is IH-only, hidden under CBED)
     * Anything else (e.g. "all" or unset) → null, meaning the normal rule applies
     * (all active national centers are shown).
     */
    public static function nationalCenterFor(?string $program = null): ?CenterEnum
    {
        return match ($program) {
            ProgramEnum::IH->value => CenterEnum::GP,
            ProgramEnum::CBED->value => CenterEnum::NIZ,
            default => null,
        };
    }

    /**
     * Centers hidden in every context, whatever the program filter.
     *
     * `hiddenCenters()` is program-conditional for the national centers — GP is
     * IH-only and NIZ is CBED-only — so its no-argument form returns [NHQ, GP].
     * That is wrong for pages which are not program-scoped (the center index, the
     * user list), where hiding GP would drop a real center and its account. This
     * returns the always-on part only.
     *
     * @return array<int, string>
     */
    public static function alwaysHiddenCenters(): array
    {
        return [CenterEnum::NHQ->value];
    }

    public static function hiddenCenters(?string $program = null): array
    {
        $hidden = [CenterEnum::NHQ];

        // "all" (null from Filters::resolveDataProgram) should hide GP — NIZ is the CBED national center.
        if ($program === null) {
            $hidden[] = CenterEnum::GP;

            return array_map(fn (CenterEnum $c) => $c->value, $hidden);
        }

        $national = self::nationalCenterFor($program);
        if ($national instanceof CenterEnum) {
            // Hide the national center that is NOT meant for this program.
            $hidden[] = $national === CenterEnum::GP ? CenterEnum::NIZ : CenterEnum::GP;
        }

        return array_map(fn (CenterEnum $c) => $c->value, $hidden);
    }

    public static function isHidden(CenterEnum $center, ?string $program = null): bool
    {
        return in_array($center->value, self::hiddenCenters($program), true);
    }

    public static function activeCenterCodes(): array
    {
        return Center::query()
            ->where('status', ProfileStatusEnum::ACTIVE->value)
            ->pluck('code')
            ->all();
    }

    public static function forActiveProgram(?string $program = null): Collection
    {
        $activeCodes = self::activeCenterCodes();

        return self::forProgram($program)
            ->reject(fn (CenterEnum $c) => ! in_array($c->code(), $activeCodes, true));
    }

    public static function forProgram(?string $program = null): Collection
    {
        $hidden = self::hiddenCenters($program);

        return collect(CenterEnum::cases())
            ->reject(fn (CenterEnum $c) => in_array($c->value, $hidden, true));
    }

    public static function displayLabel(CenterEnum $center): string
    {
        return match ($center) {
            CenterEnum::GP,
            CenterEnum::NIZ => $center->code(),
            default => $center->label(),
        };
    }

    public static function labels(?string $program = null): array
    {
        return self::forProgram($program)
            ->mapWithKeys(fn (CenterEnum $c) => [$c->value => self::displayLabel($c)])
            ->toArray();
    }

    public static function clusterLabels(?string $program = null): array
    {
        $hidden = self::hiddenCenters($program);
        $activeCodes = self::activeCenterCodes();

        $raw = CenterEnum::clusterLabels();

        $filtered = [];
        foreach ($raw as $clusterKey => $centers) {
            $filteredCenters = array_values(array_filter($centers, fn ($c) => ! in_array(Str::lower($c['value']), $hidden, true) && in_array(Str::upper($c['value']), $activeCodes, true)));
            if ($filteredCenters !== []) {
                $filtered[$clusterKey] = array_map(function (array $c): array {
                    $case = CenterEnum::tryFrom(Str::lower($c['value']));

                    if ($case === null) {
                        return $c;
                    }

                    return [
                        'label' => self::displayLabel($case),
                        'value' => $c['value'],
                        'id' => $c['id'],
                    ];
                }, $filteredCenters);
            }
        }

        return $filtered;
    }

    /**
     * Build the clustered-center filter for the currently active program.
     *
     * The effective program is resolved with this precedence:
     *  1. the request's `filter[program]` (the dashboard IH/CBED dropdown)
     *  2. the `_program` session value (dashboard only, handled by the caller)
     *  3. null → the normal rule applies (all active national centers — GP and NIZ — are shown)
     */
    public static function clustersForFilter(?string $filterProgram = null, ?string $sessionProgram = null): array
    {
        return self::clusterLabels($filterProgram ?? $sessionProgram);
    }

    public static function getCenterOptions(?string $program = null): Collection
    {
        $cacheKey = 'center_options_'.($program ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($program) {
            $hidden = self::hiddenCenters($program);
            $centerIds = Center::pluck('id', 'code')->toArray();

            return collect([['id' => 'all', 'label' => 'All Centers']])
                ->merge(
                    collect(CenterEnum::cases())
                        ->reject(fn (CenterEnum $c) => in_array($c->value, $hidden, true))
                        ->map(fn (CenterEnum $case) => [
                            'id' => $centerIds[Str::upper($case->value)] ?? null,
                            'label' => self::displayLabel($case),
                        ])
                        ->filter(fn ($item) => $item['id'] !== null)
                );
        });
    }

    public static function categories(?string $program = null): array
    {
        return self::forProgram($program)
            ->map(fn (CenterEnum $c) => $c->code())
            ->values()
            ->toArray();
    }

    public static function selectableOptions(?string $program = null): array
    {
        $hiddenCodes = self::hiddenCenters($program);

        $options = CenterEnum::selectableOptions();
        $visible = ['all' => $options['all']];

        foreach (CenterEnum::cases() as $case) {
            if (in_array($case->value, $hiddenCodes, true)) {
                continue;
            }

            $visible[$case->value] = self::displayLabel($case);
        }

        return array_values($visible);
    }

    public static function options(?string $program = null): array
    {
        return self::forProgram($program)
            ->map(fn (CenterEnum $c) => [
                'value' => $c->code(),
                'label' => self::displayLabel($c),
            ])
            ->values()
            ->toArray();
    }

    public static function labelsByCode(?string $program = null): array
    {
        return self::forProgram($program)
            ->mapWithKeys(fn (CenterEnum $c) => [$c->code() => self::displayLabel($c)])
            ->toArray();
    }
}
