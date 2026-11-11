<?php

namespace App\Actions\Address;

use App\Enums\AddressCategoryEnum;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Models\Address;

class UpsertAddressAction
{
    public function execute(
        string $addressableType,
        int $addressableId,
        ?string $street,
        ?string $barangay,
        ?string $city,
        ?string $province,
        AddressRegionEnum|string|null $region,
        AddressDistrictEnum|string|null $district,
        ?int $zipCode,
        ?float $latitude,
        ?float $longitude,
        ?string $markerIcon,
        AddressCategoryEnum|string|null $category,
        ?int $centerId = null,
        ?string $country = null,
    ): Address {
        return Address::updateOrCreate(
            [
                'addressable_type' => $addressableType,
                'addressable_id' => $addressableId,
                'street' => $street,
                'barangay' => $barangay,
                'city' => $city,
                'province' => $province,
            ],
            array_filter([
                'region' => $region,
                'district' => $district,
                'zip_code' => $zipCode,
                'country' => $country,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'marker_icon' => $markerIcon,
                'category' => $category,
                'center_id' => $centerId,
            ])
        );
    }
}
