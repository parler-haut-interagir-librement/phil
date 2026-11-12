<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\DependencyInjection;

use LogicException;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Validation\Validator;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\DefaultNodes;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use Phil\StructuredDataBundle\Infrastructure\Console\CheckStructuredDataCommand;
use Phil\StructuredDataBundle\Infrastructure\Http\ContentSecurityPolicyNonce;
use Phil\StructuredDataBundle\Infrastructure\Http\RequestAttributeNonce;
use Phil\StructuredDataBundle\Infrastructure\Menu\MenuBreadcrumbProvider;
use Phil\StructuredDataBundle\Infrastructure\SocialCards\PageShareMetadata;
use Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * The services of the bundle: the graph of the page and its providers, the nodes of the configuration, the breadcrumb
 * of a menu when phil/menu-bundle is there, the share metadata when phil/social-cards-bundle is, the Twig functions
 * when Twig is, the nonce of the request or of the content security policy when the configuration asks for one, the
 * validator and the command that checks a page.
 *
 * @phpstan-import-type WebsiteConfig from DefaultNodes
 * @phpstan-import-type OrganizationConfig from DefaultNodes
 *
 * @phpstan-type StructuredDataConfig array{
 *     base_url: ?string,
 *     language: ?string,
 *     request_language: bool,
 *     website: WebsiteConfig,
 *     organization: OrganizationConfig,
 *     web_page: bool,
 *     breadcrumb: array{menu: ?string},
 *     nonce: array{request_attribute: ?string, content_security_policy: bool},
 *     check: array{timeout: float|int},
 *     allow_localhost: bool,
 * }
 */
final class StructuredDataServices
{
    public const string PROVIDER_TAG = 'phil_structured_data.provider';

    public const string NONCE_TAG = 'phil_structured_data.nonce_provider';

    /**
     * The nodes of the configuration, as the graph of every page starts with them.
     *
     * @param StructuredDataConfig $config
     */
    public static function defaultNodes(array $config): DefaultNodes
    {
        return new DefaultNodes($config['website'], $config['organization'], $config['web_page']);
    }

    /**
     * @param StructuredDataConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        // The nodes of the configuration come first: the providers of other packages add to them.
        $container->registerForAutoconfiguration(StructuredDataProvider::class)->addTag(self::PROVIDER_TAG, ['after' => DefaultNodes::class]);
        $container->registerForAutoconfiguration(ScriptNonceProvider::class)->addTag(self::NONCE_TAG);

        $container->register(DefaultNodes::class, DefaultNodes::class)
            ->setArguments([$config['website'], $config['organization'], $config['web_page']])
            ->addTag(self::PROVIDER_TAG);

        $container->register(PageStructuredData::class, PageStructuredData::class)
            ->setArguments([
                new TaggedIteratorArgument(self::PROVIDER_TAG),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['base_url'],
                $config['language'],
                $config['request_language'],
            ])
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->setAlias(NodeCollector::class, PageStructuredData::class)->setPublic(true);

        $menu = $config['breadcrumb']['menu'];

        if (null !== $menu && class_exists(MenuExtension::class)) {
            $container->register(MenuBreadcrumbProvider::class, MenuBreadcrumbProvider::class)
                ->setArguments([new Reference(MenuExtension::class), $menu, $config['web_page']])
                ->addTag(self::PROVIDER_TAG, ['after' => DefaultNodes::class]);
        }

        if (null !== $config['nonce']['request_attribute']) {
            $container->register(RequestAttributeNonce::class, RequestAttributeNonce::class)
                ->setArguments([new Reference('request_stack'), $config['nonce']['request_attribute']])
                ->addTag(self::NONCE_TAG);
        }

        if ($config['nonce']['content_security_policy']) {
            if (!class_exists(ContentSecurityPolicyContext::class)) {
                throw new LogicException('The nonce.content_security_policy of phil_structured_data needs phil/browser-security-bundle: run "composer require phil/browser-security-bundle".');
            }

            $container->register(ContentSecurityPolicyNonce::class, ContentSecurityPolicyNonce::class)
                ->setArguments([new Reference(ContentSecurityPolicyContext::class)])
                ->addTag(self::NONCE_TAG);
        }

        if (interface_exists(ShareMetadataSource::class)) {
            $container->register(PageShareMetadata::class, PageShareMetadata::class)
                ->setArguments([new Reference(PageStructuredData::class)])
                ->setAutoconfigured(true);
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(StructuredDataExtension::class, StructuredDataExtension::class)
                ->setArguments([new Reference(PageStructuredData::class), new TaggedIteratorArgument(self::NONCE_TAG)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        // The validator of the command, and, through the bundle's boot, that of Schema, take localhost in development.
        $container->setParameter('phil_structured_data.allow_localhost', $config['allow_localhost']);
        $container->register(Validator::class, Validator::class)->setArguments([null, $config['allow_localhost']]);
        $container->register(CheckStructuredDataCommand::class, CheckStructuredDataCommand::class)
            ->setArguments([new Reference(Validator::class), new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE), (float) $config['check']['timeout']])
            ->addTag('console.command');
    }
}
