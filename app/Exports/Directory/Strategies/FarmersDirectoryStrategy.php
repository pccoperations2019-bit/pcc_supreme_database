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
use App\Exports\Directory\Support\DirectoryProductResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class FarmersDirectoryStrategy implements DirectoryEntityStrategy
{
    use FormatsDirectoryValues, InteractsWithDirectoryFilters;

    public function entityType(): string
    {
        return 'farmer';
    }

    public function query(DirectoryFilters $filters): Builder
    {
        return DB::table('farmers')
            ->select([
                'farmers.id',
                'farmers.code',
                'farmers.center_id',
                'farmers.partner_id',
                'farmers.first_name',
                'farmers.last_name',
                'farmers.middle_name',
                'farmers.status',
                'farmers.project_category as profile_project_category',
                DB::raw("(SELECT GROUP_CONCAT(p.program ORDER BY p.program SEPARATOR ', ') FROM farmer_project fp INNER JOIN projects p ON fp.project_id = p.id WHERE fp.farmer_id = farmers.id) as all_programs"),
                'farmers.is_assisted',
                'farmers.date_assisted',
                'farmers.has_cso',
                'farmers.has_fda_lto',
                'farmers.has_nda_lto',
                'farmers.has_philgeps',
                'farmers.has_ffedis',
                'farmers.has_rsbsa',
                'farmers.metadata',
                'centers.code as center_code',
                'partners.name as partner_name',
                'partners.code as partner_code',
            ])
            ->leftJoin('centers', 'farmers.center_id', '=', 'centers.id')
            ->leftJoin('projects', 'centers.project_id', '=', 'projects.id')
            ->leftJoin('partners', 'farmers.partner_id', '=', 'partners.id')
            ->when($filters->centerId, fn ($query) => $this->applyWhereIn($query, 'farmers.center_id', $filters->centerId))
            ->when($filters->partnerId, fn ($query) => $this->applyWhereIn($query, 'farmers.partner_id', $filters->partnerId))
            ->when($filters->farmerId, fn ($query) => $this->applyWhereIn($query, 'farmers.id', $filters->farmerId))
            ->when($filters->status, fn ($query) => $this->applyStatusFilter($query, 'farmers', $filters->status))
            ->when($filters->project, fn ($query) => $this->applyProjectFilter($query, 'farmers', $filters->project))
            ->when($filters->accreditation, fn ($query) => $this->applyAccreditationFilter($query, $filters->accreditation))
            ->when($filters->hasRestrictiveLocationFilter(), fn ($query) => DirectoryLocationResolver::applyAddressFilter(
                $query,
                'farmer',
                'farmers.id',
                $filters->region,
                $filters->province,
                $filters->city,
                $filters->district,
            ))
            ->orderBy('centers.code')
            ->orderBy('partners.code')
            ->orderBy('farmers.code');
    }

    public function listHeaders(int $depth, ?string $variant = null): array
    {
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
        if ($variant === 'status') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'ASSISTANCE STATUS'])];
        }
        if ($variant === 'accreditations') {
            $accreditationHeaders = ['CSO Accreditation', 'Date Accredited', 'FDA LTO', 'Date Accredited', 'NDA LTO', 'Date Accredited', 'mPhilGEPS', 'Date Accredited', 'FFEDIS', 'Date Accredited', 'RSBSA', 'Date Accredited'];

            return [array_merge($locationHeaders, ['FARMER NAME'], $accreditationHeaders)];
        }
        if ($variant === 'date-assisted') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'DATE ASSISTED'])];
        }
        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return [array_merge($locationHeaders, ['FARMER NAME', 'Products'])];
        }
        if ($depth >= 6) {
            $locationHeaders[] = 'PARTNER NAME';
        }
        if ($depth >= 7) {
            // BUG FIX: Use middle_name instead of middle_initial
            $locationHeaders[] = 'LAST NAME';
            $locationHeaders[] = 'FIRST NAME';
            $locationHeaders[] = 'MIDDLE INITIAL';
        }

        if ($variant === 'program') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'PROGRAM'])];
        }

        $otherHeaders = [
            'ASSISTANCE STATUS', 'PROGRAM', 'Products', 'Value Chain', 'DATE ASSISTED',
            'Milk Feeding', 'Date Engaged in MFP', 'NDA LTO', 'Date Accredited',
            'FFEDIS', 'Date Accredited', 'RSBSA', 'Date Accredited',
        ];

        return [array_merge($locationHeaders, $otherHeaders)];
    }

    public function mapListRows(Collection $data, int $depth, ?string $variant = null): array
    {
        if ($variant === 'status') {
            $farmerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);

            return $data->map(function ($farmer) use ($depth, $addresses) {
                $row = [$farmer->center_code];

                $address = $addresses[$farmer->id] ?? null;
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

                $row[] = trim(implode(' ', array_filter([
                    $farmer->first_name ?? null,
                    $farmer->middle_name ?? null,
                    $farmer->last_name ?? null,
                ])));
                $row[] = $this->profileStatusLabel($farmer->status);

                return $row;
            })->toArray();
        }

        if ($variant === 'program') {
            $farmerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);

            return $data->map(function ($farmer) use ($depth, $addresses) {
                $row = [$farmer->center_code];

                $address = $addresses[$farmer->id] ?? null;
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
                if ($depth >= 6) {
                    $row[] = $farmer->partner_name;
                }

                $row[] = trim(implode(' ', array_filter([
                    $farmer->first_name ?? null,
                    $farmer->middle_name ?? null,
                    $farmer->last_name ?? null,
                ])));
                $row[] = $this->projectProgramLabel($this->farmerPrograms($farmer));

                return $row;
            })->toArray();
        }

        if ($variant === 'accreditations') {
            $farmerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);
            $farmerCodes = $data->pluck('code')->filter()->values()->all();
            $farmerIdsArray = $data->pluck('id')->filter()->values()->all();
            // Use IDs for farmers tags (partner/farmer store IDs)
            $csoDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'cso');
            $fdaLtoDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'fda-lto');
            $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'nda-lto');
            $philgepsDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'philgeps');
            $ffedisDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'ffedis');
            $rsbsaDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerIdsArray, 'rsbsa');

            return $data->map(function ($farmer) use ($depth, $addresses, $csoDocuments, $fdaLtoDocuments, $ndaLtoDocuments, $philgepsDocuments, $ffedisDocuments, $rsbsaDocuments) {
                $row = [$farmer->center_code];

                $address = $addresses[$farmer->id] ?? null;
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

                $row[] = trim(implode(' ', array_filter([
                    $farmer->first_name ?? null,
                    $farmer->middle_name ?? null,
                    $farmer->last_name ?? null,
                ])));

                $csoDoc = $csoDocuments[(string) $farmer->id] ?? null;
                $fdaDoc = $fdaLtoDocuments[(string) $farmer->id] ?? null;
                $ndaDoc = $ndaLtoDocuments[(string) $farmer->id] ?? null;
                $philgepsDoc = $philgepsDocuments[(string) $farmer->id] ?? null;
                $ffedisDoc = $ffedisDocuments[(string) $farmer->id] ?? null;
                $rsbsaDoc = $rsbsaDocuments[(string) $farmer->id] ?? null;

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
            $farmerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);

            return $data->map(function ($farmer) use ($depth, $addresses) {
                $row = [$farmer->center_code];

                $address = $addresses[$farmer->id] ?? null;
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

                $row[] = trim(implode(' ', array_filter([
                    $farmer->first_name ?? null,
                    $farmer->middle_name ?? null,
                    $farmer->last_name ?? null,
                ])));

                // Use date_assisted column for farmer, fallback to is_assisted for backward compat
                $raw = $farmer->date_assisted ?? null;
                if ($raw !== null && $raw !== '') {
                    try {
                        $dateStr = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : Date::parse((string) $raw)->toDateString();
                    } catch (\Exception $e) {
                        $dateStr = (string) $raw;
                    }
                } else {
                    // Fallback to is_assisted boolean if no date
                    $isAssisted = (bool) ($farmer->is_assisted ?? false);
                    $dateStr = $isAssisted ? 'Yes' : 'No';
                }
                $row[] = $dateStr;

                return $row;
            })->toArray();
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            $farmerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);
            $products = DirectoryBdpProductResolver::getForFarmers($farmerIds);

            return $data->map(function ($farmer) use ($depth, $addresses, $products) {
                $row = [$farmer->center_code];

                $address = $addresses[$farmer->id] ?? null;
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

                $row[] = trim(implode(' ', array_filter([
                    $farmer->first_name ?? null,
                    $farmer->middle_name ?? null,
                    $farmer->last_name ?? null,
                ])));
                $row[] = DirectoryProductResolver::formatList($products[$farmer->id] ?? []);

                return $row;
            })->toArray();
        }

        $farmerIds = $data->pluck('id')->unique()->values()->all();
        $products = DirectoryProductResolver::getForFarmers($farmerIds);
        $mfpEngagement = DirectoryEngagementResolver::getFarmerMfpEngagement($farmerIds);
        $addresses = DirectoryLocationResolver::resolveAddresses('farmer', $farmerIds);

        $farmerCodes = $data->pluck('code')->filter()->values()->all();
        $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerCodes, 'nda-lto');
        $ffedisDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerCodes, 'ffedis');
        $rsbsaDocuments = DirectoryAccreditationResolver::getDocuments('farmer', $farmerCodes, 'rsbsa');

        return $data->map(function ($farmer) use ($depth, $products, $mfpEngagement, $addresses, $ndaLtoDocuments, $ffedisDocuments, $rsbsaDocuments) {
            $row = [$farmer->center_code];

            $address = $addresses[$farmer->id] ?? null;
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
            if ($depth >= 6) {
                $row[] = $farmer->partner_name;
            }
            if ($depth >= 7) {
                // BUG FIX: Use middle_name instead of non-existent middle_initial
                $row[] = $farmer->last_name ?? '';
                $row[] = $farmer->first_name ?? '';
                $row[] = $farmer->middle_name ?? '';
            }

            $row[] = $farmer->is_assisted ? 'Active' : 'Inactive';
            $row[] = $this->projectProgramLabel($this->farmerPrograms($farmer));
            $row[] = DirectoryProductResolver::formatList($products[$farmer->id] ?? []);
            $row[] = 'Production (Farm Inputs, Milk, Meat, Manure, Hide, Forage, Silage)';
            $row[] = '';
            $row[] = $mfpEngagement[$farmer->id]['engaged'] ?? 'No';
            $row[] = $mfpEngagement[$farmer->id]['date'] ?? '';

            $ndaDoc = $ndaLtoDocuments[$farmer->code] ?? null;
            $ffedisDoc = $ffedisDocuments[$farmer->code] ?? null;
            $rsbsaDoc = $rsbsaDocuments[$farmer->code] ?? null;

            $row[] = DirectoryAccreditationResolver::resolveStatus($ndaDoc?->compliance_status, false);
            $row[] = DirectoryAccreditationResolver::formatValidity($ndaDoc?->validity);
            $row[] = $ffedisDoc && $ffedisDoc->compliance_status === 'compliant' ? 'Registered' : '';
            $row[] = DirectoryAccreditationResolver::formatValidity($ffedisDoc?->validity);
            $row[] = $rsbsaDoc && $rsbsaDoc->compliance_status === 'compliant' ? 'Registered' : '';
            $row[] = DirectoryAccreditationResolver::formatValidity($rsbsaDoc?->validity);

            return $row;
        })->toArray();
    }

    public function figuresHeaders(int $depth, ?string $variant = null): array
    {
        $locationHeaders = $this->figuresLocationHeaders($depth);

        if ($variant === 'status') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'Active', 'Inactive', 'TOTAL'])];
        }

        if ($variant === 'accreditations') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'Accredited', 'On Process', 'For Application', 'For Renewal', 'No Application', 'TOTAL'])];
        }

        if ($variant === 'date-assisted') {
            return [array_merge($locationHeaders, ['FARMER NAME', 'DATE ASSISTED', 'TOTAL'])];
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return [array_merge($locationHeaders, ['FARMER NAME', 'Milk', 'Manure', 'Meat', 'TOTAL'])];
        }

        return [array_merge($locationHeaders, ['FARMER NAME', $this->figuresCountLabel()])];
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

    public function figureDimensions(object $farmer, int $depth, ?string $variant = null): array
    {
        // Note: farmer object from query doesn't include address fields
        // Address data is resolved separately in mapListRows
        // For figures, we use a placeholder since addresses aren't needed for aggregation
        $dimensions = [$farmer->center_code];
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
        return 'COUNT of FARMERS';
    }

    /**
     * Program sources mirror the partners/centers exports: pivot programs
     * first, falling back to the farmer profile project category.
     */
    private function farmerPrograms(object $farmer): mixed
    {
        return $farmer->all_programs
            ?? $farmer->profile_project_category
            ?? $farmer->project_category
            ?? null;
    }

    private function applyAccreditationFilter($query, string|array|null $accreditation): void
    {
        $query->where(function ($nested) use ($accreditation) {
            foreach ((array) $accreditation as $value) {
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
                                ->where('entity_tags.key', '=', 'farmer');
                        })
                        ->join('farmers as f_doc', function ($join) {
                            $join->on('entity_tags.value', '=', 'f_doc.id');
                        })
                        ->whereColumn('f_doc.code', 'farmers.code')
                        ->where('documents.status', 'processed');
                });
            }
        });
    }
}
