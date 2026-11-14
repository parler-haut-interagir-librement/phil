<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Directory;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Subjects;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationRenderer::class)]
#[CoversClass(RenderedNotification::class)]
#[CoversClass(ResolvedSubject::class)]
final class RendererTest extends TestCase
{
    public function testANotificationIsWrittenFromItsReferences(): void
    {
        $notification = $this->notification();
        $notification->collapse(new Notification('n2', 'comment.replied', '42', null, new DateTimeImmutable('2026-11-08 11:00 UTC'), '17', NotificationData::from(['event' => 7])));

        $rendered = $this->renderer()->render($notification);

        self::assertSame($notification, $rendered->notification);
        self::assertSame('Grace replied to comment 9', $rendered->title);
        self::assertSame('2 replies on event 7', $rendered->body);
        self::assertSame('/comments/9', $rendered->url);
        self::assertSame(['id' => 'n1', 'type' => 'comment.replied', 'title' => 'Grace replied to comment 9', 'body' => '2 replies on event 7', 'url' => '/comments/9', 'count' => 2], $rendered->toArray());
    }

    public function testItIsWrittenInTheLocale(): void
    {
        self::assertSame('Grace a répondu à comment 9 (fr)', $this->renderer()->render($this->notification(), 'fr')->title);
    }

    public function testTheFirstResolverThatKnowsTheSubjectWins(): void
    {
        $renderer = new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('comment.replied')), new Directory(), [new Subjects('event'), new Subjects(), $last = new Subjects(prefix: 'other ')]);

        self::assertSame('Grace replied to comment 9', $renderer->render($this->notification())->title);
        self::assertSame(0, $last->calls);
    }

    public function testWhatCannotBeResolvedIsEmpty(): void
    {
        $renderer = new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('comment.replied')), new Directory([], []), [new Subjects('event')]);
        $rendered = $renderer->render($this->notification());

        self::assertSame(' replied to ', $rendered->title);
        self::assertNull($rendered->url);
    }

    public function testANotificationWithoutActorNorSubject(): void
    {
        $rendered = $this->renderer()->render(new Notification('n1', 'comment.replied', '42', null, new DateTimeImmutable()));

        self::assertSame(' replied to ', $rendered->title);
        self::assertSame('1 replies on event %event%', $rendered->body);
        self::assertNull($rendered->url);
    }

    public function testTheTypeNamesItsTemplates(): void
    {
        $renderer = new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('event.created', title: 'custom.title', body: 'custom.body')), new Directory());
        $rendered = $renderer->render(new Notification('n1', 'event.created', '42', null, new DateTimeImmutable()));

        self::assertSame('Custom title', $rendered->title);
        self::assertSame('Custom body', $rendered->body);
    }

    public function testATypeNoLongerDeclaredUsesTheDefaultKeys(): void
    {
        $renderer = new NotificationRenderer(Translator::make(), new NotificationTypes(), new Directory());

        self::assertSame('Grace replied to ', $renderer->render($this->notification())->title);
    }

    public function testAResolvedSubjectMayHaveNoUrl(): void
    {
        self::assertNull(new ResolvedSubject('A')->url);
        self::assertSame('A', new ResolvedSubject('A')->label);
    }

    private function renderer(): NotificationRenderer
    {
        return new NotificationRenderer(Translator::make(), new NotificationTypes(new NotificationType('comment.replied')), new Directory(), [new Subjects()]);
    }

    private function notification(): Notification
    {
        return new Notification('n1', 'comment.replied', '42', new SubjectReference('comment', '9'), new DateTimeImmutable('2026-11-08 10:00 UTC'), '17', NotificationData::from(['event' => 7]));
    }
}
