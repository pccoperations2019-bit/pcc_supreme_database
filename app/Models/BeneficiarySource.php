<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiarySource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'integer',
        ];
    }
}
