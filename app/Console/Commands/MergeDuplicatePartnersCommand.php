<?php

namespace App\Console\Commands;

use App\Models\Partner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MergeDuplicatePartnersCommand extends Command
{
    protected $signature = 'app:merge-duplicates
                            {--dry-run : Simulate without actually making changes}
                            {--table-prefix= : Prefix for related tables (e.g., "cbu_")}
                            {--min-confidence=75 : Minimum confidence to include group}';

    protected $description = 'Automatically detect and merge duplicate partner records with fuzzy matching';

    protected $relatedTables = [
        'farmers',
        'milk_feeding_programs',
        'milk_productions',
        'milk_sales',
    ];

    public function handle()
    {
        $this->info('🔍 Scanning for duplicate partners...');

        $duplicateGroups = $this->findDuplicateGroups();

        if ($duplicateGroups === []) {
            $this->info('✅ No duplicates found!');

            return 0;
        }

        $this->info("\n📊 Found ".count($duplicateGroups)." duplicate groups:\n");

        foreach ($duplicateGroups as $group) {
            $centerInfo = $group['center_id'] ? " (Center: {$group['center_id']})" : '';

            $this->line(
                "  • {$group['name']}{$centerInfo} ".
                "[Confidence: {$group['confidence']}%] ".
                '- '.count($group['ids']).' duplicates (IDs: '.implode(', ', $group['ids']).')'
            );

            foreach ($group['matches'] as $match) {
                if ($match['confidence'] === 100) {
                    continue;
                }
                $this->line("     ↳ ID {$match['id']} ({$match['confidence']}%)");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn("\n⚠️ DRY RUN - No changes will be made");

            return 0;
        }

        if (! $this->confirm("\n⚠️ Proceed with merging? This cannot be undone!")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->mergeDuplicates($duplicateGroups);

        return 0;
    }

    /**
     * Find duplicate groups per center
     */
    protected function findDuplicateGroups(): array
    {
        $partnersByCenter = Partner::whereNotNull('name')
            ->where('name', '!=', '')
            ->get()
            ->groupBy('center_id');

        $groups = [];
        $processedIds = [];
        $minConfidence = (int) $this->option('min-confidence');

        foreach ($partnersByCenter as $centerId => $partners) {
            foreach ($partners as $partner) {

                if (in_array($partner->id, $processedIds)) {
                    continue;
                }

                $similarData = $this->findSimilarPartners($partner, $partners);

                $similarIds = collect($similarData)->pluck('id')->toArray();

                if (count($similarIds) <= 1) {
                    continue;
                }

                $avgConfidence = collect($similarData)->avg('confidence');

                if ($avgConfidence < $minConfidence) {
                    continue;
                }

                $masterId = $this->determineMasterId($similarIds, $partners);

                $groups[] = [
                    'name' => $this->normalizeName($partner->name),
                    'master_id' => $masterId,
                    'ids' => array_diff($similarIds, [$masterId]),
                    'all_ids' => $similarIds,
                    'center_id' => $centerId,
                    'confidence' => round($avgConfidence, 2),
                    'matches' => $similarData,
                ];

                $processedIds = array_merge($processedIds, $similarIds);
            }
        }

        return $groups;
    }

    /**
     * FIND SIMILAR with partner_code RULE (IMPORTANT)
     */
    protected function findSimilarPartners($partner, $allPartners): array
    {
        $normalizedName = $this->normalizeName($partner->name);

        if (strlen($normalizedName) <= 3) {
            return [['id' => $partner->id, 'confidence' => 100]];
        }

        $similar = [
            ['id' => $partner->id, 'confidence' => 100],
        ];

        foreach ($allPartners as $other) {

            if ($other->id === $partner->id) {
                continue;
            }

            if (
                ! empty($partner->partner_code) &&
                ! empty($other->partner_code) &&
                $partner->partner_code !== $other->partner_code
            ) {
                continue;
            }

            $otherNormalized = $this->normalizeName($other->name);

            $score = $this->calculateConfidence($normalizedName, $otherNormalized);

            if ($score['confidence'] >= 75) {
                $similar[] = [
                    'id' => $other->id,
                    'confidence' => $score['confidence'],
                ];
            }
        }

        return $similar;
    }

    /**
     * Normalize names for matching
     */
    protected function normalizeName(string $name): string
    {
        $name = preg_replace('/\s*\(.*?\)\s*/', '', $name);
        $name = strtoupper($name);

        $name = preg_replace('/\b(FARMERS?|ASSOCIATION|ASSN|COOPERATIVE|COOP|MPC|INC|LTD)\b/', '', $name);
        $name = preg_replace('/[^A-Z0-9]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Confidence scoring engine
     */
    protected function calculateConfidence(string $a, string $b): array
    {
        $tokenScore = $this->tokenSimilarity($a, $b);

        $sorted1 = collect(explode(' ', $a))->sort()->implode(' ');
        $sorted2 = collect(explode(' ', $b))->sort()->implode(' ');

        $orderMatch = $sorted1 === $sorted2 ? 100 : 0;

        $distance = levenshtein($a, $b);
        $maxLength = max(strlen($a), strlen($b));

        $levScore = $maxLength > 0
            ? (1 - ($distance / $maxLength)) * 100
            : 0;

        $confidence = (
            ($tokenScore * 0.5) +
            ($levScore * 0.3) +
            ($orderMatch * 0.2)
        );

        return [
            'confidence' => round($confidence, 2),
        ];
    }

    /**
     * Token-based similarity (main engine)
     */
    protected function tokenSimilarity(string $a, string $b): float
    {
        $tokensA = collect(explode(' ', $a))->filter();
        $tokensB = collect(explode(' ', $b))->filter();

        if ($tokensA->isEmpty() || $tokensB->isEmpty()) {
            return 0;
        }

        $intersection = $tokensA->intersect($tokensB)->count();
        $union = $tokensA->merge($tokensB)->unique()->count();

        return ($intersection / $union) * 100;
    }

    /**
     * Master selection logic
     */
    protected function determineMasterId(array $ids, $partners): int
    {
        $scores = [];

        foreach ($ids as $id) {
            $partner = $partners->firstWhere('id', $id);
            $score = 0;

            if (! empty($partner->metadata) && $partner->metadata !== '[]') {
                $score += 100;
            }
            if (! empty($partner->contact_name)) {
                $score += 50;
            }
            if (! empty($partner->contact_email) || ! empty($partner->mobile_number)) {
                $score += 30;
            }
            if ($partner->total_composition > 0 || $partner->total_farmers > 0) {
                $score += 20;
            }
            if (! empty($partner->description)) {
                $score += 10;
            }

            $scores[$id] = $score;
        }

        arsort($scores);

        return key($scores);
    }

    /**
     * Merge execution (unchanged but safe)
     */
    protected function mergeDuplicates(array $groups): void
    {
        DB::beginTransaction();

        try {
            $totalMerged = 0;
            $totalUpdated = 0;

            foreach ($groups as $group) {

                $masterId = $group['master_id'];
                $duplicateIds = $group['ids'];

                if (empty($duplicateIds)) {
                    continue;
                }

                $this->info("\n🔄 Merging: {$group['name']}");
                $this->line("   Master ID: {$masterId}");
                $this->line('   Duplicates: '.implode(', ', $duplicateIds));

                foreach ($this->relatedTables as $table) {

                    $prefix = $this->option('table-prefix');
                    $fullTable = $prefix ? $prefix.$table : $table;

                    if (! Schema::hasTable($fullTable) || ! Schema::hasColumn($fullTable, 'partner_id')) {
                        continue;
                    }

                    $updated = DB::table($fullTable)
                        ->whereIn('partner_id', $duplicateIds)
                        ->update(['partner_id' => $masterId]);

                    if ($updated > 0) {
                        $this->line("      ✓ Updated {$updated} records in {$fullTable}");
                        $totalUpdated += $updated;
                    }
                }

                $this->mergeJsonData($masterId, $duplicateIds);

                Partner::whereIn('id', $duplicateIds)->delete();

                $totalMerged += count($duplicateIds);
                $this->line('      ✓ Deleted '.count($duplicateIds).' duplicates');
            }

            DB::commit();

            $this->info("\n✅ Merge complete!");
            $this->info("   - {$totalMerged} removed");
            $this->info("   - {$totalUpdated} relations updated");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: '.$e->getMessage());
            Log::error('Merge failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Merge JSON metadata
     */
    protected function mergeJsonData(int $masterId, array $duplicateIds): void
    {
        $master = Partner::find($masterId);
        $masterMetadata = $master->metadata ?? [];

        foreach ($duplicateIds as $dupId) {
            $dup = Partner::find($dupId);
            if (! $dup) {
                continue;
            }

            foreach (($dup->metadata ?? []) as $key => $value) {
                if (! empty($value) && empty($masterMetadata[$key])) {
                    $masterMetadata[$key] = $value;
                }
            }
        }

        $master->metadata = $masterMetadata;
        $master->save();
    }
}
