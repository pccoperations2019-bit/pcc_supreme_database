<?php

namespace App\Actions\Center;

use App\Models\Center;

class UpsertCenterAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $code,
        int $agencyId,
        string $name,
        ?string $description,
        ?string $website,
        ?string $logo,
        ?string $contactPerson,
        ?string $contactNumber,
        ?string $emailAddress,
        array $additionalData = []
    ): Center {

        $center = Center::updateOrCreate(
            [
                'agency_id' => $agencyId,
                'code' => $code,
            ],
            array_filter([
                'name' => $name,
                'description' => $description,
                'website' => $website,
                'logo' => $logo,
                'contact_person' => $contactPerson,
                'contact_number' => $contactNumber,
                'email_address' => $emailAddress,
                'metadata' => $additionalData,
            ], fn ($value) => ! is_null($value))
        );

        $center->agency()->associate($agencyId);
        $center->save();

        return $center;
    }
}
