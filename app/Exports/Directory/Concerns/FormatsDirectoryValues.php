<?php

namespace App\Exports\Directory\Concerns;

use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProjectProgramEnum;

trait FormatsDirectoryValues
{
    private function regionLabel(mixed $region): string
    {
        if ($region === null || trim((string) $region) === '') {
            return '';
        }

        return AddressRegionEnum::tryFroMatches((string) $region)?->label() ?? (string) $region;
    }

    private function districtLabel(mixed $district): string
    {
        if ($district === null || trim((string) $district) === '') {
            return '';
        }

        $value = trim((string) $district);
        $enum = AddressDistrictEnum::tryFroMatches($value);

        return $enum instanceof AddressDistrictEnum ? AddressDistrictEnum::ordinals()[$enum->value] ?? $value : $value;
    }

    private function profileStatusLabel(mixed $status): string
    {
        if ($status instanceof ProfileStatusEnum) {
            return $status->label();
        }

        $value = trim((string) $status);
        if ($value === '') {
            return '';
        }

        return ProfileStatusEnum::tryFrom(strtolower($value))?->label() ?? $value;
    }

    private function projectProgramsLabel(string $programs): string
    {
        if ($programs === '') {
            return '';
        }

        $labels = [];
        foreach (explode(',', $programs) as $program) {
            $program = trim($program);
            if ($program === '') {
                continue;
            }
            $label = ProjectProgramEnum::tryFroMatches($program)?->label()
                ?? ProjectProgramEnum::tryFrom(strtolower($program))?->label()
                ?? strtoupper($program);
            $labels[] = $label;
        }

        return implode(', ', $labels);
    }

    private function projectProgramLabel(mixed $program): string
    {
        if ($program instanceof ProjectProgramEnum) {
            return $program->label();
        }

        $values = is_array($program) ? $program : explode(',', (string) $program);
        $labels = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $labels[] = ProjectProgramEnum::tryFroMatches($value)?->label()
                ?? ProjectProgramEnum::tryFrom(strtolower($value))?->label()
                ?? $value;
        }

        return implode(', ', $labels);
    }
}
