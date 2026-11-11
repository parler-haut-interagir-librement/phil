<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Domain\Identifier;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\OtherId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(Identifier::class)]
final class IdentifierTest extends TestCase
{
    public function testGenerateProducesAV7(): void
    {
        self::assertInstanceOf(UuidV7::class, ExampleId::generate()->value);
    }

    /**
     * The caller generates the identifier, so `generate()` must return the subclass type, not the base type.
     * A `new self()` instead of `new static()` would return an `Identifier` and break all downstream typing
     * without any type assertion complaining at the base level.
     */
    public function testGenerateReturnsTheSubclass(): void
    {
        self::assertInstanceOf(ExampleId::class, ExampleId::generate());
        self::assertInstanceOf(OtherId::class, OtherId::generate());
    }

    /**
     * This is why v7 exists: lexicographic order follows time order, so a B-tree insert lands at the tail of
     * the index instead of fragmenting it. If this property goes away, the reason v7 was chosen goes away with
     * it.
     */
    public function testGeneratedIdentifiersAreTimeOrdered(): void
    {
        $first = ExampleId::generate();
        $second = ExampleId::generate();

        $firstDate = $first->value->getDateTime();
        $secondDate = $second->value->getDateTime();

        self::assertLessThanOrEqual(0, strcmp($first->toString(), $second->toString()));
        self::assertLessThanOrEqual($secondDate->getTimestamp(), $firstDate->getTimestamp());
    }

    public function testItRoundTripsThroughItsStringForm(): void
    {
        $identifier = ExampleId::generate();

        self::assertTrue($identifier->equals(ExampleId::fromString($identifier->toString())));
        self::assertSame($identifier->toString(), (string) $identifier);
    }

    #[DataProvider('refusedStrings')]
    public function testItRefuses(string $value): void
    {
        $this->expectException(InvalidValue::class);

        ExampleId::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'not a UUID' => ['philippe'];
        yield 'truncated' => ['0198c2b1-9c3f-7000-8000-0000000000'];
        yield 'nil UUID' => ['00000000-0000-0000-0000-000000000000'];
    }

    /**
     * A v4 is a valid UUID. It is refused anyway: it is not time-ordered and carries no date, so admitting it
     * would let into the database identifiers that have neither of the two properties v7 was chosen for.
     */
    public function testItRefusesAValidUuidOfTheWrongVersion(): void
    {
        $v4 = Uuid::v4()->toRfc4122();

        self::assertTrue(Uuid::isValid($v4));

        $this->expectException(InvalidValue::class);

        ExampleId::fromString($v4);
    }

    public function testItWrapsAUuidV7AlreadyParsed(): void
    {
        $uuid = Uuid::v7();
        $identifier = ExampleId::fromUuid(Uuid::fromBinary($uuid->toBinary()));

        self::assertInstanceOf(ExampleId::class, $identifier);
        self::assertSame($uuid->toRfc4122(), $identifier->toString());
    }

    public function testItRefusesAParsedUuidOfTheWrongVersion(): void
    {
        $v4 = Uuid::v4();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is a UUID, but not a v7.', $v4->toRfc4122()));

        ExampleId::fromUuid($v4);
    }

    /**
     * Two identifiers from different contexts carrying the same UUID are not the same identifier. Without this
     * check, an `equals()` that compares only the value would let an account identifier pass an equality test
     * against a session identifier.
     */
    public function testTwoDifferentTypesAreNeverEqual(): void
    {
        $string = ExampleId::generate()->toString();

        self::assertFalse(ExampleId::fromString($string)->equals(OtherId::fromString($string)));
    }

    public function testTwoDifferentValuesAreNotEqual(): void
    {
        self::assertFalse(ExampleId::generate()->equals(ExampleId::generate()));
    }

    public function testItRendersInRfc4122Form(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            ExampleId::generate()->toString(),
        );
    }
}
