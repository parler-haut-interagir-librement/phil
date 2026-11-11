<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Configuration;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredFlagProvider::class)]
final class ConfiguredFlagProviderTest extends TestCase
{
    public function testAFlagIsReadWithItsStateAndRulesInOrder(): void
    {
        $provider = ConfiguredFlagProvider::fromConfiguration([
            'new-checkout' => [
                'active' => true,
                'rules' => [
                    ['attribute' => 'tenant', 'members' => ['tenant-a', 'tenant-b']],
                    ['attribute' => 'project', 'percentage' => 20],
                ],
            ],
        ]);

        $definition = $provider->find(FlagName::of('new-checkout'));

        self::assertNotNull($definition);
        self::assertTrue($definition->active);
        self::assertCount(2, $definition->rules);
        self::assertInstanceOf(MemberOf::class, $definition->rules[0]);
        self::assertSame(TargetingAttribute::Tenant, $definition->rules[0]->attribute);
        self::assertSame(['tenant-a', 'tenant-b'], $definition->rules[0]->members());
        self::assertInstanceOf(PercentageRollout::class, $definition->rules[1]);
        self::assertSame(TargetingAttribute::Project, $definition->rules[1]->attribute);
        self::assertSame(20, $definition->rules[1]->percentage->value);
    }

    public function testAFlagWithoutStateIsInactiveAndWithoutRules(): void
    {
        $definition = ConfiguredFlagProvider::fromConfiguration(['parked' => []])->find(FlagName::of('parked'));

        self::assertNotNull($definition);
        self::assertFalse($definition->active);
        self::assertSame([], $definition->rules);
    }

    public function testAnUndeclaredFlagIsLeftToTheNextProvider(): void
    {
        self::assertNull(ConfiguredFlagProvider::fromConfiguration(['known' => ['active' => true]])->find(FlagName::of('unknown')));
    }

    /**
     * An integer key is what PHP makes of a numeric name; it is still the flag of that name.
     */
    public function testANumericNameIsAName(): void
    {
        self::assertNotNull(ConfiguredFlagProvider::fromConfiguration([2026 => ['active' => true]])->find(FlagName::of('2026')));
    }

    public function testEachCircuitDeclaresItsForceOpenFlagInactiveAndWithoutRules(): void
    {
        $definition = ConfiguredFlagProvider::fromConfiguration([], ['http.payments'])->find(FlagName::of('circuit-open.http.payments'));

        self::assertNotNull($definition);
        self::assertFalse($definition->active);
        self::assertSame([], $definition->rules);
    }

    public function testADeclaredForceOpenFlagKeepsItsDeclaredState(): void
    {
        $definition = ConfiguredFlagProvider::fromConfiguration(['circuit-open.http.payments' => ['active' => true]], ['http.payments'])
            ->find(FlagName::of('circuit-open.http.payments'));

        self::assertNotNull($definition);
        self::assertTrue($definition->active);
    }

    public function testAMalformedCircuitKeyIsRefusedWithItsPath(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('circuits.Bad Key: "Bad Key" is not a circuit key');

        ConfiguredFlagProvider::fromConfiguration([], ['Bad Key']);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function malformedFlags(): iterable
    {
        yield 'not a map' => ['yes', 'flags.new-checkout: A flag is declared by a map with "active" and "rules".'];
        yield 'active not a boolean' => [['active' => 'yes'], 'flags.new-checkout: "active" is true or false.'];
        yield 'rules not a list' => [['rules' => 'all'], 'flags.new-checkout: "rules" is a list of rules.'];
        yield 'rule not a map' => [['rules' => ['tenant']], 'flags.new-checkout: A rule is a map with "attribute" and either "members" or "percentage".'];
        yield 'unknown attribute' => [['rules' => [['attribute' => 'country', 'percentage' => 10]]], 'flags.new-checkout: A rule targets one of the attributes tenant, project, subject.'];
        yield 'missing attribute' => [['rules' => [['percentage' => 10]]], 'flags.new-checkout: A rule targets one of the attributes tenant, project, subject.'];
        yield 'members not a list' => [['rules' => [['attribute' => 'tenant', 'members' => 'tenant-a']]], 'flags.new-checkout: "members" is a list of identifiers.'];
        yield 'member not a string' => [['rules' => [['attribute' => 'tenant', 'members' => [42]]]], 'flags.new-checkout: "members" is a list of identifiers.'];
        yield 'both members and percentage' => [['rules' => [['attribute' => 'tenant', 'members' => ['tenant-a'], 'percentage' => 10]]], 'flags.new-checkout: A rule has either "members" or "percentage", not both.'];
        yield 'neither members nor percentage' => [['rules' => [['attribute' => 'tenant', 'members' => []]]], 'flags.new-checkout: A rule needs either "members" or "percentage".'];
        yield 'percentage not an integer' => [['rules' => [['attribute' => 'tenant', 'percentage' => '20']]], 'flags.new-checkout: "percentage" is a whole number.'];
        yield 'percentage out of range' => [['rules' => [['attribute' => 'tenant', 'percentage' => 101]]], 'flags.new-checkout: A percentage is between 0 and 100; got 101.'];
        yield 'empty member' => [['rules' => [['attribute' => 'tenant', 'members' => ['']]]], 'flags.new-checkout: A member of a membership rule cannot be empty.'];
    }

    #[DataProvider('malformedFlags')]
    public function testAMalformedFlagIsRefusedWithItsPath(mixed $flag, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ConfiguredFlagProvider::fromConfiguration(['new-checkout' => $flag]);
    }

    public function testAMalformedNameIsRefusedWithItsPath(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('flags.New Checkout: "New Checkout" is not a flag name');

        ConfiguredFlagProvider::fromConfiguration(['New Checkout' => ['active' => true]]);
    }

    public function testAForceOpenFlagTakesNoRule(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('flags.circuit-open.http.payments: A flag that forces a circuit open takes no rule and no experiment: a circuit is forced open for every request or for none.');

        ConfiguredFlagProvider::fromConfiguration([
            'circuit-open.http.payments' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'members' => ['tenant-a']]]],
        ]);
    }
}
