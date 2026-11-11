<?php

namespace App\Models;

use App\Enums\MilkingTimeEnum;
use App\Enums\MilkProductionWeekEnum;
use App\Enums\ProductionStatusEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MilkProduction extends Model
{
    use LogsAuditTrail;
    use ScopedToCenter;

    protected $guarded = [
        'id',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function herd(): BelongsTo
    {
        return $this->belongsTo(Herd::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(MilkSale::class);
    }

    public function traded(): HasOne
    {
        return $this->hasOne(MilkTraded::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(MilkUsage::class);
    }

    public function wastage(): HasMany
    {
        return $this->hasMany(MilkWastage::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(MilkCost::class);
    }

    protected function scopeGlobalSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->whereHas('partner', fn (Builder $partnerQuery) => $partnerQuery
                ->where('name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%"))
                ->orWhereHas('farmer', fn (Builder $farmerQuery) => $farmerQuery
                    ->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%"))
                ->orWhereHas('center', fn (Builder $centerQuery) => $centerQuery
                    ->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%"));
        });
    }

    public static function booted()
    {
        static::creating(function (MilkProduction $production) {

            $dateForMonth = $production->period_start ?? $production->transaction_date;

            if ($dateForMonth) {
                $production->year = $dateForMonth->year;
                $production->month = $dateForMonth->month;
                $production->quarter = ceil($dateForMonth->month / 3);
            }
            $dateForWeek = $production->period_start ?? $production->transaction_date;

            if ($dateForWeek) {
                $production->week = MilkProductionWeekEnum::fromDate($dateForWeek);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime:Y-m-d',
            'period_start' => 'datetime:Y-m-d',
            'period_end' => 'datetime:Y-m-d',
            'collection_date' => 'datetime:Y-m-d',
            'milking_times' => MilkingTimeEnum::class,
            'status' => ProductionStatusEnum::class,
            'week' => MilkProductionWeekEnum::class,
        ];
    }
}
