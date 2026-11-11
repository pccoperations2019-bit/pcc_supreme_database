<?php

namespace App\Actions\Center;

use App\Enums\HerdTypeEnum;
use App\Enums\ProductionCategoryEnum;
use App\Models\ProductionUnit;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class UpsertProductionUnitAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $name,
        int $centerId,
        bool $withinCenter,
        ProductionCategoryEnum $category,
        HerdTypeEnum $herdType,
        ?string $description = null,
        ?DateTimeInterface $dateOfEstablishment = null,
        ?string $contactPerson = null,
        ?string $position = null,
        ?string $contactNumber = null,
        ?string $telephoneNumber = null,
        ?string $emailAddress = null,
        ?string $website = null,
        int $numberOfStaffFemale = 0,
        int $numberOfStaffMale = 0,
        string|float $avgSalary = 0,
        ?string $street = null,
        ?string $barangay = null,
        ?string $city = null,
        ?string $province = null,
        ?string $region = null,
        ?string $district = null,
        ?int $zipCode = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $markerIcon = null
    ): ProductionUnit {

        return DB::transaction(function () use (
            $name,
            $centerId,
            $withinCenter,
            $description,
            $dateOfEstablishment,
            $contactPerson,
            $position,
            $contactNumber,
            $telephoneNumber,
            $emailAddress,
            $website,
            $numberOfStaffFemale,
            $numberOfStaffMale,
            $avgSalary,
            $category,
            $herdType,
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
            /**
             * @todo: migrate the firstOrNew with ->fill() pattern
             */
            $productionUnit = ProductionUnit::firstOrNew(['name' => $name]);
            $productionUnit->fill(
                [
                    'within_center' => $withinCenter,
                    'category' => $category,
                    'herd_type' => $herdType,
                    'description' => $description,
                    'date_of_establishment' => $dateOfEstablishment,
                    'contact_person' => $contactPerson,
                    'position' => $position,
                    'contact_number' => $contactNumber,
                    'telephone_number' => $telephoneNumber,
                    'email_address' => $emailAddress,
                    'website' => $website,
                    'number_of_staff_female' => $numberOfStaffFemale,
                    'number_of_staff_male' => $numberOfStaffMale,
                    'avg_salary_of_staff' => $avgSalary,
                ]
            );
            $productionUnit->center()->associate($centerId);
            $productionUnit->save();
            if (! $withinCenter) {
                $productionUnit->address()->updateOrCreate(
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
            }

            return $productionUnit;
        });
    }
}
