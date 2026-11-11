<?php

namespace App\Models;

use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Models\Concerns\LogsAuditTrail;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use LogsAuditTrail;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProductCategoryEnum::class,
            'sub_category' => ProductSubCategoryEnum::class,
            'product_type' => 'string',
        ];
    }

    public function centers()
    {
        return $this->belongsToMany(Center::class, 'center_product');
    }

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_product');
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
}
