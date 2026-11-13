<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Poll\BallotKind;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrinePollRepository;
use Phil\PollBundle\Infrastructure\Doctrine\DoctrineVoteRepository;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * What `Polls` changes reaches the tables: each change read back with the entity manager cleared.
 */
#[CoversClass(Polls::class)]
final class PollsOnDoctrineTest extends TestCase
{
    public function testTheChangesAreSaved(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Poll::class), $entityManager->getClassMetadata(Vote::class), $entityManager->getClassMetadata(Participation::class)]);
        $polls = new Polls(new DoctrinePollRepository($entityManager), new DoctrineVoteRepository($entityManager), new MockClock('2026-11-08 10:00:00 UTC'), new Policy(), CountingMethods::defaults());
        $boss = ResourceReference::of('user', 'boss');
        $bob = ResourceReference::of('user', 'bob');

        $named = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], BallotKind::Multiple), $boss);
        $anonymous = $polls->create(new PollDraft('Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], anonymous: true), $boss);
        $polls->cast($named->id, $bob, ['a']);
        $receipt = (string) $polls->cast($anonymous->id, $bob, ['a'])->receipt;
        $entityManager->clear();

        $polls->change($named->id, $bob, ['a', 'b']);
        $entityManager->clear();
        self::assertSame(['a', 'b'], $polls->ballotOf($named->id, $bob)?->choices);

        $polls->changeWithReceipt($anonymous->id, $bob, $receipt, ['b']);
        $entityManager->clear();
        self::assertSame(['b'], $polls->ballotOfReceipt($anonymous->id, $receipt)?->choices);

        $polls->reschedule($named->id, $boss, null, new DateTimeImmutable('2026-11-09 00:00:00 UTC'));
        $entityManager->clear();
        self::assertSame('2026-11-09', $polls->get($named->id)->closesAt()?->format('Y-m-d'));

        $polls->close($anonymous->id, $boss);
        $entityManager->clear();
        self::assertSame('10:00', $polls->get($anonymous->id)->closedAt()?->format('H:i'));
    }
}
