<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Mailer\MailerGuestLinkSender;
use Phil\PrivacyBundle\Infrastructure\Routing\GuestLinkOrigin;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * The requests of people without an account, off until `guest_requests.enabled`. Once on, nothing in them is optional:
 * the limits need symfony/rate-limiter, and the build fails rather than open a form anyone could flood.
 *
 * @phpstan-import-type GuestConfig from RightsServices
 */
final class GuestRequestServices
{
    public const string PER_CLIENT = 'phil_privacy.guest_requests.per_client';

    public const string PER_ADDRESS = 'phil_privacy.guest_requests.per_address';

    private function __construct()
    {
    }

    /**
     * @param GuestConfig $config
     */
    public static function load(array $config, ServicesConfigurator $services, Reference $clock): void
    {
        if (!$config['enabled']) {
            return;
        }

        if (!class_exists(RateLimiterFactory::class)) {
            throw new InvalidConfigurationException('phil_privacy.guest_requests needs symfony/rate-limiter: a form that sends mail to any address must be limited.');
        }

        if (null === $config['sender'] && null === $config['from']) {
            throw new InvalidConfigurationException('phil_privacy.guest_requests.from is the sender of the links; name it, or a service of your own under guest_requests.sender.');
        }

        RightsServices::duration('guest_requests.link_lifetime', $config['link_lifetime']);
        $storage = new Definition(CacheStorage::class, [new Reference($config['cache'])]);

        foreach ([self::PER_CLIENT => $config['per_client'], self::PER_ADDRESS => $config['per_address']] as $id => $limit) {
            $services->set($id, RateLimiterFactory::class)->args([
                ['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit['limit'], 'interval' => $limit['interval']],
                $storage,
                new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
        }

        if (null === $config['sender']) {
            $services->set(GuestLinkOrigin::class)->args([new Reference('router'), '%'.GuestLinkOrigin::OPTION.'%', '%kernel.debug%']);
            $services->set(MailerGuestLinkSender::class)->args([new Reference('mailer.transports'), new Reference('twig'), new Reference('router'), $config['from'], new Reference(GuestLinkOrigin::class)]);
        }

        $services->set(EmailGuestSubjects::class);
        $services->alias(GuestLinkSender::class, $config['sender'] ?? MailerGuestLinkSender::class);
        $services->alias(GuestSubjects::class, $config['subjects'] ?? EmailGuestSubjects::class);
        $services->alias(GuestRequestStore::class, $config['store'] ?? DbalGuestRequests::class);

        $services->set(GuestRequests::class)->args([
            new Reference(GuestRequestStore::class),
            new Reference(GuestLinkSender::class),
            new Reference(GuestSubjects::class),
            new Reference(SubjectExport::class),
            new Reference(SubjectErasure::class),
            new Reference(ExportPackage::class),
            new Reference('lock.factory'),
            $clock,
            new Reference(self::PER_CLIENT),
            new Reference(self::PER_ADDRESS),
            '%kernel.secret%',
            $config['link_lifetime'],
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])->public();
    }
}
