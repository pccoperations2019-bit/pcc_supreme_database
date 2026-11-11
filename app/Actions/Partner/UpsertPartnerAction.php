<?php

namespace App\Actions\Partner;

use App\Enums\PartnerCategoryEnum;
use App\Enums\PartnerPartnershipTypeEnum;
use App\Models\Partner;

class UpsertPartnerAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        ?string $code,
        int $centerId,
        string $name,
        PartnerCategoryEnum|string|null $category,
        PartnerPartnershipTypeEnum|string|null $partnershipType,
        ?string $website,
        ?string $logo,
        ?string $description,
        ?string $contactName,
        ?string $contactPosition,
        ?string $contactEmail,
        ?string $contactPhone,
        ?string $telephoneNumber,
        ?string $status,
        ?int $totalComposition = null,
        ?int $maleCount = null,
        ?int $femaleCount = null,
        ?float $avgMonthlyIncome = null,
        ?int $totalFarmers = null,
        ?bool $isProcessingPartner = null,
        ?bool $isMarketingPartner = null,
        ?bool $isDairybox = null,
        ?bool $isKnpoPartner = null,
        ?bool $isFcaPartner = null,
        ?bool $hasCso = null,
        ?bool $hasFdaLto = null,
        ?bool $hasNdaLto = null,
        ?bool $hasPhilgeps = null,
        ?bool $hasRsbsa = null,
        ?bool $hasFfedis = null,
        array $additionalData = []
    ): Partner {
        $attributes = array_filter([
            'center_id' => $centerId,
            'name' => $name,
            'category' => $category,
            'partnership_type' => $partnershipType,
            'website' => $website,
            'logo' => $logo,
            'description' => $description,
            'contact_name' => $contactName,
            'position' => $contactPosition,
            'contact_email' => $contactEmail,
            'mobile_number' => $contactPhone,
            'telephone_number' => $telephoneNumber,
            'status' => $status,
            'total_composition' => $totalComposition,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'avg_monthly_income' => $avgMonthlyIncome,
            'total_farmers' => $totalFarmers,
            'is_processing_partner' => $isProcessingPartner,
            'is_marketing_partner' => $isMarketingPartner,
            'is_dairybox' => $isDairybox,
            'is_knpo_partner' => $isKnpoPartner,
            'is_fca_partner' => $isFcaPartner,
            'has_cso' => $hasCso,
            'has_fda_lto' => $hasFdaLto,
            'has_nda_lto' => $hasNdaLto,
            'has_philgeps' => $hasPhilgeps,
            'has_rsbsa' => $hasRsbsa,
            'has_ffedis' => $hasFfedis,
        ]) + ['metadata' => $additionalData];

        // Handle partner upsert: use code if available, otherwise fallback to center_id + name to prevent duplicates
        // This conditional statement is for partners without code in farmers excel
        if ($code === null) {
            $existing = Partner::query()->where('center_id', $centerId)
                ->where('name', $name)
                ->first();
            if ($existing) {

                $existing->update($attributes);

                return $existing;
            }

            return Partner::create(array_merge($attributes, ['code' => null]));
        }

        return Partner::updateOrCreate(
            ['code' => $code],
            $attributes
        );
    }
}
