<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\DependencyInjection;

use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\Tools\ToolEvents;
use LogicException;
use Phil\Automations\Attribute\AsAutomationAction;
use Phil\Automations\Attribute\AsAutomationTrigger;
use Phil\Automations\Contract\Trigger;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Verp;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\SyntaxPolicy;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\TypoSuggester;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\NewsletterBundle\Application\ApprovalPolicy;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\SandboxedTemplates;
use Phil\NewsletterBundle\Application\Contract\AudienceWebhookSender;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;
use Phil\NewsletterBundle\Application\Contract\ConfirmationThrottle;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Application\Contract\SegmentResolver;
use Phil\NewsletterBundle\Application\Contract\SendThrottle;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Handler\DeliverAudienceWebhookHandler;
use Phil\NewsletterBundle\Application\Handler\DispatchDueCampaignsHandler;
use Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler;
use Phil\NewsletterBundle\Application\Handler\NewsletterSchedule;
use Phil\NewsletterBundle\Application\Handler\SendCampaignHandler;
use Phil\NewsletterBundle\Application\Handler\SendConfirmationHandler;
use Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler;
use Phil\NewsletterBundle\Application\ListRecipients;
use Phil\NewsletterBundle\Application\NewsletterKey;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\EngagementRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SendRunRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\SuppressionRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\ValueObject\BreakerPolicy;
use Phil\NewsletterBundle\Domain\ValueObject\SendWindow;
use Phil\NewsletterBundle\Infrastructure\Automations\AudienceTrigger;
use Phil\NewsletterBundle\Infrastructure\Automations\LinkClickedTrigger;
use Phil\NewsletterBundle\Infrastructure\Automations\SendAction;
use Phil\NewsletterBundle\Infrastructure\Automations\SubscribeAction;
use Phil\NewsletterBundle\Infrastructure\Automations\UnsubscribeAction;
use Phil\NewsletterBundle\Infrastructure\Console\FetchBouncesCommand;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSendRunRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchemaListener;
use Phil\NewsletterBundle\Infrastructure\Http\ClickController;
use Phil\NewsletterBundle\Infrastructure\Http\PreviewController;
use Phil\NewsletterBundle\Infrastructure\Http\SubscriptionController;
use Phil\NewsletterBundle\Infrastructure\Imap\ImapBounceMailbox;
use Phil\NewsletterBundle\Infrastructure\Privacy\NewsletterPrivacy;
use Phil\NewsletterBundle\Infrastructure\RateLimiter\RateLimiterSendThrottle;
use Phil\NewsletterBundle\Infrastructure\RateLimiter\SubscriptionRateLimiter;
use Phil\NewsletterBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use Phil\NewsletterBundle\Infrastructure\Webhook\AudienceWebhookListener;
use Phil\NewsletterBundle\Infrastructure\Webhook\StandardWebhooksAudienceSender;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\StandardWebhooksBundle\Infrastructure\Http\WebhookSender;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The services of the bundle, from its processed configuration. The optional integrations — `phil/automations`,
 * `phil/privacy-bundle`, Symfony's RemoteEvent, `phil/standard-webhooks-bundle`, the ORM's schema listener — are
 * registered only when their packages are installed and, for the webhooks and the mailbox, configured.
 */
final class NewsletterServices
{
    public const string PROFILE_TAG = 'phil_newsletter.sender_profile';

    /** The resolver of mail domains the confirmation handler asks, when `check_mail_domain` is on. */
    public const string MAIL_DOMAINS = 'phil_newsletter.mail_domains';

    /** The limits of the subscription form, `subscription.rate_limit`. */
    public const string SUBSCRIPTION_LIMITER = 'phil_newsletter.subscription_limiter';

    /**
     * @param array<array-key, mixed> $config
     */
    public static function load(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $connection = service(self::string($config['connection']));
        $clock = service('clock');
        $secret = self::string($config['secret']);
        $bounces = self::array($config['bounces']);
        $verp = self::array($bounces['verp']);
        $imap = self::array($bounces['imap']);

        self::repositories($services, $connection, $clock, $secret);
        self::profiles($services, self::array($config['profiles']), self::string($config['default_profile']));

        $services->set(CurrentSite::class, ConfiguredSite::class)->args([self::string($config['site'])]);
        $services->set(SignedLinks::class)->args([$secret, $clock]);
        $services->set(NewsletterUrls::class)->args([service('router'), array_map(static fn (mixed $site): string => self::string(self::array($site)['base_url']), self::array($config['sites']))]);
        $services->set(SegmentResolver::class, ListRecipients::class)->args([service(SubscriberRepository::class)]);
        $services->set(SendThrottle::class, RateLimiterSendThrottle::class)
            ->args([inline_service(CacheStorage::class)->args([service(self::string($config['rate_limiter_storage']))]), $clock]);

        if (!$builder->has(Markup::class)) {
            $services->set(Markup::class)->args([inline_service(DefaultCompiler::class), inline_service(SanitizerPurifier::class)->factory([SanitizerPurifier::class, 'defaultPolicies'])]);
        }

        if (!$builder->has(EmailValidator::class)) {
            $services->set(EmailValidator::class)->args([
                inline_service(SyntaxPolicy::class),
                null,
                inline_service(DisposableDomains::class)->factory([DisposableDomains::class, 'bundled']),
                null,
                inline_service(TypoSuggester::class),
                inline_service(TopLevelDomains::class)->factory([TopLevelDomains::class, 'bundled']),
            ]);
        }

        $services->set('phil_newsletter.verp', Verp::class)->args([self::string($verp['local_part']), self::string($verp['domain']), self::string($verp['separator'])]);
        $verpService = true === $verp['enabled'] ? service('phil_newsletter.verp') : null;

        $services->set(SandboxedTemplates::class)->tag('kernel.reset', ['method' => 'reset']);
        $services->set(ContentRenderer::class)->args([service(Markup::class), service('twig'), service(SandboxedTemplates::class)]);
        $services->set(MessageComposer::class)->args([$verpService, self::derivedKey($secret, 'verp')]);
        $limiter = self::subscriptionLimiter($services, self::array(self::array($config['subscription'])['rate_limit']), self::string($config['rate_limiter_storage']), $secret);
        $services->set(Subscriptions::class)->args([
            service(MailingListRepository::class), service(SubscriberRepository::class), service(SuppressionRepository::class), service(DeliveryRepository::class),
            service(EngagementRepository::class), service(EmailValidator::class), service('messenger.default_bus'), service('event_dispatcher'), $clock, $limiter instanceof ReferenceConfigurator ? service(ConfirmationThrottle::class) : null,
        ]);
        $approval = self::array($config['approval']);
        $breaker = self::array($config['circuit_breaker']);
        $services->set(ApprovalPolicy::class)->args([true === $approval['required'], self::string($approval['role']), true === $approval['four_eyes']]);
        $services->set(SendRuns::class)->args([
            service(SendRunRepository::class), service(DeliveryRepository::class), service('messenger.default_bus'), service('event_dispatcher'), $clock,
            true === $breaker['enabled'] ? inline_service(BreakerPolicy::class)->args([self::integer($breaker['minimum']), $breaker['transport_failure_rate'], $breaker['bounce_rate'], $breaker['complaint_rate']]) : null,
        ]);
        $services->set(Campaigns::class)->args([
            service(CampaignRepository::class), service(DeliveryRepository::class), service(EngagementRepository::class), service('messenger.default_bus'), $clock,
            service(SendRuns::class), service(ApprovalPolicy::class), service('security.authorization_checker')->nullOnInvalid(), service('security.token_storage')->nullOnInvalid(), service('event_dispatcher'),
        ]);
        $services->set(ClickTracker::class)->args([
            service(SignedLinks::class), service(CampaignRepository::class), service(DeliveryRepository::class), service(EngagementRepository::class),
            service('event_dispatcher'), $clock, self::integer(self::array($config['clicks'])['minimum_seconds']),
        ]);
        $services->set(BounceHandler::class)->args([
            inline_service(BounceParser::class)->arg('$verp', $verpService), service(SubscriberRepository::class), service(DeliveryRepository::class),
            service(SuppressionRepository::class), service(Subscriptions::class), service(CurrentSite::class), $clock, $verpService, self::derivedKey($secret, 'verp'), self::integer($bounces['soft_threshold']),
            service(SendRuns::class),
        ]);

        if (true === $config['check_mail_domain']) {
            $services->set(self::MAIL_DOMAINS, DnsGetRecordResolver::class);
        }

        self::handlers($services, $clock, true === $imap['enabled'], true === $config['check_mail_domain'], true === $approval['required']);
        self::http($services, $clock, self::string($config['preview_role']), $limiter);

        if (true === $imap['enabled']) {
            $services->set(BounceMailbox::class, ImapBounceMailbox::class)
                ->factory([ImapBounceMailbox::class, 'overTls'])
                ->args([self::string($imap['host']), self::integer($imap['port']), self::string($imap['user']), self::string($imap['password']), self::string($imap['folder']), true === $imap['delete']])
                ->tag('kernel.reset', ['method' => 'reset']);
            $services->set(FetchBouncesHandler::class)->args([service(BounceMailbox::class), service(BounceHandler::class)])->tag('messenger.message_handler');
            $services->set(FetchBouncesCommand::class)->args([service(FetchBouncesHandler::class)])->tag('console.command', ['command' => 'phil:newsletter:fetch-bounces']);
        }

        self::integrations($services, $builder, $config, $clock);
    }

    /**
     * The key of one use, derived when the service that needs it is built: the secret is often an environment variable,
     * whose value the container knows only then.
     */
    private static function derivedKey(string $secret, string $use): InlineServiceConfigurator
    {
        return inline_service('string')->factory(NewsletterKey::derive(...))->args([$secret, $use]);
    }

    private static function repositories(ServicesConfigurator $services, mixed $connection, mixed $clock, string $secret): void
    {
        $services->set(MailingListRepository::class, DbalMailingListRepository::class)->args([$connection]);
        $services->set(SubscriberRepository::class, DbalSubscriberRepository::class)->args([$connection]);
        $services->set(CampaignRepository::class, DbalCampaignRepository::class)->args([$connection]);
        $services->set(DeliveryRepository::class, DbalDeliveryRepository::class)->args([$connection]);
        $services->set(TemplateRepository::class, DbalTemplateRepository::class)->args([$connection]);
        $services->set(EngagementRepository::class, DbalEngagementRepository::class)->args([$connection]);
        $services->set(SendRunRepository::class, DbalSendRunRepository::class)->args([$connection]);
        $services->set(SuppressionRepository::class, DbalSuppressionRepository::class)->args([$connection, $clock, self::derivedKey($secret, 'suppression')]);
    }

    /**
     * @param array<array-key, mixed> $profiles
     */
    private static function profiles(ServicesConfigurator $services, array $profiles, string $default): void
    {
        if ([] === $profiles) {
            $profiles = [$default => ['from' => 'newsletter@example.com', 'from_name' => '', 'reply_to' => null, 'transport' => null, 'unsubscribe_mailbox' => null, 'window' => ['days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '00:00', 'until' => '24:00', 'timezone' => 'UTC'], 'daily_cap' => null, 'rate' => 120, 'domain_rates' => []]];
        }

        foreach ($profiles as $name => $profile) {
            $profile = self::array($profile);
            $window = self::array($profile['window']);
            $services->set('phil_newsletter.profile.'.$name, SenderProfile::class)
                ->args([
                    (string) $name,
                    self::string($profile['from']),
                    self::string($profile['from_name']),
                    $profile['reply_to'],
                    $profile['transport'],
                    inline_service(SendWindow::class)->factory([SendWindow::class, 'of'])->args([$window['days'], $window['from'], $window['until'], $window['timezone']]),
                    $profile['daily_cap'],
                    $profile['rate'],
                    $profile['domain_rates'],
                    $profile['unsubscribe_mailbox'],
                ])
                ->tag(self::PROFILE_TAG);
        }

        $services->set(SenderProfiles::class)->args([tagged_iterator(self::PROFILE_TAG), $default]);
    }

    private static function handlers(ServicesConfigurator $services, mixed $clock, bool $mailbox, bool $checkMailDomain, bool $approvalRequired): void
    {
        $bus = service('messenger.default_bus');
        $services->set(SendCampaignHandler::class)
            ->args([service(CampaignRepository::class), service(DeliveryRepository::class), service(SegmentResolver::class), $bus, $clock, service(SendRuns::class), $approvalRequired])
            ->tag('messenger.message_handler');
        $services->set(SendDeliveryHandler::class)
            ->args([
                service(DeliveryRepository::class), service(SubscriberRepository::class), service(CampaignRepository::class), service(MailingListRepository::class),
                service(TemplateRepository::class), service(SuppressionRepository::class), service(SenderProfiles::class), service(SendThrottle::class),
                service(ContentRenderer::class), service(MessageComposer::class), service(ClickTracker::class), service(SignedLinks::class),
                service(NewsletterUrls::class), service('mailer.transports'), $bus, $clock, service(SendRuns::class),
            ])
            ->tag('messenger.message_handler');
        $services->set(SendConfirmationHandler::class)
            ->args([
                service(SubscriberRepository::class), service(MailingListRepository::class), service(TemplateRepository::class), service(SenderProfiles::class),
                service(SignedLinks::class), service(NewsletterUrls::class), service(ContentRenderer::class), service(MessageComposer::class), service('twig'), service('mailer.transports'),
                $clock, $checkMailDomain ? service(self::MAIL_DOMAINS) : null, service('logger')->nullOnInvalid(),
            ])
            ->tag('messenger.message_handler');
        $services->set(DispatchDueCampaignsHandler::class)->args([service(Campaigns::class)])->tag('messenger.message_handler');
        $services->set(NewsletterSchedule::class)->args([$mailbox])->tag('scheduler.schedule_provider', ['name' => 'newsletter']);
    }

    private static function http(ServicesConfigurator $services, mixed $clock, string $role, ?ReferenceConfigurator $limiter): void
    {
        $services->set(SubscriptionController::class)
            ->args([
                service(Subscriptions::class), service(MailingListRepository::class), service(SubscriberRepository::class), service(SignedLinks::class),
                service(CurrentSite::class), service('security.csrf.token_manager'), service('twig'), $clock, $limiter,
            ])
            ->tag('controller.service_arguments')
            ->public();
        $services->set(ClickController::class)->args([service(ClickTracker::class)])->tag('controller.service_arguments')->public();
        $services->set(PreviewController::class)
            ->args([service(ContentRenderer::class), service(TemplateRepository::class), service(CurrentSite::class), service('security.authorization_checker'), $role])
            ->tag('controller.service_arguments')
            ->public();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function integrations(ServicesConfigurator $services, ContainerBuilder $builder, array $config, mixed $clock): void
    {
        if (interface_exists(Trigger::class)) {
            foreach (AudienceTrigger::TYPES as $type) {
                $services->set('phil_newsletter.trigger.'.$type->value, AudienceTrigger::class)->args([$type, service(MailingListRepository::class)])->tag(AsAutomationTrigger::TAG);
            }

            $services->set(LinkClickedTrigger::class)->tag(AsAutomationTrigger::TAG);
            $services->set(SubscribeAction::class)->args([service(Subscriptions::class), service(SubscriberRepository::class), service(MailingListRepository::class)])->tag(AsAutomationAction::TAG);
            $services->set(UnsubscribeAction::class)->args([service(Subscriptions::class), service(SubscriberRepository::class), service(MailingListRepository::class)])->tag(AsAutomationAction::TAG);
            $services->set(SendAction::class)->args([service(SubscriberRepository::class), service(DeliveryRepository::class), service('messenger.default_bus'), $clock])->tag(AsAutomationAction::TAG);
        }

        if (interface_exists(PersonalDataExporter::class)) {
            $services->set(NewsletterPrivacy::class)
                ->args([service(SubscriberRepository::class), service(MailingListRepository::class), service(DeliveryRepository::class), service(EngagementRepository::class), service(Subscriptions::class)])
                ->autoconfigure();
        }

        $consumer = self::array($config['bounces'])['remote_event_consumer'];

        if (\is_string($consumer) && interface_exists(ConsumerInterface::class)) {
            $services->set(MailerEventConsumer::class)->args([service(BounceHandler::class)])->tag('remote_event.consumer', ['consumer' => $consumer]);
        }

        $webhooks = self::array($config['webhooks']);

        if ([] !== $webhooks) {
            if (!class_exists(WebhookSender::class)) {
                throw new LogicException('The audience webhooks of phil_newsletter need phil/standard-webhooks-bundle: composer require phil/standard-webhooks-bundle.');
            }

            $services->set(AudienceWebhookSender::class, StandardWebhooksAudienceSender::class)->args([service(WebhookSender::class)]);
            $services->set(AudienceWebhookListener::class)
                ->args([service('messenger.default_bus'), array_map(static fn (mixed $endpoint): array => array_values(array_map(self::string(...), self::array(self::array($endpoint)['events']))), $webhooks)])
                ->tag('kernel.event_listener', ['event' => AudienceChanged::class]);
            $services->set(DeliverAudienceWebhookHandler::class)
                ->args([service(AudienceWebhookSender::class), service(MailingListRepository::class), array_map(static fn (mixed $endpoint): string => self::string(self::array($endpoint)['url']), $webhooks), service('logger')->nullOnInvalid()])
                ->tag('messenger.message_handler');
        }

        if (class_exists(ToolEvents::class) && $builder->hasExtension('doctrine')) {
            $services->set(NewsletterSchemaListener::class)
                ->args([service(self::string($config['connection']))])
                ->tag('doctrine.event_listener', ['event' => ToolEvents::postGenerateSchema]);
        }
    }

    /**
     * The limits of the subscription form, when `subscription.rate_limit` is on: two sliding windows and a token
     * bucket of the Rate Limiter component, in the pool of `rate_limiter_storage`, under `lock.factory` when it is
     * defined; `null` when they are off.
     *
     * @param array<array-key, mixed> $limits
     *
     * @throws InvalidConfigurationException when an interval or the delay is not a duration
     */
    private static function subscriptionLimiter(ServicesConfigurator $services, array $limits, string $storage, string $secret): ?ReferenceConfigurator
    {
        if (true !== $limits['enabled']) {
            return null;
        }

        $perIp = self::array($limits['per_ip']);
        $perAddress = self::array($limits['per_address']);
        $resendDelay = self::string($limits['resend_delay']);

        foreach (['per_ip.interval' => $perIp['interval'], 'per_address.interval' => $perAddress['interval'], 'resend_delay' => $resendDelay] as $option => $duration) {
            self::duration('subscription.rate_limit.'.$option, self::string($duration));
        }

        $factory = static fn (string $count, array $config): InlineServiceConfigurator => inline_service(RateLimiterFactory::class)->args([
            ['id' => 'phil_newsletter.subscription.'.$count] + $config,
            inline_service(CacheStorage::class)->args([service($storage)]),
            service('lock.factory')->nullOnInvalid(),
        ]);

        $services->set(self::SUBSCRIPTION_LIMITER, SubscriptionRateLimiter::class)->args([
            $factory('ip', ['policy' => 'sliding_window', 'limit' => self::integer($perIp['limit']), 'interval' => self::string($perIp['interval'])]),
            $factory('address', ['policy' => 'sliding_window', 'limit' => self::integer($perAddress['limit']), 'interval' => self::string($perAddress['interval'])]),
            $factory('resend', ['policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => $resendDelay, 'amount' => 1]]),
            $secret,
        ]);
        $services->alias(SubscriptionRateLimiter::class, self::SUBSCRIPTION_LIMITER);
        $services->alias(ConfirmationThrottle::class, self::SUBSCRIPTION_LIMITER);

        return service(self::SUBSCRIPTION_LIMITER);
    }

    /**
     * @throws InvalidConfigurationException when the text is not a positive duration of the Rate Limiter component, such as "15 minutes"
     */
    private static function duration(string $option, string $duration): void
    {
        try {
            $seconds = new DateTimeImmutable('@0')->modify('+'.$duration)->getTimestamp();
        } catch (DateMalformedStringException) {
            $seconds = null;
        }

        if (null === $seconds || $seconds <= 0) {
            throw new InvalidConfigurationException(\sprintf('"%s" is a duration, such as "15 minutes", not "%s".', $option, $duration));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function array(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function integer(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }
}
