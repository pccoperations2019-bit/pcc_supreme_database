<?php

namespace App\Exports\Directory\Strategies;

use App\Exports\Directory\Concerns\FormatsDirectoryValues;
use App\Exports\Directory\Concerns\InteractsWithDirectoryFilters;
use App\Exports\Directory\Contracts\DirectoryEntityStrategy;
use App\Exports\Directory\DirectoryFilters;
use App\Exports\Directory\Support\DirectoryAccreditationResolver;
use App\Exports\Directory\Support\DirectoryBdpProductResolver;
use App\Exports\Directory\Support\DirectoryEngagementResolver;
use App\Exports\Directory\Support\DirectoryLocationResolver;
use App\Exports\Directory\Support\DirectoryMetadata;
use App\Exports\Directory\Support\DirectoryProductResolver;
use App\Exports\Directory\Support\DirectoryValueChainResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class PartnersDirectoryStrategy implements DirectoryEntityStrategy
{
    use FormatsDirectoryValues, InteractsWithDirectoryFilters;

    public function entityType(): string
    {
        return 'partner';
    }

    public function query(DirectoryFilters $filters): Builder
    {
        return DB::table('partners')
            ->select([
                'partners.id',
                'partners.code',
                'partners.center_id',
                'partners.name',
                'partners.status',
                'projects.program as program',
                'partners.project_id',
                'partners.category',
                'partners.partnership_type',
                'partners.is_processing_partner',
                'partners.is_marketing_partner',
                'partners.is_dairybox',
                'partners.is_knpo_partner',
                'partners.is_fca_partner',
                'partners.has_cso',
                'partners.has_fda_lto',
                'partners.has_nda_lto',
                'partners.has_philgeps',
                'partners.has_rsbsa',
                'partners.has_ffedis',
                'partners.date_assisted',
                'partners.metadata',
                'centers.code as center_code',
                // Get all project programs from pivot table
                DB::raw("(SELECT GROUP_CONCAT(p.program ORDER BY p.program SEPARATOR ', ') FROM partner_project pp INNER JOIN projects p ON pp.project_id = p.id WHERE pp.partner_id = partners.id) as all_programs"),
            ])
            ->leftJoin('projects', 'partners.project_id', '=', 'projects.id')
            ->leftJoin('centers', 'partners.center_id', '=', 'centers.id')
            ->when($filters->centerId, fn ($query) => $this->applyWhereIn($query, 'partners.center_id', $filters->centerId))
            ->when($filters->partnerId, fn ($query) => $this->applyWhereIn($query, 'partners.id', $filters->partnerId))
            ->when($filters->partnerCategory, fn ($query) => $this->applyPartnerTypeFilter($query, $filters->partnerCategory))
            ->when($filters->status, fn ($query) => $this->applyStatusFilter($query, 'partners', $filters->status))
            ->when($filters->project, fn ($query) => $this->applyProjectFilter($query, 'partners', $filters->project))
            ->when($filters->accreditation, fn ($query) => $this->applyAccreditationFilter($query, $filters->accreditation))
            ->when($filters->hasRestrictiveLocationFilter(), fn ($query) => DirectoryLocationResolver::applyAddressFilter(
                $query,
                'partner',
                'partners.id',
                $filters->region,
                $filters->province,
                $filters->city,
                $filters->district,
            ))
            ->orderBy('centers.code')
            ->orderBy('partners.code');
    }

    public function listHeaders(int $depth, ?string $variant = null): array
    {
        if ($variant === 'status') {
            // CENTER REGION PROVINCE MUNICIPALITY DISTRICT PARTNER NAME ASSISTANCE STATUS
            $locationHeaders = ['CENTER'];
            if ($depth >= 2) {
                $locationHeaders[] = 'REGION';
            }
            if ($depth >= 3) {
                $locationHeaders[] = 'PROVINCE';
            }
            if ($depth >= 4) {
                $locationHeaders[] = 'MUNICIPALITY';
            }
            if ($depth >= 5) {
                $locationHeaders[] = 'District';
            }
            $locationHeaders[] = 'PARTNER NAME';

            return [array_merge($locationHeaders, ['ASSISTANCE STATUS'])];
        }

        $locationHeaders = ['CENTER'];
        if ($depth >= 2) {
            $locationHeaders[] = 'REGION';
        }
        if ($depth >= 3) {
            $locationHeaders[] = 'PROVINCE';
        }
        if ($depth >= 4) {
            $locationHeaders[] = 'MUNICIPALITY';
        }
        if ($depth >= 5) {
            $locationHeaders[] = 'District';
        }
        $locationHeaders[] = 'PARTNER NAME';
        if ($depth >= 7) {
            // BUG FIX: Align headers with corrected metadata semantics
            $locationHeaders[] = 'Chairperson';
            $locationHeaders[] = 'Email Address';
            $locationHeaders[] = 'Manager';
            $locationHeaders[] = 'Email Address';
            $locationHeaders[] = 'Type of Contract';
            $locationHeaders[] = 'MAIN BUSINESS';
            $locationHeaders[] = 'OTHER LINE OF BUSINESS';
        }

        $otherHeaders = [
            'ASSISTANCE STATUS', 'PROGRAM', 'Products', 'Value Chain', 'DATE ASSISTED',
            'Milk Feeding', 'Date Engaged in MFP', 'Dairy Box', 'Date Engaged in Dairy Box',
            'Kadiwa Ni Pangulo sa DB Engagement', 'Date Engaged in KnP', 'PARTNER CATEGORY',
            'CSO Accreditation', 'Date Accredited', 'FDA LTO', 'Date Accredited',
            'NDA LTO', 'Date Accredited', 'mPhilGEPS', 'FFEDIS', 'Date Accredited',
        ];

        if ($variant === 'program') {
            $locationHeaders = ['CENTER'];
            if ($depth >= 2) {
                $locationHeaders[] = 'REGION';
            }
            if ($depth >= 3) {
                $locationHeaders[] = 'PROVINCE';
            }
            if ($depth >= 4) {
                $locationHeaders[] = 'MUNICIPALITY';
            }
            if ($depth >= 5) {
                $locationHeaders[] = 'District';
            }
            $locationHeaders[] = 'PARTNER NAME';

            return [array_merge($locationHeaders, ['PROGRAM'])];
        }

        if ($variant === 'accreditations') {
            $locationHeaders = ['CENTER'];
            if ($depth >= 2) {
                $locationHeaders[] = 'REGION';
            }
            if ($depth >= 3) {
                $locationHeaders[] = 'PROVINCE';
            }
            if ($depth >= 4) {
                $locationHeaders[] = 'MUNICIPALITY';
            }
            if ($depth >= 5) {
                $locationHeaders[] = 'District';
            }
            $locationHeaders[] = 'PARTNER NAME';
            $accreditationHeaders = ['CSO Accreditation', 'Date Accredited', 'FDA LTO', 'Date Accredited', 'NDA LTO', 'Date Accredited', 'mPhilGEPS', 'Date Accredited', 'FFEDIS', 'Date Accredited', 'RSBSA', 'Date Accredited'];

            return [array_merge($locationHeaders, $accreditationHeaders)];
        }

        if ($variant === 'date-assisted') {
            $locationHeaders = ['CENTER'];
            if ($depth >= 2) {
                $locationHeaders[] = 'REGION';
            }
            if ($depth >= 3) {
                $locationHeaders[] = 'PROVINCE';
            }
            if ($depth >= 4) {
                $locationHeaders[] = 'MUNICIPALITY';
            }
            if ($depth >= 5) {
                $locationHeaders[] = 'District';
            }
            $locationHeaders[] = 'PARTNER NAME';

            return [array_merge($locationHeaders, ['DATE ASSISTED'])];
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            $locationHeaders = ['CENTER'];
            if ($depth >= 2) {
                $locationHeaders[] = 'REGION';
            }
            if ($depth >= 3) {
                $locationHeaders[] = 'PROVINCE';
            }
            if ($depth >= 4) {
                $locationHeaders[] = 'MUNICIPALITY';
            }
            if ($depth >= 5) {
                $locationHeaders[] = 'District';
            }
            $locationHeaders[] = 'PARTNER NAME';

            return [array_merge($locationHeaders, ['Products'])];
        }

        return [array_merge($locationHeaders, $otherHeaders)];
    }

    public function mapListRows(Collection $data, int $depth, ?string $variant = null): array
    {
        if ($variant === 'status') {
            $partnerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);

            return $data->map(function ($partner) use ($depth, $addresses) {
                $metadata = DirectoryMetadata::normalize($partner->metadata ?? '{}');

                $row = [$partner->center_code];

                $address = $addresses[$partner->id] ?? null;
                if ($depth >= 2) {
                    $row[] = $this->regionLabel($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $row[] = $this->districtLabel($address->district ?? null);
                }

                $row[] = $partner->name ?? '';
                $row[] = $this->profileStatusLabel(DirectoryMetadata::get($metadata, 'assistance_status', $partner->status));

                return $row;
            })->toArray();
        }

        if ($variant === 'program') {
            $partnerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);

            return $data->map(function ($partner) use ($depth, $addresses) {
                $row = [$partner->center_code];

                $address = $addresses[$partner->id] ?? null;
                if ($depth >= 2) {
                    $row[] = $this->regionLabel($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $this->districtLabel($address->district ?? null);
                }
                if ($depth >= 5) {
                    $row[] = $address->city ?? '';
                }

                $row[] = $partner->name ?? '';

                $programs = $partner->all_programs ?? $partner->program;
                $row[] = $this->projectProgramsLabel((string) ($programs ?? ''));

                return $row;
            })->toArray();
        }

        if ($variant === 'accreditations') {
            $partnerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);
            $partnerIdsArray = $data->pluck('id')->filter()->values()->all();
            $csoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'cso');
            $fdaLtoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'fda-lto');
            $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'nda-lto');
            $philgepsDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'philgeps');
            $ffedisDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'ffedis');
            $rsbsaDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'rsbsa');

            return $data->map(function ($partner) use ($depth, $addresses, $csoDocuments, $fdaLtoDocuments, $ndaLtoDocuments, $philgepsDocuments, $ffedisDocuments, $rsbsaDocuments) {
                $row = [$partner->center_code];

                $address = $addresses[$partner->id] ?? null;
                if ($depth >= 2) {
                    $row[] = $this->regionLabel($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $row[] = $this->districtLabel($address->district ?? null);
                }

                $row[] = $partner->name ?? '';

                $csoDoc = $csoDocuments[(string) $partner->id] ?? null;
                $fdaDoc = $fdaLtoDocuments[(string) $partner->id] ?? null;
                $ndaDoc = $ndaLtoDocuments[(string) $partner->id] ?? null;
                $philgepsDoc = $philgepsDocuments[(string) $partner->id] ?? null;
                $ffedisDoc = $ffedisDocuments[(string) $partner->id] ?? null;
                $rsbsaDoc = $rsbsaDocuments[(string) $partner->id] ?? null;

                $row[] = DirectoryAccreditationResolver::resolveStatus($csoDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($csoDoc?->validity);
                $row[] = DirectoryAccreditationResolver::resolveStatus($fdaDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($fdaDoc?->validity);
                $row[] = DirectoryAccreditationResolver::resolveStatus($ndaDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($ndaDoc?->validity);
                $row[] = DirectoryAccreditationResolver::resolveStatus($philgepsDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($philgepsDoc?->validity);
                $row[] = DirectoryAccreditationResolver::resolveStatus($ffedisDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($ffedisDoc?->validity);
                $row[] = DirectoryAccreditationResolver::resolveStatus($rsbsaDoc?->compliance_status, false);
                $row[] = DirectoryAccreditationResolver::formatValidity($rsbsaDoc?->validity);

                return $row;
            })->toArray();
        }

        if ($variant === 'date-assisted') {
            $partnerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);

            return $data->map(function ($partner) use ($depth, $addresses) {
                $row = [$partner->center_code];

                $address = $addresses[$partner->id] ?? null;
                if ($depth >= 2) {
                    $row[] = $this->regionLabel($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $row[] = $this->districtLabel($address->district ?? null);
                }

                $row[] = $partner->name ?? '';

                // Use date_assisted column for partners, fallback to metadata
                $raw = $partner->date_assisted ?? null;
                if ($raw === null || $raw === '') {
                    $metadata = DirectoryMetadata::normalize($partner->metadata ?? '{}');
                    $raw = DirectoryMetadata::get($metadata, 'date_assisted', '');
                }
                $dateAssisted = '';
                if ($raw !== '' && $raw !== null) {
                    try {
                        $dateAssisted = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : Date::parse((string) $raw)->toDateString();
                    } catch (\Exception $e) {
                        $dateAssisted = (string) $raw;
                    }
                }
                $row[] = $dateAssisted;

                return $row;
            })->toArray();
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            $partnerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);
            $products = DirectoryBdpProductResolver::getForPartners($partnerIds);

            return $data->map(function ($partner) use ($depth, $addresses, $products) {
                $row = [$partner->center_code];

                $address = $addresses[$partner->id] ?? null;
                if ($depth >= 2) {
                    $row[] = $this->regionLabel($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $row[] = $this->districtLabel($address->district ?? null);
                }

                $row[] = $partner->name ?? '';
                $row[] = DirectoryProductResolver::formatList($products[$partner->id] ?? []);

                return $row;
            })->toArray();
        }

        $partnerIds = $data->pluck('id')->unique()->values()->all();
        $products = DirectoryProductResolver::getForPartners($partnerIds);
        $mfpEngagement = DirectoryEngagementResolver::getPartnerMfpEngagement($partnerIds);
        $dairyBoxEngagement = DirectoryEngagementResolver::getPartnerDairyBoxEngagement($partnerIds);
        $knpEngagement = DirectoryEngagementResolver::getPartnerKnpEngagement();
        $valueChains = DirectoryValueChainResolver::getForPartners($partnerIds);
        $addresses = DirectoryLocationResolver::resolveAddresses('partner', $partnerIds);

        // Use partner IDs for document lookup since tags store IDs
        $partnerIdsArray = $data->pluck('id')->filter()->values()->all();
        $fdaLtoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'fda-lto');
        $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'nda-lto');
        $csoDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'cso');
        $philgepsDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'philgeps');
        $ffedisDocuments = DirectoryAccreditationResolver::getDocuments('partner', $partnerIdsArray, 'ffedis');

        return $data->map(function ($partner) use ($depth, $products, $mfpEngagement, $dairyBoxEngagement, $knpEngagement, $valueChains, $addresses, $fdaLtoDocuments, $ndaLtoDocuments, $csoDocuments, $philgepsDocuments, $ffedisDocuments) {
            $metadata = DirectoryMetadata::normalize($partner->metadata ?? '{}');

            $row = [$partner->center_code];
            $address = $addresses[$partner->id] ?? null;
            if ($depth >= 2) {
                $row[] = $this->regionLabel($address->region ?? null);
            }
            if ($depth >= 3) {
                $row[] = $address->province ?? '';
            }
            if ($depth >= 4) {
                $row[] = $address->city ?? '';
            }
            if ($depth >= 5) {
                $row[] = $this->districtLabel($address->district ?? null);
            }
            $row[] = $partner->name ?? '';
            if ($depth >= 7) {
                $row[] = DirectoryMetadata::get($metadata, 'chairperson_contact_no', '');
                $row[] = DirectoryMetadata::get($metadata, 'chairperson_email', '');
                $row[] = DirectoryMetadata::get($metadata, 'manager_contact_no', '');
                $row[] = DirectoryMetadata::get($metadata, 'manager_email', '');
                $row[] = DirectoryMetadata::get($metadata, 'registrationtype', '');
                $row[] = DirectoryMetadata::get($metadata, 'main_business', '');
                $row[] = DirectoryMetadata::get($metadata, 'other_line_of_business', '');
            }

            $row[] = $this->profileStatusLabel(DirectoryMetadata::get($metadata, 'assistance_status', $partner->status));
            // Use all_programs from pivot table, fallback to single program
            $programs = $partner->all_programs ?? $partner->program;
            $row[] = $this->projectProgramsLabel((string) $programs);
            $row[] = DirectoryProductResolver::formatList($products[$partner->id] ?? []);
            $row[] = $valueChains[$partner->id] ?? '';
            $row[] = DirectoryMetadata::get($metadata, 'date_assisted', '');
            $row[] = $mfpEngagement[$partner->id]['engaged'] ?? 'No';
            $row[] = $mfpEngagement[$partner->id]['date'] ?? '';
            $row[] = $dairyBoxEngagement[$partner->id]['engaged'] ?? 'No';
            $row[] = $dairyBoxEngagement[$partner->id]['date'] ?? '';
            $row[] = $knpEngagement[$partner->id]['engaged'] ?? 'No';
            $row[] = $knpEngagement[$partner->id]['date'] ?? '';
            $row[] = DirectoryMetadata::get($metadata, 'partner_category', $partner->category ?? '');

            $csoDoc = $csoDocuments[(string) $partner->id] ?? null;
            $fdaDoc = $fdaLtoDocuments[(string) $partner->id] ?? null;
            $ndaDoc = $ndaLtoDocuments[(string) $partner->id] ?? null;
            $philgepsDoc = $philgepsDocuments[(string) $partner->id] ?? null;
            $ffedisDoc = $ffedisDocuments[(string) $partner->id] ?? null;

            $row[] = DirectoryAccreditationResolver::resolveStatus($csoDoc?->compliance_status, (bool) ($partner->has_cso ?? false));
            $row[] = ''; // CSO validity would need document lookup
            $row[] = DirectoryAccreditationResolver::resolveStatus($fdaDoc?->compliance_status, (bool) ($partner->has_fda_lto ?? false));
            $row[] = DirectoryAccreditationResolver::formatValidity($fdaDoc?->validity);
            $row[] = DirectoryAccreditationResolver::resolveStatus($ndaDoc?->compliance_status, (bool) ($partner->has_nda_lto ?? false));
            $row[] = DirectoryAccreditationResolver::formatValidity($ndaDoc?->validity);
            $row[] = DirectoryAccreditationResolver::resolveStatus($philgepsDoc?->compliance_status, (bool) ($partner->has_philgeps ?? false));
            $row[] = DirectoryAccreditationResolver::resolveStatus($ffedisDoc?->compliance_status, (bool) ($partner->has_ffedis ?? false));
            $row[] = ''; // FFEDIS validity would need document lookup

            return $row;
        })->toArray();
    }

    public function figuresHeaders(int $depth, ?string $variant = null): array
    {
        $locationHeaders = $this->figuresLocationHeaders($depth);

        if ($variant === 'status') {
            return [array_merge($locationHeaders, ['PARTNER NAME', 'Active', 'Inactive', 'TOTAL'])];
        }

        if ($variant === 'accreditations') {
            return [array_merge($locationHeaders, ['PARTNER NAME', 'Accredited', 'On Process', 'For Application', 'For Renewal', 'No Application', 'TOTAL'])];
        }

        if ($variant === 'date-assisted') {
            return [array_merge($locationHeaders, ['PARTNER NAME', 'DATE ASSISTED', 'TOTAL'])];
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return [array_merge($locationHeaders, ['PARTNER NAME', 'Milk', 'Manure', 'Meat', 'TOTAL'])];
        }

        return [array_merge($locationHeaders, ['PARTNER NAME', $this->figuresCountLabel()])];
    }

    private function figuresLocationHeaders(int $depth): array
    {
        $headers = [];
        if ($depth >= 1) {
            $headers[] = 'CENTER';
        }
        if ($depth >= 2) {
            $headers[] = 'REGION';
        }
        if ($depth >= 3) {
            $headers[] = 'PROVINCE';
        }
        if ($depth >= 4) {
            $headers[] = 'MUNICIPALITY';
        }
        if ($depth >= 5) {
            $headers[] = 'District';
        }

        return $headers;
    }

    public function figureDimensions(object $partner, int $depth, ?string $variant = null): array
    {
        // Note: partner object from query doesn't include address fields
        // Address data is resolved separately in mapListRows
        // For figures, we use a placeholder since addresses aren't needed for aggregation
        $dimensions = [$partner->center_code];
        if ($depth >= 2) {
            $dimensions[] = '';
        }
        if ($depth >= 3) {
            $dimensions[] = '';
        }
        if ($depth >= 4) {
            $dimensions[] = '';
        }
        if ($depth >= 5) {
            $dimensions[] = '';
        }

        return $dimensions;
    }

    public function figuresCountLabel(): string
    {
        return 'COUNT of PARTNERS';
    }

    private function applyAccreditationFilter($query, string|array|null $accreditation): void
    {
        $accreditations = (array) $accreditation;

        $query->where(function ($nested) use ($accreditations) {
            foreach ($accreditations as $value) {
                $documentType = $this->documentTypeFromAccreditation((string) $value);
                if ($documentType === null) {
                    continue;
                }
                $nested->orWhereExists(function ($subQuery) use ($documentType) {
                    $subQuery->from('documents')
                        ->join('tags as doc_type_tags', function ($join) use ($documentType) {
                            $join->on('documents.id', '=', 'doc_type_tags.taggable_id')
                                ->where('doc_type_tags.taggable_type', '=', 'document')
                                ->where('doc_type_tags.key', '=', 'document')
                                ->where('doc_type_tags.value', '=', $documentType);
                        })
                        ->join('tags as entity_tags', function ($join) {
                            $join->on('documents.id', '=', 'entity_tags.taggable_id')
                                ->where('entity_tags.taggable_type', '=', 'document')
                                ->where('entity_tags.key', '=', 'partner');
                        })
                        // Tags for partners store partner IDs, not codes. Join partners table to match.
                        ->join('partners as p_doc', function ($join) {
                            $join->on('entity_tags.value', '=', 'p_doc.id');
                        })
                        ->whereColumn('p_doc.code', 'partners.code')
                        ->where('documents.status', 'processed');
                });
            }
        });
    }
}
