<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Phil\NotificationsBundle\Infrastructure\Notifier\EmptyRecipientDirectory;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Phil\NotificationsBundle\Infrastructure\Security\NoCurrentRecipient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoCurrentRecipient::class)]
#[CoversClass(EmptyRecipientDirectory::class)]
#[CoversClass(PlainTranslator::class)]
final class CollaboratorsTest extends TestCase
{
    public function testNoCurrentRecipientIsNoOne(): void
    {
        self::assertNull(new NoCurrentRecipient()->id());
    }

    public function testTheEmptyDirectoryKnowsNoOne(): void
    {
        self::assertNull(new EmptyRecipientDirectory()->contact('42'));
        self::assertNull(new EmptyRecipientDirectory()->displayName('42'));
    }

    public function testThePlainTranslatorReplacesTheParameters(): void
    {
        self::assertSame('Ada replied', new PlainTranslator()->trans('%actor% replied', ['%actor%' => 'Ada'], 'phil_notifications'));
    }
}
