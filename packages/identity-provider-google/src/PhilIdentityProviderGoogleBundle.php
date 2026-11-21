<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle;

use Override;
use Phil\IdentityProviderGoogle\Application\GoogleSettings;
use Phil\IdentityProviderGoogle\Infrastructure\DependencyInjection\GoogleServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Sign-in with Google for `phil/identity-provider-bundle`: Google as a federated provider of its module `external`, on
 * Symfony's OpenID Connect pieces, and a receiver of Google's Cross-Account Protection events that closes the sessions
 * of the accounts linked to a Google account revoked, disabled or deleted.
 *
 * ```yaml
 * phil_identity_provider_google:
 *     client_id: '%env(GOOGLE_CLIENT_ID)%'
 *     client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
 * ```
 *
 * @phpstan-import-type Config from GoogleServices
 */
final class PhilIdentityProviderGoogleBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = GoogleServices::ALIAS;

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')->info("Registers the bundle's services.")->defaultTrue()->end()
                ->scalarNode('client_id')->info('The client ID of the application at Google, such as "1234-abc.apps.googleusercontent.com".')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->info('Its client secret, from Symfony secrets: "%env(GOOGLE_CLIENT_SECRET)%".')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('hosted_domains')
                    ->info('The Google Workspace domains accepted, in lowercase; any Google account when empty.')
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('profile')->info("Whether the person's name is asked for, with the scope \"profile\"; only the address by default.")->defaultFalse()->end()
                ->enumNode('prompt')->info('The "prompt" sent to Google; "default" sends none.')->values([...GoogleSettings::PROMPTS, 'default'])->defaultValue('select_account')->end()
                ->integerNode('clock_skew')->info("The seconds Google's clock may differ from the application's.")->min(0)->max(300)->defaultValue(60)->end()
                ->integerNode('attempt_lifetime')->info('The seconds a sign-in waits for Google to send the person back.')->min(60)->max(3600)->defaultValue(600)->end()
                ->scalarNode('cache_pool')->info("The cache pool of Google's discovery documents and keys, and of the security events received.")->cannotBeEmpty()->defaultValue('cache.app')->end()
                ->scalarNode('http_client')->info('The HTTP client Google is called with, wrapped by phil/safe-http; its own by default.')->defaultNull()->end()
                ->scalarNode('clock')->info('The PSR-20 clock of the time checks.')->cannotBeEmpty()->defaultValue('clock')->end()
                ->arrayNode('risc')
                    ->info("The receiver of Google's Cross-Account Protection events.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether the endpoint receives security events.')->defaultFalse()->end()
                        ->arrayNode('audiences')->info('The client IDs the events may be meant for; the client ID by default.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->integerNode('max_age')->info('The seconds a security event token may be old; its identifier is kept as long.')->min(60)->max(2_592_000)->defaultValue(604_800)->end()
                        ->booleanNode('hold_hijacked')->info('Whether a Google account disabled as hijacked may not sign in until Google enables it again.')->defaultTrue()->end()
                        ->scalarNode('holds_pool')->info('The PSR-6 pool of the held accounts, which must be durable; the cache pool by default.')->defaultNull()->end()
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

        /** @var Config $config */
        GoogleServices::register($builder, $config);
    }
}
