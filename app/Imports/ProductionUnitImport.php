<?php

namespace App\Imports;

use App\Actions\Center\UpsertProductionUnitAction;
use App\Enums\HerdTypeEnum;
use App\Enums\ProductionCategoryEnum;
use App\Support\Sanitizer;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ProductionUnitImport implements OnEachRow, WithCalculatedFormulas, WithHeadingRow
{
    public $centerIds = [];

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function onRow(Row $row)
    {
        $avgSalaryValue = $row['avg_monthly_salary'] ?? $row['avg_salary'] ?? null;
        $avgSalary = isset($avgSalaryValue) && is_numeric($avgSalaryValue)
            ? floatval($avgSalaryValue)
            : 0.00;

        resolve(UpsertProductionUnitAction::class)->execute(
            name: $row['farm_name'],
            centerId: $this->centerIds[$row['center_code']],
            withinCenter: isset($row['within_center']) ? filter_var($row['within_center'], FILTER_VALIDATE_BOOLEAN) : null,
            category: ProductionCategoryEnum::tryFrom(Str::of($row['category'])->lower()->slug('_')),
            herdType: HerdTypeEnum::tryFrom(Str::of($row['type_of_herd'])->lower()->slug('_')),
            description: null,
            dateOfEstablishment: Sanitizer::parseDate($row['establishment_date'] ?? $row['date_of_establishment'] ?? null),
            contactPerson: $row['contact_person'] ?? null,
            position: $row['position'] ?? null,
            contactNumber: $row['contact_number'] ?? null,
            telephoneNumber: $row['telephone_number'] ?? null,
            emailAddress: $row['email_address'] ?? null,
            website: $row['website'] ?? null,
            numberOfStaffFemale: is_numeric($row['number_of_staff_female']) ? (int) $row['number_of_staff_female'] : 0,
            numberOfStaffMale: is_numeric($row['number_of_staff_male']) ? (int) $row['number_of_staff_male'] : 0,
            avgSalary: number_format($avgSalary, 2, '.', ''),
            street: null,
            barangay: $row['barangay'] ?? null,
            city: $row['municipalitycity'] ?? null,
            province: $row['province'] ?? null,
            region: null,
            district: $row['district'] ?? null,
            zipCode: isset($row['zipcode']) && Sanitizer::parseNumeric($row['zipcode']) !== 0.0
                ? (int) Sanitizer::parseNumeric($row['zipcode'])
                : null,
            latitude: isset($row['latitude']) ? floatval($row['latitude']) : null,
            longitude: isset($row['longitude']) ? floatval($row['longitude']) : null
        );
    }
}
