<?php

namespace Tests\Unit\Exports\Bdp;

use App\Enums\AddressDistrictEnum;
use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Support\BdpLocationResolver;
use Tests\TestCase;

class BdpLocationResolverDistrictTest extends TestCase
{
    private BdpLocationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new BdpLocationResolver(new BuffaloDerivedProductsFilters);
    }

    /**
     * @return array<int, array{input: string, expected: string}>
     */
    private function numeralCases(): array
    {
        return [
            ['input' => 'I', 'expected' => '1st'],
            ['input' => 'II', 'expected' => '2nd'],
            ['input' => 'III', 'expected' => '3rd'],
            ['input' => 'IV', 'expected' => '4th'],
            ['input' => 'V', 'expected' => '5th'],
            ['input' => 'VI', 'expected' => '6th'],
            ['input' => 'VII', 'expected' => '7th'],
            ['input' => 'VIII', 'expected' => '8th'],
            ['input' => 'IX', 'expected' => '9th'],
            ['input' => 'X', 'expected' => '10th'],
            ['input' => 'XI', 'expected' => '11th'],
            ['input' => 'XII', 'expected' => '12th'],
        ];
    }

    public function test_roman_numerals_normalize_to_ordinals(): void
    {
        foreach ($this->numeralCases() as $case) {
            $this->assertSame($case['expected'], $this->resolver->districtLabel($case['input']));
        }
    }

    /**
     * @return array<int, array{input: string, expected: string}>
     */
    private function variantCases(): array
    {
        return [
            // "District N", "Nth District", plain numbers and ordinals all collapse to the same form.
            ['input' => 'District 1', 'expected' => '1st'],
            ['input' => '1st District', 'expected' => '1st'],
            ['input' => '1', 'expected' => '1st'],
            ['input' => '1st', 'expected' => '1st'],
            ['input' => 'District 2', 'expected' => '2nd'],
            ['input' => '3', 'expected' => '3rd'],
            ['input' => '3rd', 'expected' => '3rd'],
            ['input' => 'DISTRICT 4', 'expected' => '4th'],
            ['input' => '7', 'expected' => '7th'],
        ];
    }

    public function test_messy_variants_all_normalize_to_same_ordinal(): void
    {
        foreach ($this->variantCases() as $case) {
            $this->assertSame($case['expected'], $this->resolver->districtLabel($case['input']));
        }
    }

    public function test_lone_variants_normalize_to_lone_district(): void
    {
        foreach (['Lone', 'LONE', 'lone district', 'District Lone', 'Only District'] as $variant) {
            $this->assertSame('Lone District', $this->resolver->districtLabel($variant));
        }
    }

    public function test_named_or_unknown_districts_pass_through_uppercased(): void
    {
        $this->assertSame('BASEY', $this->resolver->districtLabel('Basey'));
        $this->assertSame('CARIGARA', $this->resolver->districtLabel('Carigara'));
        $this->assertSame('MAASIN CITY', $this->resolver->districtLabel('Maasin City'));
        $this->assertSame('16', $this->resolver->districtLabel('16'));
        $this->assertSame('N/A', $this->resolver->districtLabel('N/A'));
    }

    public function test_blank_district_returns_blank(): void
    {
        $this->assertSame('', $this->resolver->districtLabel(''));
        $this->assertSame('', $this->resolver->districtLabel(null));
    }

    public function test_ordinals_cover_all_numbered_districts_and_lone(): void
    {
        $ordinals = AddressDistrictEnum::ordinals();

        $this->assertSame('1st', $ordinals[AddressDistrictEnum::FIRST->value]);
        $this->assertSame('12th', $ordinals[AddressDistrictEnum::TWELFTH->value]);
        $this->assertSame('Lone District', $ordinals[AddressDistrictEnum::LONE->value]);
    }
}
