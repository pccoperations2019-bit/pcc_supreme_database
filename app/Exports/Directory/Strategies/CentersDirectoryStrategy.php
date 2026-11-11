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

final class CentersDirectoryStrategy implements DirectoryEntityStrategy
{
    use FormatsDirectoryValues, InteractsWithDirectoryFilters;

    public function entityType(): string
    {
        return 'center';
    }

    public function query(DirectoryFilters $filters): Builder
    {
        return DB::table('centers')
            ->select([
                'centers.id',
                'centers.code',
                'centers.name',
                'centers.status',
                'projects.program as program',
                'centers.project_id',
                'centers.is_processing_partner',
                'centers.is_marketing_partner',
                'centers.is_dairybox',
                'centers.is_knpo_partner',
                'centers.is_fca_partner',
                'centers.has_cso',
                'centers.has_fda_lto',
                'centers.has_nda_lto',
                'centers.has_philgeps',
                'centers.has_rsbsa',
                'centers.has_ffedis',
                'centers.date_assisted',
                'centers.metadata',
                // Get all project programs from pivot table
                DB::raw("(SELECT GROUP_CONCAT(p.program ORDER BY p.program SEPARATOR ', ') FROM center_project cp INNER JOIN projects p ON cp.project_id = p.id WHERE cp.center_id = centers.id) as all_programs"),
            ])
            ->leftJoin('projects', 'centers.project_id', '=', 'projects.id')
            ->when($filters->centerId, fn ($query) => $this->applyWhereIn($query, 'centers.id', $filters->centerId))
            ->when($filters->status, fn ($query) => $this->applyStatusFilter($query, 'centers', $filters->status))
            ->when($filters->project, fn ($query) => $this->applyProjectFilter($query, 'centers', $filters->project))
            ->when($filters->accreditation, fn ($query) => $this->applyAccreditationFilter($query, $filters->accreditation))
            ->when($filters->hasRestrictiveLocationFilter(), fn ($query) => DirectoryLocationResolver::applyAddressFilter(
                $query,
                'center',
                'centers.id',
                $filters->region,
                $filters->province,
                $filters->city,
                $filters->district,
            ))
            ->when(true, fn ($query) => $this->applyHiddenCentersFilter($query))
            ->orderBy('centers.code');
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
            return [array_merge($locationHeaders, ['ASSISTANCE STATUS'])];
        }

        if ($variant === 'program') {
            return [array_merge($locationHeaders, ['PROGRAM'])];
        }

        if ($variant === 'accreditations') {
            $accreditationHeaders = ['CSO Accreditation', 'Date Accredited', 'FDA LTO', 'Date Accredited', 'NDA LTO', 'Date Accredited', 'mPhilGEPS', 'Date Accredited', 'FFEDIS', 'Date Accredited', 'RSBSA', 'Date Accredited'];

            return [array_merge($locationHeaders, $accreditationHeaders)];
        }

        if ($variant === 'date-assisted') {
            return [array_merge($locationHeaders, ['DATE ASSISTED'])];
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return [array_merge($locationHeaders, ['Products'])];
        }

        $otherHeaders = ['STATUS', 'PROGRAM', 'Products', 'Value Chain', 'Milk Feeding', 'Date Engaged in MFP', 'FDA LTO', 'Date Accredited', 'NDA LTO', 'Date Accredited'];

        return [array_merge($locationHeaders, $otherHeaders)];
    }

    public function mapListRows(Collection $data, int $depth, ?string $variant = null): array
    {
        if ($variant === 'status') {
            $centerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);

            return $data->map(function ($center) use ($depth, $addresses) {
                $row = [$center->code];

                $address = $addresses[$center->id] ?? null;
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

                $row[] = $this->profileStatusLabel($center->status);

                return $row;
            })->toArray();
        }

        if ($variant === 'program') {
            $centerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);

            return $data->map(function ($center) use ($depth, $addresses) {
                $row = [$center->code];

                $address = $addresses[$center->id] ?? null;
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

                $programs = $center->all_programs ?? $center->program;
                $row[] = $this->projectProgramsLabel((string) ($programs ?? ''));

                return $row;
            })->toArray();
        }

        if ($variant === 'accreditations') {
            $centerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);
            $centerCodes = $data->pluck('code')->filter()->values()->all();
            $csoDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'cso');
            $fdaLtoDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'fda-lto');
            $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'nda-lto');
            $philgepsDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'philgeps');
            $ffedisDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'ffedis');
            $rsbsaDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'rsbsa');

            return $data->map(function ($center) use ($depth, $addresses, $csoDocuments, $fdaLtoDocuments, $ndaLtoDocuments, $philgepsDocuments, $ffedisDocuments, $rsbsaDocuments) {
                $row = [$center->code];

                $address = $addresses[$center->id] ?? null;
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

                $csoDoc = $csoDocuments[$center->code] ?? null;
                $fdaDoc = $fdaLtoDocuments[$center->code] ?? null;
                $ndaDoc = $ndaLtoDocuments[$center->code] ?? null;
                $philgepsDoc = $philgepsDocuments[$center->code] ?? null;
                $ffedisDoc = $ffedisDocuments[$center->code] ?? null;
                $rsbsaDoc = $rsbsaDocuments[$center->code] ?? null;

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
            $centerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);

            return $data->map(function ($center) use ($depth, $addresses) {
                $row = [$center->code];

                $address = $addresses[$center->id] ?? null;
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

                // Use date_assisted column, fallback to metadata for backward compat
                $raw = $center->date_assisted ?? null;
                if ($raw === null || $raw === '') {
                    $metadata = DirectoryMetadata::normalize($center->metadata ?? '{}');
                    $raw = DirectoryMetadata::get($metadata, 'date_assisted', '');
                }
                $dateAssisted = '';
                if ($raw !== '' && $raw !== null) {
                    try {
                        $dateAssisted = Date::parse((string) $raw)->toDateString();
                    } catch (\Exception $e) {
                        $dateAssisted = (string) $raw;
                    }
                }
                $row[] = $dateAssisted;

                return $row;
            })->toArray();
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            $centerIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);
            $products = DirectoryBdpProductResolver::getForCenters($centerIds);

            return $data->map(function ($center) use ($depth, $addresses, $products) {
                $row = [$center->code];

                $address = $addresses[$center->id] ?? null;
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

                $row[] = DirectoryProductResolver::formatList($products[$center->id] ?? []);

                return $row;
            })->toArray();
        }

        $centerIds = $data->pluck('id')->unique()->values()->all();
        $products = DirectoryProductResolver::getForCenters($centerIds);
        $mfpEngagement = DirectoryEngagementResolver::getCenterMfpEngagement($centerIds);
        $valueChains = DirectoryValueChainResolver::getForCenters($centerIds);
        $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);

        $centerCodes = $data->pluck('code')->filter()->values()->all();
        $fdaLtoDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'fda-lto');
        $ndaLtoDocuments = DirectoryAccreditationResolver::getDocuments('center', $centerCodes, 'nda-lto');

        return $data->map(function ($center) use ($depth, $products, $mfpEngagement, $valueChains, $addresses, $fdaLtoDocuments, $ndaLtoDocuments) {
            $row = [$center->code];

            $address = $addresses[$center->id] ?? null;
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

            $row[] = $this->profileStatusLabel($center->status);
            $programs = $center->all_programs ?? $center->program;
            $row[] = $this->projectProgramsLabel((string) ($programs ?? ''));
            $row[] = DirectoryProductResolver::formatList($products[$center->id] ?? []);
            $row[] = $valueChains[$center->id] ?? '';
            $row[] = $mfpEngagement[$center->id]['engaged'] ?? 'No';
            $row[] = $mfpEngagement[$center->id]['date'] ?? '';

            $fdaDoc = $fdaLtoDocuments[$center->code] ?? null;
            $ndaDoc = $ndaLtoDocuments[$center->code] ?? null;

            $row[] = DirectoryAccreditationResolver::resolveStatus($fdaDoc?->compliance_status, (bool) ($center->has_fda_lto ?? false));
            $row[] = DirectoryAccreditationResolver::formatValidity($fdaDoc?->validity);
            $row[] = DirectoryAccreditationResolver::resolveStatus($ndaDoc?->compliance_status, (bool) ($center->has_nda_lto ?? false));
            $row[] = DirectoryAccreditationResolver::formatValidity($ndaDoc?->validity);

            return $row;
        })->toArray();
    }

    public function figuresHeaders(int $depth, ?string $variant = null): array
    {
        $locationHeaders = [];
        if ($depth >= 1) {
            $locationHeaders[] = 'CENTER';
        }
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
            return [array_merge($locationHeaders, ['Active', 'Inactive', 'TOTAL'])];
        }

        if ($variant === 'accreditations') {
            return [array_merge($locationHeaders, ['Accredited', 'On Process', 'For Application', 'For Renewal', 'No Application', 'TOTAL'])];
        }

        if ($variant === 'date-assisted') {
            return [array_merge($locationHeaders, ['DATE ASSISTED', 'TOTAL'])];
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return [array_merge($locationHeaders, ['Milk', 'Manure', 'Meat', 'TOTAL'])];
        }

        return [array_merge($locationHeaders, ['COUNT of CENTERS'])];
    }

    public function figureDimensions(object $center, int $depth, ?string $variant = null): array
    {
        // Note: center object from query doesn't include address fields
        // Address data is resolved separately in mapListRows
        // For figures, we use a placeholder since addresses aren't needed for aggregation
        $dimensions = [$center->code];
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
        return 'COUNT of CENTERS';
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
                                ->where('entity_tags.key', '=', 'center');
                        })
                        ->whereColumn('entity_tags.value', 'centers.code')
                        ->where('documents.status', 'processed');
                });
            }
        });
    }
}
