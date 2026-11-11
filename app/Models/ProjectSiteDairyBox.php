<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSiteDairyBox extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'project_site_dairy_box';

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function dairyBox(): BelongsTo
    {
        return $this->belongsTo(DairyBox::class);
    }
}
