<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum DocumentEnum: string implements WithLabels
{
    use HasLabels;
    /**
     * Business and Compliance Documents
     * Includes all certifications and registrations required for partner validation.
     */
    case BUSINESS_DOCUMENTS = 'business_documents';
    case BIR = 'bir';
    case FDA_CERTIFICATIONS = 'fda_certifications';
    case CSO = 'cso';
    case ISO = 'iso';
    case NDA = 'nda';
    case DOLE = 'dole';
    case CDA = 'cda';
    case SEC = 'sec';
    case FDA_LTO = 'fda-lto';
    case NDA_LTO = 'nda-lto';
    case RSBSA = 'rsbsa';
    case FFEDIS = 'ffedis';
    case PHILGEPS = 'philgeps';
    case PROCESSING = 'processing';
    case MARKET = 'market';
    case KNP = 'knp';
    case PRODUCT_REGISTRATION = 'product_registration';

    public static function labels(): array
    {
        return [
            self::BUSINESS_DOCUMENTS->value => 'Business Documents',
            self::BIR->value => 'BIR',
            self::FDA_CERTIFICATIONS->value => 'FDA Certifications',
            self::CSO->value => 'CSO',
            self::ISO->value => 'ISO',
            self::NDA->value => 'NDA',
            self::DOLE->value => 'DOLE',
            self::CDA->value => 'CDA',
            self::SEC->value => 'SEC',
            self::FDA_LTO->value => 'FDA-LTO',
            self::NDA_LTO->value => 'NDA-LTO',
            self::RSBSA->value => 'RSBSA',
            self::FFEDIS->value => 'FFEDIS',
            self::PHILGEPS->value => 'PhilGEPS',
            self::PROCESSING->value => 'Processing',
            self::MARKET->value => 'Market',
            self::KNP->value => 'KNP',
            self::PRODUCT_REGISTRATION->value => 'Product Registration',
        ];
    }

    public static function getPropertyMapping(): array
    {
        return [
            'Business Documents' => 'business_documents_compliance',
            'FDA Certifications' => 'fda_certifications_compliance',
            'FFEDIS Registration' => 'ffedis_registration_compliance',
            'CSO' => 'cso_compliance',
            'ISO' => 'iso_compliance',
            'NDA' => 'nda_compliance',
            'DOLE' => 'dole_compliance',
            'CDA' => 'cda_compliance',
            'SEC' => 'sec_compliance',
            'FDA-LTO' => 'fda-lto_compliance',
            'NDA-LTO' => 'nda-lto_compliance',
            'RSBSA' => 'rsbsa_compliance',
            'FFEDIS' => 'ffedis_compliance',
            'PhilGEPS' => 'philgeps_compliance',
            'Processing' => 'processing_compliance',
            'Market' => 'market_compliance',
            'KNP' => 'knp_compliance',
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::BUSINESS_DOCUMENTS->value => 'General business registration and legal compliance documents such as permits, business licenses, and cooperative registration certificates.',

            self::FDA_CERTIFICATIONS->value => 'Official certifications issued by the Food and Drug Administration (FDA) confirming that the organization or product meets health and safety standards.',

            self::CSO->value => 'Certificate of Social Organization (CSO) accreditation or recognition for cooperatives, associations, or NGOs working with PCC.',

            self::ISO->value => 'International Organization for Standardization (ISO) certification demonstrating compliance with international management and quality standards.',

            self::NDA->value => 'Accreditation or certification documents issued by the National Dairy Authority (NDA) related to dairy production, processing, or partnership.',

            self::DOLE->value => 'Compliance certificates or permits issued by the Department of Labor and Employment (DOLE), such as labor standards compliance or occupational safety accreditation.',

            self::FDA_LTO->value => 'License to Operate (LTO) issued by the Food and Drug Administration (FDA) for facilities, products, or services that require regulatory compliance.',

            self::NDA_LTO->value => 'License to Operate (LTO) issued by the National Dairy Authority (NDA) for dairy processing facilities, dairy farms, and related dairy businesses.',

            self::RSBSA->value => 'Registry System for Basic Sectors in Agriculture (RSBSA) registration and certification for farmers, fishers, and agricultural workers.',

            self::FFEDIS->value => 'Farmers and Fisherfolk Enterprise Development Information System (FFEDIS) certification for agricultural enterprise registration and development programs.',

            self::PHILGEPS->value => 'Philippine Government Electronic Procurement System (PhilGEPS) registration and certification documents for suppliers and partners.',

            self::PROCESSING->value => 'Documents related to processing operations and procedures, including production workflows, quality control, and processing facility management.',

            self::MARKET->value => 'Documents related to market operations and sales, including marketing strategies, sales reports, and market analysis.',

            self::KNP->value => 'Documents related to KNP (Kalinga ng Programa) initiatives, including program implementation reports, beneficiary documentation, and program compliance.',

            self::PRODUCT_REGISTRATION->value => 'Documents used for product registration and related regulatory filing.',
        ];
    }
}
