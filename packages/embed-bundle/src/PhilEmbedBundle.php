<?php

declare(strict_types=1);

namespace Phil\EmbedBundle;

use Override;
use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Phil\EmbedBundle\Infrastructure\DependencyInjection\EmbedServices;
use Phil\EmbedBundle\Infrastructure\DependencyInjection\FramingPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Features other sites embed: a token signed for one origin, by an ordered list of keys; the page answered in the
 * theme the token or the page asks for, framed by that origin alone, through phil/browser-security-bundle when it is
 * enabled; the `<phil-embed>` element that holds the frame at the height its content asks for by a small
 * `postMessage` protocol, the origin checked on both sides, and a plain link without JavaScript. With AssetMapper, the
 * frame's controller is served without a build step.
 *
 * ```yaml
 * phil_embed:
 *     keys:
 *         - { id: '2026-11', secret: '%env(EMBED_KEY)%' }
 *     embeds:
 *         calendar:
 *             origins: ['https://partner.example']
 *             themes: [light, dark]
 * ```
 *
 * @phpstan-import-type Config from EmbedServices
 */
final class PhilEmbedBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_embed';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()->ifTrue($this->hasInvalidOrigin(...))->thenInvalid('An origin of the embeds is refused: an origin is an address without path, query nor fragment, on localhost only with allow_localhost.')->end()
            ->children()
                ->arrayNode('keys')
                    ->info('The keys that sign the tokens, in order: the first signs, all verify.')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('id')->info('The identifier the token names: 1 to 32 letters, digits, "_" or "-".')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('secret')->info('The secret, 32 bytes at least, from an environment variable or a secret.')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                        ->validate()->ifTrue($this->isInvalidKey(...))->thenInvalid('A key is refused: its identifier is 1 to 32 letters, digits, "_" or "-", and its secret 32 bytes at least.')->end()
                    ->end()
                ->end()
                ->booleanNode('allow_localhost')->info('Whether localhost is an origin and an address, in development and tests.')->defaultFalse()->end()
                ->enumNode('framing')
                    ->info('Who writes frame-ancestors: phil/browser-security-bundle when it is enabled ("auto"), always it ("browser_security"), or the bundle\'s own header ("header").')
                    ->values([EmbedServices::FRAMING_AUTO, EmbedServices::FRAMING_BROWSER_SECURITY, EmbedServices::FRAMING_HEADER])
                    ->defaultValue(EmbedServices::FRAMING_AUTO)
                ->end()
                ->integerNode('script_max_age')->info('How long browsers keep the script, in seconds.')->min(0)->max(31_536_000)->defaultValue(86_400)->end()
                ->arrayNode('embeds')
                    ->info('The features other sites may embed, by name.')
                    ->useAttributeAsKey('name')
                    ->validate()->ifTrue($this->hasInvalidEmbed(...))->thenInvalid('An embed of %s is refused: see Embed.')->end()
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('origins')->info('The origins allowed to embed it; any origin a token names when empty.')->scalarPrototype()->end()->end()
                            ->arrayNode('themes')->info('The themes it is shown in.')->scalarPrototype()->end()->end()
                            ->scalarNode('default_theme')->info('The theme it is shown in when the token names none, one of its themes.')->defaultNull()->end()
                            ->integerNode('ttl')->info('How long a token holds, in seconds; until its key is removed when null.')->min(1)->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/embed-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        EmbedServices::register($builder, $config, self::CONTROLLERS_DIR);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new FramingPass());
    }

    private function isInvalidKey(mixed $key): bool
    {
        /** @var array{id: string, secret: string} $key */
        try {
            SigningKey::of($key['id'], $key['secret']);
        } catch (InvalidEmbed) {
            return true;
        }

        return false;
    }

    private function hasInvalidEmbed(mixed $embeds): bool
    {
        /** @var array<string, array{themes: list<string>, default_theme: ?string, ttl: ?int}> $embeds */
        try {
            foreach ($embeds as $name => $embed) {
                Embed::of($name, [], $embed['themes'], $embed['default_theme'], $embed['ttl']);
            }
        } catch (InvalidEmbed) {
            return true;
        }

        return false;
    }

    private function hasInvalidOrigin(mixed $config): bool
    {
        /** @var Config $config */
        try {
            foreach ($config['embeds'] as $embed) {
                foreach ($embed['origins'] as $origin) {
                    Origin::fromString($origin, $config['allow_localhost']);
                }
            }
        } catch (InvalidEmbed) {
            return true;
        }

        return false;
    }
}
