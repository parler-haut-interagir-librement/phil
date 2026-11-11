<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle;

use Override;
use Phil\TextDiff\Tokenizer;
use Phil\TextDiffBundle\Infrastructure\DependencyInjection\TextDiffServices;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/text-diff` in a Symfony application: the `TextDiff` service with its bound of edits, the `text_diff` and
 * `text_diff_document` Twig filters, the `comparison` Stimulus controller that switches between the inline, old and new
 * views of a difference and steps from change to change, and a stylesheet, both mapped for AssetMapper.
 */
final class PhilTextDiffBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_text_diff';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('max_edits')
                    ->info('The most edits a comparison searches for; past it, the rest of the two texts is shown as replaced whole.')
                    ->defaultValue(1000)
                    ->min(0)
                ->end()
                ->enumNode('tokenizer')
                    ->info('What the text_diff filter compares by when the template names nothing: words, lines, paragraphs or html.')
                    ->values(array_map(static fn (Tokenizer $tokenizer): string => $tokenizer->value, Tokenizer::cases()))
                    ->defaultValue(Tokenizer::Words->value)
                ->end()
                ->scalarNode('html_sanitizer')
                    ->info('Service id of the HTML sanitizer a comparison of HTML passes the values a template does not mark safe through, as html_sanitizer.sanitizer.app; null escapes them.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [
                self::CONTROLLERS_DIR => TextDiffServices::CONTROLLER_NAMESPACE,
                self::STYLES_DIR => TextDiffServices::ASSET_NAMESPACE,
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{max_edits: int, tokenizer: string, html_sanitizer: string|null} $config */
        TextDiffServices::register($builder, $config['max_edits'], Tokenizer::from($config['tokenizer']), $config['html_sanitizer']);
    }
}
