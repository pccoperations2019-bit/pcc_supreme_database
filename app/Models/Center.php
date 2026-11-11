<?php

namespace App\Models;

use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Models\Concerns\HasFriendlyCode;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Center extends Model
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

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'center_project');
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    public function processingUnits(): HasMany
    {
        return $this->hasMany(ProcessingUnit::class);
    }

    public function consolidationUnits(): HasMany
    {
        return $this->hasMany(ConsolidationUnit::class);
    }

    public function marketingUnits(): HasMany
    {
        return $this->hasMany(MarketingUnit::class);
    }

    public static function getValueChainUnitCount(int $centerId): int
    {
        return
            ProductionUnit::where('center_id', $centerId)->count() +
            ProcessingUnit::where('center_id', $centerId)->count() +
            ConsolidationUnit::where('center_id', $centerId)->count() +
            MarketingUnit::where('center_id', $centerId)->count();
    }

    public static function getStaffsCount(int $centerId): int
    {
        return
            ProductionUnit::where('center_id', $centerId)->sum('number_of_staff_female') +
            ProcessingUnit::where('center_id', $centerId)->sum('number_of_staff_male') +
            ConsolidationUnit::where('center_id', $centerId)->sum('number_of_staff_male') +
            MarketingUnit::where('center_id', $centerId)->sum('number_of_staff_male');
    }

    public static function getAvgSalary(int $centerId, int $valueChain = 1): float
    {
        return (
            ProductionUnit::where('center_id', $centerId)->avg('avg_salary_of_staff') +
            ProcessingUnit::where('center_id', $centerId)->avg('avg_salary_of_staff') +
            ConsolidationUnit::where('center_id', $centerId)->avg('avg_salary_of_staff') +
            MarketingUnit::where('center_id', $centerId)->avg('avg_salary_of_staff')) / $valueChain;
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'center_product');
    }

    public function productionGoals(): HasMany
    {
        return $this->hasMany(CenterProductionGoal::class);
    }

    public function salesGoals(): HasMany
    {
        return $this->hasMany(CenterSalesGoal::class);
    }

    public function milkFeedingPrograms(): HasMany
    {
        return $this->hasMany(MilkFeedingProgram::class);
    }

    protected function getCenterColumn(): string
    {
        return 'id';
    }

    protected function scopeExcludingHidden(Builder $query, array $hiddenCodes): Builder
    {
        return $query->whereNotIn('code', $hiddenCodes);
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

        return $logoFile->store('centers/logos', 'media');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'animal_inventory' => 'array',
            'status' => ProfileStatusEnum::class,
            'program' => ProgramEnum::class,
            'date_assisted' => 'date',
        ];
    }
}
