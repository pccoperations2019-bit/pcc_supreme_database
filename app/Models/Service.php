<?php

namespace App\Models;

use App\Enums\ServiceProgramEnum;
use App\Enums\ValueChainSegmentEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function centers()
    {
        return $this->belongsToMany(Center::class, 'center_service');
    }

    protected function scopeGlobalSearch($query, $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('simplified_code', 'LIKE', "%{$search}%")
                ->orWhere('mfo_code', 'LIKE', "%{$search}%");
        });
    }

    public static function generateCode(string $category, ?string $subCategory = ''): string
    {
        $prefix = '';

        if (Str::of($category)->explode(' ')->count() >= 3) {
            $words = Str::of($category)->explode(' ');
            foreach ($words as $word) {
                $prefix .= Str::of($word)->upper()->substr(0, 1);
            }
        } else {
            $prefix .= Str::of($category)->upper()->substr(0, 3);
        }

        if (! in_array($subCategory, [null, '', '0'], true)) {
            $prefix .= '-';
        }
        if (Str::of($subCategory)->explode(' ')->count() >= 3) {
            $words = Str::of($subCategory)->explode(' ');
            foreach ($words as $word) {
                $prefix .= Str::of($word)->upper()->substr(0, 1);
            }
        } elseif ($subCategory !== null && $subCategory !== '') {
            $prefix .= Str::of($subCategory)->upper()->substr(0, 3);
        }

        $next = Sequence::next($prefix);
        $tail = str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$tail}";
    }

    protected function casts(): array
    {
        return [
            'program' => ServiceProgramEnum::class,
            'value_chain_segment' => ValueChainSegmentEnum::class,
            'category' => 'array',
            'sub_category' => 'array',
            'type' => 'array',
        ];
    }
}
