<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit\Notifier;

use Override;
use Phil\BrokenFlowAlerts\Flow;
use Phil\BrokenFlowAlerts\FlowAlert;
use Phil\BrokenFlowAlerts\FlowAlertKind;
use Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

#[CoversClass(NotifierAlertSender::class)]
final class NotifierAlertSenderTest extends TestCase
{
    public function testABrokenFlowIsAnImportantNotification(): void
    {
        $notification = NotifierAlertSender::notificationOf(new FlowAlert(Flow::of('checkout', 'Checkout'), FlowAlertKind::Broken, '2026-11-11', '2026-11-08', 3, 41.6));

        self::assertSame('Flow "Checkout" stopped completing', $notification->getSubject());
        self::assertSame('3 completions from 2026-11-08 to 2026-11-11, where about 42 were expected.', $notification->getContent());
        self::assertSame(Notification::IMPORTANCE_HIGH, $notification->getImportance());
    }

    public function testARecoveryIsALowImportanceNotification(): void
    {
        $notification = NotifierAlertSender::notificationOf(new FlowAlert(Flow::of('checkout', 'Checkout'), FlowAlertKind::Recovered, '2026-11-11', '2026-11-11', 12, 20.0));

        self::assertSame('Flow "Checkout" completes again', $notification->getSubject());
        self::assertSame('12 completions on 2026-11-11.', $notification->getContent());
        self::assertSame(Notification::IMPORTANCE_LOW, $notification->getImportance());
    }

    public function testTheNotificationGoesToTheGivenRecipients(): void
    {
        $notifier = $this->notifier();
        $first = new Recipient('ops@example.com');
        $second = new Recipient('', '+15145550100');

        new NotifierAlertSender($notifier, $first, $second)->send(new FlowAlert(Flow::of('checkout'), FlowAlertKind::Broken, '2026-11-11', '2026-11-11', 0, 20.0));

        self::assertCount(1, $notifier->sent);
        self::assertSame('Flow "checkout" stopped completing', $notifier->sent[0][0]->getSubject());
        self::assertSame([$first, $second], $notifier->sent[0][1]);
    }

    public function testRecipientsGivenByNameAreSentAsAList(): void
    {
        $notifier = $this->notifier();
        $recipient = new Recipient('ops@example.com');

        new NotifierAlertSender($notifier, ...['ops' => $recipient])->send(new FlowAlert(Flow::of('checkout'), FlowAlertKind::Broken, '2026-11-11', '2026-11-11', 0, 20.0));

        self::assertSame([$recipient], $notifier->sent[0][1]);
    }

    public function testTheExpectedCountIsRoundedToTheNearest(): void
    {
        $notification = NotifierAlertSender::notificationOf(new FlowAlert(Flow::of('checkout'), FlowAlertKind::Broken, '2026-11-11', '2026-11-11', 0, 41.4));

        self::assertSame('0 completions from 2026-11-11 to 2026-11-11, where about 41 were expected.', $notification->getContent());
    }

    public function testWithoutRecipientsTheNotificationGoesToTheChannelsThatNeedNone(): void
    {
        $notifier = $this->notifier();

        new NotifierAlertSender($notifier)->send(new FlowAlert(Flow::of('checkout'), FlowAlertKind::Broken, '2026-11-11', '2026-11-11', 0, 20.0));

        self::assertSame([], $notifier->sent[0][1]);
    }

    /**
     * @return NotifierInterface&object{sent: list<array{Notification, array<RecipientInterface>}>}
     */
    private function notifier(): NotifierInterface
    {
        return new class implements NotifierInterface {
            /**
             * @var list<array{Notification, array<RecipientInterface>}>
             */
            public array $sent = [];

            #[Override]
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                // The recipients as received: a name given to one stays its key.
                $this->sent[] = [$notification, $recipients];
            }
        };
    }
}
