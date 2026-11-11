<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;

class MilkUtilizationMonthlySummary extends Model
{
    use LogsAuditTrail {
        getActivitylogOptions as private sharedActivitylogOptions;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->sharedActivitylogOptions()
            ->logFillable()
            ->logExcept([...$this->auditExcludedAttributes, 'month']);
    }

    protected $table = 'milk_utilization_monthly_summary';

    protected $fillable = [
        'center_id',
        'partner_id',
        'program',
        'year',
        'month',
        'lactating_animals',
        'production',
        'received',
        'fed_to_calves',
        'value_added_products',
        'milk_feeding',
        'lactating_animals_source',
        'production_source',
        'received_source',
        'fed_to_calves_source',
        'value_added_products_source',
        'milk_feeding_source',
        'notes',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    protected function casts(): array
    {
        return [
            'lactating_animals' => 'decimal:2',
            'production' => 'decimal:2',
            'received' => 'decimal:2',
            'fed_to_calves' => 'decimal:2',
            'value_added_products' => 'decimal:2',
            'milk_feeding' => 'decimal:2',
        ];
    }
}
