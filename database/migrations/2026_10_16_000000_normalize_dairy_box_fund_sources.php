<?php

use App\Support\Sanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $columns = [
        'fund_source_outlet',
        'fund_source_equipment',
        'fund_source_processing_plant',
    ];

    public function up(): void
    {
        $this->normalizeColumns();
    }

    public function down(): void
    {
        // Canonicalizing whitespace is not reversible (e.g. "CBIN 2019" -> "CBIN-2019").
        // The previous values are preserved in the database backups.
    }

    private function normalizeColumns(): void
    {
        DB::table('dairy_boxes')
            ->select(['id', ...$this->columns])
            ->where(function ($query) {
                foreach ($this->columns as $column) {
                    $query->orWhereNotNull($column);
                }
            })
            ->orderBy('id')
            ->chunkById(500, function ($dairyBoxes) {
                foreach ($dairyBoxes as $dairyBox) {
                    $updates = [];

                    foreach ($this->columns as $column) {
                        $current = $dairyBox->{$column};
                        $normalized = Sanitizer::parseProjectInvolvement($current);

                        if ($normalized !== $current) {
                            $updates[$column] = $normalized;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('dairy_boxes')
                            ->where('id', $dairyBox->id)
                            ->update($updates);
                    }
                }
            });
    }
};
