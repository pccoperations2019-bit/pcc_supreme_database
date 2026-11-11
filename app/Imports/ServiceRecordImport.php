<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Farmer\UpsertFarmerAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Actions\Project\UpsertProjectAction;
use App\Actions\Service\UpsertServiceAction;
use App\Actions\ServiceRecord\UpsertServiceRecordAction;
use App\Enums\AddressCategoryEnum;
use App\Enums\DocumentStatusEnum;
use App\Enums\ProjectProgramEnum;
use App\Enums\ServiceProgramEnum;
use App\Enums\ValueChainSegmentEnum;
use App\Imports\Concerns\WithDocumentStatus;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceRecord;
use App\Support\RegionResolver;
use App\Support\Sanitizer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ServiceRecordImport implements OnEachRow, ShouldQueue, SkipsEmptyRows, WithBatchInserts, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow
{
    use Queueable, WithDocumentStatus;

    protected array $centerCache = [];

    protected array $serviceCache = [];

    protected array $partnerCache = [];

    protected array $projectCache = [];

    protected array $farmerCache = [];

    private int $cacheLimit = 500;

    private ?string $loadedCenterCode = null;

    private array $centerCodeCache = [];

    public function __construct(
        private readonly UpsertServiceRecordAction $upsertServiceRecordAction,
        private readonly UpsertServiceAction $upsertServiceAction,
        private readonly UpsertAddressAction $upsertAddressAction,
        private readonly UpsertPartnerAction $upsertPartnerAction,
        private readonly UpsertProjectAction $upsertProjectAction,
        private readonly UpsertFarmerAction $upsertFarmerAction,
        private readonly ?int $defaultCenterId = null,
    ) {}

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row): void
    {
        try {
            $data = $row->toArray(calculateFormulas: true);

            $this->clearCachesIfNeeded();

            $clientName = $this->getStringValue($data, [
                'name_of_client', 'name of client', 'client_name',
                'client name', 'client', 'farmer', 'Farmer',
            ]);

            if (in_array($clientName, [null, '', '0'], true)) {
                return;
            }

            $gender = $this->getStringValue($data, ['sex', 'gender']);

            $centerName = $this->getStringValue($data, ['center', 'center_name', 'center name']);
            $centerId = $this->resolveCenterId($centerName) ?? $this->defaultCenterId;

            if ($centerId === null) {
                return;
            }

            $serviceName = $this->getStringValue($data, ['services', 'service']);
            $categoryName = $serviceName ?? $this->getStringValue($data, [
                'categorygroup', 'category_group', 'category/group',
                'category', 'category group', 'CATEGORY/GROUP',
            ]);
            $typeOfServiceName = $this->getStringValue($data, [
                'type_of_services', 'type of services', 'type_of_service',
                'type of service', 'type_of_sevices', 'type of sevices',
            ]);
            $simplifiedCode = $this->getStringValue($data, [
                'mfo', 'mfo_code', 'mfo code', 'mfocode', 'simplified_code',
                'simplified code',
            ]);

            $serviceId = $this->resolveOrCreateServiceId(
                $serviceName,
                $categoryName,
                $typeOfServiceName,
                $simplifiedCode,
            );

            $monthSubmitted = $this->parseDate(
                $this->getValue($data, ['month_submitted', 'month submitted', 'month'])
            );
            $dateConducted = $this->parseDate(
                $this->getValue($data, ['date_conducted', 'date conducted', 'date'])
            );

            $barangay = $this->getStringValue($data, ['barangay']);
            $municipality = $this->getStringValue($data, ['municipality', 'munucipality']);
            $province = $this->getStringValue($data, ['province']);
            $region = RegionResolver::resolveRegionValue(
                $this->getStringValue($data, ['region', 'region x', 'region_x']),
            );
            $country = $this->getStringValue($data, ['country', 'country name', 'country_name']);
            $program = $this->resolveProgram($clientName);

            $projectName = $this->getStringValue($data, ['project', 'project name', 'project_name']);
            $projectId = $this->resolveProjectId($projectName, $centerId);

            $partnerName = $this->getStringValue($data, [
                'coop/ asso/school/university',
                'coop/asso/school/university',
                'coop_assoc_school_university',
                'coop/asso/school/university',
                'coop / asso/school/university',
                'coop',
                'asso',
                'school/university',
                'partner',
                'partner_name',
                'partner name',
            ]);
            $partnerId = $this->resolvePartnerId($partnerName, $centerId);

            [$contactNumber, $email] = $this->parseContactNumberEmail(
                $this->getStringValue($data, [
                    'contact number( tel./cp#)/ e-mail address',
                    'contact number(tel./cp#)/ e-mail address',
                    'contact_number_email',
                    'contact number/email',
                    'contact number',
                    'e-mail address',
                    'email',
                    'tel./cp#',
                ])
            );

            $farmerId = $this->shouldRegisterFarmer($simplifiedCode)
                ? $this->resolveFarmerId($clientName, $centerId, $gender, $contactNumber, $partnerId)
                : null;

            [$dateOfCalving, $calvingSex] = $this->parseCalvingDateSex(
                $this->getValue($data, [
                    'date of calving and sex',
                    'date_calving_sex',
                    'date of calving',
                    'calving sex',
                ])
            );

            $remarks = $this->getStringValue($data, ['remarks', 'remark']);
            $remarksMonitoring = $this->getStringValue($data, [
                'remarks as of monitoring date',
                'remarks_as_of_monitoring_date',
                'remarks as of date of monitoring',
            ]);

            $servicesMetadata = [];
            if ($serviceName !== null) {
                $servicesMetadata['services'] = [$serviceName];
            }
            if ($typeOfServiceName !== null) {
                $servicesMetadata['type_of_services'] = [$typeOfServiceName];
            }

            $metadata = [];
            if ($remarks !== null) {
                $metadata['remarks'] = $remarks;
            }
            if ($remarksMonitoring !== null) {
                $metadata['remarks_as_of_monitoring_date'] = $remarksMonitoring;
            }

            $serviceRecord = $this->upsertServiceRecordAction->execute(
                centerId: $centerId,
                clientName: $clientName,
                gender: $gender,
                projectId: $projectId,
                noAnimalsServed: $this->parseInt($this->getValue($data, [
                    'no_animals_served',
                    'no. animals served',
                    'no animals served',
                    'no. animals served/iec meterials',
                    'no. animals served / iec meterials',
                    'no. animals served/iec materials',
                    'animals_served',
                    'animals served',
                    'number of animals served (head)/no. of clients/no. of materials distributed (pcs.)',
                    'number of animals served (head)',
                    'no. of clients',
                    'no. of materials distributed (pcs.)',
                ])),
                program: $program,
                monthSubmitted: $monthSubmitted,
                dateConducted: $dateConducted,
                serviceId: $serviceId,
                farmerId: $farmerId,
                sourceRowIndex: $row->getIndex(),
                partnerId: $partnerId,
                unitSection: $this->getStringValue($data, ['unit / section', 'unit/section', 'unit_section', 'unit section']),
                aiTechnicianStaff: $this->getStringValue($data, [
                    'ai technician / staff involved',
                    'ai technician/staff involved',
                    'ai_technician_staff',
                    'ai technician staff involved',
                    'ai technician',
                    'staff involved',
                ]),
                animalName: $this->getStringValue($data, ['name of animal', 'name_of_animal', 'animal_name', 'animal name']),
                bcs: $this->getStringValue($data, ['bcs']),
                semenUsed: $this->getStringValue($data, [
                    'semen used if ai’ed',
                    "semen used if ai'ed",
                    'semen used',
                    'semen_used',
                ]),
                bullIdOwner: $this->getStringValue($data, [
                    'bull id or owner of bull for nm',
                    'bull_id_owner',
                    'bull id/owner of bull for nm',
                    'bull id',
                    'owner of bull',
                ]),
                dateAiNm: $this->parseDate($this->getValue($data, [
                    'date of ai/nm',
                    'date_ai_nm',
                    'date of ai',
                    'date of nm',
                ])),
                pregnant: $this->parseBoolean($this->getStringValue($data, ['pregnant'])),
                dateOfCalving: $dateOfCalving,
                calvingSex: $calvingSex,
                biologicalUsed: $this->getStringValue($data, [
                    'biological used',
                    'biological_used',
                    'biological',
                ]),
                transferTo: $this->getStringValue($data, ['transfer to', 'transfer_to']),
                contactNumber: $contactNumber,
                email: $email,
                servicesMetadata: $servicesMetadata !== [] ? $servicesMetadata : null,
                metadata: $metadata !== [] ? $metadata : null,
            );

            if ($barangay !== null || $municipality !== null || $province !== null) {
                $this->upsertAddressAction->execute(
                    addressableType: Str::of(class_basename(ServiceRecord::class))->lower(),
                    addressableId: $serviceRecord->id,
                    street: null,
                    barangay: $barangay,
                    city: $municipality,
                    province: $province,
                    region: $region,
                    district: null,
                    zipCode: null,
                    latitude: null,
                    longitude: null,
                    markerIcon: null,
                    category: AddressCategoryEnum::DEFAULT->value,
                    centerId: $centerId,
                    country: $country,
                );
            }

        } catch (\Throwable $e) {
            // Skip bad rows — do not let one bad row kill the entire chunk job.
        }
    }

    public function registerEvents(): array
    {
        return [

            BeforeImport::class => function (BeforeImport $event) {
                $this->setQueueMetadata($this->documentId, 'import');
            },

            AfterImport::class => function (AfterImport $event) {
                $this->markAsProcessed($this->documentId);
            },

            ImportFailed::class => function (ImportFailed $event) {
                $exception = $event->getException();
                $document = Document::query()->find($this->documentId);

                if ($document) {
                    $metadata = $document->metadata ?? [];
                    $queuedImport = $metadata['queue_import'] ?? [];

                    $document->update([
                        'status' => DocumentStatusEnum::FAILED,
                        'metadata' => array_merge($metadata, [
                            'queue_import' => array_merge($queuedImport, [
                                'failed_at' => now()->toIso8601String(),
                                'error' => $exception->getMessage(),
                            ]),
                        ]),
                    ]);
                }
            },
        ];
    }

    private function getValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        $normalizedData = [];
        $sluggedData = [];
        foreach ($data as $dataKey => $value) {
            $normalizedKey = strtolower(trim((string) $dataKey));
            $normalizedKey = preg_replace('/\s+/', ' ', $normalizedKey);
            $normalizedData[$normalizedKey] = $value;
            $sluggedData[Str::slug((string) $dataKey, '_')] = $value;
        }

        foreach ($keys as $key) {
            $normalizedKey = strtolower(trim($key));
            $normalizedKey = preg_replace('/\s+/', ' ', $normalizedKey);
            if (array_key_exists($normalizedKey, $normalizedData)) {
                return $normalizedData[$normalizedKey];
            }

            $sluggedKey = Str::slug($key, '_');
            if (array_key_exists($sluggedKey, $sluggedData)) {
                return $sluggedData[$sluggedKey];
            }
        }

        return null;
    }

    private function getStringValue(array $data, array $keys): ?string
    {
        $value = $this->getValue($data, $keys);
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function clearCachesIfNeeded(): void
    {
        // Service cache is bounded by unique simplified_code values (typically <200).
        // Never evict it — re-querying services is unnecessary overhead.
        // Other caches (center, partner, project, farmer) are bounded by DB data
        // and should persist for the entire import.
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $date = Date::excelToDateTimeObject((float) $value);

                return \Illuminate\Support\Facades\Date::instance($date);
            } catch (\Exception $e) {
                return null;
            }
        }

        $strValue = trim((string) $value);
        if ($strValue === '') {
            return null;
        }

        // Handle date ranges like "Aug 25-Sept. 24, 2025" — extract start date only.
        if (str_contains($strValue, '-')) {
            $parts = explode('-', $strValue);
            if (count($parts) >= 2) {
                $startDate = trim($parts[0]);
                if (count($parts) >= 3 && preg_match('/\d{4}/', $parts[count($parts) - 1])) {
                    $year = trim(preg_replace('/[^\d]/', '', $parts[count($parts) - 1]));
                    $strValue = $startDate.', '.$year;
                } else {
                    $strValue = $startDate;
                }
            }
        }

        try {
            return \Illuminate\Support\Facades\Date::parse($strValue);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = Sanitizer::parseNumeric($value);

        return $cleaned !== 0.0 ? (int) $cleaned : null;
    }

    private function parseBoolean(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['yes', 'y', 'pregnant', 'positive', 'true', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['no', 'n', 'negative', 'false', '0'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Split a "Date of Calving and Sex" value into a calving date and the sex of the calf.
     *
     * @return array{0: ?Carbon, 1: ?string}
     */
    private function parseCalvingDateSex(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        $strValue = trim((string) $value);
        if ($strValue === '') {
            return [null, null];
        }

        $sex = null;
        foreach (['male', 'female', 'bull', 'heifer'] as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $strValue)) {
                $sex = ucfirst(strtolower($keyword));
                $strValue = preg_replace('/\b'.preg_quote($keyword, '/').'\b/i', '', $strValue);
                break;
            }
        }

        $strValue = trim($strValue, " \t\n\r\0\x0B-,/");

        return [$this->parseDate($strValue !== '' ? $strValue : null), $sex];
    }

    /**
     * Split a "Contact Number( Tel./CP#)/ e-mail address" value into contact number and email.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parseContactNumberEmail(?string $value): array
    {
        if ($value === null) {
            return [null, null];
        }

        $email = null;
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $value, $matches)) {
            $email = $matches[0];
            $value = trim(str_replace($email, '', $value));
        }

        $value = trim($value, " \t\n\r\0\x0B-,/");
        $contactNumber = $value !== '' ? $value : null;

        return [$contactNumber, $email];
    }

    private function resolveCenterId(?string $centerName): ?int
    {
        if ($centerName === null) {
            return null;
        }

        $normalizedName = strtolower(trim($centerName));

        if (isset($this->centerCache[$normalizedName])) {
            return $this->centerCache[$normalizedName];
        }

        $center = Center::query()->whereRaw('LOWER(name) = ?', [$normalizedName])->first();
        if ($center) {
            $this->centerCache[$normalizedName] = $center->id;

            return $center->id;
        }

        $center = Center::query()->where('code', $normalizedName)->first();
        if ($center) {
            $this->centerCache[$normalizedName] = $center->id;

            return $center->id;
        }

        return null;
    }

    private function resolvePartnerId(?string $partnerName, int $centerId): ?int
    {
        if ($partnerName === null || in_array(strtolower($partnerName), ['', 'n/a', 'na', '-'], true)) {
            return null;
        }

        $cacheKey = $centerId.':'.strtolower(trim($partnerName));

        if (isset($this->partnerCache[$cacheKey])) {
            return $this->partnerCache[$cacheKey] ?: null;
        }

        $partner = Partner::query()->where('center_id', $centerId)
            ->whereRaw('LOWER(name) = ?', [strtolower($partnerName)])
            ->first()
            ?? Partner::query()->where('center_id', $centerId)
                ->where('name', 'LIKE', "%{$partnerName}%")
                ->first();

        if ($partner) {
            $this->partnerCache[$cacheKey] = $partner->id;

            return $partner->id;
        }

        $partner = $this->upsertPartnerAction->execute(
            code: null,
            centerId: $centerId,
            name: $partnerName,
            category: null,
            partnershipType: null,
            website: null,
            logo: null,
            description: null,
            contactName: null,
            contactPosition: null,
            contactEmail: null,
            contactPhone: null,
            telephoneNumber: null,
            status: null,
        );

        $this->partnerCache[$cacheKey] = $partner->id;

        return $partner->id;
    }

    private function resolveFarmerId(
        ?string $clientName,
        int $centerId,
        ?string $gender,
        ?string $contactNumber,
        ?int $partnerId,
    ): ?int {
        if ($clientName === null || in_array(strtolower($clientName), ['', 'n/a', 'na', '-'], true)) {
            return null;
        }

        $cacheKey = $centerId.':'.strtolower(trim($clientName));

        if (isset($this->farmerCache[$cacheKey])) {
            return $this->farmerCache[$cacheKey] ?: null;
        }

        [$firstName, $middleName, $lastName] = $this->parseClientName($clientName);

        if ($firstName === null && $lastName === null) {
            $this->farmerCache[$cacheKey] = null;

            return null;
        }

        $firstName = $firstName ?? '';
        $lastName = $lastName ?? '';
        $middleName = $middleName ?? '';

        // Targeted SQL matching — avoid loading all farmers for the center.
        // Tries the common "First Last" ordering first, then "Last, First".
        $normalizedFirstLast = $this->normalizeName(trim("{$firstName} {$lastName}"));
        $normalizedLastFirst = $this->normalizeName(trim("{$lastName} {$firstName}"));

        $farmer = Farmer::query()
            ->where('center_id', $centerId)
            ->where(function ($q) use ($normalizedFirstLast, $normalizedLastFirst) {
                $q->whereRaw(
                    'UPPER(REPLACE(CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')), \' \', \'\')) = ?',
                    [$normalizedFirstLast]
                )->orWhereRaw(
                    'UPPER(REPLACE(CONCAT(COALESCE(last_name, \'\'), \' \', COALESCE(first_name, \'\')), \' \', \'\')) = ?',
                    [$normalizedLastFirst]
                );
            })
            ->select(['id', 'center_id', 'first_name', 'middle_name', 'last_name'])
            ->first();

        // Fallback: full PHP-side matching (retains original business logic for
        // middle-name flexibility, e.g. "Sales, Freddie" matching "Freddie E. Sales").
        if (! $farmer && $middleName !== '') {
            $farmer = Farmer::query()
                ->where('center_id', $centerId)
                ->select(['id', 'center_id', 'first_name', 'middle_name', 'last_name'])
                ->get()
                ->first(function (Farmer $farmer) use ($firstName, $middleName, $lastName): bool {
                    return $this->personMatches(
                        $firstName,
                        $middleName,
                        $lastName,
                        $farmer->first_name,
                        $farmer->middle_name,
                        $farmer->last_name,
                    );
                });
        }

        if (! $farmer) {
            if (! isset($this->centerCodeCache[$centerId])) {
                $this->centerCodeCache[$centerId] = Center::query()->where('id', $centerId)->value('code');
            }
            $centerCode = $this->centerCodeCache[$centerId];

            $farmer = $this->upsertFarmerAction->execute(
                centerId: $centerId,
                farmerCode: Farmer::generateCodeForCenter((string) $centerCode),
                partnerId: $partnerId,
                dateOfInterview: null,
                dateOfBirth: null,
                photo: null,
                firstName: $firstName,
                lastName: $lastName,
                middleName: $middleName,
                contactNumber: $contactNumber,
                gender: in_array(strtolower((string) $gender), ['n/a', 'na', '-'], true) ? null : $gender,
            );
        }

        $this->farmerCache[$cacheKey] = $farmer->id;

        return $farmer->id;
    }

    /**
     * Split a client name into first, middle, and last name parts.
     *
     * Supports both "Last, First Middle" and "First Middle Last" ordering.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function parseClientName(string $clientName): array
    {
        $trimmed = trim($clientName);
        if ($trimmed === '') {
            return [null, null, null];
        }

        if (str_contains($trimmed, ',')) {
            [$lastPart, $firstPart] = array_pad(array_map('trim', explode(',', $trimmed, 2)), 2, null);
            $lastName = $lastPart !== '' ? $lastPart : null;
            $firstTokens = array_values(array_filter(preg_split('/\s+/', (string) $firstPart) ?: []));
            $firstName = $firstTokens[0] ?? null;
            $middleName = count($firstTokens) > 1 ? implode(' ', array_slice($firstTokens, 1)) : null;

            return [$firstName, $middleName, $lastName];
        }

        $tokens = array_values(array_filter(preg_split('/\s+/', $trimmed) ?: []));
        if ($tokens === []) {
            return [null, null, null];
        }

        $firstName = $tokens[0];
        $lastName = count($tokens) > 1 ? $tokens[count($tokens) - 1] : null;
        $middleName = count($tokens) > 2 ? implode(' ', array_slice($tokens, 1, -1)) : null;

        return [$firstName, $middleName, $lastName];
    }

    /**
     * Determine whether a client name matches an existing farmer record.
     *
     * Matches when the full normalized name (first/middle/last in either order)
     * is identical, or when the first+last core matches AND the middle names are
     * compatible (at least one missing, or equal). This lets a row like
     * "Sales, Freddie" match an existing "Freddie E. Sales".
     */
    private function personMatches(
        ?string $clientFirst,
        ?string $clientMiddle,
        ?string $clientLast,
        ?string $farmerFirst,
        ?string $farmerMiddle,
        ?string $farmerLast,
    ): bool {
        $clientFirst = $clientFirst ?? '';
        $clientMiddle = $clientMiddle ?? '';
        $clientLast = $clientLast ?? '';
        $farmerFirst = $farmerFirst ?? '';
        $farmerMiddle = $farmerMiddle ?? '';
        $farmerLast = $farmerLast ?? '';

        $clientFull = $this->normalizeName(trim("{$clientFirst} {$clientMiddle} {$clientLast}"));
        $clientReverse = $this->normalizeName(trim("{$clientLast} {$clientFirst} {$clientMiddle}"));
        $farmerFull = $this->normalizeName(trim("{$farmerFirst} {$farmerMiddle} {$farmerLast}"));
        $farmerReverse = $this->normalizeName(trim("{$farmerLast} {$farmerFirst} {$farmerMiddle}"));

        if (in_array($clientFull, [$farmerFull, $farmerReverse], true)
            || in_array($clientReverse, [$farmerFull, $farmerReverse], true)) {
            return true;
        }

        $clientCore = $this->normalizeName(trim("{$clientFirst} {$clientLast}"));
        $clientCoreReverse = $this->normalizeName(trim("{$clientLast} {$clientFirst}"));
        $farmerCore = $this->normalizeName(trim("{$farmerFirst} {$farmerLast}"));
        $farmerCoreReverse = $this->normalizeName(trim("{$farmerLast} {$farmerFirst}"));

        $coresMatch = in_array($clientCore, [$farmerCore, $farmerCoreReverse], true)
            || in_array($clientCoreReverse, [$farmerCore, $farmerCoreReverse], true);

        if (! $coresMatch) {
            return false;
        }

        $clientMiddleNorm = $this->normalizeName($clientMiddle);
        $farmerMiddleNorm = $this->normalizeName($farmerMiddle);

        return $clientMiddleNorm === '' || $farmerMiddleNorm === '' || $clientMiddleNorm === $farmerMiddleNorm;
    }

    private function normalizeName(string $name): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? ''));
    }

    private function resolveProjectId(?string $projectName, int $centerId): ?int
    {
        if ($projectName === null || in_array(strtolower($projectName), ['', 'n/a', 'na', '-'], true)) {
            return null;
        }

        $cacheKey = $centerId.':'.strtolower(trim($projectName));

        if (isset($this->projectCache[$cacheKey])) {
            return $this->projectCache[$cacheKey] ?: null;
        }

        $matchedProgram = ProjectProgramEnum::tryFroMatches($projectName);
        $program = $matchedProgram ?? ProjectProgramEnum::CBED;
        $normalizedName = strtolower(trim($projectName));

        $project = Project::query()
            ->where('program', $program->value)
            ->whereRaw('LOWER(name) = ?', [$normalizedName])
            ->first();

        if (! $project) {
            $project = Project::query()
                ->where('program', $program->value)
                ->whereNotNull('project_involvement')
                ->get()
                ->first(fn (Project $p) => in_array(
                    $normalizedName,
                    array_map('strtolower', $p->project_involvement),
                    true,
                ));
        }

        if (! $project && $matchedProgram instanceof ProjectProgramEnum) {
            $project = Project::query()
                ->where('program', $program->value)
                ->first();
        }

        if (! $project) {
            $project = $this->upsertProjectAction->execute(
                name: $projectName,
                program: $program,
                isFunded: false,
            );
        }

        $this->projectCache[$cacheKey] = $project->id;

        return $project->id;
    }

    private function resolveOrCreateServiceId(
        ?string $serviceName,
        ?string $categoryName,
        ?string $subCategoryName,
        ?string $simplifiedCode,
    ): ?int {
        if ($simplifiedCode === null) {
            return null;
        }

        $normalizedCode = strtolower(trim($simplifiedCode));

        if (isset($this->serviceCache[$normalizedCode])) {
            return $this->serviceCache[$normalizedCode];
        }

        $service = Service::query()->where('simplified_code', $simplifiedCode)->first();

        $categoryValues = $categoryName !== null ? [$categoryName] : [];
        $subCategoryValues = $subCategoryName !== null ? [$subCategoryName] : [];

        if ($service) {
            $updated = [];

            $existingCategories = $service->category ?? [];
            foreach ($categoryValues as $categoryValue) {
                if (! in_array($categoryValue, $existingCategories, true)) {
                    $existingCategories[] = $categoryValue;
                }
            }
            if ($existingCategories !== ($service->category ?? [])) {
                $updated['category'] = $existingCategories;
            }

            $existingSubCategories = $service->sub_category ?? [];
            foreach ($subCategoryValues as $subCategoryValue) {
                if (! in_array($subCategoryValue, $existingSubCategories, true)) {
                    $existingSubCategories[] = $subCategoryValue;
                }
            }
            if ($existingSubCategories !== ($service->sub_category ?? [])) {
                $updated['sub_category'] = $existingSubCategories;
            }

            if ($updated !== []) {
                $service->update($updated);
            }

            $this->serviceCache[$normalizedCode] = $service->id;

            return $service->id;
        }

        if ($serviceName === null) {
            return null;
        }

        $service = $this->upsertServiceAction->execute(
            code: $simplifiedCode,
            name: $serviceName,
            program: ServiceProgramEnum::CBED,
            valueChainSegment: ValueChainSegmentEnum::PRODUCTION,
            mfoCode: $this->deriveMfoCodeFromSimplifiedCode($simplifiedCode),
            category: $categoryValues,
            subCategory: $subCategoryValues,
            type: [],
            description: null,
        );

        $this->serviceCache[$normalizedCode] = $service->id;

        return $service->id;
    }

    private function deriveMfoCodeFromSimplifiedCode(?string $simplifiedCode): ?string
    {
        if ($simplifiedCode === null) {
            return null;
        }

        $normalized = trim($simplifiedCode);
        if ($normalized === '') {
            return null;
        }

        $segments = array_filter(
            explode('.', $normalized),
            fn ($segment) => $segment !== '',
        );

        if (count($segments) <= 1) {
            return $normalized;
        }

        return implode('.', array_slice($segments, 0, -1));
    }

    private function resolveProgram(string $clientName): ServiceProgramEnum
    {
        if (str_contains(strtoupper($clientName), 'PCC')) {
            return ServiceProgramEnum::IH;
        }

        return ServiceProgramEnum::CBED;
    }

    /**
     * Whether the client on a service record row should be registered as a farmer.
     *
     * Some services (e.g. MFO 1.3.x) are delivered to clients who are often not
     * farmers, so we skip farmer registration for those.
     */
    private function shouldRegisterFarmer(?string $simplifiedCode): bool
    {
        if ($simplifiedCode === null) {
            return false;
        }

        $normalized = strtolower(trim($simplifiedCode));

        return str_starts_with($normalized, '1.1.') || str_starts_with($normalized, '1.2.');
    }
}
