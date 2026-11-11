<?php

namespace App\Models;

use App\Enums\ProgramEnum;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterProductionGoal extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    protected function casts(): array
    {
        return [
            'program' => ProgramEnum::class,
            'milk_production_goal' => 'decimal:2',
            'meat_meat_equivalent_goal' => 'decimal:2',
            'manure_based_goal' => 'decimal:2',
            'hide_production_goals' => 'decimal:2',
        ];
    }
}
