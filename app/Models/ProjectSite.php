<?php

namespace App\Models;

use App\Enums\Casts\BackedEnumStringCast;
use App\Enums\ValueChainStatusEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ProjectSite extends Model
{
    use HasFactory;
    use LogsAuditTrail;

    // Carries a center_id, so every listing can be narrowed with visibleTo().
    use ScopedToCenter;

    protected $guarded = [
        'id',
        'productions',
        'processing_infrastructure',
        'equipment',
        'processing_staff',
        'marketing_staff',
        'marketing_infrastructure',
        'training_records',
        'documents',
        'legal_documents',
        'budget_utilization',
    ];

    /**
     * Build the next project site code for a program/year, e.g. "CBIN-2026-0001".
     *
     * Numbering is scoped per program and year, so each program restarts at
     * 0001 every year. The sequential counter is backed by the `sequences`
     * table to stay unique under concurrent creates.
     */
    public static function generateCode(string $program, ?int $year = null): string
    {
        $year = $year ?: (int) now()->year;
        $prefix = strtoupper($program).'-'.$year;

        $latestCode = static::query()
            ->where('project_site_code', 'like', "{$prefix}-%")
            ->orderByDesc('project_site_code')
            ->value('project_site_code');

        if (is_string($latestCode) && $latestCode !== '') {
            $tailValue = (int) substr($latestCode, strrpos($latestCode, '-') + 1);
            if ($tailValue > 0) {
                Sequence::ensureAtLeast($prefix, $tailValue);
            }
        }

        $next = Sequence::next($prefix);

        return $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'production_partner_id');
    }

    public function processingPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'processing_partner_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(ProjectSiteProduction::class);
    }

    public function constructionDetails(): HasMany
    {
        return $this->hasMany(ProjectSiteConstructionDetail::class);
    }

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(ProjectSiteTrainingRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectSiteDocument::class);
    }

    public function infrastructure(): HasMany
    {
        return $this->hasMany(ProjectSiteInfrastructure::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ProjectSiteEquipment::class);
    }

    public function budgetUtilization(): HasMany
    {
        return $this->hasMany(ProjectSiteBudgetUtilization::class);
    }

    public function siteAnimals(): HasMany
    {
        return $this->hasMany(SiteAnimal::class);
    }

    public function siteLegalDocuments(): HasMany
    {
        return $this->hasMany(SiteLegalDocument::class);
    }

    public function siteConstructionDetails(): HasMany
    {
        return $this->hasMany(SiteConstructionDetail::class);
    }

    public function processingStaff(): HasMany
    {
        return $this->hasMany(ProjectSiteProcessingStaff::class);
    }

    public function marketingStaff(): HasMany
    {
        return $this->hasMany(ProjectSiteMarketingStaff::class);
    }

    public function marketingInfrastructure(): HasMany
    {
        return $this->hasMany(ProjectSiteMarketingInfrastructure::class);
    }

    public function dairyBoxes(): HasMany
    {
        return $this->hasMany(ProjectSiteDairyBox::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function casts(): array
    {
        return [
            'value_chain_status' => BackedEnumStringCast::class.':'.ValueChainStatusEnum::class,
            'inauguration_date' => 'date',
            'metadata' => 'array',
        ];
    }
}
