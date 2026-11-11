<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeatProduction extends Model
{
    use LogsAuditTrail;

    protected $guarded = ['id'];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    protected function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->when(
            $user && $user->isCenterScoped(),
            fn ($q) => $q->where('center_id', $user->center_id)
        );
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
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

    public static function booted()
    {
        static::creating(function (self $production) {
            if ($production->month) {
                $production->quarter = (int) ceil($production->month / 3);
            }
        });

        static::updating(function (self $production) {
            if ($production->isDirty('month')) {
                $production->quarter = (int) ceil($production->month / 3);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'quarter' => 'integer',
            'monthly_data' => 'array',
        ];
    }
}
