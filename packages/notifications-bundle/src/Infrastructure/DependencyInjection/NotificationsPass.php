<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Digests;
use Phil\NotificationsBundle\Application\Handler\DigestHandler;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerChannelSender;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\Infrastructure\Notifier\NotifierChannelSender;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Phil\NotificationsBundle\Infrastructure\Scheduler\NotificationSchedule;
use Phil\NotificationsBundle\Infrastructure\Security\NoCurrentRecipient;
use Phil\RateLimit\StoreLocality;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Falls back on what the application lacks: without a translator, the templates are written as they are, parameters
 * replaced; without Symfony Mailer nor a `DigestSender` of its own, no digest is sent. And stops the container with a
 * message that says what to change when the application turns FrameworkBundle's CSRF protection off — the centre checks
 * its stateless token with Symfony's token manager —, or when it shows the centre to the users of Symfony Security
 * without a `CurrentRecipient` of its own: no default reads the user identifier, which can be an email address.
 *
 * Lists the channel senders of the `first_success` mode, the application's first, then the bundle's that can send —
 * Symfony Mailer's transports, Notifier's texter and chatter —, and refuses in the prod environment a window of
 * duplicates kept in a cache pool local to each server, where every server would send once.
 */
final readonly class NotificationsPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    public const string TOKEN_STORAGE = 'security.token_storage';

    public const string MAILER_TRANSPORTS = 'mailer.transports';

    public function __construct(
        private StoreLocality $locality = new StoreLocality(),
    ) {
    }

    /**
     * @throws LogicException                when the CSRF protection is off, or the centre has users but no current recipient
     * @throws InvalidConfigurationException when the window of duplicates is local to each server in production
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

        $this->senders($container);
        $this->dedupe($container);

        if ($container->hasDefinition(MailerDigestSender::class) && !$container->has(self::MAILER_TRANSPORTS)) {
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

    private function senders(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DeliveryPolicy::class)) {
            return;
        }

        $senders = array_map(static fn (string $id): Reference => new Reference($id), array_keys($container->findTaggedServiceIds(NotificationsServices::CHANNEL_SENDER_TAG)));

        if ($container->hasDefinition(MailerChannelSender::class) && $container->has(self::MAILER_TRANSPORTS)) {
            $senders[] = new Reference(MailerChannelSender::class);
        }

        if ($container->hasDefinition(NotifierChannelSender::class) && ($container->has('texter') || $container->has('chatter'))) {
            $senders[] = new Reference(NotifierChannelSender::class);
        }

        $container->getDefinition(DeliveryPolicy::class)->replaceArgument(0, new IteratorArgument($senders));
    }

    private function dedupe(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(NotificationsServices::DEDUPE_POOL_PARAMETER)) {
            return;
        }

        $pool = $container->getParameter(NotificationsServices::DEDUPE_POOL_PARAMETER);
        $container->getParameterBag()->remove(NotificationsServices::DEDUPE_POOL_PARAMETER);

        if (!\is_string($pool) || 'prod' !== $container->getParameter('kernel.environment') || !$this->locality->isLocal($container, $pool)) {
            return;
        }

        throw new InvalidConfigurationException(\sprintf('phil_notifications.delivery.dedupe.cache_pool: the pool "%s" is local to each server, so every server would send the same notification once. Name a cache pool on a shared store — Redis, Memcached, a database —, a shared IdempotencyStore in "store", or set "phil_notifications.delivery.single_server" to true on a single server.', $pool));
    }
}
