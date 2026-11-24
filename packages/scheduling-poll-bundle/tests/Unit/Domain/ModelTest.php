<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchedulingPoll::class)]
#[CoversClass(Slot::class)]
#[CoversClass(Respondent::class)]
#[CoversClass(InvalidSchedulingPoll::class)]
final class ModelTest extends TestCase
{
    private const string ID = '0195a6c4-0000-7000-8000-000000000001';

    private const string OTHER = '0195a6c4-0000-7000-8000-000000000002';

    private const string INVITATION = '0123456789abcdef0123456789abcdef';

    public function testAPollKeepsItsMomentsInUtcAndItsTextsTrimmed(): void
    {
        $poll = new SchedulingPoll(self::ID, '  Game night  ', ResourceReference::of('user', 'ada'), 'America/Toronto', new DateTimeImmutable('2026-11-28 05:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-21 10:00 UTC'), ' ', ' ');

        self::assertSame('Game night', $poll->title());
        self::assertNull($poll->description());
        self::assertNull($poll->location());
        self::assertSame('2026-11-28 10:00:00 UTC', $poll->deadline()->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-21 10:00:00 UTC', $poll->createdAt()->format('Y-m-d H:i:s T'));
        self::assertSame('America/Toronto', $poll->timezone()->getName());
        self::assertTrue($poll->organizerSeesAnswers());
        self::assertSame(ResultsVisibility::Always, $poll->resultsVisibility());
        self::assertTrue($poll->isOrganizedBy(ResourceReference::of('user', 'ada')));
        self::assertFalse($poll->isOrganizedBy(ResourceReference::of('user', 'bob')));
        self::assertNull($poll->closedAt());
        self::assertNull($poll->closedBy());
        self::assertNull($poll->chosenSlot());
    }

    public function testAPollIsOpenUntilItsDeadlineOrItsClosing(): void
    {
        $poll = $this->poll();

        self::assertTrue($poll->isOpenAt(new DateTimeImmutable('2026-11-28 09:59:59 UTC')));
        self::assertFalse($poll->isOpenAt(new DateTimeImmutable('2026-11-28 10:00:00 UTC')));
        self::assertFalse($poll->isClosed());

        $poll->close(new DateTimeImmutable('2026-11-22 08:00', new DateTimeZone('Europe/Paris')), ClosedBy::Organizer, self::OTHER);

        self::assertTrue($poll->isClosed());
        self::assertFalse($poll->isOpenAt(new DateTimeImmutable('2026-11-22 10:00 UTC')));
        self::assertSame('2026-11-22 07:00:00 UTC', $poll->closedAt()?->format('Y-m-d H:i:s T'));
        self::assertSame(ClosedBy::Organizer, $poll->closedBy());
        self::assertSame(self::OTHER, $poll->chosenSlot());
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?string, ?string}>
     */
    public static function invalidPolls(): iterable
    {
        yield 'not a UUID' => ['nope', 'Game night', 'UTC', '2026-11-28', null, 'A scheduling poll is identified by a UUID.'];
        yield 'an empty title' => [self::ID, ' ', 'UTC', '2026-11-28', null, 'The title of a scheduling poll is 1 to 200 characters.'];
        yield 'a title too long' => [self::ID, str_repeat('é', 201), 'UTC', '2026-11-28', null, 'The title of a scheduling poll is 1 to 200 characters.'];
        yield 'a description too long' => [self::ID, 'Game night', 'UTC', '2026-11-28', str_repeat('é', 4001), 'The description of a scheduling poll is 4000 characters at most, its place 255.'];
        yield 'an unknown zone' => [self::ID, 'Game night', 'Mars/Olympus', '2026-11-28', null, '"Mars/Olympus" is not a time zone of the IANA database, such as "America/Toronto".'];
        yield 'a deadline at the creation' => [self::ID, 'Game night', 'UTC', '2026-11-21 10:00', null, 'The deadline of a scheduling poll comes after its creation.'];
    }

    #[DataProvider('invalidPolls')]
    public function testAnInvalidPollIsRefused(string $id, string $title, string $zone, string $deadline, ?string $description, string $message): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because($message));

        new SchedulingPoll($id, $title, ResourceReference::of('user', 'ada'), $zone, new DateTimeImmutable($deadline.' UTC'), new DateTimeImmutable('2026-11-21 10:00 UTC'), $description);
    }

    public function testTheBoundsOfAPollAreInclusive(): void
    {
        $poll = new SchedulingPoll(self::ID, str_repeat('é', 200), ResourceReference::of('user', 'ada'), 'UTC', new DateTimeImmutable('2026-11-21 10:00:01 UTC'), new DateTimeImmutable('2026-11-21 10:00 UTC'), str_repeat('é', 4000), str_repeat('é', 255));

        self::assertSame(200, mb_strlen($poll->title()));
        self::assertSame(255, mb_strlen((string) $poll->location()));

        $this->expectExceptionObject(InvalidSchedulingPoll::because('The description of a scheduling poll is 4000 characters at most, its place 255.'));
        new SchedulingPoll(self::ID, 'Game night', ResourceReference::of('user', 'ada'), 'UTC', new DateTimeImmutable('2026-11-28 UTC'), new DateTimeImmutable('2026-11-21 UTC'), null, str_repeat('é', 256));
    }

    public function testALongTimeZoneNameIsCutInTheMessage(): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because(\sprintf('"%s" is not a time zone of the IANA database, such as "America/Toronto".', str_repeat('x', 64))));

        SchedulingPoll::zone(str_repeat('x', 65));
    }

    public function testALongTimeZoneNameIsCutByCharactersNotByBytes(): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because(\sprintf('"%s" is not a time zone of the IANA database, such as "America/Toronto".', str_repeat('é', 64))));

        SchedulingPoll::zone(str_repeat('é', 70));
    }

    public function testTheColumnsAreSizedAsTheTablesNeedThem(): void
    {
        $entityManager = Fixtures::entityManager(schema: false);
        $column = static function (string $class, string $field) use ($entityManager): array {
            $mapping = $entityManager->getClassMetadata($class)->getFieldMapping($field);

            return [$mapping->length, $mapping->options['fixed'] ?? false, $mapping->nullable ?? false];
        };

        self::assertSame([36, true, false], $column(SchedulingPoll::class, 'id'));
        self::assertSame([16, false, false], $column(SchedulingPoll::class, 'results'));
        self::assertSame([36, true, false], $column(Slot::class, 'id'));
        self::assertSame([36, true, false], $column(Slot::class, 'poll'));
        self::assertSame([36, true, false], $column(Respondent::class, 'id'));
        self::assertSame([36, true, false], $column(Respondent::class, 'poll'));
        self::assertSame([32, true, false], $column(Respondent::class, 'invitation'));
        self::assertSame([16, false, true], $column(Respondent::class, 'locale'));
    }

    public function testASlotKeepsItsMomentsInUtcAndShowsThemInAnyZone(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $slot = new Slot(self::OTHER, self::ID, 3, new DateTimeImmutable('2026-12-01 19:00', $toronto), new DateTimeImmutable('2026-12-15 19:00', $toronto));

        self::assertSame('2026-12-02 00:00 UTC', $slot->startsAt()->format('Y-m-d H:i T'));
        self::assertSame('2026-12-01 19:00 EST', $slot->startsAt($toronto)->format('Y-m-d H:i T'));
        self::assertSame('2026-12-16 00:00 UTC', $slot->endsAt()->format('Y-m-d H:i T'));
        self::assertSame('2026-12-15 19:00 EST', $slot->endsAt($toronto)->format('Y-m-d H:i T'));
        self::assertSame(3, $slot->position);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalidSlots(): iterable
    {
        yield 'an empty date' => [self::OTHER, self::ID, '2026-12-01 19:00', 'A proposed date ends after it starts, fourteen days later at the most.'];
        yield 'a date too long' => [self::OTHER, self::ID, '2026-12-15 19:00:01', 'A proposed date ends after it starts, fourteen days later at the most.'];
        yield 'a slot not a UUID' => ['nope', self::ID, '2026-12-01 20:00', 'A proposed date and its poll are identified by UUIDs.'];
        yield 'a poll not a UUID' => [self::OTHER, 'nope', '2026-12-01 20:00', 'A proposed date and its poll are identified by UUIDs.'];
    }

    #[DataProvider('invalidSlots')]
    public function testAnInvalidSlotIsRefused(string $id, string $poll, string $end, string $message): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because($message));

        new Slot($id, $poll, 0, new DateTimeImmutable('2026-12-01 19:00 UTC'), new DateTimeImmutable($end.' UTC'));
    }

    public function testARespondentKeepsWhatTheyGaveAndTheirAnswers(): void
    {
        $respondent = new Respondent(self::OTHER, self::ID, self::INVITATION, " Bob\u{200B}\tSmith\n ", new DateTimeImmutable('2026-11-21 05:00', new DateTimeZone('America/Toronto')), ' Bob@Example.ORG ', ResourceReference::of('user', 'bob'), 'fr_CA');

        self::assertSame('Bob Smith', $respondent->name());
        self::assertSame('Bob@example.org', $respondent->email());
        self::assertSame('user:bob', $respondent->account()?->toString());
        self::assertSame('fr_CA', $respondent->locale);
        self::assertSame('2026-11-21 10:00:00 UTC', $respondent->joinedAt()->format('Y-m-d H:i:s T'));
        self::assertFalse($respondent->hasAnswered());
        self::assertNull($respondent->answeredAt());
        self::assertSame([], $respondent->answers());
        self::assertNull($respondent->answerTo(self::ID));

        $respondent->answer([self::ID => Availability::IfNeedBe, self::OTHER => Availability::No], 'Europe/Paris', new DateTimeImmutable('2026-11-22 08:00', new DateTimeZone('Europe/Paris')));

        self::assertTrue($respondent->hasAnswered());
        self::assertSame('2026-11-22 07:00:00 UTC', $respondent->answeredAt()?->format('Y-m-d H:i:s T'));
        self::assertSame(Availability::IfNeedBe, $respondent->answerTo(self::ID));
        self::assertSame([self::ID => Availability::IfNeedBe, self::OTHER => Availability::No], $respondent->answers());
        self::assertSame('Europe/Paris', $respondent->timezone());
    }

    public function testARespondentIsRevokedNotifiedAndErasedOnce(): void
    {
        $respondent = new Respondent(self::OTHER, self::ID, self::INVITATION, 'Bob', new DateTimeImmutable('2026-11-21 UTC'), 'bob@example.org');
        $respondent->answer([], 'Europe/Paris', new DateTimeImmutable('2026-11-21 UTC'));

        $respondent->revoke(new DateTimeImmutable('2026-11-22 05:00', new DateTimeZone('America/Toronto')));
        $respondent->revoke(new DateTimeImmutable('2026-11-23 UTC'));
        self::assertTrue($respondent->isRevoked());
        self::assertSame('2026-11-22 10:00:00 UTC', $respondent->revokedAt()?->format('Y-m-d H:i:s T'));

        $respondent->notified(new DateTimeImmutable('2026-11-24 05:00', new DateTimeZone('America/Toronto')));
        self::assertSame('2026-11-24 10:00:00 UTC', $respondent->notifiedAt()?->format('Y-m-d H:i:s T'));
        $respondent->releaseNotice();
        self::assertNull($respondent->notifiedAt());

        $respondent->erase(new DateTimeImmutable('2026-12-25 05:00', new DateTimeZone('America/Toronto')));
        $respondent->erase(new DateTimeImmutable('2026-12-26 UTC'));
        self::assertNull($respondent->name());
        self::assertNull($respondent->email());
        self::assertNull($respondent->timezone());
        self::assertSame('2026-12-25 10:00:00 UTC', $respondent->erasedAt()?->format('Y-m-d H:i:s T'));
    }

    public function testAnUnknownAnswerReadBackIsLeftOut(): void
    {
        $respondent = new Respondent(self::OTHER, self::ID, self::INVITATION, 'Bob', new DateTimeImmutable('2026-11-21 UTC'));
        $stored = [self::ID => 'maybe', self::OTHER => 'yes', 12 => 'no'];
        (fn (): array => $this->answers = $stored)->call($respondent);

        self::assertNull($respondent->answerTo(self::ID));
        self::assertSame([self::OTHER => Availability::Yes, '12' => Availability::No], $respondent->answers());
    }

    /**
     * @return iterable<string, array{string, string, string, ?string, ?string, string}>
     */
    public static function invalidRespondents(): iterable
    {
        $uuids = 'A respondent and its poll are identified by UUIDs, its invitation by 32 hexadecimal digits.';

        yield 'a respondent not a UUID' => ['nope', self::ID, self::INVITATION, null, null, $uuids];
        yield 'a poll not a UUID' => [self::OTHER, 'nope', self::INVITATION, null, null, $uuids];
        yield 'an invitation too short' => [self::OTHER, self::ID, substr(self::INVITATION, 1), null, null, $uuids];
        yield 'an invitation in capitals' => [self::OTHER, self::ID, strtoupper(self::INVITATION), null, null, $uuids];
        yield 'an invitation after some text' => [self::OTHER, self::ID, 'zz'.self::INVITATION, null, null, $uuids];
        yield 'an invitation before some text' => [self::OTHER, self::ID, self::INVITATION.'zz', null, null, $uuids];
        yield 'an invitation and a line break' => [self::OTHER, self::ID, self::INVITATION."\n", null, null, $uuids];
        yield 'an address' => [self::OTHER, self::ID, self::INVITATION, 'bob@', null, 'The address of a respondent is an email address, such as "ada@example.org".'];
        yield 'a locale' => [self::OTHER, self::ID, self::INVITATION, null, 'French', 'The locale of a respondent is a language tag, such as "fr" or "fr_CA".'];
        yield 'a locale and some text' => [self::OTHER, self::ID, self::INVITATION, null, 'fr!', 'The locale of a respondent is a language tag, such as "fr" or "fr_CA".'];
        yield 'a locale and a line break' => [self::OTHER, self::ID, self::INVITATION, null, "fr\n", 'The locale of a respondent is a language tag, such as "fr" or "fr_CA".'];
    }

    #[DataProvider('invalidRespondents')]
    public function testAnInvalidRespondentIsRefused(string $id, string $poll, string $invitation, ?string $email, ?string $locale, string $message): void
    {
        $this->expectExceptionObject(InvalidSchedulingPoll::because($message));

        new Respondent($id, $poll, $invitation, 'Bob', new DateTimeImmutable('2026-11-21 UTC'), $email, null, $locale);
    }

    public function testANameIsOneToAHundredCharacters(): void
    {
        self::assertSame(str_repeat('é', 100), Respondent::checkName(str_repeat('é', 100)));
        self::assertNull(Respondent::checkEmail(' '));
        self::assertNull(Respondent::checkEmail(null));

        foreach (["\u{200B}\t", str_repeat('é', 101)] as $name) {
            try {
                Respondent::checkName($name);
                self::fail('A name out of bounds was kept.');
            } catch (InvalidSchedulingPoll $refused) {
                self::assertSame('The name of a respondent is 1 to 100 characters.', $refused->getMessage());
            }
        }
    }

    private function poll(): SchedulingPoll
    {
        return new SchedulingPoll(self::ID, 'Game night', ResourceReference::of('user', 'ada'), 'UTC', new DateTimeImmutable('2026-11-28 10:00 UTC'), new DateTimeImmutable('2026-11-21 10:00 UTC'), organizerSeesAnswers: false, results: ResultsVisibility::Managers);
    }
}
