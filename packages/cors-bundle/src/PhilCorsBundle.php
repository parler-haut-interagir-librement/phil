<?php

declare(strict_types=1);

namespace Phil\CorsBundle;

use Override;
use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Application\PolicyResolver;
use Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use Phil\CorsBundle\Infrastructure\EventListener\CorsListener;
use Phil\CorsBundle\Infrastructure\PathPolicyResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * CORS for a Symfony application: rules by path, the preflights of those paths answered before the router and the
 * firewall, Vary: Origin on every answer, and a configuration checked while the container compiles — a wildcard with
 * credentials, an origin that is not one or a pattern that does not compile stops the build.
 *
 * ```yaml
 * phil_cors:
 *     paths:
 *         '^/api/':
 *             allow_origins: ['https://app.example.com']
 *             allow_methods: [GET, POST, PUT, DELETE]
 *             allow_headers: [Content-Type, Authorization]
 *             allow_credentials: true
 *             max_age: 3600
 * ```
 */
final class PhilCorsBundle extends AbstractBundle
{
    /**
     * Above the router (32) and the firewall (8): a preflight carries no credentials and matches no route.
     */
    public const int REQUEST_PRIORITY = 250;

    #[Override]
    protected string $extensionAlias = 'phil_cors';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services; false registers none.")
                    ->defaultTrue()
                ->end()
                ->arrayNode('paths')
                    ->info('Regular expressions on the path, without delimiters, each with its policy; the first that matches wins, in this order.')
                    ->useAttributeAsKey('pattern')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->append($this->list('allow_origins', 'Exact origins, such as https://app.example.com, or "*" alone for any origin without credentials.'))
                            ->append($this->list('allow_origin_patterns', 'Regular expressions on the origin, anchored by ^ and $, without delimiters.'))
                            ->append($this->list('allow_methods', 'The methods a cross-origin request may use.', ['GET', 'HEAD', 'POST']))
                            ->append($this->list('allow_headers', 'The request headers it may send, or "*" alone for any without credentials.'))
                            ->append($this->list('expose_headers', 'The response headers its page may read, or "*" alone for any without credentials.'))
                            ->booleanNode('allow_credentials')->info('Whether cookies, TLS client certificates and HTTP authentication travel.')->defaultFalse()->end()
                            ->integerNode('max_age')->info('The seconds a browser keeps the answer to a preflight; null sends no Access-Control-Max-Age.')->defaultNull()->min(0)->max(86400)->end()
                            ->booleanNode('allow_private_network')->info('Whether a public page may reach this server on a private network (Access-Control-Allow-Private-Network).')->defaultFalse()->end()
                        ->end()
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
        /** @var array{enabled: bool, paths: array<array-key, array<array-key, mixed>>} $config */
        if (!$config['enabled']) {
            return;
        }

        $rules = [];

        foreach ($config['paths'] as $pattern => $policy) {
            $pattern = (string) $pattern;

            $environment = [];
            $builder->resolveEnvPlaceholders($policy, null, $environment);

            // A value read from the environment is known only when the container is used: the rule is then checked when
            // the resolver is built, on the first request, with the same messages.
            if ([] === $environment) {
                try {
                    PathRule::fromConfig($pattern, $policy);
                } catch (InvalidCorsPolicy $exception) {
                    throw new InvalidConfigurationException(\sprintf('phil_cors.paths."%s": %s', $pattern, $exception->getMessage()), previous: $exception);
                }
            }

            $rules[] = inline_service(PathRule::class)->factory([PathRule::class, 'fromConfig'])->args([$pattern, $policy]);
        }

        $services = $container->services();
        $services->set('phil_cors.path_policy_resolver', PathPolicyResolver::class)->args([$rules]);
        $services->alias(PolicyResolver::class, 'phil_cors.path_policy_resolver');
        $services->set('phil_cors.headers', CorsHeaders::class);
        $services->set('phil_cors.listener', CorsListener::class)
            ->args([service(PolicyResolver::class), service('phil_cors.headers')])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onKernelRequest', 'priority' => self::REQUEST_PRIORITY])
            ->tag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse']);
    }

    /**
     * A list of strings that also takes one string.
     *
     * @param list<string> $default
     */
    private function list(string $name, string $info, array $default = []): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node->info($info)
            ->beforeNormalization()->castToArray()->end()
            ->defaultValue($default)
            ->stringPrototype()->end();

        return $node;
    }
}
