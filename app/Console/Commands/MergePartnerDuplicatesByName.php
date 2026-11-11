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
use Illuminate\Support\Sleep;

class MergePartnerDuplicatesByName extends Command
{
    protected $signature = 'partners:merge-duplicates-by-name
        {--preview : Show what would be merged without making changes}
        {--force : Actually commit changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Merge duplicate partner records that have the same name and center_id';

    /**
     * Every table that stores a partner_id FK that needs to be repointed during merge.
     * Format: [ FullyQualifiedModelClass::class => 'foreign_key_column_name' ]
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
        ProjectSiteProduction::class => 'partner_id',
        ProjectSiteDocument::class => 'partner_id',
        Pl480::class => 'partner_id',
    ];

    protected array $specialForeignKeys = [
        ProjectSite::class => 'production_partner_id',
    ];

    public function handle(): int
    {
        $dryRun = ! $this->option('force') || $this->option('preview');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be saved. Pass --force to commit.');
        }

        // Find duplicate groups (same name, center_id)
        $duplicates = DB::select('
            SELECT
                LOWER(TRIM(name)) AS norm_name,
                center_id,
                GROUP_CONCAT(id ORDER BY id) AS partner_ids,
                COUNT(*) AS count
            FROM partners
            GROUP BY LOWER(TRIM(name)), center_id
            HAVING COUNT(*) > 1
        ');

        if (empty($duplicates)) {
            $this->info('No duplicate partners found (same name and center_id).');

            return self::SUCCESS;
        }

        $this->info('Found '.count($duplicates).' duplicate groups to process.');
        $this->newLine();

        $summary = [
            'groups_processed' => 0,
            'partners_merged' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($duplicates as $group) {
            $partnerIds = explode(',', $group->partner_ids);
            $keepId = min($partnerIds); // Keep the lowest ID as the survivor
            $dropIds = array_filter($partnerIds, fn ($id) => $id != $keepId);

            $this->info("Group: \"{$group->norm_name}\" (Center ID: {$group->center_id})");
            $this->line("  Keep: {$keepId}, Merge: ".implode(', ', $dropIds));

            if (count($dropIds) === 0) {
                $this->warn('  Skipped: no duplicates to merge');
                $summary['skipped']++;
                $this->newLine();

                continue;
            }

            $summary['groups_processed']++;
            $summary['partners_merged'] += count($dropIds);

            if (! $dryRun) {
                try {
                    DB::transaction(function () use ($keepId, $dropIds) {
                        // Handle partner_project pivot table (unique constraint on partner_id, project_id)
                        $this->handlePartnerProject($keepId, $dropIds);

                        // Re-point all standard foreign keys
                        foreach ($this->relatedForeignKeys as $modelClass => $foreignKey) {
                            $modelClass::whereIn($foreignKey, $dropIds)
                                ->update([$foreignKey => $keepId]);
                        }

                        // Re-point special foreign keys with different column names
                        foreach ($this->specialForeignKeys as $modelClass => $foreignKey) {
                            $modelClass::whereIn($foreignKey, $dropIds)
                                ->update([$foreignKey => $keepId]);
                        }

                        // Backfill missing data from duplicates to survivor
                        $this->backfillPartnerData($keepId, $dropIds);

                        // Delete the duplicate partners
                        Partner::whereIn('id', $dropIds)->delete();
                    });

                    $this->line('  ✓ Merged successfully');
                } catch (\Exception $e) {
                    $this->error('  ✗ Failed: '.$e->getMessage());
                    $summary['failed']++;
                    $summary['partners_merged'] -= count($dropIds); // Subtract since they weren't merged
                }

                // Small delay to reduce lock contention
                Sleep::usleep(500000); // 0.5 seconds
            } else {
                $this->line('  [DRY RUN] Would merge these records');
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Groups processed: {$summary['groups_processed']}");
        $this->line("  Partners merged: {$summary['partners_merged']}");
        $this->line("  Skipped: {$summary['skipped']}");
        $this->line("  Failed: {$summary['failed']}");

        return self::SUCCESS;
    }

    private function handlePartnerProject(int $keepId, array $dropIds): void
    {
        // Find project_ids that exist for both the keep partner and the drop partners
        $duplicateProjectIds = DB::table('partner_project')
            ->where('partner_id', $keepId)
            ->whereIn('project_id', function ($query) use ($dropIds) {
                $query->select('project_id')
                    ->from('partner_project')
                    ->whereIn('partner_id', $dropIds);
            })
            ->pluck('project_id');

        // Delete the duplicate associations from the drop partners
        if ($duplicateProjectIds->isNotEmpty()) {
            DB::table('partner_project')
                ->whereIn('partner_id', $dropIds)
                ->whereIn('project_id', $duplicateProjectIds)
                ->delete();
        }

        // Repoint remaining
        DB::table('partner_project')
            ->whereIn('partner_id', $dropIds)
            ->update(['partner_id' => $keepId]);
    }

    private function backfillPartnerData(int $keepId, array $dropIds): void
    {
        DB::update('
            UPDATE partners p
            LEFT JOIN partners d1 ON d1.id = ?
            LEFT JOIN partners d2 ON d2.id = ?
            SET p.logo = COALESCE(p.logo, d1.logo, d2.logo),
                p.website = COALESCE(p.website, d1.website, d2.website),
                p.description = COALESCE(p.description, d1.description, d2.description),
                p.contact_name = COALESCE(p.contact_name, d1.contact_name, d2.contact_name),
                p.position = COALESCE(p.position, d1.position, d2.position),
                p.contact_email = COALESCE(p.contact_email, d1.contact_email, d2.contact_email),
                p.mobile_number = COALESCE(p.mobile_number, d1.mobile_number, d2.mobile_number),
                p.telephone_number = COALESCE(p.telephone_number, d1.telephone_number, d2.telephone_number),
                p.metadata = COALESCE(p.metadata, d1.metadata, d2.metadata)
            WHERE p.id = ?
        ', [$dropIds[0] ?? null, $dropIds[1] ?? null, $keepId]);
    }
}
