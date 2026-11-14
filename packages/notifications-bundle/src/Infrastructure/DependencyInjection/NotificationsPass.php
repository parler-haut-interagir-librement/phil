<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Digests;
use Phil\NotificationsBundle\Application\Handler\DigestHandler;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Phil\NotificationsBundle\Infrastructure\Scheduler\NotificationSchedule;
use Phil\NotificationsBundle\Infrastructure\Security\NoCurrentRecipient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Falls back on what the application lacks: without a translator, the templates are written as they are, parameters
 * replaced; without Symfony Mailer nor a `DigestSender` of its own, no digest is sent. And stops the container with a
 * message that says what to change when the application turns FrameworkBundle's CSRF protection off — the centre checks
 * its stateless token with Symfony's token manager —, or when it shows the centre to the users of Symfony Security
 * without a `CurrentRecipient` of its own: no default reads the user identifier, which can be an email address.
 */
final readonly class NotificationsPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    public const string TOKEN_STORAGE = 'security.token_storage';

    /**
     * @throws LogicException when the CSRF protection is off, or the centre has users but no current recipient
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('translator')) {
            $container->register(PlainTranslator::class, PlainTranslator::class);
            $container->setAlias(NotificationsServices::TRANSLATOR, PlainTranslator::class);
        }

        if ($container->hasDefinition(NoCurrentRecipient::class) && $container->hasDefinition(CentreRequest::class) && $container->has(self::TOKEN_STORAGE)) {
            throw new LogicException('phil/notifications-bundle shows the notification centre of the current recipient, and Symfony Security is installed, but "phil_notifications.current_recipient" names no service. Name a CurrentRecipient service that returns the stable identifier notifications are sent to, such as the id of the member\'s account: the user identifier of Symfony Security can be an email address, which a notification never carries.');
        }

        if ($container->hasDefinition(MailerDigestSender::class) && !$container->has('mailer')) {
            $container->removeDefinition(MailerDigestSender::class);
            $container->removeAlias(DigestSender::class);
            $container->removeDefinition(Digests::class);
            $container->removeDefinition(DigestHandler::class);

            if ($container->hasDefinition(NotificationSchedule::class)) {
                $container->getDefinition(NotificationSchedule::class)->replaceArgument(4, false);
            }
        }

        if ($container->hasDefinition(CentreRequest::class) && !$container->has(self::TOKEN_MANAGER)) {
            throw new LogicException(\sprintf('phil/notifications-bundle checks the changes of the notification centre with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "%s" is stateless and needs no session.', CentreRequest::TOKEN_ID));
        }
    }
}
