<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAnimal extends Model
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
            'number_of_farmers' => 'integer',
            'total_animals_included' => 'integer',
            'pcc_counterpart' => 'integer',
            'total_animals_distributed' => 'integer',
        ];
    }
}
