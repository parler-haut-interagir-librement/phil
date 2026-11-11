<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessengerBundle\Application\AcceptedMessengers;
use Phil\InstantMessengerBundle\Tests\Unit\Fixtures\ForumMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(AcceptedMessengers::class)]
final class AcceptedMessengersTest extends TestCase
{
    public function testEveryInternationalServiceByDefault(): void
    {
        $sets = AcceptedMessengers::sets(null);

        self::assertSame([KnownMessenger::class, EnumSet::all(KnownMessenger::class)->names()], [$sets[0]->enum(), $sets[0]->names()]);
        self::assertCount(1, $sets);
        self::assertSame(KnownMessenger::cases(), AcceptedMessengers::cases(null));
    }

    public function testServicesAreWrittenInManyWays(): void
    {
        $set = EnumSet::of(KnownMessenger::class, KnownMessenger::Signal);

        self::assertSame([$set], AcceptedMessengers::sets($set));
        self::assertSame(['WhatsApp'], AcceptedMessengers::sets(KnownMessenger::WhatsApp)[0]->names());
        self::assertSame([ForumMessenger::Forum, KnownMessenger::WhatsApp, KnownMessenger::Signal], AcceptedMessengers::cases([ForumMessenger::class, KnownMessenger::Signal, KnownMessenger::WhatsApp]));
        self::assertSame([0, 1], array_keys(AcceptedMessengers::sets([ForumMessenger::class, $set, KnownMessenger::WhatsApp])));
    }

    public function testOtherValuesAreRefused(): void
    {
        foreach ([stdClass::class, 42, [new stdClass()]] as $types) {
            try {
                AcceptedMessengers::sets($types);
                self::fail('A bad service was accepted.');
            } catch (InvalidArgumentException $invalid) {
                self::assertStringStartsWith('A messaging service is a case of an enum implementing Phil\\InstantMessenger\\MessengerType, its class, or an enum set of them;', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A field accepts at least one messaging service.');

        AcceptedMessengers::sets([]);
    }
}
