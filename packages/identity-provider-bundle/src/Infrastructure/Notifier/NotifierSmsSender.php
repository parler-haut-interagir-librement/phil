<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Notifier;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Symfony\Component\Notifier\Exception\ExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

/**
 * Text messages through a texter of the Notifier component, to whichever SMS transport the application configured.
 *
 * The bundle hands it a texter on the transports, without a bus: the message leaves in the request that asks for it.
 * The texter `texter` of the Notifier component puts an `SmsMessage` on the bus when Messenger routes it, `async` in
 * the recipe of Messenger, and the message would wait in the queue with the number and the code.
 */
final readonly class NotifierSmsSender implements SmsSender
{
    public function __construct(
        private TexterInterface $texter,
        private string $from = '',
    ) {
    }

    #[Override]
    public function send(string $phone, string $message): void
    {
        try {
            $this->texter->send(new SmsMessage($phone, $message, $this->from));
        } catch (ExceptionInterface $exception) {
            throw DeliveryFailed::because('The texter did not accept the message.', $exception);
        }
    }
}
