<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkCost extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
    ];

    public function milkProduction(): BelongsTo
    {
        return $this->belongsTo(MilkProduction::class);
    }

    protected function getTotalStaffCountAttribute()
    {
        return $this->number_of_staff_male + $this->number_of_staff_female;
    }
}
