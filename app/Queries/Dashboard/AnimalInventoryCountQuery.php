<?php

namespace App\Queries\Dashboard;

use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;

class AnimalInventoryCountQuery
{
    /**
     * Sum the "count" values inside the animal_inventory JSON array cells of the
     * given table(s), optionally scoped to a center.
     *
     * For CBED: sums carabaos from FCAs (partners + farmers with is_fca_partner=true)
     *
     * @param  string|array<string>  $tables
     */
    public function execute(
        string|array $tables,
        ?int $centerId = null,
        ?ProgramEnum $program = null
    ): int {
        $tables = is_array($tables) ? $tables : [$tables];

        $total = 0;
        foreach ($tables as $table) {
            if (! in_array($table, ['farmers', 'partners'], true)) {
                continue;
            }

            $scopedColumn = $table === 'centers' ? 'id' : 'center_id';

            $query = DB::table($table)
                ->when($centerId, fn ($query) => $query->where("$table.$scopedColumn", $centerId))
                ->when($program && $table === 'partners', fn ($query) => $query->where('program', $program->value));

            // For farmers, only include those with is_fca_partner=true
            if ($table === 'farmers') {
                $query->where('is_fca_partner', true);
            }

            $total += (int) $query
                ->crossJoin(DB::raw("JSON_TABLE($table.animal_inventory, '\$[*]' COLUMNS (cnt INT PATH '\$.count')) AS jt"))
                ->sum('jt.cnt');
        }

        return $total;
    }
}
