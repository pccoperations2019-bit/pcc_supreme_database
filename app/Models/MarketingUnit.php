<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MarketingUnit extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'center_id',
        'created_at',
        'updated_at',
    ];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function casts(): array
    {
        return [
            'number_of_staff_male' => 'integer',
            'number_of_staff_female' => 'integer',
            'avg_salary_of_staff' => 'decimal:2',
        ];
    }
}
