<?php

namespace App\Console\Commands;

use App\Models\ConsolidationUnit;
use App\Models\DairyBox;
use App\Models\DairyBoxSale;
use App\Models\Farmer;
use App\Models\ManureProduction;
use App\Models\ManureSales;
use App\Models\MarketingUnit;
use App\Models\MeatProduction;
use App\Models\MeatSales;
use App\Models\MilkConsolidation;
use App\Models\MilkFeedingProgram;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MilkTraded;
use App\Models\Partner;
use App\Models\Pl480;
use App\Models\ProcessingUnit;
use App\Models\ProductionUnit;
use App\Models\ProjectSite;
use App\Models\ProjectSiteDocument;
use App\Models\ProjectSiteProduction;
use App\Models\ServiceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MergePartnerDuplicates extends Command
{
    /**
     * php artisan partners:merge-duplicates
     *     --file=storage/app/partner_duplicates_simple.json   (optional, has a default below)
     *     --dry-run   (default behaviour if --force is not passed — nothing is written)
     *     --force     (actually commits the merge to the database)
     */
    protected $signature = 'partners:merge-duplicates
        {--file= : Path to the partner_duplicates_simple.json file}
        {--force : Actually commit changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Merge duplicate Partner records into their master, based on partner_duplicates_simple.json';

    /**
     * EDIT THIS: every other table that stores a partner_id (or similar FK) pointing at the
     * partners table. Before a duplicate row is deleted, its related records are re-pointed
     * to the master's id so nothing gets orphaned.
     *
     * Format: [ FullyQualifiedModelClass::class => 'foreign_key_column_name' ]
     *
     * Example:
     *   \App\Models\AssistanceRecord::class => 'partner_id',
     *   \App\Models\Certification::class    => 'partner_id',
     */
    protected array $relatedForeignKeys = [
        Farmer::class => 'partner_id',
        ProductionUnit::class => 'partner_id',
        ConsolidationUnit::class => 'partner_id',
        ProcessingUnit::class => 'partner_id',
        MarketingUnit::class => 'partner_id',
        MilkProduction::class => 'partner_id',
        MilkSale::class => 'partner_id',
        MilkTraded::class => 'partner_id',
        MilkConsolidation::class => 'partner_id',
        MilkFeedingProgram::class => 'partner_id',
        DairyBox::class => 'partner_id',
        DairyBoxSale::class => 'partner_id',
        MeatProduction::class => 'partner_id',
        MeatSales::class => 'partner_id',
        ManureProduction::class => 'partner_id',
        ManureSales::class => 'partner_id',
        ServiceRecord::class => 'partner_id',
        ProjectSite::class => 'production_partner_id',
        ProjectSiteProduction::class => 'partner_id',
        ProjectSiteDocument::class => 'partner_id',
        // \App\Models\EpahpGatasangKalabaw::class => 'partner_id',
        // \App\Models\EpahpCapacityBuilding::class => 'partner_id',
        Pl480::class => 'partner_id',
    ];

    public function handle(): int
    {
        $path = $this->option('file') ?: storage_path('app/partner_duplicates_simple.json');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $groups = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $dryRun = ! $this->option('force');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be saved. Pass --force to commit.');
        }

        $summary = [
            'merged' => 0,
            'skipped_no_master' => 0,
            'skipped_no_duplicate' => 0,
            'duplicates_merged' => 0,
        ];

        foreach ($groups as $group) {
            $masterData = $group['master'] ?? null;
            $duplicates = $group['merge_into_master'] ?? [];

            if (! $masterData) {
                $this->line("Group {$group['group_id']}: no master data, skipped.");
                $summary['skipped_no_master']++;

                continue;
            }

            $master = $this->findPartner($masterData);

            if (! $master instanceof Partner) {
                $this->error("Group {$group['group_id']}: MASTER not found in DB — \"{$masterData['partner_name']}\". Skipped whole group.");
                $summary['skipped_no_master']++;

                continue;
            }

            foreach ($duplicates as $dupData) {
                $duplicate = $this->findPartner($dupData, excludeId: $master->id);

                if (! $duplicate instanceof Partner) {
                    $this->warn("  Group {$group['group_id']}: duplicate not found in DB — \"{$dupData['partner_name']}\". Skipped.");
                    $summary['skipped_no_duplicate']++;

                    continue;
                }

                if ($duplicate->id === $master->id) {
                    // Safety: never merge a record into itself.
                    continue;
                }

                $this->info("  Merging duplicate #{$duplicate->id} \"{$duplicate->name}\" into master #{$master->id} \"{$master->name}\"");

                if (! $dryRun) {
                    DB::transaction(function () use ($master, $duplicate) {
                        // Re-point every related table's foreign key from duplicate -> master.
                        foreach ($this->relatedForeignKeys as $modelClass => $foreignKey) {
                            $modelClass::where($foreignKey, $duplicate->id)
                                ->update([$foreignKey => $master->id]);
                        }

                        // Master's values win — we don't copy any fields from the duplicate.
                        // Remove the duplicate (soft delete if the model supports it).
                        if (method_exists($duplicate, 'trashed')) {
                            $duplicate->delete(); // SoftDeletes
                        } else {
                            $duplicate->delete(); // hard delete
                        }
                    });
                }

                $summary['duplicates_merged']++;
            }

            $summary['merged']++;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($v, $k) => [Str::headline($k), $v])->values()->toArray()
        );

        if ($dryRun) {
            $this->newLine();
            $this->comment('This was a dry run — re-run with --force to actually merge and delete duplicates.');
        }

        return self::SUCCESS;
    }

    /**
     * Match a JSON record to a Partner row.
     *
     * The `partners` table exposes no short "partner code" column — its `code`
     * is auto-generated (e.g. "COOP-00001"), so it cannot be used to match the
     * friendly codes found in the import file. Matching is therefore done by
     * PARTNER NAME + CENTER CODE (case-insensitive, trimmed), which is the only
     * stable, unique key available for duplicate detection.
     *
     * The `centers` table is keyed by its own `code` column and the search is
     * scoped to the partner's center (selected by center code) to keep matches
     * unambiguous across centers.
     */
    protected function findPartner(array $data, ?int $excludeId = null): ?Partner
    {
        $name = trim($data['partner_name'] ?? '');
        $centerCode = trim($data['center_code'] ?? '');

        if ($name === '') {
            return null;
        }

        return Partner::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)])
            ->when($centerCode, function ($q) use ($centerCode) {
                $q->whereHas('center', fn ($center) => $center->where('code', $centerCode));
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }
}
