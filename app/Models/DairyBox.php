<?php

namespace App\Models;

use App\Enums\Casts\BackedEnumStringCast;
use App\Enums\DairyBoxEnum;
use App\Enums\LandOwnershipEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use App\Support\Sanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DairyBox extends Model
{
    use HasFactory, LogsAuditTrail;

    // Carries a center_id, so reference dropdowns can be narrowed with visibleTo().
    use ScopedToCenter;

    protected $appends = [
        'logo_url',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'land_ownership' => BackedEnumStringCast::class.':'.LandOwnershipEnum::class,
            'launched_date' => 'date',
            'status' => DairyBoxEnum::class,
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(DairyBoxSale::class, 'dairy_box_id', 'dairy_box_id');
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk('media')->url($this->logo);
    }

    /**
     * Fund sources reference a project's `project_involvement` tag, so store them
     * in the same canonical form the options list uses. This keeps the create,
     * update and Excel import paths consistent.
     */
    protected function setFundSourceOutletAttribute(mixed $value): void
    {
        $this->attributes['fund_source_outlet'] = Sanitizer::parseProjectInvolvement($value);
    }

    protected function setFundSourceEquipmentAttribute(mixed $value): void
    {
        $this->attributes['fund_source_equipment'] = Sanitizer::parseProjectInvolvement($value);
    }

    protected function setFundSourceProcessingPlantAttribute(mixed $value): void
    {
        $this->attributes['fund_source_processing_plant'] = Sanitizer::parseProjectInvolvement($value);
    }

    protected function scopeGlobalSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('dairy_box_id', 'LIKE', "%{$search}%")
                ->orWhere('business_name', 'LIKE', "%{$search}%")
                ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                    $partnerQuery->where('name', 'LIKE', "%{$search}%");
                });
        });
    }

    public function storeLogo(?UploadedFile $logoFile): ?string
    {
        if (! $logoFile || ! $logoFile->isValid()) {
            return $this->logo;
        }

        if ($this->logo) {
            Storage::disk('media')->delete($this->logo);
        }

        return $logoFile->store('dairy-boxes/logos', 'media');
    }
}
