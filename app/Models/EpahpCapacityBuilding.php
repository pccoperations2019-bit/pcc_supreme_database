<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpahpCapacityBuilding extends Model
{
    use LogsAuditTrail;

    protected $table = 'epahp_capacity_building';

    protected $guarded = ['id'];

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

    public function projectSite(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'number_of_cooperatives' => 'integer',
            'number_of_farmers_trained' => 'integer',
            'training_conducted' => 'integer',
            'pcc_budget_allocated' => 'decimal:2',
            'center_id' => 'integer',
            'partner_id' => 'integer',
            'farmer_id' => 'integer',
            'project_site_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
