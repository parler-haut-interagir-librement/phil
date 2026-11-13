<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\PhilPollBundle;
use Phil\PollBundle\Tests\Functional\App\RecordedEvents;
use Phil\PollBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A ranked anonymous poll through the services of a booted kernel and the Doctrine stores on SQLite, counted by a
 * method of the application.
 */
#[CoversClass(PhilPollBundle::class)]
final class PollBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testAnAnonymousRankedPoll(): void
    {
        $polls = self::getContainer()->get(Polls::class);
        self::assertInstanceOf(Polls::class, $polls);
        $methods = self::getContainer()->get(CountingMethods::class);
        self::assertInstanceOf(CountingMethods::class, $methods);
        self::assertEqualsCanonicalizing(['instant_runoff', 'borda', 'schulze', 'meek_stv', 'first_preferences'], $methods->namesFor(BallotKind::Ranked));

        $options = [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee'), PollOption::of('cocoa', 'Cocoa')];
        $poll = $polls->create(new PollDraft('What do we drink?', $options, BallotKind::Ranked, counting: 'first_preferences', anonymous: true), ResourceReference::of('user', 'ada'));

        $receipt = $polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['coffee', 'tea'])->receipt;
        $polls->cast($poll->id, ResourceReference::of('user', 'cid'), ['tea']);
        $polls->cast($poll->id, ResourceReference::of('user', 'dan'), ['tea', 'cocoa']);
        $polls->changeWithReceipt($poll->id, ResourceReference::of('user', 'bob'), (string) $receipt, ['cocoa']);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
        $result = $polls->results($poll->id);

        self::assertSame(['first_preferences', 3, ['tea']], [$result->method, $result->ballots, $result->winners()]);
        self::assertSame(1, $result->standingOf('cocoa')?->score);
        self::assertTrue($polls->hasVoted($poll->id, ResourceReference::of('user', 'bob')));
        self::assertSame('What do we drink?', $polls->get($poll->id)->question());

        $events = self::getContainer()->get(RecordedEvents::class);
        self::assertInstanceOf(RecordedEvents::class, $events);
        self::assertSame([$poll->id, $poll->id, $poll->id], $events->polls);

        $polls->delete($poll->id, ResourceReference::of('user', 'ada'));
        self::assertSame([], $polls->ofSubject(ResourceReference::of('idea', '1')));
    }
}
