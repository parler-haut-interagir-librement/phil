<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use Phil\Reservation\Decision;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use Phil\ReservationBundle\Tests\Unit\Fixture\Translations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViolationMessages::class)]
final class ViolationMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{ViolationCode, array<string, int>, string, string}>
     */
    public static function violations(): iterable
    {
        yield 'quantity of one' => [ViolationCode::Quantity, ['max' => 1], 'Only one unit can be booked.', 'Une seule unité peut être réservée.'];
        yield 'quantity' => [ViolationCode::Quantity, ['max' => 8], 'From 1 to 8 units can be booked.', 'De 1 à 8 unités peuvent être réservées.'];
        yield 'past' => [ViolationCode::TooSoon, ['minutes' => 0], 'This time has passed.', 'Ce moment est passé.'];
        yield 'too soon' => [ViolationCode::TooSoon, ['minutes' => 120], 'Book at least 120 minutes ahead.', 'Réservez au moins 120 minutes à l’avance.'];
        yield 'too far' => [ViolationCode::TooFarAhead, ['minutes' => 1], 'Book at most 1 minute ahead.', 'Réservez au plus 1 minute à l’avance.'];
        yield 'too short' => [ViolationCode::TooShort, ['minutes' => 30], 'A booking lasts at least 30 minutes.', 'Une réservation dure au moins 30 minutes.'];
        yield 'too long' => [ViolationCode::TooLong, ['minutes' => 240], 'A booking lasts at most 240 minutes.', 'Une réservation dure au plus 240 minutes.'];
        yield 'off grid' => [ViolationCode::OffGrid, ['minutes' => 30], 'Bookings start and end on steps of 30 minutes.', 'Les réservations commencent et finissent par pas de 30 minutes.'];
        yield 'unavailable' => [ViolationCode::Unavailable, [], 'This time is outside the opening hours.', 'Ce moment est hors des heures d’ouverture.'];
        yield 'full' => [ViolationCode::Full, ['free' => 0], 'This time is already booked.', 'Ce moment est déjà réservé.'];
        yield 'one left' => [ViolationCode::Full, ['free' => 1], 'Only 1 unit is left at this time.', 'Il ne reste qu’une unité à ce moment.'];
        yield 'some left' => [ViolationCode::Full, ['free' => 3], 'Only 3 units are left at this time.', 'Il ne reste que 3 unités à ce moment.'];
    }

    /**
     * @param array<string, int> $parameters
     */
    #[DataProvider('violations')]
    public function testEachReasonHasItsMessage(ViolationCode $code, array $parameters, string $english, string $french): void
    {
        $messages = new ViolationMessages(Translations::translator());
        $violation = new Violation($code, $parameters);

        self::assertSame($english, $messages->message($violation));
        self::assertSame($french, $messages->message($violation, 'fr'));
    }

    public function testADecisionGivesOneMessagePerReasonInOrder(): void
    {
        $messages = new ViolationMessages(Translations::translator('fr'));
        $decision = new Decision([new Violation(ViolationCode::Unavailable), new Violation(ViolationCode::Full, ['free' => 0])]);

        self::assertSame(['Ce moment est hors des heures d’ouverture.', 'Ce moment est déjà réservé.'], $messages->messages($decision));
        self::assertSame(['This time is outside the opening hours.', 'This time is already booked.'], $messages->messages($decision, 'en'));
        self::assertSame([], $messages->messages(new Decision()));
    }
}
