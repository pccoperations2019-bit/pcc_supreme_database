<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Agency extends Model
{
    use HasFactory, LogsAuditTrail;

    protected $guarded = [
        'id',
        'slug',
        'agency_code',
        'created_at',
        'updated_at',
    ];

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
