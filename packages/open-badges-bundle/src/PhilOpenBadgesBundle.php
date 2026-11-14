<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle;

use Override;
use Phil\OpenBadges\Did\DidWeb;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\OpenBadgesServices;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\VerificationRateLimitPass;
use Phil\SafeHttp\Limits;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/open-badges` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_open_badges:
 *     issuer:
 *         id: https://example.org/open-badges/issuer
 *         name: Example
 *         url: https://example.org
 *     key:
 *         algorithm: EdDSA
 *         secret: '%env(base64:OPEN_BADGES_SEED)%'
 *     securing: data_integrity
 * ```
 *
 * The bundle wires the issuer and its key, the builders of credentials, endorsements, learner records and status
 * lists, the securing the configuration chooses, and the verifier with the issuer's key and the keys the application
 * trusts — and, when `remote_issuers` is enabled, the keys of other issuers read from their profiles. Its routes serve
 * the issuer's profile and keys, the status lists the application's `StatusLists` gives, and a verification page when
 * Twig is installed, whose checks count against a limiter of `phil/rate-limit-bundle` when the configuration names
 * one.
 *
 * An application that registers the bundle without its issuer and key is refused when the container is built, with
 * the options to set.
 */
final class PhilOpenBadgesBundle extends AbstractBundle
{
    public const string DEFAULT_LAYOUT = '@PhilOpenBadges/layout.html.twig';

    /**
     * Whether TwigBundle is registered, which the verification page needs; set before the extension is loaded.
     */
    public const string TWIG_PARAMETER = '.phil_open_badges.twig';

    /**
     * Whether PhilRateLimitBundle is registered, which a limit on the verification page needs; set before the
     * extension is loaded.
     */
    public const string RATE_LIMIT_PARAMETER = '.phil_open_badges.rate_limit_bundle';

    #[Override]
    protected string $extensionAlias = 'phil_open_badges';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('issuer')
                    ->info("The issuer's profile; its identifier is the absolute URL the bundle serves the profile at.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('id')->defaultNull()->cannotBeEmpty()->end()
                        ->scalarNode('name')->defaultNull()->cannotBeEmpty()->end()
                        ->scalarNode('url')->defaultNull()->end()
                        ->scalarNode('email')->defaultNull()->end()
                        ->scalarNode('image')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('key')
                    ->info("The issuer's private key, read from a secret: a seed of 32 bytes for EdDSA, an RSA key in PEM for RS256.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('algorithm')->values(['EdDSA', 'RS256'])->defaultValue('EdDSA')->end()
                        ->scalarNode('secret')->info('"%env(base64:OPEN_BADGES_SEED)%", "%env(file:OPEN_BADGES_KEY)%".')->defaultNull()->cannotBeEmpty()->end()
                        ->scalarNode('passphrase')->defaultNull()->end()
                    ->end()
                ->end()
                ->enumNode('securing')
                    ->info('How the issuer secures its credentials: "jwt", or "data_integrity" (eddsa-rdfc-2022, an EdDSA key).')
                    ->values([Securing::JWT, Securing::DATA_INTEGRITY])
                    ->defaultValue(Securing::JWT)
                ->end()
                ->booleanNode('allow_localhost')
                    ->info("Whether the issuer's addresses may name localhost or a host under .localhost, for development and tests.")
                    ->defaultFalse()
                ->end()
                ->scalarNode('clock')
                    ->info('Service id of the PSR-20 clock.')
                    ->cannotBeEmpty()
                    ->defaultValue('clock')
                ->end()
                ->integerNode('max_age')
                    ->info('How many seconds the profile and the keys may be cached.')
                    ->min(0)
                    ->defaultValue(3600)
                ->end()
                ->arrayNode('contexts')
                    ->info('The JSON-LD context documents the application provides, a file for each URL, such as those of Open Badges 3.0 and CLR 2.0 published by 1EdTech.')
                    ->useAttributeAsKey('url')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('trusted_keys')
                    ->info('The Ed25519 keys of other issuers the verifier accepts: the publicKeyMultibase of each verification method.')
                    ->useAttributeAsKey('method')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('remote_issuers')
                    ->info('Verifying the credentials of issuers the application does not configure, by reading their profile through phil/safe-http.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('trusted')
                            ->info('The identifiers of the only issuers whose profile is read, each an HTTPS address or a did:web; any issuer on a public host when empty.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->scalarNode('cache')->info('Service id of the cache pool the keys read are kept in.')->cannotBeEmpty()->defaultValue('cache.app')->end()
                        ->integerNode('ttl')->info('How many seconds the keys of a profile are kept.')->min(60)->max(86_400)->defaultValue(3600)->end()
                        ->integerNode('failure_ttl')->info('How many seconds a profile that could not be read is not asked again.')->min(1)->max(3600)->defaultValue(60)->end()
                        ->floatNode('timeout')->info('How many seconds the issuer may keep silent.')->min(0.1)->max(Limits::MAX_TIMEOUT)->defaultValue(3.0)->end()
                        ->floatNode('max_duration')->info('How many seconds the whole read of a profile may take, its redirects included.')->min(0.1)->max(Limits::MAX_DURATION)->defaultValue(5.0)->end()
                        ->integerNode('max_bytes')->info('The largest profile read, in bytes.')->min(Limits::MIN_BYTES)->max(1_048_576)->defaultValue(65_536)->end()
                        ->scalarNode('user_agent')
                            ->info('The User-Agent of the requests: a name for the site and a way to reach whoever runs it.')
                            ->defaultValue('PhilOpenBadges/0.1')
                            ->validate()
                                ->ifTrue(static fn (mixed $agent): bool => !\is_string($agent) || !Limits::isHeaderValue($agent))
                                ->thenInvalid('The User-Agent is one line of text, without control characters; %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('status_lists')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service')
                            ->info("Service id of the application's StatusLists; without it, no list is served.")
                            ->defaultValue(StatusLists::class)
                        ->end()
                        ->integerNode('size')->info('The bits of each list, from 131072.')->min(131_072)->defaultValue(131_072)->end()
                        ->integerNode('max_age')->info('How many seconds a status list is valid and may be cached.')->min(1)->defaultValue(300)->end()
                    ->end()
                ->end()
                ->arrayNode('verification')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('layout')
                            ->info('The Twig layout the page extends; it defines the "title" and "content" blocks.')
                            ->cannotBeEmpty()
                            ->defaultValue(self::DEFAULT_LAYOUT)
                        ->end()
                        ->integerNode('max_upload')->info('The largest image or text checked, in bytes.')->min(1024)->defaultValue(2_097_152)->end()
                        ->scalarNode('rate_limit')
                            ->info('The name of a limiter of phil/rate-limit-bundle the checks of the page count against; none by default.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $limiter): bool => null !== $limiter && (!\is_string($limiter) || 1 !== preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $limiter)))
                                ->thenInvalid('The name of a limiter is 1 to 64 letters, digits, "_", "." and "-"; %s is not.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new VerificationRateLimitPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter(self::TWIG_PARAMETER, $builder->hasExtension('twig'));
        $builder->setParameter(self::RATE_LIMIT_PARAMETER, $builder->hasExtension('phil_rate_limit'));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{issuer: array{id: ?string, name: ?string, url: ?string, email: ?string, image: ?string}, key: array{algorithm: string, secret: ?string, passphrase: ?string}, securing: string, allow_localhost: bool, clock: string, max_age: int, contexts: array<string, string>, trusted_keys: array<string, string>, status_lists: array{service: ?string, size: int, max_age: int}, verification: array{enabled: bool, layout: string, max_upload: int, rate_limit: ?string}, remote_issuers: array{enabled: bool, trusted: list<string>, cache: string, ttl: int, failure_ttl: int, timeout: float, max_duration: float, max_bytes: int, user_agent: string}} $config */
        $id = $config['issuer']['id'];
        $name = $config['issuer']['name'];
        $secret = $config['key']['secret'];

        if (\in_array(null, [$id, $name, $secret], true)) {
            $missing = array_keys(array_filter(['issuer.id' => $id, 'issuer.name' => $name, 'key.secret' => $secret], static fn (?string $value): bool => null === $value));

            throw new InvalidConfigurationException(\sprintf('PhilOpenBadgesBundle signs as an issuer the application names: set phil_open_badges.issuer.id, phil_open_badges.issuer.name and phil_open_badges.key.secret in config/packages/phil_open_badges.yaml; %s %s missing.', implode(', ', $missing), 1 === \count($missing) ? 'is' : 'are'));
        }

        $config['issuer']['id'] = $id;
        $config['issuer']['name'] = $name;
        $config['key']['secret'] = $secret;

        foreach (array_filter(['id' => $config['issuer']['id'], 'url' => $config['issuer']['url'], 'image' => $config['issuer']['image']], \is_string(...)) as $address => $url) {
            try {
                Url::fromString($url, $config['allow_localhost']);
            } catch (InvalidUrl $invalidUrl) {
                throw new InvalidConfigurationException(\sprintf('phil_open_badges.issuer.%s: %s', $address, $invalidUrl->getMessage()), 0, $invalidUrl);
            }
        }

        $this->checkTrustedIssuers($config['remote_issuers']['trusted'], $config['allow_localhost']);

        try {
            new Issuer($config['issuer']['id'], $config['issuer']['name'], $config['issuer']['url'], $config['issuer']['email'], $config['issuer']['image']);
        } catch (InvalidBadge $invalidBadge) {
            throw new InvalidConfigurationException('phil_open_badges.issuer: '.$invalidBadge->getMessage(), 0, $invalidBadge);
        }

        if (Securing::DATA_INTEGRITY === $config['securing'] && 'EdDSA' !== $config['key']['algorithm']) {
            throw new InvalidConfigurationException('phil_open_badges.securing: Data Integrity proofs are signed with an EdDSA key.');
        }

        if (null !== $config['verification']['rate_limit'] && true !== ($builder->hasParameter(self::RATE_LIMIT_PARAMETER) ? $builder->getParameter(self::RATE_LIMIT_PARAMETER) : false)) {
            throw new InvalidConfigurationException('phil_open_badges.verification.rate_limit: a limit on the verification page needs PhilRateLimitBundle, from phil/rate-limit-bundle: register it too.');
        }

        $config['verification']['enabled'] = $config['verification']['enabled'] && true === ($builder->hasParameter(self::TWIG_PARAMETER) ? $builder->getParameter(self::TWIG_PARAMETER) : false);
        OpenBadgesServices::register($builder, $config);
    }

    /**
     * Each trusted issuer is a did:web whose document can be read, or an HTTPS address without a fragment.
     *
     * @param list<string> $trusted
     */
    private function checkTrustedIssuers(array $trusted, bool $allowLocalhost): void
    {
        foreach ($trusted as $index => $issuer) {
            if (DidWeb::isDidWeb($issuer)) {
                try {
                    DidWeb::fromString($issuer);
                } catch (InvalidCredential $invalidCredential) {
                    throw new InvalidConfigurationException(\sprintf('phil_open_badges.remote_issuers.trusted.%d: "%s" is not a did:web whose document can be read: %s', $index, $issuer, $invalidCredential->getMessage()), 0, $invalidCredential);
                }

                continue;
            }

            try {
                $url = Url::fromString($issuer, $allowLocalhost);
            } catch (InvalidUrl $invalidUrl) {
                throw new InvalidConfigurationException(\sprintf('phil_open_badges.remote_issuers.trusted.%d: %s', $index, $invalidUrl->getMessage()), 0, $invalidUrl);
            }

            if ('https' !== $url->scheme || null !== $url->fragment) {
                throw new InvalidConfigurationException(\sprintf('phil_open_badges.remote_issuers.trusted.%d: an issuer whose profile is read is an HTTPS address without a fragment, or a did:web; "%s" is not.', $index, $issuer));
            }
        }
    }
}
