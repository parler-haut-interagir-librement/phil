<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommentsBundle\Application\Comments;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Infrastructure\Comments\CommentMentions;
use Phil\MentionsBundle\PhilMentionsBundle;
use Phil\MentionsBundle\Tests\Functional\App\TestKernel;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

/**
 * A comment that mentions members, saved twice, through the Doctrine store on SQLite and the notifications bundle; and
 * a comment of the comments bundle, whose mentions the bridge records as it is written.
 */
#[CoversClass(PhilMentionsBundle::class)]
final class MentionsBundleTest extends KernelTestCase
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

    public function testAMentionIsStoredNotifiedOnceAndLinked(): void
    {
        $mentions = self::getContainer()->get(Mentions::class);
        self::assertInstanceOf(Mentions::class, $mentions);
        $comment = ResourceReference::of('comment', '7');

        $mentions->inText($comment, ResourceReference::of('user', '9'), 'Hi @ada, @bob and @chloé', context: ResourceReference::of('page', '1'));
        $mentions->inText($comment, ResourceReference::of('user', '9'), 'Hi @ada, @bob and @chloé, again');

        $notifications = self::getContainer()->get(NotificationRepository::class);
        self::assertInstanceOf(NotificationRepository::class, $notifications);
        self::assertSame([['member.mentioned', 'comment:7', 'user:9']], array_map(static fn (Notification $notification): array => [$notification->type, $notification->subject?->key(), $notification->actor], $notifications->latest('user:1', 10)));
        self::assertCount(1, $notifications->latest('b-2', 10));
        self::assertSame(['user:1', 'user:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $mentions->mentionedIn($comment)));

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertSame('<a class="mention" href="/members/ada" data-mention="user:1">@ada</a> &amp;', $twig->createTemplate('{{ text|mentions }}')->render(['text' => '@ada &']));
    }

    public function testTheMentionsOfACommentOnThePageAreRecordedAndNotifiedAsItIsWritten(): void
    {
        $comments = self::getContainer()->get(Comments::class);
        self::assertInstanceOf(Comments::class, $comments);
        $mentions = self::getContainer()->get(Mentions::class);
        self::assertInstanceOf(Mentions::class, $mentions);

        $discussion = $comments->openOnPage(ResourceReference::of('page', '1'), ResourceReference::of('user', '9'), 'What do you think, @ada?');
        $subject = CommentMentions::subject($discussion->comments[0]->id);

        $notifications = self::getContainer()->get(NotificationRepository::class);
        self::assertInstanceOf(NotificationRepository::class, $notifications);
        self::assertSame([['member.mentioned', $subject->toString(), 'user:9']], array_map(static fn (Notification $notification): array => [$notification->type, $notification->subject?->key(), $notification->actor], $notifications->latest('user:1', 10)));
        self::assertSame(['user:1'], array_map(static fn (ResourceReference $member): string => $member->toString(), $mentions->mentionedIn($subject)));

        $comments->delete($discussion->comments[0]->id, ResourceReference::of('user', '9'));
        self::assertSame([], $mentions->mentionedIn($subject));
    }
}
