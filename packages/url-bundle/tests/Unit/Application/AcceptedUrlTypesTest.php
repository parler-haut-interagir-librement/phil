<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\KnownUrlType;
use Phil\UrlBundle\Application\AcceptedUrlTypes;
use Phil\UrlBundle\Tests\Unit\Fixtures\RegistryUrlType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(AcceptedUrlTypes::class)]
final class AcceptedUrlTypesTest extends TestCase
{
    public function testEveryInternationalKindByDefault(): void
    {
        $sets = AcceptedUrlTypes::sets(null);

        self::assertCount(1, $sets);
        self::assertSame([KnownUrlType::class, EnumSet::all(KnownUrlType::class)->names()], [$sets[0]->enum(), $sets[0]->names()]);
    }

    public function testKindsAreWrittenInManyWays(): void
    {
        $set = EnumSet::of(KnownUrlType::class, KnownUrlType::X);

        self::assertSame([$set], AcceptedUrlTypes::sets($set));
        self::assertSame(['Facebook'], AcceptedUrlTypes::sets(KnownUrlType::Facebook)[0]->names());
        self::assertSame(RegistryUrlType::class, AcceptedUrlTypes::sets(RegistryUrlType::class)[0]->enum());
        self::assertSame(['Listing', 'Registry'], AcceptedUrlTypes::sets(RegistryUrlType::class)[0]->names());

        $sets = AcceptedUrlTypes::sets([RegistryUrlType::Registry, KnownUrlType::Instagram, $set, KnownUrlType::Facebook]);

        self::assertSame([RegistryUrlType::class, KnownUrlType::class], array_map(static fn (EnumSet $set): string => $set->enum(), $sets));
        self::assertSame(['Facebook', 'Instagram', 'X'], $sets[1]->names());
        self::assertSame([0, 1], array_keys($sets));
    }

    public function testOtherValuesAreRefused(): void
    {
        foreach ([stdClass::class, 'Unknown\\Thing', 42, [new stdClass()]] as $types) {
            try {
                AcceptedUrlTypes::sets($types);
                self::fail('A bad kind was accepted.');
            } catch (InvalidArgumentException $invalid) {
                self::assertStringStartsWith('A kind of address is a case of an enum implementing Phil\\Url\\UrlType, its class, or an enum set of them;', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A field accepts at least one kind of address.');

        AcceptedUrlTypes::sets([]);
    }
}
