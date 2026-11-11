<?php

declare(strict_types=1);

namespace App\Imports;

use App\Actions\Partner\UpsertPartnerAction;
use App\Actions\Project\UpsertProjectAction;
use App\Enums\ProjectProgramEnum;
use App\Imports\Concerns\WithDocumentStatus;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Services\ProjectSiteAddressService;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Row;

/**
 * Row importer for the `CBINCCDP` sheet of Projectsite.xlsx.
 *
 * Uses positional indexing (OnEachRow) since the cleaned file has
 * numeric column indexes. Data starts at row 2 (after the header row).
 *
 * Mirrors CBIN/CCDPProjectSiteController::store() semantics:
 * one ProjectSite + address + nested relations per row, all inside a
 * single DB transaction so a bad child row never leaves an orphaned site.
 *
 * Import-specific rules:
 * - Row 1 is header; data starts at row 2.
 * - `For input`, `Input`, `ATTACHMENT`, `Yes or No` are placeholders → null.
 * - Date columns may hold status words / ranges / Excel serials → parse
 *   defensively via Sanitizer, keep the raw value in metadata.
 * - `No. of Recipients` may be `communal` → flag, not zero.
 * - Dairy Box Code is NOT unique → lookup only, never create on it.
 */
class CBINCCDPSiteSheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithEvents
{
    use WithDocumentStatus;

    /** Placeholders that mean "empty", never data. */
    private const PLACEHOLDERS = ['for input', 'input', 'attachment', 'yes or no'];

    /** 0-based columns with vertically merged cells → forward-fill. */
    private const FORWARD_FILL = [15, 16, 18, 28, 29, 30, 31, 32, 37];

    /** Training date/pax pairs: [dateColIndex, paxColIndex, trainingRecordFieldPrefix]. */
    private const TRAINING_PAIRS = [
        [62, 63, 'project_orientation'],
        [64, 65, 'strategic_planning'],
        [66, 67, 'social_preparation_training'],
        [68, 69, 'forage_production_training'],
        [70, 71, 'silage_making_training'],
        [72, 73, 'animal_health_training'],
        [74, 75, 'breeding_management_training'],
        [76, 77, 'milk_handling_training'],
        [78, 79, 'milk_processing_training'],
        [80, 81, 'animal_husbandry_training'],
        [82, 83, 'organic_fertilizer_training'],
        [84, 85, 'cooperative_management_training'],
        [86, 87, 'fls_launching'],
        [88, 89, 'fls_buffalo_training'],
        [90, 91, 'fls'],
    ];

    /** Last non-empty value per forward-fill column (merged cells). */
    private array $lastSeen = [];

    private array $centerIds = [];

    private array $centerCodeCache = [];

    private array $partnerCache = [];

    private array $projectCache = [];

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function onRow(Row $row): void
    {
        // Skip the header row; data starts at row 2.
        if ($row->getIndex() < 2) {
            return;
        }

        $cells = array_values($row->toArray(calculateFormulas: true));
        $peek = fn (int $i): mixed => $cells[$i] ?? null;

        // Program check runs before forward-fill so header rows never
        // pollute the merged-cell state. Silent for the first rows (header
        // layouts vary: 1 row in current files, 3 in older ones).
        $program = $this->cleanString($peek(0));
        $programEnum = $program !== null ? ProjectProgramEnum::tryFroMatches($program) : null;
        if (! $programEnum instanceof ProjectProgramEnum
            || ! in_array($programEnum, [ProjectProgramEnum::CBIN, ProjectProgramEnum::CCDP], true)) {
            if ($row->getIndex() > 3 && $program !== null) {
                Log::warning('CBINCCDP import: unknown program, row skipped', [
                    'row' => $row->getIndex(), 'program' => $program,
                ]);
            }

            return;
        }

        // Forward-fill vertically merged cells (e.g. shared animal batches).
        foreach (self::FORWARD_FILL as $col) {
            $value = $this->cleanString($peek($col));
            if ($value !== null) {
                $this->lastSeen[$col] = $peek($col);
            } elseif (isset($this->lastSeen[$col])) {
                $cells[$col] = $this->lastSeen[$col];
            }
        }
        $get = fn (int $i): mixed => $cells[$i] ?? null;

        try {
            DB::transaction(function () use ($get, $row, $programEnum): void {
                $this->importRow($get, $row->getIndex(), $programEnum);
            });
        } catch (\Throwable $e) {
            // Skip bad rows — one bad site must not kill the whole import,
            // mirroring ServiceRecordImport::onRow().
            Log::warning('CBINCCDP import: row failed, skipped', [
                'row' => $row->getIndex(), 'error' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------

    /**
     * @param  callable  $get  Column accessor over the row.
     */
    private function importRow(callable $get, int $rowIndex, ProjectProgramEnum $program): void
    {
        $year = is_numeric($get(1)) ? (int) $get(1) : null;
        $centerId = $this->resolveCenterId($this->cleanString($get(2)));

        $partnerCode = $this->cleanString($get(3));
        $partnerName = $this->resolveProductionPartnerName($this->cleanString($get(4)));
        $partnerId = $this->resolvePartnerId($partnerCode, $partnerName, $centerId);

        $projectId = $this->resolveProjectId($program, $year);

        // Idempotent on the guide's composite key (project + year +
        // partner + municipality → center/partner/project/year here; the
        // municipality lives on the address). A fresh code is minted only
        // on create so re-imports never churn existing codes.
        $site = ProjectSite::firstOrNew(
            [
                'center_id' => $centerId,
                'production_partner_id' => $partnerId,
                'project_id' => $projectId,
                'year' => $year,
            ]
        );
        if (! $site->exists) {
            $site->project_site_code = ProjectSite::generateCode($program->value, $year);
        }
        $site->fill(
            [
                'type_of_contract' => $this->cleanString($get(9)),
                'value_chain_status' => $this->cleanString($get(37)),
                'inauguration_date' => $this->parseFlexibleDate($get(30)),
                'metadata' => [
                    'source' => 'Projectsite.xlsx/CBINCCDP',
                    'source_row' => $rowIndex,
                    'program' => $program->value,
                    'partner_code_raw' => $this->cleanString($get(3)),
                    'partner_cooperative_raw' => $this->cleanString($get(4)),
                    'partner_farmer_org' => $this->cleanString($get(8)),
                    'communal_recipients' => $this->isCommunal($get(19)),
                    'development_status_raw' => $this->cleanString($get(37)),
                    'processing_status_raw' => [
                        'start_of_construction' => $this->cleanString($get(28)),
                        'completion_status' => $this->cleanString($get(29)),
                        'target_date_for_completion' => $this->cleanString($get(30)),
                    ],
                    'dairy_box_code' => $this->cleanString($get(50)),
                    'dairy_box' => array_filter([
                        'status' => $this->cleanString($get(51)),
                        'manager' => $this->cleanString($get(52)),
                        'manager_contact' => $this->cleanString($get(53)),
                        'staff_count' => $this->parseInt($get(54)),
                        'date_inaugurated_raw' => $this->cleanString($get(49)),
                    ]),
                    'marketing_raw' => array_filter([
                        'municipality' => $this->cleanString($get(41)),
                        'district' => $this->cleanString($get(42)),
                        'province' => $this->cleanString($get(43)),
                        'latitude' => is_numeric($get(44)) ? (float) $get(44) : null,
                        'longitude' => is_numeric($get(45)) ? (float) $get(45) : null,
                        'start_of_construction' => $this->cleanString($get(46)),
                        'date_of_completion' => $this->cleanString($get(47)),
                        'contractor' => $this->cleanString($get(48)),
                    ]),
                    'kadiwa_ni_pangulo' => $this->cleanString($get(55)),
                    'milk_feeding_program' => $this->cleanString($get(56)),
                    'legal_documents_raw' => array_filter([
                        'moa' => $this->cleanString($get(57)),
                        'dod_usufruct' => $this->cleanString($get(58)),
                        'management_agreement_contract' => $this->cleanString($get(59)),
                        'dairy_buffalo_dispersal_agreement' => $this->cleanString($get(60)),
                    ]),
                    'courtesy_call_date_raw' => $this->cleanString($get(61)),
                ],
            ]
        );

        $site->save();

        // Site address — same service the CRUD controllers use.
        /** @var ProjectSiteAddressService $addresses */
        $addresses = resolve(ProjectSiteAddressService::class);
        $addressFields = [
            'municipality' => $this->cleanString($get(10)),
            'district' => $this->cleanString($get(11)),
            'province' => $this->cleanString($get(12)),
            'latitude' => is_numeric($get(13)) ? (float) $get(13) : null,
            'longitude' => is_numeric($get(14)) ? (float) $get(14) : null,
        ];
        if ($site->wasRecentlyCreated) {
            $addresses->create($site, $addressFields);
        } else {
            $addresses->sync($site, $addressFields);
        }

        // Replace-on-save children (import is authoritative per row,
        // mirroring ProjectSiteRelationsService semantics).
        $site->productions()->delete();
        $site->infrastructure()->delete();
        $site->equipment()->delete();
        $site->trainingRecords()->delete();
        $site->budgetUtilization()->delete();
        $site->marketingInfrastructure()->delete();

        // Integer columns are NOT NULL with default 0 in the schema,
        // so empty cells coalesce to 0 rather than null.
        $site->productions()->create([
            'no_of_animals_to_be_delivered' => $this->parseInt($get(15)) ?? 0,
            'pcc_counterpart' => $this->cleanString($get(16)),
            'delivered' => $this->parseInt($get(17)) ?? 0,
            'supplier' => $this->cleanString($get(18)),
            'no_of_recipients' => $this->parseInt($get(19)) ?? 0,
            'procuring_agency' => $this->cleanString($get(20)),
            'mode_of_procurement' => $this->cleanString($get(21)),
        ]);

        $completionRaw = $this->cleanString($get(29));
        $infra = $site->infrastructure()->create([
            'type_of_ownership' => $this->cleanString($get(22)),
            'start_of_construction' => $this->parseFlexibleDate($get(28)),
            'completion_status' => $completionRaw,
            'target_date_for_completion' => $this->parseFlexibleDate($get(30)),
            'date_inaugurated' => $this->parseFlexibleDate($get(31)),
            'contractor' => $this->cleanString($get(32)),
            'date_completed' => $this->parseFlexibleDate($get(36)),
            'percentage_of_progress' => $this->parseCompletionPercent($completionRaw),
            'metadata' => ['start_of_construction_raw' => $this->cleanString($get(28))],
        ]);

        // Processing facility location (W:AA) → infrastructure address.
        $procAddress = [
            'municipality' => $this->cleanString($get(23)),
            'district' => $this->cleanString($get(24)),
            'province' => $this->cleanString($get(25)),
            'latitude' => is_numeric($get(26)) ? (float) $get(26) : null,
            'longitude' => is_numeric($get(27)) ? (float) $get(27) : null,
        ];
        if (array_filter($procAddress) !== []) {
            resolve(ProjectSiteAddressService::class)->create($infra, $procAddress);
        }

        // Processing staff (AL:AN) → nested staff rows.
        $procStaffName = $this->cleanString($get(38));
        $procStaffContact = $this->cleanString($get(39));
        $procStaffCount = $this->parseInt($get(40));
        if ($procStaffName !== null || $procStaffContact !== null || $procStaffCount !== null) {
            $infra->processingStaff()->create([
                'staff_name' => $procStaffName,
                'contact_number' => $procStaffContact,
                'number_of_staff' => $procStaffCount ?? 0,
            ]);
        }

        if ($this->cleanString($get(33)) !== null || $this->cleanString($get(34)) !== null || $this->cleanString($get(35)) !== null) {
            $site->equipment()->create([
                'name_of_equipment' => $this->cleanString($get(33)),
                'delivery_of_equipment' => $this->parseFlexibleDate($get(34)),
                'date_installed' => $this->parseFlexibleDate($get(35)),
                'metadata' => [
                    'delivery_of_equipment_raw' => $this->cleanString($get(34)),
                    'date_installed_raw' => $this->cleanString($get(35)),
                ],
            ]);
        }

        $training = $this->buildTrainingRecord($get);
        if ($training !== []) {
            $site->trainingRecords()->create($training);
        }

        $budget = array_filter([
            'budget_allocated' => $this->parseDecimal($get(5)),
            'obligated' => $this->parseDecimal($get(6)),
            'disbursed' => $this->parseDecimal($get(7)),
        ]);
        if ($budget !== []) {
            $site->budgetUtilization()->create([...$budget, 'year' => $year]);
        }

        $this->linkMarketingInfrastructure($get, $site, $centerId);
    }

    // ------------------------------------------------------------------
    // Resolvers (cached per import, same pattern as ServiceRecordImport).
    // ------------------------------------------------------------------

    private function resolveCenterId(?string $code): ?int
    {
        if ($code === null) {
            return null;
        }

        $key = strtoupper(trim($code));
        if (isset($this->centerCodeCache[$key])) {
            return $this->centerCodeCache[$key];
        }

        foreach ($this->centerIds as $storedCode => $id) {
            if (strtoupper(trim((string) $storedCode)) === $key) {
                return $this->centerCodeCache[$key] = $id;
            }
        }

        $center = Center::query()->where('code', $key)->first();
        if (! $center) {
            Log::warning('CBINCCDP import: unknown center code', ['code' => $code]);

            return $this->centerCodeCache[$key] = null;
        }

        return $this->centerCodeCache[$key] = $center->id;
    }

    /**
     * A cell may hold "Market\nProduction" partners (guide §4.2 CCDP-C).
     * The Production-tagged line is the production partner; else the first line.
     */
    private function resolveProductionPartnerName(?string $cell): ?string
    {
        if ($cell === null) {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cell) ?: [])));
        if ($lines === []) {
            return null;
        }

        foreach ($lines as $line) {
            if (str_contains(strtolower($line), '(production)')) {
                return trim(str_ireplace('(production)', '', $line));
            }
        }

        return trim(preg_replace('/\s*\((market|production)\)\s*/i', '', $lines[0]) ?? $lines[0]);
    }

    private function resolvePartnerId(?string $code, ?string $name, ?int $centerId): ?int
    {
        if ($name === null && $code === null) {
            return null;
        }

        if ($centerId === null) {
            return null;
        }

        $key = $centerId.':'.strtolower($name ?? $code);
        if (isset($this->partnerCache[$key])) {
            return $this->partnerCache[$key];
        }

        // When partner code is available, delegate to UpsertPartnerAction which
        // does updateOrCreate by code and also corrects the partner name if needed
        if ($code !== null) {
            $partner = resolve(UpsertPartnerAction::class)->execute(
                code: $code,
                centerId: $centerId,
                name: $name ?? $code,
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

            return $this->partnerCache[$key] = $partner->id;
        }

        // Only partner name available: try exact name match first
        $partner = Partner::query()->where('center_id', $centerId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if (! $partner) {
            $partner = resolve(UpsertPartnerAction::class)->execute(
                code: null,
                centerId: $centerId,
                name: $name,
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
        }

        return $this->partnerCache[$key] = $partner->id;
    }

    /**
     * The sheet has no project name — one Project per program, with the year
     * appended to project_involvement so fund-source values stay comparable
     * (cf. Sanitizer::parseProjectInvolvement).
     */
    private function resolveProjectId(ProjectProgramEnum $program, ?int $year): ?int
    {
        $key = $program->value.':'.($year ?? 'any');
        if (isset($this->projectCache[$key])) {
            return $this->projectCache[$key];
        }

        $project = Project::query()->where('program', $program->value)->first()
            ?? resolve(UpsertProjectAction::class)->execute(
                name: strtoupper($program->value),
                program: $program,
                isFunded: true,
            );

        if ($year !== null) {
            $involvement = $project->project_involvement ?? [];
            $tag = strtoupper($program->value).'-'.$year;
            if (! in_array($tag, $involvement, true)) {
                $project->update(['project_involvement' => [...$involvement, $tag]]);
            }
        }

        return $this->projectCache[$key] = $project->id;
    }

    private function linkMarketingInfrastructure(callable $get, ProjectSite $site, ?int $centerId): void
    {
        $dairyBoxCode = $this->cleanString($get(50));

        $marketingAddress = [
            'municipality' => $this->cleanString($get(41)),
            'district' => $this->cleanString($get(42)),
            'province' => $this->cleanString($get(43)),
            'latitude' => is_numeric($get(44)) ? (float) $get(44) : null,
            'longitude' => is_numeric($get(45)) ? (float) $get(45) : null,
        ];

        // Only create the row when the sheet actually carries marketing data.
        if ($dairyBoxCode === null && array_filter($marketingAddress) === []) {
            return;
        }

        // Dairy Box Code repeats across rows (guide rule 10) — lookup only.
        $dairyBox = $dairyBoxCode !== null
            ? DairyBox::query()->where('dairy_box_id', $dairyBoxCode)->first()
            : null;
        if (! $dairyBox && $dairyBoxCode !== null && $centerId !== null) {
            $dairyBox = DairyBox::query()
                ->where('center_id', $centerId)
                ->where('dairy_box_id', 'LIKE', "%{$dairyBoxCode}%")
                ->first();
        }

        $marketing = $site->marketingInfrastructure()->create([
            'dairy_box_id' => $dairyBox?->id,
            'manager' => $this->cleanString($get(52)),
            'operational_status' => $this->cleanString($get(51)),
            'launched_date' => $this->parseFlexibleDate($get(49)),
            'metadata' => ['dairy_box_code_raw' => $dairyBoxCode],
        ]);

        if (array_filter($marketingAddress) !== []) {
            resolve(ProjectSiteAddressService::class)->create($marketing, $marketingAddress);
        }
    }

    // ------------------------------------------------------------------
    // Cell helpers.
    // ------------------------------------------------------------------

    private function buildTrainingRecord(callable $get): array
    {
        $record = [];
        foreach (self::TRAINING_PAIRS as [$dateCol, $paxCol, $prefix]) {
            $date = $this->parseFlexibleDate($get($dateCol));
            $pax = $this->parseInt($get($paxCol));
            // CCDP rows have no activity data — skip empty pairs entirely.
            if (! $date instanceof \DateTimeInterface && $pax === null) {
                continue;
            }
            $record["{$prefix}_date"] = $date?->format('Y-m-d');
            $record["{$prefix}_manual_pax"] = $pax ?? 0;
        }

        return $record;
    }

    /**
     * Tolerant date parser for the sheet's free-text date columns.
     *
     * Sanitizer::parseDate() handles clean dates and Excel serials; this
     * adds the sheet's real-world shapes and returns the START date:
     * - ranges: `03/30-31/2022`, `11/18 & 21/2022`, `Dec. 12-13, 2022`,
     *   `09/29/2022 to 10/01/2022`
     * - notes: `2/10/2025 (Soft opening)`, `Construction started August
     *   16, 2022`, `Installation next week as per supplier (03/13/2024)`
     * - lists: newline-separated dates (first parseable wins)
     *
     * Pure status words (`Completed`, `Installed`, `Terminated`,
     * `On-going`, …) still return null — the raw text is already kept in
     * metadata by the caller.
     */
    private function parseFlexibleDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $direct = Sanitizer::parseDate($value);
        if ($direct instanceof \DateTimeInterface) {
            return $direct;
        }

        $candidates = preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            // Collapse range separators, then keep the start date:
            // "11/18 & 21/2022" / "09/29/2022 to 10/01/2022" → start kept.
            $normalized = preg_replace('/\s*(?:&|\bto\b)\s*/i', '-', $candidate) ?? $candidate;
            // "03/30-31/2022" → "03/30/2022".
            $normalized = preg_replace('/(\d{1,2}\/\d{1,2})\s*-\s*\d{1,2}(\/\d{2,4})/', '$1$2', $normalized) ?? $normalized;
            // "Dec. 12-13, 2022" → "Dec. 12, 2022".
            $normalized = preg_replace('/([A-Za-z]+\.?\s+\d{1,2})\s*-\s*\d{1,2}(,?\s+\d{4})/', '$1$2', $normalized) ?? $normalized;

            $attempts = [$normalized];
            if (preg_match_all('/\d{1,2}\/\d{1,2}\/\d{2,4}|[A-Za-z]+\.?\s+\d{1,2},?\s+\d{4}/', $normalized, $m)) {
                array_push($attempts, ...$m[0]);
            }

            foreach ($attempts as $attempt) {
                try {
                    return Date::parse(trim($attempt));
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || in_array(strtolower($trimmed), self::PLACEHOLDERS, true)) {
            return null;
        }

        return $trimmed;
    }

    private function isCommunal(mixed $value): bool
    {
        return strtolower(trim((string) $value)) === 'communal';
    }

    private function parseInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $this->isCommunal($value)) {
            return null;
        }

        $parsed = (int) Sanitizer::parseNumeric($value);

        return $value !== null && trim((string) $value) !== '' ? $parsed : null;
    }

    private function parseDecimal(mixed $value): ?float
    {
        $cleaned = $this->cleanString($value);
        if ($cleaned === null) {
            return null;
        }

        return Sanitizer::parseNumeric($cleaned);
    }

    /**
     * AC is a 0-1 fraction (guide rule 6), but the cleaned file also uses
     * percent strings ("100%"). Free text → 0 with raw kept in metadata.
     */
    private function parseCompletionPercent(?string $raw): int
    {
        if ($raw === null) {
            return 0;
        }

        $trimmed = trim($raw);
        if (str_ends_with($trimmed, '%')) {
            $number = trim(substr($trimmed, 0, -1));

            return is_numeric($number) ? (int) round((float) $number) : 0;
        }

        if (! is_numeric($trimmed)) {
            return 0;
        }

        return (int) round(((float) $trimmed) * 100);
    }
}
