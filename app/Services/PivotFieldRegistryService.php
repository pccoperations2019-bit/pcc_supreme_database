<?php

namespace App\Services;

class PivotFieldRegistryService
{
    public function sources(): array
    {
        return collect(config('pivot_sources'))
            ->map(fn ($cfg, $key) => [
                'key' => $key,
                'label' => $cfg['label'],
            ])
            ->values()
            ->all();
    }

    public function fieldsFor(string $sourceKey): array
    {
        $cfg = config("pivot_sources.{$sourceKey}");

        abort_if(
            ! $cfg,
            404,
            "Unknown pivot source: {$sourceKey}"
        );

        return collect($cfg['fields'])
            ->map(fn ($field, $key) => [
                'field' => $key,
                'label' => $field['label'],
                'type' => $field['type'],
            ])
            ->values()
            ->all();
    }

    /**
     * Throws if any requested field isn't in
     * the source whitelist.
     */
    public function assertFieldsValid(
        string $sourceKey,
        array $fieldKeys
    ): void {
        $allowed = array_keys(
            config("pivot_sources.{$sourceKey}.fields", [])
        );

        $invalid = array_diff($fieldKeys, $allowed);

        abort_if(
            $invalid !== [],
            422,
            'Invalid field(s): '.implode(', ', $invalid)
        );
    }

    public function valuesFor(string $sourceKey, string $field, ?string $search = null): array
    {
        $this->assertFieldsValid($sourceKey, [$field]);
        $cfg = config("pivot_sources.{$sourceKey}");

        $query = app(PivotBuilderService::class)->baseQuery($cfg);
        $qualified = app(PivotBuilderService::class)->qualify($cfg, $field);

        $blankCount = (clone $query)
            ->where(function ($q) use ($qualified) {
                $q->whereNull($qualified)->orWhere($qualified, '');
            })
            ->count();

        $rawValues = (clone $query)
            ->select($qualified)
            ->whereNotNull($qualified)
            ->where($qualified, '!=', '')
            ->when($search, fn ($q) => $q->where($qualified, 'like', "%{$search}%"))
            ->distinct()
            ->orderBy($qualified)
            ->pluck($qualified)
            ->all();

        // Format values using the same logic as pivot results (enum labels, etc.)
        $builder = app(PivotBuilderService::class);
        $formattedValues = [];
        foreach ($rawValues as $rawValue) {
            $formattedValue = $builder->formatValue($cfg, $field, $rawValue);
            $formattedValues[] = [
                'value' => $rawValue,  // Raw value for filtering
                'label' => $formattedValue,  // Formatted label for display
            ];
        }

        return [
            'values' => $formattedValues,
            'has_blanks' => $blankCount > 0,
            'blank_count' => $blankCount,
            'total' => count($formattedValues) + ($blankCount > 0 ? 1 : 0),
        ];
    }
}
