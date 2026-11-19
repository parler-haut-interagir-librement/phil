<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle;

use Override;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;
use Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection\VirtualClassroomServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Live classes in a Symfony application: rooms on BigBlueButton or Jitsi servers, a join link written for each
 * participant's role, the attendance — when each participant arrived and left — and the recordings read from the
 * servers' signed notifications or by asking them, and, with phil/courses-bundle, a live class as an activity of a
 * course, completed by the share of the session attended.
 *
 * ```yaml
 * phil_virtual_classroom:
 *     providers:
 *         main: { type: bigbluebutton, url: '%env(BBB_URL)%', secret: '%env(BBB_SECRET)%' }
 *     courses: { threshold: 75 }
 * ```
 *
 * @phpstan-import-type Config from VirtualClassroomServices
 */
final class PhilVirtualClassroomBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_virtual_classroom';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (mixed $config): bool => \is_array($config) && \is_string($config['default_provider'] ?? null) && !\array_key_exists($config['default_provider'], \is_array($config['providers'] ?? null) ? $config['providers'] : []))
                ->thenInvalid('The default provider is one of the providers of the configuration.')
            ->end()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services.")
                    ->defaultTrue()
                ->end()
                ->scalarNode('default_provider')
                    ->info('The provider a room is scheduled on when none is named; the first one by default.')
                    ->defaultNull()
                ->end()
                ->append($this->providers())
                ->scalarNode('store')
                    ->info('Where the rooms are kept: "dbal", "memory" for tests, or the identifier of a ClassroomStore service.')
                    ->cannotBeEmpty()
                    ->defaultValue(VirtualClassroomServices::STORE_DBAL)
                ->end()
                ->scalarNode('connection')->info('The Doctrine DBAL connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
                ->scalarNode('table_prefix')
                    ->info('The start of the names of the three tables.')
                    ->defaultValue(ClassroomTables::PREFIX)
                    ->validate()->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,40}$/D', $prefix))->thenInvalid('A table prefix is 1 to 41 lowercase letters, digits or "_", starting with a letter; %s is not.')->end()
                ->end()
                ->scalarNode('clock')->info('The PSR-20 clock that dates the rooms and the stays.')->cannotBeEmpty()->defaultValue('clock')->end()
                ->scalarNode('http_client')->info('The HTTP client the BigBlueButton servers are called with.')->cannotBeEmpty()->defaultValue('http_client')->end()
                ->scalarNode('webhook_secret')
                    ->info('The secret the addresses of the webhook are signed with, 16 bytes or more.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.secret%')
                ->end()
                ->booleanNode('allow_localhost')
                    ->info('Whether the webhook addresses may be on localhost, in development; a room then gets callbacks a server outside cannot reach.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('moderator_role')
                    ->info('The Symfony role that makes the user a moderator of a room; the room is the subject of the check.')
                    ->cannotBeEmpty()
                    ->defaultValue('ROLE_VIRTUAL_CLASSROOM_MODERATOR')
                ->end()
                ->scalarNode('current_participant')
                    ->info('A CurrentParticipant service replacing the authenticated user of Symfony Security.')
                    ->defaultNull()
                ->end()
                ->arrayNode('courses')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Registers the activity type virtual_classroom when PhilCoursesBundle is enabled.')->defaultTrue()->end()
                        ->integerNode('threshold')->info('The share of the session attended that completes the activity, in percents, unless the activity sets its own.')->min(1)->max(100)->defaultValue(75)->end()
                    ->end()
                ->end()
                ->arrayNode('privacy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Gives the stays in the access and erasure requests when PhilPrivacyBundle is enabled.')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (true !== $config['enabled']) {
            return;
        }

        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        /** @var Config $config */
        VirtualClassroomServices::register($builder, $config, \is_array($bundles) ? array_values(array_filter(array_keys($bundles), is_string(...))) : []);
    }

    private function providers(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('providers');
        $node
            ->info('The conferencing servers, by name.')
            ->useAttributeAsKey('name')
            ->validate()
                ->ifTrue(static fn (mixed $providers): bool => \is_array($providers) && [] !== array_filter(array_keys($providers), static fn (int|string $name): bool => 1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', (string) $name)))
                ->thenInvalid('A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".')
            ->end()
            ->arrayPrototype()
                ->validate()
                    ->ifTrue(static fn (mixed $provider): bool => \is_array($provider) && 'service' === $provider['type'] && null === $provider['service'])
                    ->thenInvalid('A provider of type "service" names its service.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (mixed $provider): bool => \is_array($provider) && \in_array($provider['type'], ['bigbluebutton', 'jitsi'], true) && (null === $provider['url'] || null === $provider['secret']))
                    ->thenInvalid('A BigBlueButton or Jitsi server has its "url" and its "secret".')
                ->end()
                ->validate()
                    ->ifTrue(static fn (mixed $provider): bool => \is_array($provider) && \in_array($provider['type'], ['jitsi', 'jaas'], true) && null === $provider['app_id'])
                    ->thenInvalid('A Jitsi server has its "app_id".')
                ->end()
                ->validate()
                    ->ifTrue(static fn (mixed $provider): bool => \is_array($provider) && 'jaas' === $provider['type'] && (null === $provider['key_id'] || null === $provider['private_key']))
                    ->thenInvalid('A JaaS account has its "key_id" and its "private_key".')
                ->end()
                ->children()
                    ->enumNode('type')
                        ->info('"bigbluebutton"; "jitsi", a server of the application with tokens; "jaas", 8x8\'s hosted Jitsi; "service", a VirtualClassroom service of the application.')
                        ->values(['bigbluebutton', 'jitsi', 'jaas', 'service'])
                        ->isRequired()
                    ->end()
                    ->scalarNode('service')->info('The VirtualClassroom service of a provider of type "service".')->defaultNull()->end()
                    ->scalarNode('url')->info('BigBlueButton: the address of its API; Jitsi: the address of the server; JaaS: another server than 8x8.vc.')->defaultNull()->end()
                    ->scalarNode('secret')->info('BigBlueButton: the shared secret; Jitsi: the application secret of its tokens.')->defaultNull()->end()
                    ->enumNode('checksum')->info('BigBlueButton: the hash of the checksums.')->values(['sha256', 'sha384', 'sha512'])->defaultValue('sha256')->end()
                    ->booleanNode('analytics')->info('BigBlueButton: reads the attendance its learning analytics callback posts at the end of a session.')->defaultTrue()->end()
                    ->booleanNode('signed_analytics')->info('BigBlueButton: reads that attendance only with its bearer token.')->defaultTrue()->end()
                    ->scalarNode('app_id')->info('Jitsi: the application identifier of the tokens; JaaS: vpaas-magic-cookie-….')->defaultNull()->end()
                    ->scalarNode('key_id')->info('JaaS: the identifier of the API key.')->defaultNull()->end()
                    ->scalarNode('private_key')->info('JaaS: the private key of the API key, in PEM.')->defaultNull()->end()
                    ->scalarNode('webhook_secret')->info('Jitsi and JaaS: the secret its notifications are signed with; none read without it.')->defaultNull()->end()
                    ->integerNode('token_ttl')->info('Jitsi and JaaS: how long a join link lets its participant in, in seconds.')->min(60)->max(86_400)->defaultValue(10_800)->end()
                    ->scalarNode('audience')->info('Jitsi: the "aud" claim its server expects.')->cannotBeEmpty()->defaultValue('jitsi')->end()
                    ->scalarNode('subject')->info('Jitsi: the "sub" claim, the host of the server by default.')->defaultNull()->end()
                    ->floatNode('timeout')->info('BigBlueButton: the seconds without a byte before a call gives up.')->min(0.1)->max(120)->defaultValue(5.0)->end()
                    ->floatNode('max_duration')->info('BigBlueButton: the seconds a whole call may take.')->min(0.1)->max(120)->defaultValue(10.0)->end()
                    ->integerNode('max_response_bytes')->info('BigBlueButton: the largest answer read.')->min(1024)->max(16_777_216)->defaultValue(1_048_576)->end()
                ->end()
            ->end();

        return $node;
    }
}
