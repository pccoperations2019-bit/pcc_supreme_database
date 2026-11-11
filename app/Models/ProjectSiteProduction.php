<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSiteProduction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function constructionDetails(): HasMany
    {
        return $this->hasMany(ProjectSiteConstructionDetail::class, 'production_id');
    }

    protected function casts(): array
    {
        return [
            'no_of_animals_to_be_delivered' => 'integer',
            'delivered' => 'integer',
            'no_of_recipients' => 'integer',
            'metadata' => 'array',
        ];
    }
}
