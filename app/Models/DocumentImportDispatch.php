<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentImportDispatch extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'submission_version',
        'workflow_key',
        'created_at',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
