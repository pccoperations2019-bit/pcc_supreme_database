<?php

namespace App\Models;

use App\Casts\Latitude;
use App\Casts\Longitude;
use App\Enums\AddressRegionEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use HasFactory;

    protected $appends = [
        'region_label',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function municipality(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->city,
        );
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function productionUnit()
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => trim(implode(', ', array_filter([
                $this->street,
                $this->barangay,
                $this->city,
                $this->province,
                $this->region,
                $this->district,
                $this->zip_code,
            ]))),
        );
    }

    protected function regionLabel(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $raw = $this->region;
                if ($raw === null || $raw === '') {
                    return null;
                }

                $rawString = (string) $raw;

                $case =
                    AddressRegionEnum::fromPsgcCode($rawString)
                    ?? AddressRegionEnum::tryFroMatches($rawString)
                    ?? AddressRegionEnum::fromLabel($rawString)
                    ?? AddressRegionEnum::tryFrom($rawString);

                if (! $case) {
                    return $rawString;
                }

                return AddressRegionEnum::labels()[$case->value] ?? $rawString;
            }
        );
    }

    public function geocode(): mixed
    {
        return resolve('geocoder')
            ->doNotCache()
            ->geocode($this->full_address)
            ->get();
    }

    protected function casts(): array
    {
        return [
            'latitude' => Latitude::class,
            'longitude' => Longitude::class,
        ];
    }
}
