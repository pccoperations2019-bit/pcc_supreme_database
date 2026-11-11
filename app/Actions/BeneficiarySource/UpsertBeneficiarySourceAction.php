<?php

namespace App\Actions\BeneficiarySource;

use App\Models\BeneficiarySource;

class UpsertBeneficiarySourceAction
{
    public function execute(
        string $code,
        string $name,
        int $year,
        int $amount
    ): BeneficiarySource {
        return BeneficiarySource::updateOrCreate(
            [
                'code' => $code,
                'year' => $year,
            ],
            [
                'name' => $name,
                'amount' => $amount,
            ]
        );
    }
}
