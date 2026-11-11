<?php

namespace App\Models;

use App\Casts\ProjectProgramEnumCast;
use App\Casts\RegionEnumCast;
use App\Enums\CivilStatusEnum;
use App\Enums\EducationalAttainmentEnum;
use App\Enums\EntrepreneurTypeEnum;
use App\Enums\IncomeSourceEnum;
use App\Enums\ManagementSystemEnum;
use App\Enums\MembershipTypeEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\TvTypeEnum;
use App\Models\Concerns\HasFriendlyCode;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

class Farmer extends Model
{
    use HasFactory, LogsAuditTrail;
    use HasFriendlyCode;
    use ScopedToCenter;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'photo_url',
        'geotag_image_url',
        'entrepreneur_image_url',
        'enterprise_image_url',
    ];

    protected function scopeGlobalSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereRaw(
                    "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                    ["%{$search}%"]
                )
                ->orWhereRaw(
                    "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_name, ''), ' ', COALESCE(last_name, ''), ' ', COALESCE(extension_name, '')) LIKE ?",
                    ["%{$search}%"]
                );
        });
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'farmer_project');
    }

    public static function generateCodeForCenter(string $centerCode): string
    {
        $prefix = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', $centerCode) ?? ''));
        $prefix = $prefix !== '' ? $prefix : 'FARMER';

        $latestCode = static::query()
            ->where('code', 'like', "{$prefix}-%")
            ->orderByDesc('code')
            ->value('code');

        if (is_string($latestCode) && $latestCode !== '') {
            $tailValue = (int) substr($latestCode, -5);
            if ($tailValue > 0) {
                Sequence::ensureAtLeast($prefix, $tailValue);
            }
        }

        $next = Sequence::next($prefix);
        $tail = str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$tail}";
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function getAgeAttribute(): ?int
    {
        if (! $this->birthday) {
            return null;
        }

        return Date::parse($this->birthday)->age;
    }

    protected function casts(): array
    {
        return [
            'civil_status' => CivilStatusEnum::class,
            'type_of_entrepreneur' => EntrepreneurTypeEnum::class,
            'educational_attainment' => EducationalAttainmentEnum::class,
            'management_system' => ManagementSystemEnum::class,
            'main_source_of_income' => IncomeSourceEnum::class,
            'secondary_source_of_income' => IncomeSourceEnum::class,
            'tertiary_source_of_income' => IncomeSourceEnum::class,
            'quaternary_source_of_income' => IncomeSourceEnum::class,
            'farm_region' => RegionEnumCast::class,
            'project_category' => ProjectProgramEnumCast::class,
            'tv_type' => TvTypeEnum::class,
            'membership_in_organization' => MembershipTypeEnum::class,
            'is_active' => 'boolean',
            'is_pwd' => 'boolean',
            'is_4ps' => 'boolean',
            'is_ips' => 'boolean',
            'is_indigent' => 'boolean',
            'involved_in_buffalo_farming' => 'boolean',
            'are_you_the_head_of_the_family' => 'boolean',
            'is_drylot' => 'boolean',
            'is_applied_in_the_field' => 'boolean',
            'is_dry_rice_field' => 'boolean',
            'is_dry_vegetable' => 'boolean',
            'is_vermicomposting' => 'boolean',
            'is_pasture_range_paddock' => 'boolean',
            'is_daily_spread' => 'boolean',
            'is_storage_bulking' => 'boolean',
            'is_liquid_slurry' => 'boolean',
            'is_anaerobic_digester' => 'boolean',
            'is_assisted' => 'boolean',
            'date_assisted' => 'date',
            'has_personal_cellphone' => 'boolean',
            'is_twice_a_day_milking' => 'boolean',
            'date_of_interview' => 'date',
            'birthday' => 'date',
            'gps_latitude' => 'float',
            'gps_longitude' => 'float',
            'pcc_assets_latitude' => 'float',
            'pcc_assets_longitude' => 'float',
            'main_annual_net_income' => 'decimal:2',
            'secondary_annual_net_income' => 'decimal:2',
            'tertiary_annual_net_income' => 'decimal:2',
            'quaternary_annual_net_income' => 'decimal:2',
            'avg_net_income' => 'decimal:2',
            'total_net_income' => 'decimal:2',
            'ave_monthly_salary' => 'decimal:2',
            'status' => ProfileStatusEnum::class,
            'metadata' => 'array',
            'other_livestock_details' => 'array',
            'source_of_income_details' => 'array',
            'animal_inventory' => 'array',
            'animal_ownership' => 'array',
            'type_of_farm_business_registration' => 'array',
            'milk_selling_locations' => 'array',
            'assistance_needed' => 'array',
            'daily_milk_production_min' => 'decimal:2',
            'daily_milk_production_max' => 'decimal:2',
            'milk_price_per_liter_min' => 'decimal:2',
            'milk_price_per_liter_max' => 'decimal:2',
            'has_rsbsa' => 'boolean',
        ];
    }

    protected function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return Storage::disk('media')->url($this->photo);
    }

    protected function getGeotagImageUrlAttribute(): ?string
    {
        if (! $this->geotag_image) {
            return null;
        }

        $value = (string) $this->geotag_image;
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('media')->url($value);
    }

    protected function getEntrepreneurImageUrlAttribute(): ?string
    {
        if (! $this->entrepreneur_image) {
            return null;
        }

        $value = (string) $this->entrepreneur_image;
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('media')->url($value);
    }

    protected function getEnterpriseImageUrlAttribute(): ?string
    {
        if (! $this->enterprise_image) {
            return null;
        }

        $value = (string) $this->enterprise_image;
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('media')->url($value);
    }

    public function storePhoto(?UploadedFile $photoFile): ?string
    {
        if (! $photoFile || ! $photoFile->isValid()) {
            return $this->photo;
        }

        if ($this->photo) {
            Storage::disk('media')->delete($this->photo);
        }

        return $photoFile->store('farmers/photos', 'media');
    }

    public function storeGeotagImage(?UploadedFile $imageFile): ?string
    {
        if (! $imageFile || ! $imageFile->isValid()) {
            return $this->geotag_image;
        }

        if ($this->geotag_image && ! str_starts_with((string) $this->geotag_image, 'http')) {
            Storage::disk('media')->delete($this->geotag_image);
        }

        return $imageFile->store('farmers/geotag', 'media');
    }

    public function storeEntrepreneurImage(?UploadedFile $imageFile): ?string
    {
        if (! $imageFile || ! $imageFile->isValid()) {
            return $this->entrepreneur_image;
        }

        if ($this->entrepreneur_image && ! str_starts_with((string) $this->entrepreneur_image, 'http')) {
            Storage::disk('media')->delete($this->entrepreneur_image);
        }

        return $imageFile->store('farmers/entrepreneur', 'media');
    }

    public function storeEnterpriseImage(?UploadedFile $imageFile): ?string
    {
        if (! $imageFile || ! $imageFile->isValid()) {
            return $this->enterprise_image;
        }

        if ($this->enterprise_image && ! str_starts_with((string) $this->enterprise_image, 'http')) {
            Storage::disk('media')->delete($this->enterprise_image);
        }

        return $imageFile->store('farmers/enterprise', 'media');
    }
}
