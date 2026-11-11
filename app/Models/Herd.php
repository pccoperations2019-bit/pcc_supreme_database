<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use App\Models\Concerns\ScopedToCenter;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Herd extends Model
{
    use HasFactory, LogsAuditTrail, ScopedToCenter;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public static function generateCode(string $herdName): string
    {
        $prefix = strtoupper(substr(preg_replace('/\s+/', '', $herdName), 0, 3));

        $next = Sequence::next($prefix);
        $tail = str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$tail}";
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }
}
