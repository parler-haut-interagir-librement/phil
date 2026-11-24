<?php

declare(strict_types=1);

namespace Phil\FollowBundle;

use Override;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\FollowBundle\Infrastructure\DependencyInjection\FollowServices;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Follow a content item, a thread, a listing or a member, at a level — watching, mentions only, ignoring, or one per
 * kind of event —, inherited from parents unless a nearer choice says otherwise, and hear of new activity through
 * phil/notifications-bundle, sent to the followers a bounded page at a time; requests for locked profiles, mutual
 * relationships beside one-way following, and followers hidden at each member's choice.
 *
 * ```yaml
 * phil_follow:
 *     access: App\Follow\SubjectAccess
 *     parents: App\Follow\ForumParents
 * ```
 */
final class PhilFollowBundle extends AbstractBundle
{
    /** The notification types the bundle declares when `notifications.declare_types` is true. */
    public const array NOTIFICATION_TYPES = [
        FollowTypes::ACTIVITY => ['channels' => ['site', 'email'], 'collapse' => true],
        FollowTypes::QUIET => ['channels' => ['site'], 'collapse' => true, 'toast' => false],
        FollowTypes::REQUESTED => ['channels' => ['site', 'email']],
        FollowTypes::ACCEPTED => ['channels' => ['site']],
        FollowTypes::FOLLOWED => ['channels' => ['site']],
    ];

    #[Override]
    protected string $extensionAlias = 'phil_follow';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('connection')->info('The Doctrine connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
            ->scalarNode('member_subject_type')->info('The subject type that names a member, whose follows are follows of people.')->cannotBeEmpty()->defaultValue('member')->end()
            ->integerNode('batch_size')->info('How many followers one message serves.')->min(1)->max(FanOutActivityHandler::MAX_BATCH)->defaultValue(500)->end()
            ->scalarNode('message_bus')->info('The bus of the FanOutActivity messages; "messenger.default_bus" when null and Messenger is there.')->defaultNull()->end()
            ->scalarNode('current_member')->info('The service giving the current member, a CurrentRecipient; the one of phil_notifications when null.')->defaultNull()->end()
            ->scalarNode('access')->info('The FollowAccess service; required with Symfony Security, every subject open otherwise.')->defaultNull()->end()
            ->scalarNode('approval')->info('The FollowApproval service, which names the approver of a locked subject; every subject open when null.')->defaultNull()->end()
            ->scalarNode('parents')->info('The SubjectParents service; no subject has parents when null.')->defaultNull()->end()
            ->arrayNode('inheritance')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_depth')->info('How many parents of a subject are read, nearest first.')->min(0)->max(SubjectChain::MAX_DEPTH)->defaultValue(5)->end()
                ->end()
            ->end()
            ->arrayNode('events')->info('The kinds of event a member chooses a level for at the custom level, such as "reply.created".')->scalarPrototype()->end()->end()
            ->arrayNode('auto_follow')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('created')->info('Whether AutoFollow::created() follows what a member creates.')->defaultFalse()->end()
                    ->booleanNode('replied')->info('Whether AutoFollow::replied() follows what a member replies to.')->defaultFalse()->end()
                    ->enumNode('level')->values([FollowLevel::Watching->value, FollowLevel::Mentions->value])->defaultValue(FollowLevel::Watching->value)->end()
                ->end()
            ->end()
            ->arrayNode('mutual')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->info('Whether members may join in mutual relationships.')->defaultFalse()->end()
                    ->booleanNode('follow_each_other')->info('Whether an accepted relationship makes each member follow the other, unless they chose a level already.')->defaultTrue()->end()
                ->end()
            ->end()
            ->arrayNode('followers')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('hidden_by_default')->info('Whether a member who never chose hides their followers.')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('email')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('once_until_seen')->info('Whether the activities after one notification sent on every channel stay in the centre until the member sees the subject.')->defaultTrue()->end()
                    ->integerNode('resend_after')->info('After how many seconds unseen a notification goes out on every channel again.')->min(0)->defaultValue(604800)->end()
                ->end()
            ->end();

        $this->notifications($children);
        $this->schedule($children);
        $children->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [FollowGuard::TOKEN_ID]]]);
        }

        if ($builder->hasExtension('phil_notifications') && $this->declaresTypes($builder)) {
            $container->extension('phil_notifications', ['types' => self::NOTIFICATION_TYPES]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, member_subject_type: string, batch_size: int, message_bus: ?string, current_member: ?string, access: ?string, approval: ?string, parents: ?string, inheritance: array{max_depth: int}, events: list<string>, auto_follow: array{created: bool, replied: bool, level: string}, mutual: array{enabled: bool, follow_each_other: bool}, followers: array{hidden_by_default: bool}, email: array{once_until_seen: bool, resend_after: int}, notifications: array{declare_types: bool, activity: string, quiet: string, requested: string, accepted: string, followed: string}, retention: array{activity_days: int}, schedule: array{enabled: bool, at: string, timezone: string, lock_factory: ?string, state_pool: ?string}} $config */
        FollowServices::register($builder, $config);
    }

    private function notifications(NodeBuilder $children): void
    {
        $children
            ->arrayNode('notifications')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('declare_types')->info('Whether the bundle declares its notification types under their default keys in phil_notifications.types.')->defaultTrue()->end()
                    ->scalarNode('activity')->info('The type of a new activity on a followed subject.')->cannotBeEmpty()->defaultValue(FollowTypes::ACTIVITY)->end()
                    ->scalarNode('quiet')->info('The type of the activities that follow it while the member has not seen the subject.')->cannotBeEmpty()->defaultValue(FollowTypes::QUIET)->end()
                    ->scalarNode('requested')->info('The type of a request to approve.')->cannotBeEmpty()->defaultValue(FollowTypes::REQUESTED)->end()
                    ->scalarNode('accepted')->info('The type of a request accepted.')->cannotBeEmpty()->defaultValue(FollowTypes::ACCEPTED)->end()
                    ->scalarNode('followed')->info('The type of a new follower of a member.')->cannotBeEmpty()->defaultValue(FollowTypes::FOLLOWED)->end()
                ->end()
            ->end()
            ->arrayNode('retention')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('activity_days')->info('How many days an activity and the claims of its deliveries are kept.')->min(1)->defaultValue(30)->end()
                ->end()
            ->end();
    }

    private function schedule(NodeBuilder $children): void
    {
        $children
            ->arrayNode('schedule')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->info('Whether the schedule "phil_follow" purges every day, with Symfony Scheduler.')->defaultTrue()->end()
                    ->scalarNode('at')->info('The time of the purge, HH:MM.')->defaultValue('04:15')->end()
                    ->scalarNode('timezone')->defaultValue('UTC')->end()
                    ->scalarNode('lock_factory')->info('The lock factory that keeps the schedule to one worker; used when it exists, none when null.')->defaultValue('lock.factory')->end()
                    ->scalarNode('state_pool')->info('The cache pool that keeps the last run of the schedule; used when it exists, none when null.')->defaultValue('cache.app')->end()
                ->end()
            ->end();
    }

    /**
     * Reads `notifications.declare_types` from the application's configuration, before it is processed: the last
     * file that sets it wins, as the processed configuration would say.
     */
    private function declaresTypes(ContainerBuilder $builder): bool
    {
        $declare = true;

        foreach ($builder->getExtensionConfig('phil_follow') as $config) {
            $notifications = \is_array($config) ? $config['notifications'] ?? null : null;

            if (\is_array($notifications) && \array_key_exists('declare_types', $notifications)) {
                $declare = false !== $notifications['declare_types'];
            }
        }

        return $declare;
    }
}
