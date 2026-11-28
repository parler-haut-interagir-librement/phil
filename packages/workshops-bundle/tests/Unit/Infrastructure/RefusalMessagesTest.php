<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Infrastructure;

use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Phil\WorkshopsBundle\Infrastructure\Translation\RefusalMessages;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\Translations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefusalMessages::class)]
final class RefusalMessagesTest extends TestCase
{
    public function testEachReasonAndStatusHasItsWordsInBothLanguages(): void
    {
        $messages = new RefusalMessages(Translations::translator());

        self::assertSame('This session is full.', $messages->message(new RegistrationRefused(RefusalReason::Full)));
        self::assertSame('Cette séance est complète.', $messages->message(new RegistrationRefused(RefusalReason::Full), 'fr'));
        self::assertSame('This session is cancelled.', $messages->message(new RegistrationRefused(RefusalReason::Cancelled)));
        self::assertSame('Registration for this session has not opened yet.', $messages->message(new RegistrationRefused(RefusalReason::NotOpen)));
        self::assertSame('Les inscriptions à cette séance sont closes.', $messages->message(new RegistrationRefused(RefusalReason::Closed), 'fr'));
        self::assertSame('Only one seat can be taken per registration.', $messages->message(new RegistrationRefused(RefusalReason::TooManySeats, ['max' => 1])));
        self::assertSame('From 1 to 4 seats can be taken per registration.', $messages->message(new RegistrationRefused(RefusalReason::TooManySeats, ['max' => 4])));
        self::assertSame('De 1 à 4 places peuvent être prises par inscription.', $messages->message(new RegistrationRefused(RefusalReason::TooManySeats, ['max' => 4]), 'fr'));
        self::assertSame('You already have seats in this session.', $messages->message(new RegistrationRefused(RefusalReason::Registered)));
        self::assertSame('Vous avez déjà des places à cette séance.', $messages->message(new RegistrationRefused(RefusalReason::Registered), 'fr'));
        self::assertSame('Cancelled', $messages->status(RegistrationStatus::Cancelled));
        self::assertSame('Confirmée', $messages->status(RegistrationStatus::Confirmed, 'fr'));
        self::assertSame('phil_workshops', RefusalMessages::DOMAIN);
    }

    public function testBothLanguagesHaveEveryMessage(): void
    {
        $english = require Translations::file('en');
        $french = require Translations::file('fr');
        self::assertIsArray($english);
        self::assertIsArray($french);

        $expected = [
            ...array_map(static fn (RefusalReason $reason): string => 'refusal.'.$reason->value, RefusalReason::cases()),
            ...array_map(static fn (RegistrationStatus $status): string => 'status.'.$status->value, RegistrationStatus::cases()),
        ];
        self::assertSame($expected, array_keys($english));
        self::assertSame($expected, array_keys($french));
    }
}
