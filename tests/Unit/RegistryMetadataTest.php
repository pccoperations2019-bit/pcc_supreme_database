<?php

namespace Tests\Unit;

use App\Support\RegistryMetadata;
use PHPUnit\Framework\TestCase;

class RegistryMetadataTest extends TestCase
{
    public function test_it_merges_new_keys_without_touching_old_import_keys(): void
    {
        $base = [
            'source' => 'Projectsite.xlsx/CBINCCDP',
            'source_row' => 4,
            'partner_cooperative_raw' => 'Some Coop',
        ];

        $merged = RegistryMetadata::deepMerge($base, [
            'registry_import' => ['identity_key' => 'abc', 'row_type' => 'standard'],
        ]);

        $this->assertSame('Projectsite.xlsx/CBINCCDP', $merged['source']);
        $this->assertSame(4, $merged['source_row']);
        $this->assertSame('Some Coop', $merged['partner_cooperative_raw']);
        $this->assertSame('abc', $merged['registry_import']['identity_key']);
    }

    public function test_it_deep_merges_nested_registry_import_keys(): void
    {
        $base = [
            'registry_import' => [
                'identity_key' => 'abc',
                'training' => ['project_orientation' => ['raw' => 'x']],
            ],
        ];

        $merged = RegistryMetadata::deepMerge($base, [
            'registry_import' => [
                'training' => ['strategic_planning' => ['raw' => 'y']],
                'row_type' => 'standard',
            ],
        ]);

        $this->assertSame('abc', $merged['registry_import']['identity_key']);
        $this->assertSame('x', $merged['registry_import']['training']['project_orientation']['raw']);
        $this->assertSame('y', $merged['registry_import']['training']['strategic_planning']['raw']);
    }

    public function test_it_handles_a_null_base(): void
    {
        $this->assertSame(
            ['registry_import' => ['a' => 1]],
            RegistryMetadata::deepMerge(null, ['registry_import' => ['a' => 1]])
        );
    }

    public function test_it_replaces_lists_instead_of_appending(): void
    {
        $merged = RegistryMetadata::deepMerge(
            ['registry_import' => ['flags' => ['a']]],
            ['registry_import' => ['flags' => ['b']]]
        );

        $this->assertSame(['b'], $merged['registry_import']['flags']);
    }

    public function test_it_lets_the_overlay_win_on_scalar_conflicts(): void
    {
        $merged = RegistryMetadata::deepMerge(
            ['registry_import' => ['row_type' => 'standard']],
            ['registry_import' => ['row_type' => 'continuation']]
        );

        $this->assertSame('continuation', $merged['registry_import']['row_type']);
    }
}
