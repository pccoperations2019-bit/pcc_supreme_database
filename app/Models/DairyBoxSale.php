<?php

namespace App\Models;

use App\Enums\MilkProductionWeekEnum;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DairyBoxSale extends Model
{
    use HasFactory;
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public static function booted()
    {
        static::creating(function (DairyBoxSale $sale) {
            $dateForMonth = $sale->period_start;

            if ($dateForMonth) {
                $sale->year = (int) $dateForMonth->format('Y');
                $sale->month = (int) $dateForMonth->format('m');
                $sale->quarter = ceil((int) $dateForMonth->format('m') / 3);
            }

            $dateForWeek = $sale->period_start;

            if ($dateForWeek) {
                $sale->week = MilkProductionWeekEnum::fromDate($dateForWeek);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'year' => 'integer',
            'week' => MilkProductionWeekEnum::class,
        ];
    }

    public function dairyBox(): BelongsTo
    {
        return $this->belongsTo(DairyBox::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    protected function scopeGlobalSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->whereHas('dairyBox', function ($dairyBoxQuery) use ($search) {
                $dairyBoxQuery->where('dairy_box_id', 'LIKE', "%{$search}%")
                    ->orWhere('business_name', 'LIKE', "%{$search}%");
            })
                ->orWhereHas('partner', fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
                ->orWhereHas('center', fn ($q) => $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%"));
        });
    }
}
