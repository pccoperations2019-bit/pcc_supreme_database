<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkSale extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
    ];

    public function milkProduction(): BelongsTo
    {
        return $this->belongsTo(MilkProduction::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    protected function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->when(
            $user && $user->isCenterScoped(),
            fn ($q) => $q->where('center_id', $user->center_id)
        );
    }

    protected function scopeGlobalSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->whereHas('partner', function ($partnerQuery) use ($search) {
                $partnerQuery->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            })
                ->orWhereHas('farmer', function ($farmerQuery) use ($search) {
                    $farmerQuery->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('center', function ($centerQuery) use ($search) {
                    $centerQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                });
        });
    }

    protected function getTotalSalesValueAttribute()
    {
        return ($this->raw_milk_traded_sales ?? 0)
             + ($this->value_added_products_sales ?? 0)
             + ($this->dairy_box_sales ?? 0);
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime:Y-m-d',
        ];
    }
}
