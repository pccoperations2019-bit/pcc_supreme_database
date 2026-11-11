<?php

namespace App\Actions\ServiceRecord;

use App\Enums\ServiceProgramEnum;
use App\Models\ServiceRecord;
use Carbon\Carbon;

class UpsertServiceRecordAction
{
    public function execute(
        int $centerId,
        string $clientName,
        ?string $gender = null,
        ?int $projectId = null,
        ?int $noAnimalsServed = null,
        ?ServiceProgramEnum $program = ServiceProgramEnum::DEFAULT,
        ?Carbon $monthSubmitted = null,
        ?Carbon $dateConducted = null,
        ?int $serviceId = null,
        ?int $farmerId = null,
        ?int $sourceRowIndex = null,
        ?int $partnerId = null,
        ?string $unitSection = null,
        ?string $aiTechnicianStaff = null,
        ?string $animalName = null,
        ?string $bcs = null,
        ?string $semenUsed = null,
        ?string $bullIdOwner = null,
        ?Carbon $dateAiNm = null,
        ?bool $pregnant = null,
        ?Carbon $dateOfCalving = null,
        ?string $calvingSex = null,
        ?string $biologicalUsed = null,
        ?string $transferTo = null,
        ?string $contactNumber = null,
        ?string $email = null,
        ?array $servicesMetadata = null,
        ?array $metadata = null,
    ): ServiceRecord {
        return ServiceRecord::updateOrCreate(
            [
                'client_name' => $clientName,
                'center_id' => $centerId,
                'date_conducted' => $dateConducted,
                'service_id' => $serviceId,
                'source_row_index' => $sourceRowIndex,
            ],
            [
                'gender' => $gender,
                'program' => $program,
                'project_id' => $projectId,
                'no_animals_served' => $noAnimalsServed ?? 0,
                'month_submitted' => $monthSubmitted,
                'farmer_id' => $farmerId,
                'partner_id' => $partnerId,
                'unit_section' => $unitSection,
                'ai_technician_staff' => $aiTechnicianStaff,
                'animal_name' => $animalName,
                'bcs' => $bcs,
                'semen_used' => $semenUsed,
                'bull_id_owner' => $bullIdOwner,
                'date_ai_nm' => $dateAiNm,
                'pregnant' => $pregnant,
                'date_of_calving' => $dateOfCalving,
                'calving_sex' => $calvingSex,
                'biological_used' => $biologicalUsed,
                'transfer_to' => $transferTo,
                'contact_number' => $contactNumber,
                'email' => $email,
                'services_metadata' => $servicesMetadata,
                'metadata' => $metadata,
            ]
        );
    }
}
