<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSiteProcessingStaff extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(ProjectSiteInfrastructure::class, 'project_site_infrastructure_id');
    }

    protected function casts(): array
    {
        return [
            'number_of_staff' => 'integer',
        ];
    }
}
