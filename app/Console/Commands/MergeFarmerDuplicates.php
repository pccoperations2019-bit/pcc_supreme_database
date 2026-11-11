<?php

namespace App\Console\Commands;

use App\Models\ConsolidationUnit;
use App\Models\Farmer;
use App\Models\ManureProduction;
use App\Models\ManureSales;
use App\Models\MarketingUnit;
use App\Models\MeatProduction;
use App\Models\MeatSales;
use App\Models\MilkConsolidation;
use App\Models\MilkProduction;
use App\Models\MilkSale;
use App\Models\MilkTraded;
use App\Models\ProcessingUnit;
use App\Models\ProductionUnit;
use App\Models\ServiceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

class MergeFarmerDuplicates extends Command
{
    protected $signature = 'farmers:merge-duplicates
        {--preview : Show what would be merged without making changes}
        {--force : Actually commit changes. Without this flag the command only reports what it would do.}';

    protected $description = 'Merge duplicate farmer records that have the same name and partner_id';

    /**
     * Every table that stores a farmer_id FK that needs to be repointed during merge.
     * Format: [ FullyQualifiedModelClass::class => 'foreign_key_column_name' ]
     */
    protected array $relatedForeignKeys = [
        ConsolidationUnit::class => 'farmer_id',
        ManureProduction::class => 'farmer_id',
        ManureSales::class => 'farmer_id',
        MarketingUnit::class => 'farmer_id',
        MeatProduction::class => 'farmer_id',
        MeatSales::class => 'farmer_id',
        MilkConsolidation::class => 'farmer_id',
        MilkProduction::class => 'farmer_id',
        MilkSale::class => 'farmer_id',
        MilkTraded::class => 'farmer_id',
        ProcessingUnit::class => 'farmer_id',
        ProductionUnit::class => 'farmer_id',
        ServiceRecord::class => 'farmer_id',
    ];

    public function handle(): int
    {
        $dryRun = ! $this->option('force') || $this->option('preview');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be saved. Pass --force to commit.');
        }

        // Find duplicate groups (same first_name, last_name, partner_id)
        $duplicates = DB::select("
            SELECT
                LOWER(TRIM(first_name)) AS norm_first,
                LOWER(TRIM(COALESCE(last_name, ''))) AS norm_last,
                partner_id,
                GROUP_CONCAT(id ORDER BY id) AS farmer_ids,
                COUNT(*) AS count
            FROM farmers
            WHERE partner_id IS NOT NULL
            GROUP BY LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(last_name, ''))), partner_id
            HAVING COUNT(*) > 1
        ");

        if (empty($duplicates)) {
            $this->info('No duplicate farmers found (same name and partner_id).');

            return self::SUCCESS;
        }

        $this->info('Found '.count($duplicates).' duplicate groups to process.');
        $this->newLine();

        $summary = [
            'groups_processed' => 0,
            'farmers_merged' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($duplicates as $group) {
            $farmerIds = explode(',', $group->farmer_ids);
            $keepId = min($farmerIds); // Keep the lowest ID as the survivor
            $dropIds = array_filter($farmerIds, fn ($id) => $id != $keepId);

            $this->info("Group: \"{$group->norm_first} {$group->norm_last}\" (Partner ID: {$group->partner_id})");
            $this->line("  Keep: {$keepId}, Merge: ".implode(', ', $dropIds));

            if (count($dropIds) === 0) {
                $this->warn('  Skipped: no duplicates to merge');
                $summary['skipped']++;
                $this->newLine();

                continue;
            }

            $summary['groups_processed']++;
            $summary['farmers_merged'] += count($dropIds);

            if (! $dryRun) {
                try {
                    DB::transaction(function () use ($keepId, $dropIds) {
                        // Handle farmer_project pivot table (unique constraint on farmer_id, project_id)
                        $this->handleFarmerProject($keepId, $dropIds);

                        // Handle addresses (morphMany)
                        $this->handleAddresses($keepId, $dropIds);

                        // Re-point all other foreign keys
                        foreach ($this->relatedForeignKeys as $modelClass => $foreignKey) {
                            $modelClass::whereIn($foreignKey, $dropIds)
                                ->update([$foreignKey => $keepId]);
                        }

                        // Backfill missing data from duplicates to survivor
                        $this->backfillFarmerData($keepId, $dropIds);

                        // Delete the duplicate farmers
                        Farmer::whereIn('id', $dropIds)->delete();
                    });

                    $this->line('  ✓ Merged successfully');
                } catch (\Exception $e) {
                    $this->error('  ✗ Failed: '.$e->getMessage());
                    $summary['failed']++;
                    $summary['farmers_merged'] -= count($dropIds); // Subtract since they weren't merged
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
        $this->line("  Farmers merged: {$summary['farmers_merged']}");
        $this->line("  Skipped: {$summary['skipped']}");
        $this->line("  Failed: {$summary['failed']}");

        return self::SUCCESS;
    }

    private function handleFarmerProject(int $keepId, array $dropIds): void
    {
        // Find project_ids that exist for both the keep farmer and the drop farmers
        $duplicateProjectIds = DB::table('farmer_project')
            ->where('farmer_id', $keepId)
            ->whereIn('project_id', function ($query) use ($dropIds) {
                $query->select('project_id')
                    ->from('farmer_project')
                    ->whereIn('farmer_id', $dropIds);
            })
            ->pluck('project_id');

        // Delete the duplicate associations from the drop farmers
        if ($duplicateProjectIds->isNotEmpty()) {
            DB::table('farmer_project')
                ->whereIn('farmer_id', $dropIds)
                ->whereIn('project_id', $duplicateProjectIds)
                ->delete();
        }

        // Repoint remaining
        DB::table('farmer_project')
            ->whereIn('farmer_id', $dropIds)
            ->update(['farmer_id' => $keepId]);
    }

    private function handleAddresses(int $keepId, array $dropIds): void
    {
        // Get all addresses from drop farmers
        $addresses = DB::table('addresses')
            ->where('addressable_type', 'farmer')
            ->whereIn('addressable_id', $dropIds)
            ->get();

        foreach ($addresses as $address) {
            // Skip empty addresses
            if (empty($address->street) || $address->street === 'N/A' ||
                empty($address->barangay) || $address->barangay === 'N/A' ||
                empty($address->city) || $address->city === 'N/A') {
                DB::table('addresses')->where('id', $address->id)->delete();

                continue;
            }

            // Check if an identical address already exists for the keep farmer
            $existing = DB::table('addresses')
                ->where('addressable_type', 'farmer')
                ->where('addressable_id', $keepId)
                ->where('category', $address->category)
                ->where('city', $address->city)
                ->where('barangay', $address->barangay)
                ->first();

            if ($existing) {
                // Delete duplicate
                DB::table('addresses')->where('id', $address->id)->delete();
            } else {
                // Repoint to keep farmer
                DB::table('addresses')
                    ->where('id', $address->id)
                    ->update(['addressable_id' => $keepId]);
            }
        }
    }

    private function backfillFarmerData(int $keepId, array $dropIds): void
    {
        DB::update('
            UPDATE farmers f
            LEFT JOIN farmers d1 ON d1.id = ?
            LEFT JOIN farmers d2 ON d2.id = ?
            SET f.operation_in_charge = COALESCE(f.operation_in_charge, d1.operation_in_charge, d2.operation_in_charge),
                f.photo = COALESCE(f.photo, d1.photo, d2.photo),
                f.farm_name = COALESCE(f.farm_name, d1.farm_name, d2.farm_name),
                f.metadata = COALESCE(f.metadata, d1.metadata, d2.metadata)
            WHERE f.id = ?
        ', [$dropIds[0] ?? null, $dropIds[1] ?? null, $keepId]);
    }
}
