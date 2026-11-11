<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Pulls address-shaped fields out of a flat validated payload and persists them
 * on any model exposing an address() morphOne/hasOne relation. Used for the
 * ProjectSite itself and for nested relations (infrastructure, marketing
 * infrastructure) that each carry their own address.
 */
class ProjectSiteAddressService
{
    public const FIELDS = [
        'region',
        'province',
        'municipality',
        'barangay',
        'district',
        'latitude',
        'longitude',
    ];

    /** Pull the address-shaped keys out of a payload. */
    public function extract(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    /** The same payload with address-shaped keys removed. */
    public function withoutAddress(array $data): array
    {
        return array_diff_key($data, array_flip(self::FIELDS));
    }

    private function payload(array $addressFields): array
    {
        return [
            ...$addressFields,
            'city' => $addressFields['municipality'] ?? null,
        ];
    }

    /** Always creates a new address record (used on initial store()). */
    public function create(Model $model, array $addressFields): void
    {
        $model->address()->create($this->payload($addressFields));
    }

    /**
     * Update the existing address if one is loaded, otherwise create one —
     * but only when there's actually something to save.
     */
    public function sync(Model $model, array $addressFields): void
    {
        if (array_filter($addressFields) === []) {
            return;
        }

        if ($model->address) {
            $model->address->update($this->payload($addressFields));
        } else {
            $this->create($model, $addressFields);
        }
    }
}
