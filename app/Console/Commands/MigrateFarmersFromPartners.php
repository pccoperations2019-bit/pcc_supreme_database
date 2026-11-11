<?php

namespace App\Console\Commands;

use App\Enums\FarmerCategoryEnum;
use App\Models\Center;
use App\Models\EpahpCapacityBuilding;
use App\Models\EpahpGatasangKalabaw;
use App\Models\Farmer;
use App\Models\ManureProduction;
use App\Models\ManureSales;
use App\Models\MeatProduction;
use App\Models\MeatSales;
use App\Models\MilkConsolidation;
use App\Models\MilkFeedingProgram;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MilkTraded;
use App\Models\Partner;
use App\Models\Pl480;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrateFarmersFromPartners extends Command
{
    /**
     * php artisan farmers:migrate
     *     --file=storage/app/farmer_migration.json   (optional, has a default below)
     *     --force     (actually commits the migration. Without it, nothing is written.)
     */
    protected $signature = 'farmers:migrate
        {--file= : Path to the farmer_migration.json file}
        {--force : Actually commit changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Move farmer records that are currently stored in the partners table into the farmers table, merging duplicates and creating new farmer records where no match exists.';

    /**
     * Every table that stores a `partner_id` FK pointing at the partners table and
     * that should now point at the farmer instead (once the record moves out of
     * partners). The farmers table's own `partner_id` is reassigned automatically,
     * so you only need to list OTHER related tables here.
     *
     * 'from' is the column that currently holds the partner's id.
     * 'to' is the column on the SAME related table that should receive the farmer's id
     * (often a separate `farmer_id` column — check each table's migration).
     *
     * Defined against this codebase (verified each table carries both columns):
     *   productions / sales / tradeds / consolidations reference "farmer or partner",
     *   so any row still pointing at a migrated partner is repointed to the farmer.
     *
     * NOTE: tables WITHOUT a `farmer_id` column (products, dairy_boxes, project_sites,
     * milk_feeding_programs, pl480, service_records, dairy_box_sales) are intentionally
     * NOT listed here — their `partner_id` is a true ownership FK, not a recordable sale.
     */
    protected array $relatedForeignKeys = [
        MilkProduction::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        MilkSale::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        MilkTraded::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        MilkConsolidation::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        MeatProduction::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        MeatSales::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        ManureProduction::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        ManureSales::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        EpahpGatasangKalabaw::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
        EpahpCapacityBuilding::class => ['from' => 'partner_id', 'to' => 'farmer_id'],
    ];

    /**
     * Tables that reference a partner through a RESTRICT `partner_id` FK but have NO
     * `farmer_id` column to repoint: their `partner_id` is simply nulled before the
     * partner row is deleted, otherwise the delete would fail the FK constraint.
     * Verified in the live schema (information_schema) as NO ACTION rules.
     */
    protected array $relatedClearedForeignKeys = [
        MilkFeedingProgram::class,
        Pl480::class,
    ];

    public function handle(): int
    {
        $path = $this->option('file') ?: storage_path('app/farmer_migration.json');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be saved. Pass --force to commit.');
        }

        $summary = [
            'linked_to_existing_farmer' => 0,
            'new_farmer_created' => 0,
            'duplicates_merged' => 0,
            'skipped_master_not_found' => 0,
            'skipped_ambiguous_match' => 0,
            'skipped_matched_farmer_missing' => 0,
        ];

        $skippedDetails = [];

        foreach ($records as $record) {
            $master = $this->findPartner($record);

            if (! $master instanceof Partner) {
                $this->error("Master not found in partners table — \"{$record['partner_name']}\" ({$record['center_code']}). Skipped.");
                $summary['skipped_master_not_found']++;
                $skippedDetails[] = [
                    'reason' => 'master_not_found_in_db',
                    'partner_name' => $record['partner_name'],
                    'center_code' => $record['center_code'],
                ];

                continue;
            }

            $matchInfo = $record['existing_farmer_match'] ?? ['status' => 'not_found'];

            if ($matchInfo['status'] === 'ambiguous') {
                $this->warn("  Ambiguous farmer match for \"{$record['partner_name']}\" — candidates: ".implode(', ', $matchInfo['candidates'] ?? []).'. Skipped, needs manual review.');
                $summary['skipped_ambiguous_match']++;
                $skippedDetails[] = [
                    'reason' => 'ambiguous_farmer_match',
                    'partner_name' => $record['partner_name'],
                    'candidates' => $matchInfo['candidates'] ?? [],
                ];

                continue;
            }

            $targetFarmer = null;
            $newFarmerData = null;

            if ($matchInfo['status'] === 'exists') {
                $targetFarmer = Farmer::where('code', $matchInfo['farmer_id'])->first();

                if (! $targetFarmer) {
                    $this->error("  Matched farmer code \"{$matchInfo['farmer_id']}\" not found in farmers table for \"{$record['partner_name']}\". Skipped — check the directory reference data.");
                    $summary['skipped_matched_farmer_missing']++;
                    $skippedDetails[] = [
                        'reason' => 'matched_farmer_id_missing_in_db',
                        'partner_name' => $record['partner_name'],
                        'expected_farmer_id' => $matchInfo['farmer_id'],
                    ];

                    continue;
                }

                $this->info("  Linking \"{$record['partner_name']}\" to existing farmer {$targetFarmer->code}");
                $summary['linked_to_existing_farmer']++;
            } else {
                // status === 'not_found' -> create a brand new farmer record
                $centerId = $this->resolveCenterId($record['center_code'] ?? null);
                if (! $centerId) {
                    $this->error("  Center not found for code \"{$record['center_code']}\" — \"{$record['partner_name']}\". Skipped.");
                    $summary['skipped_master_not_found']++;
                    $skippedDetails[] = [
                        'reason' => 'center_not_found',
                        'partner_name' => $record['partner_name'],
                        'center_code' => $record['center_code'],
                    ];

                    continue;
                }

                $farmerCode = $this->generateFarmerCode($record['center_code']);
                $this->info("  Creating new farmer \"{$record['partner_name']}\" with code {$farmerCode}");

                $newFarmerData = $this->mapPartnerDataToFarmer($record, $farmerCode, $centerId, $master->id);
                $summary['new_farmer_created']++;
            }

            $allPartnerRows = array_merge([$master], $this->findDuplicates($record['merge_into_master'] ?? [], excludeId: $master->id));

            if (! $dryRun) {
                DB::transaction(function () use ($allPartnerRows, $targetFarmer, $newFarmerData) {
                    // Create the farmer INSIDE the transaction: if any FK step below
                    // fails, the whole migration for this record — including the new
                    // farmer row — is rolled back (no orphan farmers on retry).
                    $farmer = $targetFarmer ?? Farmer::create($newFarmerData);

                    foreach ($allPartnerRows as $partnerRow) {
                        // Farmers still pointing at this partner row can no longer reference
                        // it once the partner is migrated/deleted. `farmers.partner_id` is an
                        // FK to partners (not farmers), so it must be cleared — never set to
                        // the migration-target farmer's id.
                        Farmer::where('partner_id', $partnerRow->id)
                            ->update(['partner_id' => null]);

                        foreach ($this->relatedForeignKeys as $modelClass => $cols) {
                            if (! Schema::hasTable((new $modelClass)->getTable())) {
                                $this->warn("      Skipping FK repoint: table for {$modelClass} does not exist (migration not run).");

                                continue;
                            }
                            // Repoint the record at the migrated farmer and clear the old
                            // partner reference. Some tables (milk_productions, milk_sales,
                            // milk_tradeds) use a RESTRICT `partner_id` FK, so the partner
                            // row cannot be deleted while it is still referenced.
                            $update = [$cols['to'] => $farmer->id];
                            if ($cols['from'] !== $cols['to']) {
                                $update[$cols['from']] = null;
                            }
                            $modelClass::where($cols['from'], $partnerRow->id)
                                ->update($update);
                        }

                        // RESTRICT partner_id FKs with no farmer_id column: clear the
                        // reference so the upcoming delete is not blocked.
                        foreach ($this->relatedClearedForeignKeys as $modelClass) {
                            if (! Schema::hasTable((new $modelClass)->getTable())) {
                                $this->warn("      Skipping FK clear: table for {$modelClass} does not exist (migration not run).");

                                continue;
                            }
                            $modelClass::where('partner_id', $partnerRow->id)
                                ->update(['partner_id' => null]);
                        }
                        $partnerRow->delete();
                    }
                });
            }

            $summary['duplicates_merged'] += count($allPartnerRows) - 1;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($v, $k) => [Str::headline($k), $v])->values()->toArray()
        );

        if ($dryRun) {
            $this->newLine();
            $this->comment('This was a dry run — re-run with --force to actually migrate the data.');
        }

        $reportPath = storage_path('app/farmer_migration_skipped_report.json');
        file_put_contents($reportPath, json_encode($skippedDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->comment("Full detail of every skipped record written to: {$reportPath}");

        return self::SUCCESS;
    }

    /**
     * Generate a unique farmer code using the app's own code generator
     * (`Farmer::generateCodeForCenter`), which uses the `Sequence` store and
     * honors the per-center `code` prefix (e.g. "DMMMSU-00038"). The generated
     * code goes into the farmers `code` column.
     */
    protected function generateFarmerCode(string $centerCode): string
    {
        return Farmer::generateCodeForCenter($centerCode);
    }

    /**
     * Map the raw JSON/partner fields onto the actual `farmers` table columns.
     *
     * The farmers table stores: `code` (unique friendly code), `center_id`/`partner_id`
     * foreign keys, and `first_name`/`last_name` (see 2025_10_17_220428_create_farmers_table).
     *
     * `partner_name` here is often a personal name ("Adelpha Adriano") or a farm name
     * ("Abergas Dairy Farm") with inconsistent ordering. We split it conservatively:
     * the last word becomes `last_name` and everything before it becomes `first_name`
     * (matching the LGU-style "Olivia Palazo" ordering). Adjust if your data stores
     * farm names instead of personal names.
     */
    protected function mapPartnerDataToFarmer(array $record, string $farmerCode, ?int $centerId, ?int $partnerId): array
    {
        $name = trim($record['partner_name'] ?? '');
        $tokens = preg_split('/\s+/', $name) ?: [];
        $lastName = count($tokens) > 1 ? array_pop($tokens) : null;
        $firstName = $name !== '' ? ($tokens !== [] ? implode(' ', $tokens) : $name) : '';

        return [
            'code' => $farmerCode,
            'center_id' => $centerId,
            'partner_id' => $partnerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'category' => FarmerCategoryEnum::DEFAULT->value,
        ];
    }

    /**
     * Match a JSON record to a Partner row.
     * Strategy: exact PARTNER CODE match if present, otherwise PARTNER NAME + CENTER CODE
     * (case-insensitive, trimmed) as a fallback.
     *
     * The partners table stores its unique code in `code` and its name in `name`;
     * it links to a center through `center_id`, so a `center_code` string is
     * resolved to its Center first and matched on the FK.
     */
    protected function findPartner(array $data, ?int $excludeId = null): ?Partner
    {
        $query = Partner::query();

        if (! empty($data['partner_code'])) {
            $match = (clone $query)->where('code', $data['partner_code']);
            if ($excludeId) {
                $match->where('id', '!=', $excludeId);
            }
            $found = $match->first();
            if ($found) {
                return $found;
            }
        }

        $name = trim($data['partner_name'] ?? '');
        $centerCode = trim($data['center_code'] ?? '');
        $centerId = $centerCode !== '' ? $this->resolveCenterId($centerCode) : null;

        $match = (clone $query)->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)]);

        if ($centerId) {
            $match->where('center_id', $centerId);
        }

        if ($excludeId) {
            $match->where('id', '!=', $excludeId);
        }

        return $match->first();
    }

    /**
     * Resolve a `center_code` string (e.g. "CSU", "DMMMSU") to the Center's primary key.
     * The partners/farmers tables store a `center_id` FK, not a code string.
     */
    protected function resolveCenterId(?string $centerCode): ?int
    {
        if (blank($centerCode)) {
            return null;
        }

        return Center::query()
            ->whereRaw('LOWER(TRIM(code)) = ?', [Str::lower(trim($centerCode))])
            ->value('id');
    }

    /**
     * Resolve each duplicate entry (from merge_into_master) to its actual Partner row.
     * Unresolvable duplicates are logged and simply skipped — they'll remain as
     * orphaned partner rows for manual cleanup rather than being silently lost.
     */
    protected function findDuplicates(array $duplicates, int $excludeId): array
    {
        $rows = [];
        foreach ($duplicates as $dupData) {
            $row = $this->findPartner($dupData, excludeId: $excludeId);
            if ($row instanceof Partner) {
                $rows[] = $row;
            } else {
                $this->warn("    Duplicate not found in DB — \"{$dupData['partner_name']}\". Skipped.");
            }
        }

        return $rows;
    }
}
