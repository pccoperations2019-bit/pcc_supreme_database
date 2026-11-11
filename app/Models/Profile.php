<?php

namespace App\Models;

use App\Enums\AddressRegionEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Profile extends Model
{
    protected $appends = [
        'url',
        'region_label',
    ];

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->profile_type) {
                'center' => route('centers.show', $this->id),
                'partner' => route('partners.show', $this->id),
                default => '#',
            },
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

                $rawString = trim((string) $raw);
                if ($rawString === '') {
                    return null;
                }

                $rawUpper = strtoupper($rawString);

                $case =
                    AddressRegionEnum::tryFroMatches($rawString)
                    ?? AddressRegionEnum::tryFroMatches($rawUpper)
                    ?? AddressRegionEnum::fromLabel($rawString)
                    ?? AddressRegionEnum::fromLabel($rawUpper)
                    ?? AddressRegionEnum::tryFrom($rawString)
                    ?? AddressRegionEnum::tryFrom($rawUpper);

                if (! $case) {
                    return $rawString;
                }

                return AddressRegionEnum::labels()[$case->value] ?? $rawString;
            }
        );
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    protected function casts(): array
    {
        return [];
    }

    protected function scopeGlobalSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%");
        });
    }
}
