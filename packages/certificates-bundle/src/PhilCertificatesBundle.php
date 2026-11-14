<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle;

use LogicException;
use Override;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Infrastructure\DependencyInjection\CertificatesServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Certificates of completion on Open Badges, from its configuration:
 *
 * ```yaml
 * phil_certificates:
 *     pdf:
 *         page_size: a4
 *         font: '%kernel.project_dir%/assets/fonts/NotoSans-Regular.ttf'
 *         bold_font: '%kernel.project_dir%/assets/fonts/NotoSans-Bold.ttf'
 * ```
 *
 * The bundle builds on `phil/open-badges-bundle`, which signs the credentials and publishes the status lists — the
 * bundle names its own `CertificateStatusLists` as the lists to publish —, and on `phil/paged-media-bundle`, which
 * renders the PDF and embeds the credential through Gotenberg. With DoctrineBundle, courses and certificates are
 * mapped and stored; with Twig, the register's routes are served.
 */
final class PhilCertificatesBundle extends AbstractBundle
{
    public const string DEFAULT_LAYOUT = '@PhilCertificates/layout.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_certificates';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('clock')
                    ->info('Service id of the PSR-20 clock.')
                    ->cannotBeEmpty()
                    ->defaultValue('clock')
                ->end()
                ->scalarNode('lock_factory')
                    ->info('Service id of the LockFactory the status index of an award is taken under, null for none. The default one is used when FrameworkBundle registers it; its store must be shared by every server that awards.')
                    ->defaultValue(CertificatesServices::DEFAULT_LOCK_FACTORY)
                ->end()
                ->arrayNode('reminders')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('days')
                            ->info('The days before the end of a certificate at which CertificateExpiring is dispatched, once each.')
                            ->requiresAtLeastOneElement()
                            ->integerPrototype()->min(1)->max(ExpiryReminders::MAX_DAYS)->end()
                            ->defaultValue([60, 30, 7])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('status_lists')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')
                            ->info('The name of the status lists the certificates fill: <prefix>-0, <prefix>-1 and so on.')
                            ->defaultValue('certificates')
                            ->validate()
                                ->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match('/^'.StatusListLayout::PREFIX.'$/D', $prefix))
                                ->thenInvalid('The prefix is up to 50 letters, digits, dashes and underscores; %s is not.')
                            ->end()
                        ->end()
                        ->integerNode('size')
                            ->info('The indexes of each list: phil_open_badges.status_lists.size, or less.')
                            ->min(1)
                            ->defaultValue(131_072)
                        ->end()
                        ->scalarNode('others')
                            ->info("Service id of the application's own StatusLists, asked for the lists of other names.")
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('pdf')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('page_size')->values(['letter', 'a4'])->defaultValue('letter')->end()
                        ->scalarNode('font')->info('A TrueType font file for the text; Helvetica when none, which prints Latin-1 only.')->defaultNull()->end()
                        ->scalarNode('bold_font')->info('A TrueType font file for the titles and the name; Helvetica Bold when none.')->defaultNull()->end()
                        ->booleanNode('bake')->info('Whether the signed credential is embedded in the PDF, through Gotenberg.')->defaultTrue()->end()
                        ->booleanNode('archival')->info('Whether the PDF is repaired to PDF/A-3b, through Gotenberg, after the credential is embedded.')->defaultFalse()->end()
                        ->booleanNode('sign')->info("Whether the PDF is signed with phil_paged_media's certificate, last.")->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('register')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('layout')
                            ->info('The Twig layout the pages extend; it defines the "title" and "content" blocks.')
                            ->cannotBeEmpty()
                            ->defaultValue(self::DEFAULT_LAYOUT)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * The checks run while extensions prepend, the one moment the container knows every registered extension.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (['phil_open_badges' => 'PhilOpenBadgesBundle, from phil/open-badges-bundle', 'phil_paged_media' => 'PhilPagedMediaBundle, from phil/paged-media-bundle'] as $extension => $bundle) {
            if (!$builder->hasExtension($extension)) {
                throw new LogicException(\sprintf('PhilCertificatesBundle needs %s: register it too.', $bundle));
            }
        }

        $builder->prependExtensionConfig('phil_open_badges', ['status_lists' => ['service' => CertificateStatusLists::class]]);

        if ($this->hasBundle($builder, 'DoctrineBundle')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCertificatesBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\CertificatesBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{clock: string, lock_factory: ?string, reminders: array{days: list<int>}, status_lists: array{prefix: string, size: int, others: ?string}, pdf: array{page_size: string, font: ?string, bold_font: ?string, bake: bool, archival: bool, sign: bool}, register: array{enabled: bool, layout: string}} $config */
        $config['register']['enabled'] = $config['register']['enabled'] && $this->hasBundle($builder, 'TwigBundle');
        CertificatesServices::register($builder, $config, $this->hasBundle($builder, 'DoctrineBundle'), class_exists(Command::class));
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
