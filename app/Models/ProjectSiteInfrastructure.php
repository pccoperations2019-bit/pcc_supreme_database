<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProjectSiteInfrastructure extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function processingStaff(): HasMany
    {
        return $this->hasMany(ProjectSiteProcessingStaff::class, 'project_site_infrastructure_id');
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function casts(): array
    {
        return [
            'start_of_construction' => 'date',
            'target_date_for_completion' => 'date',
            'date_completed' => 'date',
            'date_inaugurated' => 'date',
            'percentage_of_progress' => 'integer',
            'metadata' => 'array',
        ];
    }
}
