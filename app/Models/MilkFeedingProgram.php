<?php

namespace App\Models;

use App\Enums\Casts\BackedEnumStringCast;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Enums\MilkFeedingStatusEnum;
use App\Enums\MilkSubcategoryEnum;
use App\Enums\MilkTypeEnum;
use App\Enums\ModeOfProcurementEnum;
use App\Enums\ProgramEnum;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MilkFeedingProgram extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
    ];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function scopeGlobalSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('mode_of_procurement', 'LIKE', "%{$search}%")
                ->orWhereHas('address', function ($addressQuery) use ($search) {
                    $addressQuery->where('province', 'LIKE', "%{$search}%")
                        ->orWhere('city', 'LIKE', "%{$search}%")
                        ->orWhere('region', 'LIKE', "%{$search}%")
                        ->orWhere('district', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('center', function ($centerQuery) use ($search) {
                    $centerQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('partner', function ($partnerQuery) use ($search) {
                    $partnerQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                });

            $procurementLabels = ModeOfProcurementEnum::labels();
            $matchingValues = [];

            foreach ($procurementLabels as $value => $label) {
                if (stripos($label, $search) !== false) {
                    $matchingValues[] = $value;
                }
            }

            if ($matchingValues !== []) {
                $q->orWhereIn('mode_of_procurement', $matchingValues);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_started' => 'datetime:Y-m-d',
            'date_completed' => 'datetime:Y-m-d',
            'moa_signing_date' => 'datetime:Y-m-d',
            'fund_transfer_date' => 'datetime:Y-m-d',
            'liquidation_date' => 'datetime:Y-m-d',
            'status' => MilkFeedingStatusEnum::class,
            'milk_type' => MilkTypeEnum::class,
            'milk_subcategory' => MilkSubcategoryEnum::class,
            'funded_by' => BackedEnumStringCast::class.':'.MilkFeedingProgramFunderEnum::class,
            'mode_of_procurement' => BackedEnumStringCast::class.':'.ModeOfProcurementEnum::class,
            'program' => BackedEnumStringCast::class.':'.ProgramEnum::class,
        ];
    }
}
