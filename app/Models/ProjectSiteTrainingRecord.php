<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSiteTrainingRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class);
    }

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    protected function casts(): array
    {
        return [
            'project_orientation_date' => 'date',
            'project_orientation_manual_pax' => 'integer',
            'strategic_planning_date' => 'date',
            'strategic_planning_manual_pax' => 'integer',
            'social_preparation_training_date' => 'date',
            'social_preparation_training_manual_pax' => 'integer',
            'forage_production_training_date' => 'date',
            'forage_production_training_manual_pax' => 'integer',
            'silage_making_training_date' => 'date',
            'silage_making_training_manual_pax' => 'integer',
            'animal_health_training_date' => 'date',
            'animal_health_training_manual_pax' => 'integer',
            'breeding_management_training_date' => 'date',
            'breeding_management_training_manual_pax' => 'integer',
            'milk_handling_training_date' => 'date',
            'milk_handling_training_manual_pax' => 'integer',
            'milk_processing_training_date' => 'date',
            'milk_processing_training_manual_pax' => 'integer',
            'animal_husbandry_training_date' => 'date',
            'animal_husbandry_training_manual_pax' => 'integer',
            'organic_fertilizer_training_date' => 'date',
            'organic_fertilizer_training_manual_pax' => 'integer',
            'cooperative_management_training_date' => 'date',
            'cooperative_management_training_manual_pax' => 'integer',
            'fls_launching_date' => 'date',
            'fls_launching_manual_pax' => 'integer',
            'fls_buffalo_training_date' => 'date',
            'fls_buffalo_training_manual_pax' => 'integer',
            'fls_date' => 'date',
            'fls_manual_pax' => 'integer',
            'metadata' => 'array',
        ];
    }
}
