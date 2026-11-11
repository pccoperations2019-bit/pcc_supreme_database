<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSiteConstructionDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function dairybox(): BelongsTo
    {
        return $this->belongsTo(DairyBox::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(ProjectSiteProduction::class, 'production_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_establishment' => 'date',
        ];
    }
}
