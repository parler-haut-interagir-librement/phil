<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\UnreadableFlag;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The row the admin reads, as a driver returns it: a revision may come as a string of digits.
 */
#[CoversClass(DoctrineFlagDefinitions::class)]
final class DoctrineFlagDefinitionsTest extends TestCase
{
    public function testARevisionReturnedAsAStringOfDigitsIsAWholeNumber(): void
    {
        $revised = $this->definitions('{"active": true}', '7')->findRevised(FlagName::of('beta'));

        self::assertNotNull($revised);
        self::assertSame(7, $revised->revision);
        self::assertTrue($revised->definition->active);
    }

    public function testTheRowIsReadByTheFlagsName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT definition, revision FROM phil_feature_flags_definition WHERE name = ?', ['beta'])
            ->willReturn(false);

        self::assertNull(new DoctrineFlagDefinitions($connection, new MockClock())->findRevised(FlagName::of('beta')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function revisionsAmiss(): iterable
    {
        yield 'letters before' => ['v7'];
        yield 'letters after' => ['7a'];
        yield 'a new line after' => ["7\n"];
    }

    #[DataProvider('revisionsAmiss')]
    public function testARevisionThatIsNotOnlyDigitsIsRefused(string $revision): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The stored revision of "beta" is not a whole number.');

        $this->definitions('{"active": true}', $revision)->findRevised(FlagName::of('beta'));
    }

    /**
     * Sixteen levels are read, then refused as a definition; seventeen are not read at all.
     */
    public function testADefinitionIsReadSixteenLevelsDeep(): void
    {
        foreach ([11 => false, 12 => true] as $nesting => $tooDeep) {
            try {
                $this->definitions($this->nested($nesting), 1)->findRevised(FlagName::of('beta'));
                self::fail('A definition nested too deep was read.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame($tooDeep, str_contains($invalidValue->getMessage(), 'is not JSON: Maximum stack depth exceeded'), $invalidValue->getMessage());
            }
        }
    }

    /**
     * The unreadable rows are read with the definitions, whichever is asked first, and a reset forgets them along
     * with the definitions: a row repaired since reads again.
     */
    public function testUnreadableRowsAreReadOncePerRequestAndForgottenOnReset(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAllKeyValue')
            ->with('SELECT name, definition FROM phil_feature_flags_definition')
            ->willReturnOnConsecutiveCalls(['zeta' => 'not json', 'beta' => '{"rules": "all"}', 'good' => '{}', 'other' => '{}'], ['zeta' => '{}', 'beta' => '{}', 'good' => '{}']);
        $definitions = new DoctrineFlagDefinitions($connection, new MockClock());

        self::assertSame(['beta', 'zeta'], array_map(static fn (UnreadableFlag $flag): string => $flag->name, $definitions->unreadable()));
        self::assertSame('"rules" is a list of rules.', $definitions->unreadable()[0]->reason);
        self::assertSame(['good', 'other'], array_map(static fn (FlagDefinition $definition): string => $definition->name->value, $definitions->all()));
        self::assertSame([0, 1], array_keys($definitions->all()));
        self::assertNull($definitions->find(FlagName::of('absent')));

        $definitions->reset();

        self::assertNotNull($definitions->find(FlagName::of('zeta')));
        self::assertNotNull($definitions->find(FlagName::of('beta')));
        self::assertSame([], $definitions->unreadable());
    }

    /**
     * Asked first for an unreadable flag, the provider reads the table and says why, rather than answering `null`.
     */
    public function testAnUnreadableFlagAskedFirstIsAParseFailure(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['beta' => 'not json']);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The stored definition of "beta" is not JSON');

        new DoctrineFlagDefinitions($connection, new MockClock())->find(FlagName::of('beta'));
    }

    /**
     * A name of digits only is a flag name, and stays a string when its row is unreadable.
     */
    public function testAnUnreadableNameOfDigitsIsAString(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['123' => 'not json', '456' => '{}']);
        $definitions = new DoctrineFlagDefinitions($connection, new MockClock());

        self::assertSame('123', $definitions->unreadable()[0]->name);
        self::assertSame('456', $definitions->find(FlagName::of('456'))?->name->value);
    }

    private function nested(int $nesting): string
    {
        return '{"rules": [{"attribute": "tenant", "members": ['.str_repeat('[', $nesting).str_repeat(']', $nesting).']}]}';
    }

    private function definitions(string $definition, int|string $revision): DoctrineFlagDefinitions
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['definition' => $definition, 'revision' => $revision]);

        return new DoctrineFlagDefinitions($connection, new MockClock());
    }
}
