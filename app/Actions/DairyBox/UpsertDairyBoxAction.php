<?php

namespace App\Actions\DairyBox;

use App\Models\DairyBox;

class UpsertDairyBoxAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $dairyBoxId,
        ?string $businessName,
        ?string $manager,
        ?string $dairyBoxFocal,
        ?string $contactNumber,
        ?int $centerId,
        ?int $partnerId,
        ?string $status,
        ?\DateTime $launchedDate,
        ?string $kadiwaIntegration = null,
        ?string $milkFeedingEngagement = null,
        ?string $birRegistered = null,
        ?string $fdaLto = null,
        ?string $csoAccreditation = null,
        ?string $philgeps = null,
        ?string $ffedis = null,
        ?string $fundSourceOutlet = null,
        ?string $fundSourceEquipment = null,
        ?string $fundSourceProcessingPlant = null,
        ?string $landOwnership = null,
        ?string $remarks = null,
        ?\DateTime $startOfConstruction = null,
        ?\DateTime $dateOfCompletion = null,
        ?string $contractor = null,
        ?\DateTime $dateInaugurated = null,
        ?array $metadata = null
    ): DairyBox {

        $dairyBox = DairyBox::updateOrCreate(
            [
                'dairy_box_id' => $dairyBoxId,
            ],
            array_filter([
                'business_name' => $businessName,
                'manager' => $manager,
                'dairy_box_focal' => $dairyBoxFocal,
                'contact_number' => $contactNumber,
                'center_id' => $centerId,
                'partner_id' => $partnerId,
                'status' => $status,
                'launched_date' => $launchedDate?->format('Y-m-d'),
                'kadiwa_integration' => $kadiwaIntegration,
                'milk_feeding_engagement' => $milkFeedingEngagement,
                'bir_registered' => $birRegistered,
                'fda_lto' => $fdaLto,
                'cso_accreditation' => $csoAccreditation,
                'philgeps' => $philgeps,
                'ffedis' => $ffedis,
                'fund_source_outlet' => $fundSourceOutlet,
                'fund_source_equipment' => $fundSourceEquipment,
                'fund_source_processing_plant' => $fundSourceProcessingPlant,
                'land_ownership' => $landOwnership,
                'remarks' => $remarks,
                'start_of_construction' => $startOfConstruction?->format('Y-m-d'),
                'date_of_completion' => $dateOfCompletion?->format('Y-m-d'),
                'contractor' => $contractor,
                'date_inaugurated' => $dateInaugurated?->format('Y-m-d'),
                'metadata' => $metadata,
            ], fn ($value) => ! is_null($value))
        );

        if ($centerId) {
            $dairyBox->center()->associate($centerId);
        }

        if ($partnerId) {
            $dairyBox->partner()->associate($partnerId);
        }

        $dairyBox->save();

        return $dairyBox;
    }
}
