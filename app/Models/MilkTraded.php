<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkTraded extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
    ];

    public function milkProduction(): BelongsTo
    {
        return $this->belongsTo(MilkProduction::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime:Y-m-d',
        ];
    }
}
