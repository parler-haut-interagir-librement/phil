<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle;

use Override;
use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Application\WebPushMessage;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsPass;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsServices;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Mercure\MercureToasts;
use Phil\NotificationsBundle\Infrastructure\WebPush\MinishlinkWebPushSender;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;

/**
 * Notifications for an application: the types it declares, delivered on Symfony Notifier to each recipient's channels
 * — the site's own centre and toasts, email and its digest, Web Push, chat —, as each recipient chose, and received
 * from the other sites of a network. With MercureBundle, toasts are pushed live; without it, they wait in the session.
 * The tables are added to the Doctrine schema, the centre's CSRF token to the stateless ones of FrameworkBundle, and
 * with AssetMapper the Stimulus controller and its stylesheet are served without a build step.
 */
final class PhilNotificationsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    public const string WORKER_DIR = __DIR__.'/../assets/worker';

    private const string TIME = '/^([01]\d|2[0-3]):[0-5]\d$/D';

    #[Override]
    protected string $extensionAlias = 'phil_notifications';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->enumNode('store')
                ->info('Where notifications are kept: "dbal", or "memory" for tests.')
                ->values(['dbal', 'memory'])
                ->defaultValue('dbal')
            ->end()
            ->scalarNode('connection')
                ->info('The Doctrine connection of the tables.')
                ->cannotBeEmpty()
                ->defaultValue('default')
            ->end()
            ->scalarNode('preferences')
                ->info('Where recipients\' choices come from: "config" (the defaults for everyone), "dbal" (a row each), or the id of a NotificationPreferences service.')
                ->cannotBeEmpty()
                ->defaultValue('config')
            ->end()
            ->scalarNode('recipient_directory')->info('The RecipientDirectory service giving addresses and names; none skips email and SMS.')->defaultNull()->end()
            ->scalarNode('current_recipient')->info("The CurrentRecipient service naming, by a stable identifier, whose centre a request shows; none shows no one's.")->defaultNull()->end()
            ->scalarNode('web_push_sender')->info('The WebPushSender service of the "web_push" channel; the bundle\'s with "web_push" enabled, none otherwise, which skips the channel.')->defaultNull()->end()
            ->scalarNode('message_bus')->info('The Messenger bus notifications and digests go through; none delivers at once.')->defaultValue('messenger.default_bus')->end();

        $this->defaults($children);
        $this->types($children);
        $this->toasts($children);
        $this->schedules($children);
        $this->webPush($children);

        $children
            ->arrayNode('remote')
                ->info('Receiving the notifications of the other sites of a network, signed as Standard Webhooks.')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('receiver')->info("The RemoteNotificationReceiver service; by default, delivered as this site's own.")->defaultNull()->end()
                    ->arrayNode('origins')->info('The origins taken; any other is refused.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('mercure')) {
            $container->extension('phil_notifications', ['toasts' => ['transport' => 'mercure']]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [CentreRequest::TOKEN_ID]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/notifications-bundle',
                self::STYLES_DIR => 'phil-notifications',
                self::WORKER_DIR => 'phil-notifications-worker',
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, preferences: string, recipient_directory: ?string, current_recipient: ?string, web_push_sender: ?string, message_bus: ?string, defaults: array{digest: string, quiet_hours?: array{start: string, end: string, timezone: string}, opt_outs: list<string>, locale: ?string}, types: array<string, array{channels: list<string>, broadcast: list<string>, collapse: bool, digest: bool, toast: bool, importance: string, title: ?string, body: ?string}>, toasts: array{transport: string, hub: string, topic_prefix: string, private: bool}, digest: array{enabled: bool, sender: ?string, from: ?string, template: string, tracking_consent: ?string, at: string, weekday: string, limit: int}, retention: array{read_days: int, unread_days: int, purge_at: string}, schedule: array{enabled: bool, timezone: string}, remote: array{enabled: bool, receiver: ?string, origins: list<string>}, web_push: array{enabled: bool, subject: ?string, public_key: string, private_key: string, ttl: int, push_services: list<string>, max_subscriptions: positive-int, worker: ?string, http_client: ?string, timeout: float}} $config */
        NotificationsServices::register($builder, $config);
    }

    /**
     * Falls back on what the application lacks — a translator, Symfony Security — and stops the container with a
     * helpful message when the application turns the CSRF protection off.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new NotificationsPass());
    }

    private function defaults(NodeBuilder $children): void
    {
        $children
            ->arrayNode('defaults')
                ->info('What each recipient has until they choose otherwise.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('digest')->values(array_map(static fn (DigestFrequency $f): string => $f->value, DigestFrequency::cases()))->defaultValue(DigestFrequency::Never->value)->end()
                    ->arrayNode('quiet_hours')
                        ->info('The hours toasts and interrupting channels are held back, such as 22:00 to 07:00.')
                        ->children()
                            ->scalarNode('start')->isRequired()->end()
                            ->scalarNode('end')->isRequired()->end()
                            ->scalarNode('timezone')->defaultValue('UTC')->end()
                        ->end()
                    ->end()
                    ->arrayNode('opt_outs')->info('The channels turned off, "type:channel" or "*:channel".')->scalarPrototype()->end()->end()
                    ->scalarNode('locale')->info('The locale notifications are written in; the current one when null.')->defaultNull()->end()
                ->end()
            ->end();
    }

    private function types(NodeBuilder $children): void
    {
        $children
            ->arrayNode('types')
                ->info('The notification types, by key, such as "comment.replied".')
                ->useAttributeAsKey('key')
                ->arrayPrototype()
                    ->children()
                        ->arrayNode('channels')->info('Each recipient\'s channels by default: "site", "email", "web_push", "sms", "push"…')->scalarPrototype()->end()->defaultValue([Channels::SITE])->end()
                        ->arrayNode('broadcast')->info('The channels posted to once, such as "chat/discord" or "chat/slack".')->scalarPrototype()->end()->end()
                        ->booleanNode('collapse')->info('Whether a later one about the same subject joins the unread one.')->defaultFalse()->end()
                        ->booleanNode('digest')->info('Whether email leaves it to the digest of those who chose one.')->defaultTrue()->end()
                        ->booleanNode('toast')->info('Whether the centre shows it as a toast.')->defaultTrue()->end()
                        ->enumNode('importance')->values(NotificationType::IMPORTANCES)->defaultValue('medium')->end()
                        ->scalarNode('title')->info('The translation key of the title; "<key>.title" when null.')->defaultNull()->end()
                        ->scalarNode('body')->info('The translation key of the body; "<key>.body" when null.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function toasts(NodeBuilder $children): void
    {
        $children
            ->arrayNode('toasts')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('transport')
                        ->info('How toasts reach the browser: "mercure" (by default with MercureBundle), "flash" (the session), or "none".')
                        ->values(['mercure', 'flash', 'none'])
                        ->defaultValue('flash')
                    ->end()
                    ->scalarNode('hub')->info('The service of the Mercure hub.')->cannotBeEmpty()->defaultValue(HubInterface::class)->end()
                    ->scalarNode('topic_prefix')->info('What comes before a recipient in their topic.')->defaultValue(MercureToasts::PREFIX)->end()
                    ->booleanNode('private')->info('Whether updates go only to subscribers authorized for the topic.')->defaultTrue()->end()
                ->end()
            ->end();
    }

    private function schedules(NodeBuilder $children): void
    {
        $children
            ->arrayNode('digest')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('sender')->info('The DigestSender service; Symfony Mailer by default.')->defaultNull()->end()
                    ->scalarNode('from')->info("The sender address; the mailer's own when null.")->defaultNull()->end()
                    ->scalarNode('template')->info('The Twig template of the email.')->defaultValue('@PhilNotifications/email/digest.html.twig')->end()
                    ->scalarNode('tracking_consent')->info("The EmailTrackingConsent service saying whose opens and clicks may be followed; none follows no one's.")->defaultNull()->end()
                    ->scalarNode('at')->info('The time of the digests, HH:MM.')->defaultValue('07:00')->validate()->ifTrue(static fn (mixed $v): bool => !\is_string($v) || 1 !== preg_match(self::TIME, $v))->thenInvalid('The time of the digests is written HH:MM, not %s.')->end()->end()
                    ->enumNode('weekday')->values(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->defaultValue('monday')->end()
                    ->integerNode('limit')->info('How many notifications a digest holds at most.')->min(1)->max(500)->defaultValue(50)->end()
                ->end()
            ->end()
            ->arrayNode('retention')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('read_days')->info('How long read and archived notifications are kept.')->min(1)->defaultValue(90)->end()
                    ->integerNode('unread_days')->info('How long unread notifications are kept.')->min(1)->defaultValue(365)->end()
                    ->scalarNode('purge_at')->info('The time of the daily purge, HH:MM.')->defaultValue('03:30')->validate()->ifTrue(static fn (mixed $v): bool => !\is_string($v) || 1 !== preg_match(self::TIME, $v))->thenInvalid('The time of the purge is written HH:MM, not %s.')->end()->end()
                ->end()
            ->end()
            ->arrayNode('schedule')
                ->info('The schedule of the digests and the purge, with Symfony Scheduler.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('timezone')->defaultValue('UTC')->end()
                ->end()
            ->end();
    }

    private function webPush(NodeBuilder $children): void
    {
        $children
            ->arrayNode('web_push')
                ->info('Web Push of the bundle: the browsers\' subscriptions and their endpoints, the button and its service worker, and the sender on minishlink/web-push unless "web_push_sender" names another.')
                ->canBeEnabled()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => true === $v['enabled'] && !\is_string($v['subject'] ?? null))
                    ->thenInvalid('Web Push needs a VAPID contact: set "phil_notifications.web_push.subject" to a "mailto:" address or an "https://" page.')
                ->end()
                ->children()
                    ->scalarNode('subject')->info('The VAPID contact push services reach the site\'s operator at: a "mailto:" address or an "https://" page.')->defaultNull()->end()
                    ->scalarNode('public_key')->info('The VAPID public key, in URL-safe Base64; phil:notifications:vapid-keys writes a pair.')->cannotBeEmpty()->defaultValue('%env(PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY)%')->end()
                    ->scalarNode('private_key')->info('The VAPID private key, in URL-safe Base64, kept in the secrets.')->cannotBeEmpty()->defaultValue('%env(PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY)%')->end()
                    ->integerNode('ttl')->info('How long a push service keeps a message for an offline browser, in seconds.')->min(0)->max(WebPushMessage::MAX_TTL)->defaultValue(WebPushMessage::TTL)->end()
                    ->arrayNode('push_services')
                        ->info("The hosts a subscription's endpoint may be on, or under; an empty list takes any public host.")
                        ->scalarPrototype()->cannotBeEmpty()->end()
                        ->defaultValue(PushSubscriber::PUSH_SERVICES)
                    ->end()
                    ->integerNode('max_subscriptions')->info('How many browsers a recipient keeps subscribed, the latest.')->min(1)->max(100)->defaultValue(DbalPushSubscriptions::LIMIT)->end()
                    ->scalarNode('worker')->info("The URL of the service worker; the bundle's, through AssetMapper, when null.")->defaultNull()->end()
                    ->scalarNode('http_client')->info("The HttpClient service messages are posted with; one of the bundle's own when null. Private networks are refused either way.")->defaultNull()->end()
                    ->floatNode('timeout')->info('How long a push service has to answer, in seconds.')->min(1)->max(60)->defaultValue(MinishlinkWebPushSender::TIMEOUT)->end()
                ->end()
            ->end();
    }
}
