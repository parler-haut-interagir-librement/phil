<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Event\ClosedWithoutDate;
use Phil\SchedulingPollBundle\Application\Event\DateChosen;
use Phil\SchedulingPollBundle\Application\Exception\AnswerRefused;
use Phil\SchedulingPollBundle\Application\Exception\NotFound;
use Phil\SchedulingPollBundle\Application\Exception\NotOrganizer;
use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\RespondentAccess;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;
use Phil\SchedulingPollBundle\Application\SchedulingPolls;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineSchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemoryRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemorySchedulingPollRepository;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\FlakyInvitations;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\ForgetfulRespondents;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\GhostPolls;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\RacingPolls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;

/**
 * The polls on both stores: in memory and on SQLite, whose conditional writes the tests hold to the same contract.
 */
#[CoversClass(SchedulingPolls::class)]
#[CoversClass(InMemorySchedulingPollRepository::class)]
#[CoversClass(InMemoryRespondentRepository::class)]
#[CoversClass(DoctrineSchedulingPollRepository::class)]
#[CoversClass(DoctrineRespondentRepository::class)]
#[CoversClass(AnswerRefused::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(NotOrganizer::class)]
#[CoversClass(RespondentAccess::class)]
final class SchedulingPollsTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): Fixtures}>
     */
    public static function stores(): iterable
    {
        yield 'memory' => [static fn (): Fixtures => Fixtures::memory()];
        yield 'doctrine' => [static fn (): Fixtures => Fixtures::doctrine()];
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAPollKeepsItsDatesInOrderAndInUtc(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll(['2026-12-03 19:00', '2026-12-01 19:00']);
        $read = $test->polls->get($poll->id);
        $slots = $test->polls->slots($poll->id);

        self::assertSame('Game night', $read->title());
        self::assertSame('Bring dice.', $read->description());
        self::assertSame(Fixtures::organizer()->toString(), $read->organizer()->toString());
        self::assertSame('2026-11-28 10:00:00', $read->deadline()->format('Y-m-d H:i:s'));
        self::assertCount(2, $slots);
        self::assertSame('2026-12-04 00:00 UTC', $slots[0]->startsAt()->format('Y-m-d H:i T'));
        self::assertSame('2026-12-02 02:00 UTC', $slots[1]->endsAt()->format('Y-m-d H:i T'));
        self::assertSame([0, 1], [$slots[0]->position, $slots[1]->position]);
        self::assertFalse($read->isClosed());
    }

    public function testAPollProposesTwoToAHundredDates(): void
    {
        $test = Fixtures::memory();
        $slot = static fn (int $hour): ProposedSlot => new ProposedSlot(new DateTimeImmutable('2026-12-01 00:00 UTC')->modify("+{$hour} hours"), new DateTimeImmutable('2026-12-01 01:00 UTC')->modify("+{$hour} hours"));
        $draft = static fn (int $count): SchedulingPollDraft => new SchedulingPollDraft('Game night', array_map($slot, range(0, $count - 1)), 'UTC', new DateTimeImmutable('2026-11-28 10:00 UTC'));

        self::assertCount(100, $test->polls->slots($test->polls->create($draft(100), Fixtures::organizer())->id));
        self::assertCount(2, $test->polls->slots($test->polls->create($draft(2), Fixtures::organizer())->id));

        foreach ([1, 101] as $count) {
            try {
                $test->polls->create($draft($count), Fixtures::organizer());
                self::fail(\sprintf('A poll of %d dates was created.', $count));
            } catch (InvalidSchedulingPoll $refused) {
                self::assertSame(\sprintf('A scheduling poll proposes 2 to 100 dates, not %d.', $count), $refused->getMessage());
            }
        }
    }

    public function testTwoDatesStartingAtTheSameMomentAreRefused(): void
    {
        $test = Fixtures::memory();
        $toronto = new DateTimeImmutable('2026-12-01 19:00', new DateTimeZone('America/Toronto'));
        $paris = new DateTimeImmutable('2026-12-02 01:00', new DateTimeZone('Europe/Paris'));

        $this->expectExceptionObject(InvalidSchedulingPoll::because('Two dates of a scheduling poll start at the same moment.'));

        $test->polls->create(new SchedulingPollDraft('Game night', [new ProposedSlot($toronto, $toronto->modify('+1 hour')), new ProposedSlot($paris, $paris->modify('+2 hours'))], 'UTC', new DateTimeImmutable('2026-11-28 10:00 UTC')), Fixtures::organizer());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAnUnknownPollIsNotFound(Closure $fixtures): void
    {
        $this->expectExceptionObject(NotFound::poll('0195a6c4-0000-7000-8000-000000000000'));

        $fixtures()->polls->get('0195a6c4-0000-7000-8000-000000000000');
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testSomeoneJoinsByNameAloneAndAnswersByTheirLink(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first, $second, $third] = $test->slotIds($poll);
        $issued = $test->polls->join($poll->id, '  Bob  ', locale: 'fr_CA');

        self::assertStringStartsWith(Fixtures::PAGE.'?token=', $issued->url->toString());
        self::assertSame(SchedulingPolls::CAMPAIGN_PREFIX.$poll->id, $issued->invitation->campaign);
        self::assertSame('2026-11-28 10:00:00', $issued->invitation->closesAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-28 10:00:00', $issued->invitation->expiresAt->format('Y-m-d H:i:s'));

        $access = $test->polls->open($issued->token);
        self::assertInstanceOf(RespondentAccess::class, $access);
        self::assertTrue($access->editable);
        self::assertSame('Bob', $access->respondent->name());
        self::assertSame('fr_CA', $access->respondent->locale);
        self::assertNull($access->respondent->email());
        self::assertSame($poll->id, $access->poll->id);
        self::assertCount(3, $access->slots);
        self::assertFalse($access->respondent->hasAnswered());

        $test->clock->modify('+1 hour');
        $respondent = $test->polls->answer($issued->token, [$first => Availability::Yes, $second => Availability::IfNeedBe], 'Europe/Paris');

        self::assertSame([$first => Availability::Yes, $second => Availability::IfNeedBe, $third => Availability::No], $respondent->answers());
        self::assertSame('Europe/Paris', $respondent->timezone());
        self::assertSame('2026-11-21 11:00:00', $respondent->answeredAt()?->format('Y-m-d H:i:s'));
        self::assertSame('yin', $test->links->invitation($issued->invitation->id)->answer);

        $changed = $test->polls->answer($issued->token, [$third => Availability::Yes]);
        self::assertSame([$first => Availability::No, $second => Availability::No, $third => Availability::Yes], $changed->answers());
        self::assertNull($changed->timezone());
        self::assertSame([$respondent->id], array_map(static fn (Respondent $one): string => $one->id, $test->polls->respondents($poll->id)));
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheOrganizerInvitesByNameAndAddressAndIssuesAnotherLink(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $issued = $test->polls->invite($poll->id, Fixtures::organizer(), 'Carol', 'carol@EXAMPLE.org', 'en');
        $access = $this->opened($test, $issued->token);

        self::assertSame('carol@example.org', $access->respondent->email());
        self::assertSame('en', $access->respondent->locale);

        $again = $test->polls->reissue($poll->id, Fixtures::organizer(), $access->respondent->id);
        self::assertNotSame($issued->token, $again->token);
        self::assertSame($access->respondent->id, $this->opened($test, $again->token)->respondent->id);
        self::assertSame($access->respondent->id, $this->opened($test, $issued->token)->respondent->id);

        $this->expectExceptionObject(NotOrganizer::of($poll->id));
        $test->polls->invite($poll->id, ResourceReference::of('user', 'mallory'), 'Dave');
    }

    public function testOnlyTheOrganizerIssuesAnotherLink(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll();
        $respondent = $test->polls->open($test->polls->join($poll->id, 'Bob')->token)?->respondent->id;

        $this->expectExceptionObject(NotOrganizer::of($poll->id));
        $test->polls->reissue($poll->id, ResourceReference::of('user', 'mallory'), (string) $respondent);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testANameOrAnAddressRefusedLeavesNoInvitation(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();

        foreach ([['', null], ['Bob', 'not an address']] as [$name, $email]) {
            try {
                $test->polls->join($poll->id, $name, $email);
                self::fail('A respondent was refused nothing.');
            } catch (InvalidSchedulingPoll) {
            }
        }

        self::assertSame(0, $test->links->counts(SchedulingPolls::CAMPAIGN_PREFIX.$poll->id)->invited);
        self::assertSame([], $test->polls->respondents($poll->id));
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAnAnswerToADateThePollDoesNotProposeOrInAnUnknownZoneIsRefused(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $token = $test->polls->join($poll->id, 'Bob')->token;

        try {
            $test->polls->answer($token, ['0195a6c4-0000-7000-8000-000000000000' => Availability::Yes]);
            self::fail('An answer to an unknown date was kept.');
        } catch (InvalidSchedulingPoll $invalidSchedulingPoll) {
            self::assertSame('An answer names a date the scheduling poll does not propose.', $invalidSchedulingPoll->getMessage());
        }

        try {
            $test->polls->answer($token, [], 'Mars/Olympus');
            self::fail('An unknown time zone was kept.');
        } catch (InvalidSchedulingPoll) {
        }

        self::assertFalse($this->opened($test, $token)->respondent->hasAnswered());
        self::assertNull($test->links->invitation($this->opened($test, $token)->respondent->invitation)->answer);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAMalformedOrUnknownLinkOpensNothing(Closure $fixtures): void
    {
        $test = $fixtures();
        $test->poll();

        self::assertNull($test->polls->open('not a token'));
        self::assertNull($test->polls->open(str_repeat('A', 43)));

        $this->expectExceptionObject(AnswerRefused::linkNotUsable());
        $test->polls->answer(str_repeat('A', 43), []);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testARevokedLinkOpensNothingAndItsAnswersAreNotCounted(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $bob = $test->polls->join($poll->id, 'Bob');
        $carol = $test->polls->join($poll->id, 'Carol');
        $test->polls->answer($bob->token, [$first => Availability::Yes]);
        $test->polls->answer($carol->token, [$first => Availability::Yes]);

        $bobId = $this->opened($test, $bob->token)->respondent->id;

        self::assertTrue($test->polls->revoke($poll->id, Fixtures::organizer(), $bobId));
        self::assertFalse($test->polls->revoke($poll->id, Fixtures::organizer(), $bobId));
        self::assertNull($test->polls->open($bob->token));
        self::assertTrue($test->links->invitation($bob->invitation->id)->isRevoked());
        self::assertSame(1, $test->polls->tally($poll->id)[0]->yes);

        try {
            $test->polls->answer($bob->token, [$first => Availability::No]);
            self::fail('A revoked link answered.');
        } catch (AnswerRefused $answerRefused) {
            self::assertSame('This link opens no scheduling poll.', $answerRefused->getMessage());
        }

        $this->expectExceptionObject(NotOrganizer::of($poll->id));
        $test->polls->revoke($poll->id, ResourceReference::of('user', 'mallory'), $bobId);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testARespondentOfAnotherPollIsNotFound(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $other = $test->poll();
        $respondent = (string) $test->polls->open($test->polls->join($other->id, 'Bob')->token)?->respondent->id;

        $this->expectExceptionObject(NotFound::respondent($respondent));
        $test->polls->revoke($poll->id, Fixtures::organizer(), $respondent);
    }

    public function testAPollTakesItsMostRespondents(): void
    {
        $test = Fixtures::memory(maxRespondents: 2);
        $poll = $test->poll();
        $test->polls->join($poll->id, 'Bob');
        $test->polls->join($poll->id, 'Carol');

        $this->expectExceptionObject(AnswerRefused::full(2));
        $test->polls->join($poll->id, 'Dave');
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheOrganizerClosesOnADateAndItIsAnnouncedOnce(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [, $second] = $test->slotIds($poll);
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $test->clock->modify('+2 hours');

        self::assertTrue($test->polls->close($poll->id, Fixtures::organizer(), $second));

        $closed = $test->polls->get($poll->id);
        self::assertTrue($closed->isClosed());
        self::assertSame(ClosedBy::Organizer, $closed->closedBy());
        self::assertSame($second, $closed->chosenSlot());
        self::assertSame('2026-11-21 12:00:00', $closed->closedAt()?->format('Y-m-d H:i:s'));
        self::assertEquals([new AnnounceChosenDate($poll->id)], $test->bus->messages);
        self::assertEquals([new DateChosen($poll->id, $second, ClosedBy::Organizer)], $test->events->events);

        self::assertFalse($test->polls->close($poll->id, Fixtures::organizer(), $second));
        $test->clock->modify('+30 days');
        self::assertSame(0, $test->polls->closeDue());
        self::assertCount(1, $test->bus->messages);
        self::assertCount(1, $test->events->events);

        $this->expectExceptionObject(AnswerRefused::closed());
        $test->polls->answer($token, []);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAnAnswerAfterTheOrganizerClosedIsRefused(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $test->polls->close($poll->id, Fixtures::organizer(), $first);

        self::assertFalse($this->opened($test, $token)->editable);

        try {
            $test->polls->answer($token, [$first => Availability::Yes]);
            self::fail('An answer after the closing was kept.');
        } catch (AnswerRefused $answerRefused) {
            self::assertSame('This scheduling poll is closed: its answers no longer change.', $answerRefused->getMessage());
        }

        self::assertFalse($this->opened($test, $token)->respondent->hasAnswered());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAnAnswerOrANewRespondentAfterTheDeadlineIsRefused(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $test->clock->modify('2026-11-28 10:00:00 UTC');

        self::assertFalse($this->opened($test, $token)->editable);

        try {
            $test->polls->answer($token, []);
            self::fail('An answer at the deadline was kept.');
        } catch (AnswerRefused $answerRefused) {
            self::assertSame('This scheduling poll is closed: its answers no longer change.', $answerRefused->getMessage());
        }

        $this->expectExceptionObject(AnswerRefused::closed());
        $test->polls->join($poll->id, 'Carol');
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheOrganizerChoosesOnlyADateOfThePoll(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $other = $test->slotIds($test->poll())[0];

        try {
            $test->polls->close($poll->id, Fixtures::organizer(), $other);
            self::fail('A date of another poll was chosen.');
        } catch (InvalidSchedulingPoll $invalidSchedulingPoll) {
            self::assertSame('The date chosen is not one the scheduling poll proposes.', $invalidSchedulingPoll->getMessage());
        }

        self::assertFalse($test->polls->get($poll->id)->isClosed());

        $this->expectExceptionObject(NotOrganizer::of($poll->id));
        $test->polls->close($poll->id, ResourceReference::of('user', 'mallory'), $test->slotIds($poll)[0]);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAtTheDeadlineTheBestDateIsChosen(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $early = $test->poll(['2026-12-10 19:00', '2026-12-11 19:00'], deadline: '2026-11-25 10:00:00 UTC');
        [$first, $second, $third] = $test->slotIds($poll);
        $answers = [
            [$first => Availability::Yes, $second => Availability::Yes, $third => Availability::IfNeedBe],
            [$first => Availability::IfNeedBe, $second => Availability::Yes, $third => Availability::Yes],
            [$first => Availability::Yes, $second => Availability::IfNeedBe, $third => Availability::Yes],
            [$first => Availability::No, $second => Availability::IfNeedBe, $third => Availability::Yes],
        ];

        foreach ($answers as $index => $answer) {
            $test->polls->answer($test->polls->join($poll->id, 'Respondent '.$index)->token, $answer);
        }

        $test->clock->modify('2026-11-27 10:00:00 UTC');
        self::assertSame(1, $test->polls->closeDue());
        self::assertTrue($test->polls->get($early->id)->isClosed());
        self::assertFalse($test->polls->get($poll->id)->isClosed());
        self::assertEquals([new ClosedWithoutDate($early->id)], $test->events->events);
        self::assertSame([], $test->bus->messages);

        $test->clock->modify('2026-11-28 10:00:00 UTC');
        self::assertSame(1, $test->polls->closeDue());

        $closed = $test->polls->get($poll->id);
        self::assertSame($third, $closed->chosenSlot());
        self::assertSame(ClosedBy::Deadline, $closed->closedBy());
        self::assertEquals(new DateChosen($poll->id, $third, ClosedBy::Deadline), $test->events->events[1]);
        self::assertEquals([new AnnounceChosenDate($poll->id)], $test->bus->messages);
        self::assertSame(0, $test->polls->closeDue());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheDeadlineClosesTheMostOverduePollsFirstWithinTheLimit(Closure $fixtures): void
    {
        $test = $fixtures();
        $late = $test->poll(deadline: '2026-11-27 10:00:00 UTC');
        $early = $test->poll(deadline: '2026-11-26 10:00:00 UTC');
        $test->clock->modify('2026-11-28 10:00:00 UTC');

        self::assertSame(1, $test->polls->closeDue(1));
        self::assertTrue($test->polls->get($early->id)->isClosed());
        self::assertFalse($test->polls->get($late->id)->isClosed());
        self::assertSame(1, $test->polls->closeDue(1));
        self::assertTrue($test->polls->get($late->id)->isClosed());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAClosingByHandRacingTheDeadlineAnnouncesTheDateOnce(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first, $second] = $test->slotIds($poll);
        $test->polls->answer($test->polls->join($poll->id, 'Bob')->token, [$second => Availability::Yes]);
        $test->clock->modify('2026-11-28 10:00:00 UTC');

        $organizer = new SchedulingPolls(new RacingPolls($test->pollStore, static fn (): int => $test->polls->closeDue()), $test->respondentStore, $test->links, $test->clock, new AvailabilityCount(), $test->bus, $test->events);

        self::assertFalse($organizer->close($poll->id, Fixtures::organizer(), $first));

        self::assertSame($second, $test->polls->get($poll->id)->chosenSlot());
        self::assertSame(ClosedBy::Deadline, $test->polls->get($poll->id)->closedBy());
        self::assertEquals([new AnnounceChosenDate($poll->id)], $test->bus->messages);
        self::assertEquals([new DateChosen($poll->id, $second, ClosedBy::Deadline)], $test->events->events);
    }

    public function testTwoProcessesClosingOneDatabaseAnnounceTheDateOnce(): void
    {
        $directory = sys_get_temp_dir().'/phil-scheduling-poll-test';

        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $path = tempnam($directory, 'race');
        self::assertIsString($path);

        try {
            $manager = Fixtures::doctrine(Fixtures::entityManager($path));
            $task = Fixtures::doctrine(Fixtures::entityManager($path, schema: false), $manager->clock);
            $poll = $manager->poll();
            [$first] = $manager->slotIds($poll);
            $manager->clock->modify('2026-11-28 10:00:00 UTC');
            $organizer = new SchedulingPolls(new RacingPolls($manager->pollStore, static fn (): int => $task->polls->closeDue()), $manager->respondentStore, $manager->links, $manager->clock, new AvailabilityCount(), $manager->bus, $manager->events);

            self::assertFalse($organizer->close($poll->id, Fixtures::organizer(), $first));

            self::assertSame([], $manager->bus->messages);
            self::assertSame([], $task->bus->messages);
            self::assertEquals([new ClosedWithoutDate($poll->id)], $task->events->events);
            self::assertSame(ClosedBy::Deadline, $manager->polls->get($poll->id)->closedBy());
            self::assertSame(0, $manager->polls->closeDue());
        } finally {
            unlink($path);
        }
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheNamesAndAddressesAreErasedAfterTheLifetimeOfALink(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        $open = $test->poll();
        [$first] = $test->slotIds($poll);
        $bob = $test->polls->join($poll->id, 'Bob', 'bob@example.org');
        $test->polls->answer($bob->token, [$first => Availability::Yes], 'Europe/Paris');
        $carol = $test->polls->join($open->id, 'Carol', 'carol@example.org');
        $test->polls->close($poll->id, Fixtures::organizer(), $first);

        $test->clock->modify('+30 days');
        self::assertSame(0, $test->polls->eraseExpired());

        $test->clock->modify('+1 second');
        self::assertSame(1, $test->polls->eraseExpired());
        self::assertSame(0, $test->polls->eraseExpired());

        [$erased] = $test->polls->respondents($poll->id);
        self::assertNull($erased->name());
        self::assertNull($erased->email());
        self::assertNull($erased->timezone());
        self::assertSame('2026-12-21 10:00:01', $erased->erasedAt()?->format('Y-m-d H:i:s'));
        self::assertSame([$first => Availability::Yes], \array_slice($erased->answers(), 0, 1, true));
        self::assertSame(1, $test->polls->tally($poll->id)[0]->yes);
        self::assertSame('Carol', $test->polls->respondents($open->id)[0]->name());
        self::assertNull($this->opened($test, $bob->token)->respondent->name());
        self::assertSame('Carol', $this->opened($test, $carol->token)->respondent->name());
    }

    public function testTheDefaultsAreFiveHundredRespondentsAndAHundredPollsAtATime(): void
    {
        $defaults = static fn (string $class, string $method): array => array_column(array_map(static fn (ReflectionParameter $parameter): array => [$parameter->getName(), $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null], new ReflectionMethod($class, $method)->getParameters()), 1, 0);

        self::assertSame(500, $defaults(SchedulingPolls::class, '__construct')['maxRespondents']);
        self::assertSame(100, $defaults(SchedulingPolls::class, 'closeDue')['limit']);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testWithoutABusOrEventsAPollIsStillClosed(Closure $fixtures): void
    {
        $test = $fixtures();
        $quiet = new SchedulingPolls($test->pollStore, $test->respondentStore, $test->links, $test->clock, new AvailabilityCount());
        $chosen = $test->poll();
        $undecided = $test->poll(deadline: '2026-11-22 10:00:00 UTC');
        [$first] = $test->slotIds($chosen);

        self::assertTrue($quiet->close($chosen->id, Fixtures::organizer(), $first));
        self::assertSame($first, $quiet->get($chosen->id)->chosenSlot());

        $test->clock->modify('2026-11-22 10:00:00 UTC');
        self::assertSame(1, $quiet->closeDue());
        self::assertTrue($quiet->get($undecided->id)->isClosed());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testThePollHeldByTheCallerIsClosedWithTheStore(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $held = $test->polls->get($poll->id);

        self::assertTrue($test->polls->close($poll->id, Fixtures::organizer(), $first));

        self::assertTrue($held->isClosed());
        self::assertSame($first, $held->chosenSlot());
    }

    public function testAPollGoneSinceTheListingOfTheDuePollsIsSkipped(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll(deadline: '2026-11-22 10:00:00 UTC');
        $test->clock->modify('2026-11-22 10:00:00 UTC');

        $polls = new SchedulingPolls(new GhostPolls($test->pollStore), $test->respondentStore, $test->links, $test->clock, new AvailabilityCount(), $test->bus, $test->events);

        self::assertSame(1, $polls->closeDue());
        self::assertTrue($test->polls->get($poll->id)->isClosed());
    }

    public function testALinkThatStopsOpeningWhileAnAnswerIsGivenIsNotUsable(): void
    {
        $flaky = new FlakyInvitations(new InMemoryInvitationStore());
        $test = Fixtures::memory(invitations: $flaky);
        $poll = $test->poll();
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $flaky->finds = 1;

        $this->expectExceptionObject(AnswerRefused::linkNotUsable());
        $test->polls->answer($token, []);
    }

    public function testAnInvitationClosedWhileThePollIsOpenRefusesTheAnswer(): void
    {
        $flaky = new FlakyInvitations(new InMemoryInvitationStore());
        $test = Fixtures::memory(invitations: $flaky);
        $poll = $test->poll();
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $flaky->refuseAnswers = true;

        try {
            $test->polls->answer($token, []);
            self::fail('An answer to a closed invitation was kept.');
        } catch (AnswerRefused $answerRefused) {
            self::assertSame(AnswerRefused::closed()->getMessage(), $answerRefused->getMessage());
        }

        self::assertFalse($this->opened($test, $token)->respondent->hasAnswered());
    }

    public function testARespondentGoneAfterTheirAnswerIsNotFound(): void
    {
        $test = Fixtures::memory();
        $poll = $test->poll();
        $token = $test->polls->join($poll->id, 'Bob')->token;
        $respondent = $this->opened($test, $token)->respondent->id;
        $polls = new SchedulingPolls($test->pollStore, new ForgetfulRespondents($test->respondentStore), $test->links, $test->clock, new AvailabilityCount());

        $this->expectExceptionObject(NotFound::respondent($respondent));
        $polls->answer($token, []);
    }

    private function opened(Fixtures $test, string $token): RespondentAccess
    {
        $access = $test->polls->open($token);
        self::assertInstanceOf(RespondentAccess::class, $access);

        return $access;
    }
}
