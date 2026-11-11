<?php

namespace App\Models;

use App\Enums\Casts\BackedEnumStringCast;
use App\Enums\PartnerCategoryEnum;
use App\Enums\PartnerPartnershipTypeEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Concerns\HasFriendlyCode;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Partner extends Model
{
    use HasFactory, HasFriendlyCode, LogsAuditTrail, ScopedToCenter;

    protected $appends = [
        'logo_url',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'partner_project');
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    public function consolidationUnits(): HasMany
    {
        return $this->hasMany(ConsolidationUnit::class);
    }

    public function processingUnits(): HasMany
    {
        return $this->hasMany(ProcessingUnit::class);
    }

    public function marketingUnits(): HasMany
    {
        return $this->hasMany(MarketingUnit::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'partner_product');
    }

    public function dairyBoxes(): HasMany
    {
        return $this->hasMany(DairyBox::class);
    }

    public function milkProductions(): HasMany
    {
        return $this->hasMany(MilkProduction::class);
    }

    public function milkSales(): HasMany
    {
        return $this->hasMany(MilkSale::class);
    }

    public function milkTradeds(): HasMany
    {
        return $this->hasMany(MilkTraded::class);
    }

    public function milkConsolidations(): HasMany
    {
        return $this->hasMany(MilkConsolidation::class);
    }

    public function milkFeedingPrograms(): HasMany
    {
        return $this->hasMany(MilkFeedingProgram::class);
    }

    protected function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk('media')->url($this->logo);
    }

    public function storeLogo(?UploadedFile $logoFile): ?string
    {
        if (! $logoFile || ! $logoFile->isValid()) {
            return $this->logo;
        }

        if ($this->logo) {
            Storage::disk('media')->delete($this->logo);
        }

        return $logoFile->store('partners/logos', 'media');
    }

    protected function casts(): array
    {
        return [
            'category' => BackedEnumStringCast::class.':'.PartnerCategoryEnum::class,
            'partnership_type' => BackedEnumStringCast::class.':'.PartnerPartnershipTypeEnum::class,
            'status' => ProfileStatusEnum::class,
            'program' => ProgramEnum::class,
            'metadata' => 'array',
            'animal_inventory' => 'array',
            'date_assisted' => 'date',
        ];
    }
}
