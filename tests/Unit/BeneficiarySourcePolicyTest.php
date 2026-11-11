<?php

namespace Tests\Unit;

use App\Models\BeneficiarySource;
use App\Models\User;
use App\Policies\BeneficiarySourcePolicy;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BeneficiarySourcePolicyTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public static function accessCases(): array
    {
        return [
            'PCC admin' => ['agency-admin', true, true, true, true],
            'agency encoder' => ['encoder', true, true, true, true],
            'agency reviewer' => ['reviewer', true, false, true, false],
            'executive' => ['executive', true, false, true, false],
            'center encoder' => ['encoder', false, true, false, false],
            'center admin' => ['center-admin', false, true, false, false],
            'center reviewer' => ['reviewer', false, false, false, false],
        ];
    }

    #[DataProvider('accessCases')]
    public function test_access_by_role_and_scope(string $role, bool $agencyWide, bool $enterData, bool $canView, bool $canManage): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('enter-data')->andReturn($enterData);
        $user->shouldReceive('hasRole')->andReturnUsing(fn (string $expected) => $role === $expected);
        $user->shouldReceive('isAgencyWideDataEntry')->andReturn($agencyWide);
        $user->shouldReceive('isPccLevel')->andReturn($agencyWide);
        $policy = new BeneficiarySourcePolicy;
        $source = new BeneficiarySource;

        $this->assertSame($canView, $policy->viewAny($user));
        $this->assertSame($canView, $policy->view($user, $source));
        $this->assertSame($canManage, $policy->create($user));
        $this->assertSame($canManage, $policy->update($user, $source));
        $this->assertSame($canManage, $policy->delete($user, $source));
    }
}
