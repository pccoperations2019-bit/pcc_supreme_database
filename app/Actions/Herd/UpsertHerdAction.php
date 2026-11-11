<?php

namespace App\Actions\Herd;

use App\Models\Herd;
use Illuminate\Support\Facades\DB;

class UpsertHerdAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $code,
        int $agencyId,
        ?int $centerId,
        string $name,
        ?string $description,
        ?string $street,
        ?string $barangay,
        ?string $city,
        ?string $province,
        ?string $region,
        ?string $district,
        ?int $zipCode,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $markerIcon = null
    ): Herd {

        return DB::transaction(function () use (
            $code,
            $agencyId,
            $centerId,
            $name,
            $description,
            $street,
            $barangay,
            $city,
            $province,
            $region,
            $district,
            $zipCode,
            $latitude,
            $longitude,
            $markerIcon
        ) {
            $herd = Herd::firstOrNew(
                [
                    'code' => $code,
                ],
                [
                    'name' => $name,
                    'description' => $description,
                ]
            );
            $herd->agency()->associate($agencyId);
            if ($centerId !== null && $centerId !== 0) {
                $herd->center()->associate($centerId);
            }
            $herd->save();

            $herd->address()->updateOrCreate(
                [],
                array_filter([
                    'street' => $street,
                    'barangay' => $barangay,
                    'city' => $city,
                    'province' => $province,
                    'region' => $region,
                    'district' => $district,
                    'zip_code' => $zipCode,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'marker_icon' => $markerIcon,
                ])
            );

            return $herd;
        });
    }
}
