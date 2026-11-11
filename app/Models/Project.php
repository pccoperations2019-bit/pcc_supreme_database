<?php

namespace App\Models;

use App\Enums\Casts\BackedEnumStringCast;
use App\Enums\ProjectProgramEnum;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function centers()
    {
        return $this->belongsToMany(Center::class, 'center_project');
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function farmers(): BelongsToMany
    {
        return $this->belongsToMany(Farmer::class, 'farmer_project');
    }

    protected function casts(): array
    {
        return [
            'program' => BackedEnumStringCast::class.':'.ProjectProgramEnum::class,
            'is_funded' => 'boolean',
            'project_involvement' => 'array',
        ];
    }
}
