<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Messenger;

use Closure;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Contract\RespondentAddresses;
use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Application\Message\SendChosenDate;
use Phil\SchedulingPollBundle\Application\NoRespondentAddresses;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Infrastructure\Mailer\ChosenDateMail;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\AnnounceChosenDateHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\CloseDuePollsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\EraseRespondentsHandler;
use Phil\SchedulingPollBundle\Infrastructure\Messenger\SendChosenDateHandler;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\RecordingMailer;
use Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Mailer\ChosenDateMailTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(AnnounceChosenDateHandler::class)]
#[CoversClass(SendChosenDateHandler::class)]
#[CoversClass(CloseDuePollsHandler::class)]
#[CoversClass(EraseRespondentsHandler::class)]
#[CoversClass(NoRespondentAddresses::class)]
#[CoversClass(AnnounceChosenDate::class)]
#[CoversClass(SendChosenDate::class)]
final class HandlersTest extends TestCase
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
    public function testTheAnnouncementQueuesOneMessageOfIdentifiersPerRespondentWhoAnswered(Closure $fixtures): void
    {
        $test = $fixtures();
        [$poll, $first, $ids] = $this->closedPoll($test);
        $bus = new RecordingBus();
        $handler = new AnnounceChosenDateHandler($test->pollStore, $test->respondentStore, $bus);

        $handler(new AnnounceChosenDate($poll->id));

        self::assertEquals([new SendChosenDate($poll->id, $ids['bob']), new SendChosenDate($poll->id, $ids['account'])], $bus->messages);

        $bus->messages = [];
        $open = $test->poll();
        $handler(new AnnounceChosenDate($open->id));
        $handler(new AnnounceChosenDate('0195a6c4-0000-7000-8000-000000000000'));
        self::assertSame([], $bus->messages);
        self::assertNotSame('', $first);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testTheDateIsSentOnceToEachRespondentWithAnAddress(Closure $fixtures): void
    {
        $test = $fixtures();
        [$poll, , $ids] = $this->closedPoll($test);
        $mailer = new RecordingMailer();
        $handler = $this->sender($test, $mailer, new class implements RespondentAddresses {
            public function addressOf(ResourceReference $account): ?Address
            {
                return 'user:dave' === $account->toString() ? new Address('dave@example.org') : null;
            }
        });

        foreach ($ids as $respondent) {
            $handler(new SendChosenDate($poll->id, $respondent));
            $handler(new SendChosenDate($poll->id, $respondent));
        }

        self::assertSame(['"Bob" <bob@example.org>', 'dave@example.org'], array_map(static fn (RawMessage $email): string => $email instanceof Email ? $email->getTo()[0]->toString() : '', $mailer->sent));
        self::assertNotNull($test->respondentStore->get($ids['bob'])?->notifiedAt());
        self::assertNull($test->respondentStore->get($ids['silent'])?->notifiedAt());

        $handler(new SendChosenDate($test->poll()->id, $ids['bob']));
        $handler(new SendChosenDate($poll->id, '0195a6c4-0000-7000-8000-000000000000'));
        self::assertCount(2, $mailer->sent);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testARevokedRespondentOrAPollWithoutDateSendsNothing(Closure $fixtures): void
    {
        $test = $fixtures();
        [$poll, , $ids] = $this->closedPoll($test);
        $test->polls->revoke($poll->id, Fixtures::organizer(), $ids['bob']);
        $open = $test->poll();
        $carol = $test->polls->join($open->id, 'Carol', 'carol@example.org');
        $mailer = new RecordingMailer();
        $handler = $this->sender($test, $mailer, new NoRespondentAddresses());

        $handler(new SendChosenDate($poll->id, $ids['bob']));
        $handler(new SendChosenDate($poll->id, $ids['account']));
        $handler(new SendChosenDate($open->id, (string) $test->polls->open($carol->token)?->respondent->id));

        self::assertSame([], $mailer->sent);
        self::assertNull($test->respondentStore->get($ids['account'])?->notifiedAt());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAMailThatFailsGivesTheNoticeBackSoThatTheRetrySendsIt(Closure $fixtures): void
    {
        $test = $fixtures();
        [$poll, , $ids] = $this->closedPoll($test);
        $mailer = new RecordingMailer();
        $mailer->whileSending = static fn (): never => throw new RuntimeException('The transport is down.');

        $handler = $this->sender($test, $mailer, new NoRespondentAddresses());

        try {
            $handler(new SendChosenDate($poll->id, $ids['bob']));
            self::fail('A mail that failed was not reported.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The transport is down.', $runtimeException->getMessage());
        }

        self::assertNull($test->respondentStore->get($ids['bob'])?->notifiedAt());

        $mailer->whileSending = null;
        $handler(new SendChosenDate($poll->id, $ids['bob']));
        self::assertCount(1, $mailer->sent);
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAMessageThatNamesAnotherPollOrNoneSendsNothing(Closure $fixtures): void
    {
        $test = $fixtures();
        [$poll, , $ids] = $this->closedPoll($test);
        $other = $test->poll();
        $test->polls->close($other->id, Fixtures::organizer(), $test->slotIds($other)[0]);
        $mailer = new RecordingMailer();
        $handler = $this->sender($test, $mailer, new NoRespondentAddresses());

        $handler(new SendChosenDate($other->id, $ids['bob']));
        $handler(new SendChosenDate('0195a6c4-0000-7000-8000-000000000000', $ids['bob']));

        self::assertCount(0, $mailer->sent);
        self::assertNull($test->respondentStore->get($ids['bob'])?->notifiedAt());

        $handler(new SendChosenDate($poll->id, $ids['bob']));
        self::assertCount(1, $mailer->sent);
    }

    public function testTheMailWritesTheDateTheOrganizerChose(): void
    {
        $test = Fixtures::memory();
        [$poll, , $ids] = $this->closedPoll($test);
        $mailer = new RecordingMailer();

        $this->sender($test, $mailer, new NoRespondentAddresses())(new SendChosenDate($poll->id, $ids['bob']));

        [$email] = $mailer->sent;
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('December 1, 2026', (string) $email->getTextBody());
        self::assertStringNotContainsString('December 2, 2026', (string) $email->getTextBody());
    }

    public function testWithoutTheMailerNothingIsSentNorClaimed(): void
    {
        $test = Fixtures::memory();
        [$poll, , $ids] = $this->closedPoll($test);

        new SendChosenDateHandler($test->pollStore, $test->respondentStore, new NoRespondentAddresses(), new ChosenDateMail(ChosenDateMailTest::translator()), $test->clock)(new SendChosenDate($poll->id, $ids['bob']));

        self::assertNull($test->respondentStore->get($ids['bob'])?->notifiedAt());
    }

    public function testTheScheduleClosesAHundredPollsAtATimeUnlessToldOtherwise(): void
    {
        $batch = new ReflectionParameter([CloseDuePollsHandler::class, '__construct'], 'batch');

        self::assertSame(100, $batch->getDefaultValue());
    }

    public function testTheMessagesOfTheScheduleCloseAndErase(): void
    {
        $test = Fixtures::memory();
        $due = $test->poll(deadline: '2026-11-22 10:00:00 UTC');
        $later = $test->poll(deadline: '2026-11-22 11:00:00 UTC');
        $test->polls->join($due->id, 'Bob');
        $test->clock->modify('2026-11-22 12:00:00 UTC');

        new CloseDuePollsHandler($test->polls, 1)();
        self::assertTrue($test->polls->get($due->id)->isClosed());
        self::assertFalse($test->polls->get($later->id)->isClosed());

        $test->clock->modify('+31 days');
        new EraseRespondentsHandler($test->polls)();
        self::assertNull($test->polls->respondents($due->id)[0]->name());
    }

    private function sender(Fixtures $test, RecordingMailer $mailer, RespondentAddresses $addresses): SendChosenDateHandler
    {
        return new SendChosenDateHandler($test->pollStore, $test->respondentStore, $addresses, new ChosenDateMail(ChosenDateMailTest::translator()), $test->clock, $mailer);
    }

    /**
     * A poll closed on its first date: Bob answered with an address, Dave with an account, Erin answered then was
     * revoked, and Silent never answered.
     *
     * @return array{SchedulingPoll, string, array{bob: string, account: string, silent: string}}
     */
    private function closedPoll(Fixtures $test): array
    {
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $bob = $test->polls->join($poll->id, 'Bob', 'bob@example.org');
        $silent = $test->polls->join($poll->id, 'Silent', 'silent@example.org');
        $dave = $test->polls->join($poll->id, 'Dave', null, ResourceReference::of('user', 'dave'));
        $erin = $test->polls->join($poll->id, 'Erin', 'erin@example.org');

        foreach ([$bob, $dave, $erin] as $issued) {
            $test->polls->answer($issued->token, [$first => Availability::Yes]);
        }

        $test->polls->revoke($poll->id, Fixtures::organizer(), (string) $test->polls->open($erin->token)?->respondent->id);
        $ids = ['bob' => (string) $test->polls->open($bob->token)?->respondent->id, 'account' => (string) $test->polls->open($dave->token)?->respondent->id, 'silent' => (string) $test->polls->open($silent->token)?->respondent->id];
        $test->polls->close($poll->id, Fixtures::organizer(), $first);

        return [$poll, $first, $ids];
    }
}
