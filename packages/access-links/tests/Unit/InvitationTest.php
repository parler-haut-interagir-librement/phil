<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessLinks\CampaignCounts;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Invitation;
use Phil\AccessLinks\InvitationAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invitation::class)]
#[CoversClass(CampaignCounts::class)]
#[CoversClass(InvitationAccess::class)]
final class InvitationTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testItHoldsWhatItWasGiven(): void
    {
        $created = self::at('10:00');
        $invitation = new Invitation(self::ID, 'poll:42.a_b-c', 'member-7', $created, self::at('12:00'), self::at('13:00'), self::at('11:00'), 'yes', self::at('11:30'), self::at('11:45'), 2, self::at('11:50'), self::at('11:55'));

        self::assertSame(self::ID, $invitation->id);
        self::assertSame('poll:42.a_b-c', $invitation->campaign);
        self::assertSame('member-7', $invitation->recipient);
        self::assertSame($created, $invitation->createdAt);
        self::assertEquals(self::at('11:00'), $invitation->validFrom);
        self::assertEquals(self::at('12:00'), $invitation->closesAt);
        self::assertEquals(self::at('13:00'), $invitation->expiresAt);
        self::assertSame('yes', $invitation->answer);
        self::assertEquals(self::at('11:30'), $invitation->respondedAt);
        self::assertEquals(self::at('11:45'), $invitation->answeredAt);
        self::assertSame(2, $invitation->remindersSent);
        self::assertEquals(self::at('11:50'), $invitation->nextReminderAt);
        self::assertTrue($invitation->hasResponded());
        self::assertTrue($invitation->isRevoked());
    }

    public function testTheLinkOpensFromCreationUntilClosureByDefault(): void
    {
        $invitation = new Invitation(self::ID, 'c', 'r', self::at('10:00'), self::at('12:00'));

        self::assertEquals(self::at('10:00'), $invitation->validFrom);
        self::assertEquals(self::at('12:00'), $invitation->expiresAt);
        self::assertFalse($invitation->hasResponded());
        self::assertFalse($invitation->isRevoked());
        self::assertSame(0, $invitation->remindersSent);
        self::assertFalse($invitation->opensAt(self::at('09:59')));
        self::assertTrue($invitation->opensAt(self::at('10:00')));
        self::assertTrue($invitation->isEditableAt(self::at('11:59')));
        self::assertFalse($invitation->opensAt(self::at('12:00')));
        self::assertFalse($invitation->isEditableAt(self::at('12:00')));
    }

    public function testAfterTheClosureTheLinkShowsTheAnswerUntilItExpires(): void
    {
        $invitation = new Invitation(self::ID, 'c', 'r', self::at('10:00'), self::at('12:00'), self::at('14:00'), self::at('11:00'));

        self::assertFalse($invitation->opensAt(self::at('10:30')));
        self::assertFalse($invitation->isEditableAt(self::at('10:30')));
        self::assertTrue($invitation->isEditableAt(self::at('11:00')));
        self::assertTrue($invitation->opensAt(self::at('12:00')));
        self::assertFalse($invitation->isEditableAt(self::at('12:00')));
        self::assertTrue($invitation->opensAt(self::at('13:59')));
        self::assertFalse($invitation->opensAt(self::at('14:00')));
    }

    public function testARevokedLinkOpensNothing(): void
    {
        $invitation = new Invitation(self::ID, 'c', 'r', self::at('10:00'), self::at('12:00'), revokedAt: self::at('10:30'));

        self::assertFalse($invitation->opensAt(self::at('11:00')));
        self::assertFalse($invitation->isEditableAt(self::at('11:00')));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an identifier of 31' => [['id' => str_repeat('a', 31)], 'An invitation identifier is 32 lowercase hexadecimal digits.'];
        yield 'an identifier in capitals' => [['id' => str_repeat('A', 32)], 'An invitation identifier is 32 lowercase hexadecimal digits.'];
        yield 'an identifier with a newline' => [['id' => str_repeat('a', 32)."\n"], 'An invitation identifier is 32 lowercase hexadecimal digits.'];
        yield 'an empty campaign' => [['campaign' => ''], 'A campaign is 1 to 100 letters, digits, colons, dots, hyphens and underscores.'];
        yield 'a campaign of 101' => [['campaign' => str_repeat('c', 101)], 'A campaign is 1 to 100 letters, digits, colons, dots, hyphens and underscores.'];
        yield 'a campaign with a slash' => [['campaign' => 'a/b'], 'A campaign is 1 to 100 letters, digits, colons, dots, hyphens and underscores.'];
        yield 'an email address' => [['recipient' => 'alice@example.com'], 'A recipient is a stable identifier of 1 to 191 bytes, never an email address.'];
        yield 'a blank recipient' => [['recipient' => ' '], 'A recipient is a stable identifier of 1 to 191 bytes, never an email address.'];
        yield 'a recipient of 192' => [['recipient' => str_repeat('r', 192)], 'A recipient is a stable identifier of 1 to 191 bytes, never an email address.'];
        yield 'an empty answer' => [['answer' => ''], 'An answer is 1 to 4096 bytes of UTF-8.'];
        yield 'an answer of 4097' => [['answer' => str_repeat('a', 4097)], 'An answer is 1 to 4096 bytes of UTF-8.'];
        yield 'an answer outside UTF-8' => [['answer' => "\xFF"], 'An answer is 1 to 4096 bytes of UTF-8.'];
        yield 'a closure at the opening' => [['closesAt' => '11:00', 'validFrom' => '11:00'], 'An invitation closes after its link opens.'];
        yield 'a closure before creation' => [['closesAt' => '09:00'], 'An invitation closes after its link opens.'];
        yield 'an expiry before closure' => [['expiresAt' => '11:59'], 'The link of an invitation expires at its closure or after.'];
        yield 'reminders below zero' => [['remindersSent' => -1], 'The reminders sent are a whole number from zero.'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalid')]
    public function testItRefusesValuesOutOfBounds(array $values, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        $at = static fn (string $key): ?DateTimeImmutable => isset($values[$key]) && \is_string($values[$key]) ? self::at($values[$key]) : null;
        $id = $values['id'] ?? self::ID;
        $campaign = $values['campaign'] ?? 'c';
        $recipient = $values['recipient'] ?? 'r';
        $answer = $values['answer'] ?? null;
        $sent = $values['remindersSent'] ?? 0;
        self::assertIsString($id);
        self::assertIsString($campaign);
        self::assertIsString($recipient);
        self::assertTrue(null === $answer || \is_string($answer));
        self::assertIsInt($sent);

        new Invitation($id, $campaign, $recipient, self::at('10:00'), $at('closesAt') ?? self::at('12:00'), $at('expiresAt'), $at('validFrom'), $answer, remindersSent: $sent);
    }

    public function testItAcceptsTheBounds(): void
    {
        $invitation = new Invitation(self::ID, str_repeat('c', 100), str_repeat('r', 191), self::at('10:00'), self::at('10:01'), self::at('10:01'), answer: str_repeat('é', 2048));

        self::assertSame(4096, \strlen((string) $invitation->answer));
    }

    public function testARecipientAndAnAnswerAreCheckedOnTheirOwn(): void
    {
        Invitation::checkRecipient('member-7');
        Invitation::checkAnswer('yes');

        $this->expectException(InvalidConfiguration::class);
        Invitation::checkAnswer('');
    }

    public function testARecipientThatIsAnEmailAddressIsRefused(): void
    {
        $this->expectException(InvalidConfiguration::class);

        Invitation::checkRecipient('someone@example.org');
    }

    public function testAnIdentifierIsThirtyTwoHexadecimalDigitsFromItsStart(): void
    {
        $this->expectException(InvalidConfiguration::class);

        new Invitation('x'.self::ID, 'c', 'r', self::at('10:00'), self::at('12:00'));
    }

    public function testThePendingInvitationsAreNeitherAnsweredNorRevoked(): void
    {
        $counts = new CampaignCounts(10, 4, 1);

        self::assertSame([10, 4, 1, 5], [$counts->invited, $counts->responded, $counts->revoked, $counts->pending()]);
    }

    public function testAnAccessCarriesItsInvitationAndWhetherItIsEditable(): void
    {
        $invitation = new Invitation(self::ID, 'c', 'r', self::at('10:00'), self::at('12:00'));
        $access = new InvitationAccess($invitation, true);

        self::assertSame($invitation, $access->invitation);
        self::assertTrue($access->editable);
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 '.$time.':00 UTC');
    }
}
