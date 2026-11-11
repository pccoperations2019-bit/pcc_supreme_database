<?php

namespace App\Actions\Center;

use App\Enums\ConsolidationCategoryEnum;
use App\Models\ConsolidationUnit;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class UpsertConsolidationUnitAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $name,
        int $centerId,
        bool $withinCenter,
        ConsolidationCategoryEnum $category,
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
    ): ConsolidationUnit {

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
            $category,
            $numberOfStaffFemale,
            $numberOfStaffMale,
            $avgSalary,
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
            $consolidationUnit = ConsolidationUnit::firstOrNew(
                [
                    'name' => $name,
                ],
            );
            $consolidationUnit->fill(
                [
                    'within_center' => $withinCenter,
                    'category' => $category,
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
            $consolidationUnit->center()->associate($centerId);
            $consolidationUnit->save();
            if (! $withinCenter) {
                $consolidationUnit->address()->updateOrCreate(
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

            return $consolidationUnit;
        });
    }
}
