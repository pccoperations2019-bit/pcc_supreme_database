<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Concerns\HasMatches;
use App\Enums\Concerns\HasSelectableOptions;
use App\Enums\Contracts\WithLabels;
use App\Enums\Contracts\WithMatches;
use App\Enums\Contracts\WithSelectableOptions;
use App\Models\MilkFeedingProgram;

enum ModeOfProcurementEnum: string implements WithLabels, WithMatches, WithSelectableOptions
{
    use HasLabels;
    use HasMatches;
    use HasSelectableOptions;

    case BAYANIHAN_ACT = 'bayanihan_act';
    case NP_EMERGENCY_CASES = 'np_emergency_cases';
    case NP_TAKEOVER_OF_CONTRACTS = 'np_takeover_of_contracts';
    case NP_ADJACENT_OR_CONTIGUOUS_PROJECTS = 'np_adjacent_or_contiguous_projects';
    case NP_AGENCY_TO_AGENCY = 'np_agency_to_agency';
    case NP_SCIENTIFIC_SCHOLARLY_OR_ARTISTIC_WORK = 'np_scientific_scholarly_or_artistic_work';
    case NP_HIGHLY_TECHNICAL_CONSULTANTS = 'np_highly_technical_consultants';
    case NP_SMALL_VALUE_PROCUREMENT = 'np_small_value_procurement';
    case NP_LEASE_OF_REAL_PROPERTY = 'np_lease_of_real_property';
    case NP_COMMUNITY_PARTICIPATION = 'np_community_participation';
    case NP_TWO_FAILED_BIDDINGS = 'np_two_failed_biddings';
    case NP_FOREIGN_GRANTS = 'np_foreign_grants';
    case NP_UN_AGENCIES_INTERNATIONAL_ORGANIZATIONS = 'np_un_agencies_international_organizations';
    case NP_PURCHASE_OF_GOODS_FROM_ANOTHER_GOVERNMENT_AGENCY = 'np_purchase_of_goods_from_another_government_agency';
    case PUBLIC_BIDDING = 'public_bidding';
    case AGENCY_TO_AGENCY = 'agency_to_agency';

    public static function labels(): array
    {
        return [
            self::BAYANIHAN_ACT->value => 'Bayanihan Act',
            self::NP_EMERGENCY_CASES->value => 'NP-Emergency Cases',
            self::NP_TAKEOVER_OF_CONTRACTS->value => 'NP – Takeover of Contracts',
            self::NP_ADJACENT_OR_CONTIGUOUS_PROJECTS->value => 'NP – Adjacent or Contiguous Projects',
            self::NP_AGENCY_TO_AGENCY->value => 'NP – Agency-to-Agency (A2A)',
            self::NP_SCIENTIFIC_SCHOLARLY_OR_ARTISTIC_WORK->value => 'NP – Scientific, Scholarly or Artistic Work',
            self::NP_HIGHLY_TECHNICAL_CONSULTANTS->value => 'NP – Highly Technical Consultants',
            self::NP_SMALL_VALUE_PROCUREMENT->value => 'NP – Small Value Procurement (SVP)',
            self::NP_LEASE_OF_REAL_PROPERTY->value => 'NP – Lease of Real Property',
            self::NP_COMMUNITY_PARTICIPATION->value => 'NP-Community Participation',
            self::NP_TWO_FAILED_BIDDINGS->value => 'NP – Two Failed Biddings',
            self::NP_FOREIGN_GRANTS->value => 'NP – Foreign Grants',
            self::NP_UN_AGENCIES_INTERNATIONAL_ORGANIZATIONS->value => 'NP – UN Agencies / International Organizations',
            self::NP_PURCHASE_OF_GOODS_FROM_ANOTHER_GOVERNMENT_AGENCY->value => 'NP – Purchase of Goods from another Government Agency',
            self::PUBLIC_BIDDING->value => 'Public Bidding',
            self::AGENCY_TO_AGENCY->value => 'Agency-to-Agency',
        ];
    }

    public static function matches(): array
    {
        return [
            self::BAYANIHAN_ACT->value => ['Bayanihan Act', 'bayanihan act'],
            self::NP_EMERGENCY_CASES->value => ['NP-Emergency Cases', 'NP – Emergency Cases', 'Emergency Cases', 'emergency cases'],
            self::NP_TAKEOVER_OF_CONTRACTS->value => ['NP – Takeover of Contracts', 'Takeover of Contracts', 'NP Takeover of Contracts', 'takeover of contracts'],
            self::NP_ADJACENT_OR_CONTIGUOUS_PROJECTS->value => ['NP – Adjacent or Contiguous Projects', 'Adjacent or Contiguous Projects', 'NP Adjacent or Contiguous Projects', 'adjacent or contiguous projects'],
            self::NP_AGENCY_TO_AGENCY->value => ['NP – Agency-to-Agency (A2A)', 'Agency-to-Agency', 'A2A', 'NP Agency-to-Agency', 'agency-to-agency'],
            self::NP_SCIENTIFIC_SCHOLARLY_OR_ARTISTIC_WORK->value => ['NP – Scientific, Scholarly or Artistic Work', 'Scientific, Scholarly or Artistic Work', 'NP Scientific, Scholarly or Artistic Work', 'scientific, scholarly or artistic work'],
            self::NP_HIGHLY_TECHNICAL_CONSULTANTS->value => ['NP – Highly Technical Consultants', 'Highly Technical Consultants', 'NP Highly Technical Consultants', 'highly technical consultants'],
            self::NP_SMALL_VALUE_PROCUREMENT->value => ['NP – Small Value Procurement (SVP)', 'Small Value Procurement', 'SVP', 'NP Small Value Procurement', 'small value procurement'],
            self::NP_LEASE_OF_REAL_PROPERTY->value => ['NP – Lease of Real Property', 'Lease of Real Property', 'NP Lease of Real Property', 'lease of real property'],
            self::NP_COMMUNITY_PARTICIPATION->value => ['NP-Community Participation', 'NP – Community Participation', 'Community Participation', 'community participation'],
            self::NP_TWO_FAILED_BIDDINGS->value => ['NP – Two Failed Biddings', 'Two Failed Biddings', 'NP Two Failed Biddings', 'two failed biddings'],
            self::NP_FOREIGN_GRANTS->value => ['NP – Foreign Grants', 'Foreign Grants', 'NP Foreign Grants', 'foreign grants'],
            self::NP_UN_AGENCIES_INTERNATIONAL_ORGANIZATIONS->value => ['NP – UN Agencies / International Organizations', 'UN Agencies / International Organizations', 'NP UN Agencies / International Organizations', 'un agencies / international organizations'],
            self::NP_PURCHASE_OF_GOODS_FROM_ANOTHER_GOVERNMENT_AGENCY->value => ['NP – Purchase of Goods from another Government Agency', 'Purchase of Goods from another Government Agency', 'NP Purchase of Goods from another Government Agency', 'purchase of goods from another government agency'],
            self::PUBLIC_BIDDING->value => ['Public Bidding', 'public bidding'],
            self::AGENCY_TO_AGENCY->value => ['Agency-to-Agency', 'agency to agency', 'agency-to-agency'],
        ];
    }

    /**
     * Build procurement options combining enum labels with custom database values
     */
    public static function buildProcurementOptions(): array
    {
        $procurementOptions = self::labels();

        $customProcurements = MilkFeedingProgram::query()
            ->whereNotNull('mode_of_procurement')
            ->where('mode_of_procurement', '!=', '')
            ->distinct()
            ->orderBy('mode_of_procurement')
            ->pluck('mode_of_procurement');

        foreach ($customProcurements as $customProcurement) {
            $value = $customProcurement instanceof \BackedEnum ? $customProcurement->value : (string) $customProcurement;
            $value = trim($value);
            if ($value === '' || isset($procurementOptions[$value])) {
                continue;
            }
            $procurementOptions[$value] = $value;
        }

        return $procurementOptions;
    }
}
