<?php

namespace App\Exports\BuffaloDerivedProducts;

use App\Enums\AddressRegionEnum;

final readonly class BuffaloDerivedProductsFilters
{
    public function __construct(
        public string $template = 'bdp-by-year',
        public ?string $templateVariant = null,
        public string $product = 'milk',
        public string $type = 'production',
        public int|array|null $year = null,
        public int|array|null $centerId = null,
        public int|array|null $month = null,
        public string|array|null $region = null,
        public string|array|null $province = null,
        public string|array|null $district = null,
        public string|array|null $city = null,
        public ?string $program = null,
        public string|array|null $partner = null,
        public string|array|null $farmer = null,
    ) {}

    public static function fromArguments(
        string $template = 'bdp-by-year',
        ?string $templateVariant = null,
        string $product = 'milk',
        string $type = 'production',
        int|array|null $year = null,
        int|array|null $centerId = null,
        int|array|null $month = null,
        string|array|null $region = null,
        string|array|null $province = null,
        string|array|null $district = null,
        string|array|null $city = null,
        ?string $program = null,
        string|array|null $partner = null,
        string|array|null $farmer = null,
    ): self {
        return new self(
            template: $template,
            templateVariant: $templateVariant,
            product: $product,
            type: $type,
            year: $year,
            centerId: self::normalizeIds($centerId),
            month: $month,
            region: self::normalizeLocation($region),
            province: self::normalizeLocation($province),
            district: self::normalizeLocation($district),
            city: self::normalizeLocation($city),
            program: $program !== '' ? $program : null,
            partner: self::normalizePartner($partner),
            farmer: self::normalizePartner($farmer),
        );
    }

    public function hasLocationFilter(): bool
    {
        return $this->region !== null || $this->province !== null || $this->district !== null || $this->city !== null;
    }

    public function locationDepth(): int
    {
        $depth = 0;
        if ($this->region !== null) {
            $depth = max($depth, 2);
        }
        if ($this->province !== null) {
            $depth = max($depth, 3);
        }
        if ($this->city !== null) {
            $depth = max($depth, 4);
        }
        if ($this->district !== null) {
            $depth = max($depth, 5);
        }

        return $depth;
    }

    public function locationLabels(array $addressData): array
    {
        $region = $this->resolveRegion($addressData);
        $province = $this->resolveProvince($addressData);
        $municipality = $this->resolveMunicipality($addressData);
        $district = $this->resolveDistrict($addressData);

        return array_filter([
            $region !== '' ? strtoupper($region) : null,
            $province !== '' ? strtoupper($province) : null,
            $municipality !== '' ? strtoupper($municipality) : null,
            $district !== '' ? strtoupper($district) : null,
        ]);
    }

    private function resolveRegion(array $addressData): string
    {
        if ($addressData === []) {
            return '';
        }

        $raw = $addressData['region'] ?? '';

        return AddressRegionEnum::labels()[$raw] ?? $raw;
    }

    private function resolveProvince(array $addressData): string
    {
        return $addressData['province'] ?? '';
    }

    private function resolveMunicipality(array $addressData): string
    {
        return $addressData['municipality'] ?? '';
    }

    private function resolveDistrict(array $addressData): string
    {
        return $addressData['district'] ?? '';
    }

    private static function normalizeIds(int|array|null $value): int|array|null
    {
        if (in_array($value, [null, 'all', ''], true)) {
            return null;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_filter(array_map(
            static fn ($item) => is_numeric($item) ? (int) $item : trim((string) $item),
            $values,
        ), static fn ($item) => $item !== null));

        return count($values) === 1 ? $values[0] : $values;
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
                static fn ($item) => trim((string) $item) !== '' ? trim((string) $item) : null,
                $value,
            )));

            return $values === [] ? null : $values;
        }

        return trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * Normalize the partner filter. "all" is preserved as a sentinel so it can
     * enable the PARTNER NAME breakdown without filtering, mirroring how the
     * location (region/province/...) filters toggle column visibility.
     */
    private static function normalizePartner(mixed $value): string|array|null
    {
        if (in_array($value, [null, '', '0'], true)) {
            return null;
        }

        if ($value === 'all' || (is_array($value) && in_array('all', $value, true))) {
            return 'all';
        }

        return self::normalizeList($value);
    }
}
