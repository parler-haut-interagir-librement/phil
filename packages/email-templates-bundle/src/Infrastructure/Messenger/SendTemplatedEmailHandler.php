<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Messenger;

use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Delivery;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Handles `SendTemplatedEmail`: asks the application whom the email goes to and what it says, renders the template for
 * the site and the locale of the delivery, and sends it through Symfony Mailer. A reference the application no longer
 * sends anything for is dropped.
 */
final readonly class SendTemplatedEmailHandler
{
    public function __construct(
        private DeliveryResolver $deliveries,
        private EmailComposer $composer,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(SendTemplatedEmail $message): void
    {
        $delivery = $this->deliveries->resolve($message->template, $message->reference);

        if (!$delivery instanceof Delivery) {
            return;
        }

        $site = $delivery->site ?? $message->site;
        $email = $this->composer->compose($message->template, $delivery->variables, new RenderOptions(null === $site ? null : Site::fromString($site), $delivery->locale, $delivery->segments));
        $this->mailer->send($email->to(...$delivery->to));
    }
}
