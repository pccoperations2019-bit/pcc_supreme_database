<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSiteBudgetUtilization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'budget_allocated' => 'decimal:2',
            'obligated' => 'decimal:2',
            'disbursed' => 'decimal:2',
            'utilization_percentage' => 'decimal:2',
        ];
    }
}
