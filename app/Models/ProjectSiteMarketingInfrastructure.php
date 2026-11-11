<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProjectSiteMarketingInfrastructure extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function dairyBox(): BelongsTo
    {
        return $this->belongsTo(DairyBox::class, 'dairy_box_id');
    }

    public function marketingStaff(): HasMany
    {
        return $this->hasMany(ProjectSiteMarketingStaff::class, 'project_site_marketing_infrastructure_id');
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function casts(): array
    {
        return [
            'launched_date' => 'date',
            'start_of_construction' => 'date',
            'date_of_completion' => 'date',
            'date_inaugurated' => 'date',
            'metadata' => 'array',
        ];
    }
}
