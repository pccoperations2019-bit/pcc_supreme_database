<?php

namespace App\Imports;

use App\Actions\Center\UpsertProcessingUnitAction;
use App\Enums\ProcessingCategoryEnum;
use App\Support\Sanitizer;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ProcessingUnitImport implements OnEachRow, WithCalculatedFormulas, WithHeadingRow
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
        $avgSalaryValue = $row['average_monthly_salary_of_staff'] ?? $row['avg_monthly_salary'] ?? $row['avg_salary'] ?? null;
        $avgSalary = isset($avgSalaryValue) && is_numeric($avgSalaryValue)
            ? floatval($avgSalaryValue)
            : 0.00;

        $centerCode = isset($row['center_code']) ? trim($row['center_code']) : null;
        if (! $centerCode || ! isset($this->centerIds[$centerCode])) {
            return;
        }
        $name = $row['name'] ?? $row['unit_name'] ?? $row['processing_unit_name'] ?? $row['pmo_name'] ?? null;
        if (! $name) {
            return;
        }
        resolve(UpsertProcessingUnitAction::class)->execute(
            name: $name,
            centerId: $this->centerIds[$centerCode],
            withinCenter: isset($row['within_center']) && filter_var($row['within_center'], FILTER_VALIDATE_BOOLEAN),
            category: ProcessingCategoryEnum::tryFrom(Str::of($row['category'] ?? '')->lower()->slug('_')) ?? ProcessingCategoryEnum::KITCHEN_TYPE,
            description: null,
            dateOfEstablishment: Sanitizer::parseDate($row['establishment_date'] ?? $row['date_of_establishment'] ?? null),
            contactPerson: $row['contact_person'] ?? null,
            position: $row['position'] ?? null,
            contactNumber: $row['contact_number'] ?? null,
            telephoneNumber: $row['telephone_number'] ?? null,
            emailAddress: $row['email_address'] ?? null,
            website: $row['website'] ?? null,
            numberOfStaffFemale: isset($row['number_of_staff_female']) && is_numeric($row['number_of_staff_female']) ? (int) $row['number_of_staff_female'] : 0,
            numberOfStaffMale: isset($row['number_of_staff_male']) && is_numeric($row['number_of_staff_male']) ? (int) $row['number_of_staff_male'] : 0,
            avgSalary: number_format($avgSalary, 2, '.', ''),
            street: null,
            barangay: $row['barangay'] ?? null,
            city: $row['municipalitycity'] ?? $row['city'] ?? null,
            province: $row['province'] ?? null,
            region: null,
            district: $row['district'] ?? null,
            zipCode: (isset($row['zipcode']) && Sanitizer::parseNumeric($row['zipcode']) !== 0.0)
                || (isset($row['zip_code']) && Sanitizer::parseNumeric($row['zip_code']) !== 0.0)
                ? (int) Sanitizer::parseNumeric($row['zipcode'] ?? $row['zip_code'])
                : null,
            latitude: isset($row['latitude']) ? floatval($row['latitude']) : null,
            longitude: isset($row['longitude']) ? floatval($row['longitude']) : null
        );
    }
}
