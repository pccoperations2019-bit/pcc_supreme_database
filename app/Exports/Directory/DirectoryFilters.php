<?php

namespace App\Exports\Directory;

/**
 * Immutable, normalized filters shared by all directory report strategies.
 */
final readonly class DirectoryFilters
{
    public function __construct(
        public string $template = 'centers',
        public ?string $templateVariant = null,
        public ?string $listAndFigures = null,
        public int|string|array|null $centerId = null,
        public int|string|array|null $partnerId = null,
        public int|string|array|null $farmerId = null,
        public string|array|null $region = null,
        public string|array|null $province = null,
        public string|array|null $district = null,
        public string|array|null $city = null,
        public string|array|null $partnerCategory = null,
        public string|array|null $status = null,
        public string|array|null $project = null,
        public string|array|null $accreditation = null,
    ) {}

    public static function fromArguments(
        string $template = 'centers',
        ?string $templateVariant = null,
        ?string $listAndFigures = null,
        int|string|array|null $centerId = null,
        int|string|array|null $partnerId = null,
        int|string|array|null $farmerId = null,
        string|array|null $region = null,
        string|array|null $province = null,
        string|array|null $district = null,
        string|array|null $city = null,
        string|array|null $partnerCategory = null,
        string|array|null $status = null,
        string|array|null $project = null,
        string|array|null $accreditation = null,
        string|array|null $division = null,
        string|array|null $businessType = null,
    ): self {
        $resolvedPartnerCategory = $partnerCategory ?? $businessType;

        return new self(
            template: $template,
            templateVariant: self::normalizeVariant($templateVariant),
            listAndFigures: $listAndFigures,
            centerId: self::normalizeIds($centerId),
            partnerId: self::normalizeIds($partnerId),
            farmerId: self::normalizeIds($farmerId),
            region: self::normalizeLocation($region),
            province: self::normalizeLocation($province),
            district: self::normalizeLocation($district ?? $division),
            city: self::normalizeLocation($city),
            partnerCategory: self::normalizeFilter($resolvedPartnerCategory),
            status: self::normalizeFilter($status),
            project: self::normalizeFilter($project),
            accreditation: self::normalizeFilter($accreditation),
        );
    }

    /**
     * Returns true only when at least one location filter specifies an actual
     * restriction (i.e. a non-null, non-"all" value). An "all" filter expands
     * the depth shown in the output but does NOT restrict which rows are
     * returned, so a whereExists subquery must not be applied in that case.
     */
    public function hasRestrictiveLocationFilter(): bool
    {
        foreach ([$this->region, $this->province, $this->city, $this->district] as $value) {
            if ($this->hasSpecificLocationFilter($value)) {
                return true;
            }
        }

        return false;
    }

    public function locationDepth(): int
    {
        $locationFilters = [
            ['value' => $this->region, 'depth' => 2],
            ['value' => $this->province, 'depth' => 3],
            ['value' => $this->city, 'depth' => 4],
            ['value' => $this->district, 'depth' => 5],
        ];

        $depth = 1;
        $hasAnyFilter = false;

        foreach ($locationFilters as $locationFilter) {
            $value = $locationFilter['value'];

            // If this level has a specific value or "all", set the depth
            if ($this->hasSpecificLocationFilter($value) || $this->isAllLocationFilter($value)) {
                $depth = $locationFilter['depth'];
                $hasAnyFilter = true;
            }
        }

        return $depth;
    }

    private static function normalizeIds(int|string|array|null $value): int|array|null
    {
        if (in_array($value, [null, 'all', ''], true)) {
            return null;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_filter(array_map(
            static fn ($item) => self::normalizeScalar($item),
            $values,
        ), static fn ($item) => $item !== null));

        return count($values) === 1 ? $values[0] : $values;
    }

    private static function normalizeFilter(mixed $value): string|array|null
    {
        if (in_array($value, [null, '', '0', 'all'], true)) {
            return null;
        }

        return self::normalizeList($value);
    }

    private static function normalizeLocation(mixed $value): string|array|null
    {
        if (in_array($value, [null, '', '0'], true)) {
            return null;
        }

        if ($value === 'all' || (is_array($value) && in_array('all', $value, true))) {
            return 'all';
        }

        return self::normalizeList($value);
    }

    private static function normalizeList(mixed $value): string|array|null
    {
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        if (is_array($value)) {
            $values = array_values(array_filter(array_map(
                static fn ($item) => self::normalizeScalar($item),
                $value,
            ), static fn ($item) => $item !== null));

            return $values === [] ? null : $values;
        }

        return self::normalizeScalar($value);
    }

    private static function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;
        if (in_array($value, ['', '0', 'all'], true)) {
            return null;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    private function hasSpecificLocationFilter(string|array|null $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item) => ! $this->isAllLocationFilter($item));
        }

        return $value !== null && $value !== '' && $value !== '0' && $value !== 'all';
    }

    private function isAllLocationFilter(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 1 && $value[0] === 'all';
        }

        return $value === 'all';
    }

    private static function normalizeVariant(?string $variant): string
    {
        if ($variant === null || trim($variant) === '') {
            return 'all-data';
        }

        $normalized = strtolower(trim($variant));

        // Canonicalize variant names: "All Data", "all_data", "all-data" => "all-data"
        $normalized = str_replace([' ', '_'], '-', $normalized);

        return in_array($normalized, ['all-data', 'all'], true) ? 'all-data' : $normalized;
    }
}
