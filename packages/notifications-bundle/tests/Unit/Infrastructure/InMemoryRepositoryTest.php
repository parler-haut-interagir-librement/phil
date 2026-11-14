<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryNotificationRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testAResetForgetsEverything(): void
    {
        $repository = new InMemoryNotificationRepository();
        $repository->record($this->notification('a', '42', '2026-11-08 10:00:00'), false);
        $repository->markDigested('42', ['a'], new DateTimeImmutable());
        $repository->reset();

        self::assertSame(0, $repository->unreadCount('42'));
        $repository->record($this->notification('a', '42', '2026-11-08 10:00:00'), false);
        self::assertCount(1, $repository->undigested('42', new DateTimeImmutable('2026-11-01'), 10));
    }

    #[Override]
    protected function repository(): NotificationRepository
    {
        return new InMemoryNotificationRepository();
    }
}
