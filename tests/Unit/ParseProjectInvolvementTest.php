<?php

namespace Tests\Unit;

use App\Support\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParseProjectInvolvementTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_it_normalizes_involvement_tags_to_the_canonical_form(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, Sanitizer::parseProjectInvolvement($input));
    }

    public static function normalizationCases(): array
    {
        return [
            'space becomes hyphen' => ['CBIN 2019', 'CBIN-2019'],
            'already canonical' => ['CBIN-2019', 'CBIN-2019'],
            'surrounding whitespace' => ['  CBIN-2019  ', 'CBIN-2019'],
            'repeated whitespace collapses' => ['CBIN  2019', 'CBIN-2019'],
            'repeated hyphens collapse' => ['CBIN--2019', 'CBIN-2019'],
            'tabs and newlines' => ["CBIN\t2019", 'CBIN-2019'],
            'empty string' => ['', null],
            'whitespace only' => ['   ', null],
            'null' => [null, null],
            'array is rejected' => [['CBIN-2019'], null],
            'bool is rejected' => [true, null],
        ];
    }

    public function test_it_normalizes_and_deduplicates_a_list_of_tags(): void
    {
        $this->assertSame(
            ['CBIN-2019', 'CBIN-2021'],
            Sanitizer::parseProjectInvolvementList(['CBIN 2019', 'CBIN-2021', 'CBIN-2019', null, ''])
        );
    }
}
