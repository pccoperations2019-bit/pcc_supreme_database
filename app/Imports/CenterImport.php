<?php

namespace App\Imports;

use App\Actions\Address\UpsertAddressAction;
use App\Actions\Center\UpsertCenterAction;
use App\Enums\AddressCategoryEnum;
use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Models\Center;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CenterImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
{
    public function headingRow(): int
    {
        return 2;
    }

    public function __construct(
        private readonly int $agencyId,
        private readonly UpsertCenterAction $upsertCenterAction,
        private readonly UpsertAddressAction $upsertAddressAction
    ) {}

    public function onRow(Row $row)
    {
        $row = $row->toArray(calculateFormulas: true);

        $centerCode = $row['center_code'] ?? null;
        if (empty($centerCode)) {
            return;
        }

        $logoInput = $row['logo'] ?? null;
        $logo = null;

        $disk = Storage::disk('media');

        if (is_string($logoInput) && $logoInput !== '') {
            $path = (string) parse_url($logoInput, PHP_URL_PATH);
            if ($path !== '' && ! str_starts_with($logoInput, 'http://') && ! str_starts_with($logoInput, 'https://')) {
                $candidate = ltrim($path, '/');
                if ($disk->exists($candidate)) {
                    $logo = $candidate;
                }
            } else {

                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
                $filename = 'centers/logos/'.$centerCode.'.'.$ext;

                if ($disk->exists($filename)) {
                    $logo = $filename;
                } else {
                    try {
                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
                            'Accept' => 'image/*,*/*;q=0.8',
                        ])
                            ->retry(3, 1000)
                            ->timeout(30)
                            ->get($logoInput);

                        $contentType = (string) ($response->header('Content-Type') ?? '');
                        if ($response->successful() && str_starts_with($contentType, 'image/')) {
                            $disk->put($filename, $response->body(), [
                                'visibility' => 'public',
                            ]);
                            $logo = $filename;
                        } else {
                            $logo = null;
                        }
                    } catch (\Throwable) {
                        $logo = null;
                    }
                }
            }
        }

        Log::info([
            'logo' => $logo,
        ]);

        $center = $this->upsertCenterAction->execute(
            code: $centerCode,
            agencyId: $this->agencyId,
            name: $row['center_name'],
            description: $row['description'] ?? null,
            website: $row['website'] ?? null,
            logo: $logo,
            contactPerson: $row['contact_person'] ?? null,
            contactNumber: $row['contact_number'] ?? null,
            emailAddress: $row['email'] ?? null,
            additionalData: [
                'established_date' => $row['established_date'] ?? null,
                'center_type' => $row['center_type'] ?? null,
            ]
        );

        $this->upsertAddressAction->execute(
            addressableType: Str::of(class_basename(Center::class))->lower(),
            addressableId: $center->id,
            street: $row['street'] ?? null,
            barangay: $row['barangay'] ?? null,
            city: $row['municipality'] ?? null,
            province: $row['province'] ?? null,
            region: isset($row['region']) ? AddressRegionEnum::fromLabel($row['region']) : null,
            district: isset($row['district']) ? AddressDistrictEnum::fromLabel($row['district']) : null,
            zipCode: isset($row['zipcode']) && Sanitizer::parseNumeric($row['zipcode']) !== 0.0
                ? (int) Sanitizer::parseNumeric($row['zipcode'])
                : null,
            latitude: is_numeric($row['latitude']) ? (float) $row['latitude'] : null,
            longitude: is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
            markerIcon: null,
            category: AddressCategoryEnum::CENTER->value,
        );
    }
}
