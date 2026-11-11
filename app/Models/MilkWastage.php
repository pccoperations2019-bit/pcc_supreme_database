<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkWastage extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
    ];

    public function milkProduction(): BelongsTo
    {
        return $this->belongsTo(MilkProduction::class);
    }
}
