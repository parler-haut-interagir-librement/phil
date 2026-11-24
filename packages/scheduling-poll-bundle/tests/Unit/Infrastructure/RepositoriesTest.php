<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Doctrine\DoctrineSchedulingPollRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemoryRespondentRepository;
use Phil\SchedulingPollBundle\Infrastructure\Memory\InMemorySchedulingPollRepository;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * What a store gives back after another writer changed its rows, and what it does with rows whose poll is gone.
 */
#[CoversClass(InMemorySchedulingPollRepository::class)]
#[CoversClass(InMemoryRespondentRepository::class)]
#[CoversClass(DoctrineSchedulingPollRepository::class)]
#[CoversClass(DoctrineRespondentRepository::class)]
final class RepositoriesTest extends TestCase
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
    public function testAClosingWrittenByTheStoreIsReadBackAndAnUnknownOrClosedPollIsNotClosed(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first, $second] = $test->slotIds($poll);
        $now = new DateTimeImmutable(Fixtures::NOW);

        self::assertFalse($test->pollStore->get($poll->id)?->isClosed());
        self::assertFalse($test->pollStore->close('0195a6c4-0000-7000-8000-000000000000', $now, ClosedBy::Organizer, $first));
        self::assertTrue($test->pollStore->close($poll->id, $now, ClosedBy::Organizer, $first));
        self::assertFalse($test->pollStore->close($poll->id, $now, ClosedBy::Deadline, $second));

        $closed = $test->pollStore->get($poll->id);
        self::assertInstanceOf(SchedulingPoll::class, $closed);
        self::assertTrue($closed->isClosed());
        self::assertSame($first, $closed->chosenSlot());
        self::assertSame(ClosedBy::Organizer, $closed->closedBy());
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testAnAnswerWrittenByTheStoreIsReadBackByEveryRead(Closure $fixtures): void
    {
        $test = $fixtures();
        $poll = $test->poll();
        [$first] = $test->slotIds($poll);
        $test->polls->join($poll->id, 'Bob');
        [$bob] = $test->respondentStore->ofPoll($poll->id);
        $id = $bob->id;

        self::assertFalse($test->respondentStore->get($id)?->hasAnswered());
        self::assertFalse($test->respondentStore->ofInvitation($bob->invitation)?->hasAnswered());
        self::assertFalse($test->respondentStore->ofPoll($poll->id)[0]->hasAnswered());

        self::assertTrue($test->respondentStore->answer($id, [$first => Availability::Yes], 'Europe/Paris', new DateTimeImmutable(Fixtures::NOW)));

        self::assertSame(Availability::Yes, $test->respondentStore->get($id)->answerTo($first));
        self::assertSame(Availability::Yes, $test->respondentStore->ofInvitation($bob->invitation)->answerTo($first));
        self::assertSame(Availability::Yes, $test->respondentStore->ofPoll($poll->id)[0]->answerTo($first));
    }

    /**
     * @param Closure(): Fixtures $fixtures
     */
    #[DataProvider('stores')]
    public function testARespondentOfAPollThatIsGoneCannotAnswerAndIsNotErased(Closure $fixtures): void
    {
        $test = $fixtures();
        $now = new DateTimeImmutable(Fixtures::NOW);
        $orphan = new Respondent(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), bin2hex(random_bytes(16)), 'Ghost', $now, 'ghost@example.org');
        $test->respondentStore->add($orphan);

        self::assertFalse($test->respondentStore->answer($orphan->id, [], null, $now));
        self::assertSame(0, $test->respondentStore->eraseClosedBefore($now->modify('+1 year'), $now->modify('+1 year')));
        self::assertNotNull($test->respondentStore->get($orphan->id)?->email());
    }
}
