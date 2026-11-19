<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/deployment/ProductionDatabase.Common.php';

final class ProductionBackupSafetyTest extends TestCase
{
    public function test_client_option_values_cannot_inject_another_option(): void
    {
        $value = "quote\" slash\\\n[client]\nhost=other\r\t\x08 #;";
        $encoded = quoteMysqlOption($value);
        $this->assertSame('"quote\\" slash\\\\\\n[client]\\nhost=other\\r\\t\\b #;"', $encoded);
        $this->assertStringNotContainsString("\n", $encoded);
        $this->assertStringNotContainsString("\r", $encoded);
        $this->assertSame('""', quoteMysqlOption(''));
    }

    public function test_nul_in_option_value_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        quoteMysqlOption("value\0ignored");
    }

    public function test_only_verified_all_innodb_metadata_is_accepted(): void
    {
        assertProductionBackupMetadata($this->metadata());
        $this->addToAssertionCount(1);
    }

    public function test_mixed_engines_and_target_or_schema_drift_are_rejected(): void
    {
        $original = $this->metadata();
        $cases = [];
        foreach (['version' => '8.0.45', 'database_name' => 'another_database', 'gtid_mode' => 'ON'] as $key => $value) {
            $changed = $original;
            $changed['server'][$key] = $value;
            $cases[] = $changed;
        }
        $changed = $original;
        $changed['tableEngines'][] = ['engine' => 'MyISAM', 'table_count' => 1];
        $cases[] = $changed;
        $changed = $original;
        $changed['tableEngines'][0]['engine'] = 'MyISAM';
        $cases[] = $changed;
        $changed = $original;
        $changed['tableEngines'][0]['table_count'] = 79;
        $cases[] = $changed;
        $changed = $original;
        $changed['viewCount'] = 2;
        $cases[] = $changed;
        foreach ($cases as $metadata) {
            $rejected = false;
            try {
                assertProductionBackupMetadata($metadata);
            } catch (RuntimeException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'Unverified backup metadata was accepted.');
        }
    }

    private function metadata(): array
    {
        return [
            'server' => ['version' => '8.0.40', 'database_name' => 'pcc', 'gtid_mode' => 'OFF'],
            'tableEngines' => [['engine' => 'InnoDB', 'table_count' => 78]],
            'viewCount' => 1,
        ];
    }
}
