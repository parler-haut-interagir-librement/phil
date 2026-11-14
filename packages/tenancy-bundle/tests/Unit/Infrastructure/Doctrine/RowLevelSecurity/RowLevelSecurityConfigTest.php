<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\RowLevelSecurity;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowLevelSecurityConfig::class)]
final class RowLevelSecurityConfigTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $config = new RowLevelSecurityConfig();

        self::assertSame(['phil.tenant_id', 'phil_tenant_isolation'], [$config->setting, $config->policy]);
    }

    #[TestWith(['app.tenant', 'tenant_rows'])]
    #[TestWith(['_a.b9', '_'])]
    public function testLowercaseIdentifiersAreAccepted(string $setting, string $policy): void
    {
        $config = new RowLevelSecurityConfig($setting, $policy);

        self::assertSame([$setting, $policy], [$config->setting, $config->policy]);
    }

    #[TestWith(['tenant'])]
    #[TestWith(['phil.tenant.id'])]
    #[TestWith(['Phil.tenant'])]
    #[TestWith(["phil.tenant'); DROP TABLE invoice; --"])]
    #[TestWith(['phil.9tenant'])]
    #[TestWith(["phil.tenant\n"])]
    public function testASettingThatIsNotADottedPairIsRefused(string $setting): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The session variable of row-level security is two lowercase identifiers joined by a dot');

        new RowLevelSecurityConfig($setting);
    }

    #[TestWith(['tenant isolation'])]
    #[TestWith(['Tenant'])]
    #[TestWith([''])]
    #[TestWith(['a.b'])]
    public function testAPolicyThatIsNotAnIdentifierIsRefused(string $policy): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The policy of row-level security is a lowercase identifier');

        new RowLevelSecurityConfig(policy: $policy);
    }

    public function testAnIdentifierIsAtMost63Characters(): void
    {
        self::assertSame(63, \strlen(new RowLevelSecurityConfig(policy: str_repeat('a', 63))->policy));

        $this->expectException(InvalidValue::class);

        new RowLevelSecurityConfig(policy: str_repeat('a', 64));
    }
}
