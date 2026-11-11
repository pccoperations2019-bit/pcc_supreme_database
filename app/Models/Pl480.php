<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pl480 extends Model
{
    use LogsAuditTrail;

    protected $table = 'pl480';

    protected $guarded = ['id'];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'value' => 'decimal:2',
            'center_id' => 'integer',
            'partner_id' => 'integer',
        ];
    }
}
