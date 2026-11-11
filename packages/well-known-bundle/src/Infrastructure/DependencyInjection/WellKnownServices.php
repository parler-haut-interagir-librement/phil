<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\DependencyInjection;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\WellKnownBundle\Application\Attribute\AsWellKnownDocument;
use Phil\WellKnownBundle\Application\ConfiguredNodeInfoProvider;
use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Application\StaticWebFingerResolver;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\Did;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\SecurityTxt;
use Phil\WellKnownBundle\Infrastructure\Http\Document\AtprotoDidDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\ChangePasswordDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\DocumentResponses;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDiscoveryDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\NodeInfoDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\SecurityTxtDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\StaticDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\WebFingerDocument;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Phil\WellKnownBundle\Infrastructure\Monitoring\SecurityTxtExpiryCheck;
use Phil\WellKnownBundle\Infrastructure\Routing\WellKnownRouteLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the well-known documents: the controller and the route loader, always; each document its
 * configuration turns on, tagged with its path; the resolvers of WebFinger and the provider of NodeInfo; and the check
 * of the expiry of the security.txt, when phil/monitoring-bundle is installed.
 *
 * @phpstan-import-type ResourceConfig from StaticWebFingerResolver
 *
 * @phpstan-type SecurityTxtConfig array{contact: list<string>, expires: ?string, encryption: list<string>, acknowledgments: list<string>, preferred_languages: list<string>, canonical: list<string>, policy: list<string>, hiring: list<string>, ...}
 * @phpstan-type JsonDocumentConfig array{enabled: bool, document: ?array<string, mixed>, file: ?string}
 * @phpstan-type WellKnownConfig array{
 *     cache_max_age: int,
 *     security_txt: array{enabled: bool, contact: list<string>, expires: ?string, encryption: list<string>, acknowledgments: list<string>, preferred_languages: list<string>, canonical: list<string>, policy: list<string>, hiring: list<string>, signer: ?string, expiry_warning_days: int},
 *     change_password: array{enabled: bool, route: ?string, parameters: array<string, string>, url: ?string},
 *     webfinger: array{enabled: bool, resources: array<string, ResourceConfig>},
 *     nodeinfo: array{enabled: bool, path: string, software: array{name: string, version: string, repository: ?string, homepage: ?string}, protocols: list<string>, services: array{inbound: list<string>, outbound: list<string>}, open_registrations: bool, metadata: array<string, mixed>, provider: ?string},
 *     did: JsonDocumentConfig,
 *     did_configuration: array{enabled: bool, linked_dids: list<string|array<string, mixed>>, files: list<string>},
 *     atproto: array{enabled: bool, did: ?string, hosts: array<string, string>},
 *     apple_app_site_association: JsonDocumentConfig,
 *     asset_links: array{enabled: bool, android_apps: list<array{package_name: string, sha256_cert_fingerprints: list<string>, relations: list<string>}>, statements: list<mixed>},
 *     documents: array<string, array{content: ?string, file: ?string, content_type: string, cors: bool}>,
 * }
 */
final class WellKnownServices
{
    public const string DOCUMENT_TAG = 'phil_well_known.document';

    public const string RESOLVER_TAG = 'phil_well_known.webfinger_resolver';

    public const string NODEINFO_ROUTE = 'phil_well_known_nodeinfo_document';

    public const string DID_CONFIGURATION_CONTEXT = 'https://identity.foundation/.well-known/did-configuration/v1';

    private const string JSON = 'application/json';

    private const string JWT = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/D';

    /**
     * @param WellKnownConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(WellKnownController::class, WellKnownController::class)
            ->setArguments([null, $config['cache_max_age']])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        $container->register(WellKnownRouteLoader::class, WellKnownRouteLoader::class)
            ->setArguments([[]])
            ->addTag('routing.loader');
        $container->registerAttributeForAutoconfiguration(AsWellKnownDocument::class, static function (ChildDefinition $definition, AsWellKnownDocument $document): void {
            $definition->addTag(self::DOCUMENT_TAG, array_filter(['path' => $document->path, 'route' => $document->route], static fn (?string $value): bool => null !== $value));
        });
        // The resources of the configuration come first; the constraint is dropped when WebFinger is off.
        $container->registerForAutoconfiguration(WebFingerResolver::class)->addTag(self::RESOLVER_TAG, ['after' => StaticWebFingerResolver::class]);

        if ($config['security_txt']['enabled']) {
            $signer = $config['security_txt']['signer'];
            $securityTxt = self::securityTxt($config['security_txt']);
            self::document($container, 'security.txt', SecurityTxtDocument::class, [$securityTxt->toString(), null === $signer ? null : new Reference($signer)]);
            self::expiryCheck($container, $securityTxt->expires, $config['security_txt']['expiry_warning_days']);
        }

        if ($config['change_password']['enabled']) {
            $route = $config['change_password']['route'];
            self::document($container, 'change-password', ChangePasswordDocument::class, null === $route ? [$config['change_password']['url']] : [$route, new Reference('router'), $config['change_password']['parameters']]);
        }

        if ($config['webfinger']['enabled']) {
            $container->register(StaticWebFingerResolver::class, StaticWebFingerResolver::class)
                ->setArguments([$config['webfinger']['resources']])
                ->addTag(self::RESOLVER_TAG);
            $container->register(WebFinger::class, WebFinger::class)->setArguments([new TaggedIteratorArgument(self::RESOLVER_TAG)]);
            self::document($container, 'webfinger', WebFingerDocument::class, [new Reference(WebFinger::class)]);
        }

        if ($config['nodeinfo']['enabled']) {
            self::nodeInfo($container, $config['nodeinfo']);
        }

        self::json($container, $config['did'], 'did.json', static fn (mixed $document): array => self::didDocument($document), true);
        self::json($container, $config['apple_app_site_association'], 'apple-app-site-association', static fn (mixed $document): array => self::jsonObject($document, 'apple_app_site_association'), false);

        if ($config['did_configuration']['enabled']) {
            $linked = $config['did_configuration']['linked_dids'];

            foreach ($config['did_configuration']['files'] as $file) {
                $text = trim(self::read($container, $file));
                $linked[] = self::checkFile($file, static fn (): string|array => self::linkedDid(str_starts_with($text, '{') ? self::decode($text, $file) : $text, $file));
            }

            self::document($container, 'did-configuration.json', StaticDocument::class, [json_encode(['@context' => self::DID_CONFIGURATION_CONTEXT, 'linked_dids' => $linked], DocumentResponses::FLAGS), self::JSON, true]);
        }

        if ($config['atproto']['enabled']) {
            self::document($container, 'atproto-did', AtprotoDidDocument::class, [$config['atproto']['did'], $config['atproto']['hosts']]);
        }

        if ($config['asset_links']['enabled']) {
            self::document($container, 'assetlinks.json', StaticDocument::class, [json_encode(self::assetLinks($config['asset_links']), DocumentResponses::FLAGS), self::JSON, false]);
        }

        foreach ($config['documents'] as $path => $document) {
            self::document($container, $path, StaticDocument::class, [$document['content'] ?? self::read($container, (string) $document['file']), $document['content_type'], $document['cors']]);
        }
    }

    /**
     * The security.txt of the configuration, which has not expired yet.
     *
     * @param SecurityTxtConfig $config
     *
     * @throws InvalidValue when a field is missing or wrong, or the file has expired
     */
    public static function securityTxt(array $config): SecurityTxt
    {
        if (null === $config['expires']) {
            throw InvalidValue::because('security_txt needs its expires date.');
        }

        $expires = SecurityTxt::expiresAt($config['expires']);

        if ($expires->getTimestamp() <= new DateTimeImmutable()->getTimestamp()) {
            throw InvalidValue::because(\sprintf('The security.txt expired on %s: give it a date in the future.', $config['expires']));
        }

        return new SecurityTxt($config['contact'], $expires, $config['encryption'], $config['acknowledgments'], $config['preferred_languages'], $config['canonical'], $config['policy'], $config['hiring']);
    }

    /**
     * A DID document of a `did:web` identifier: a map whose `id` is one.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidValue when it is not
     */
    public static function didDocument(mixed $document): array
    {
        $document = self::jsonObject($document, 'did');

        if (!\is_string($document['id'] ?? null)) {
            throw InvalidValue::because('The DID document of did has an "id".');
        }

        Did::of($document['id'], 'web');

        return $document;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidValue when the document is not a map with at least one member
     */
    public static function jsonObject(mixed $document, string $name): array
    {
        if (!\is_array($document) || [] === $document || array_is_list($document)) {
            throw InvalidValue::because(\sprintf('The document of %s is a map.', $name));
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    /**
     * A domain linkage credential: a JWT, or a JSON-LD credential as a map.
     *
     * @return string|array<string, mixed>
     *
     * @throws InvalidValue when it is neither
     */
    public static function linkedDid(mixed $credential, string $source): string|array
    {
        if (\is_string($credential) && 1 === preg_match(self::JWT, $credential)) {
            return $credential;
        }

        if (\is_array($credential) && [] !== $credential && !array_is_list($credential)) {
            /** @var array<string, mixed> $credential */
            return $credential;
        }

        throw InvalidValue::because(\sprintf('A linked DID of %s is a JWT, or a JSON-LD credential as a map.', $source));
    }

    /**
     * Registers the check of the `Expires` of the security.txt, when phil/monitoring-bundle is installed.
     */
    private static function expiryCheck(ContainerBuilder $container, DateTimeImmutable $expires, int $warningDays): void
    {
        if (!interface_exists(HealthCheck::class)) {
            return;
        }

        $container->register(SecurityTxtExpiryCheck::class, SecurityTxtExpiryCheck::class)
            ->setArguments([
                new Definition(DateTimeImmutable::class, [$expires->format(DateTimeInterface::ATOM)]),
                new Reference('clock'),
                $warningDays,
            ])
            ->addTag(PhilMonitoringBundle::HEALTH_CHECK_TAG);
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function document(ContainerBuilder $container, string $path, string $class, array $arguments, ?string $route = null): void
    {
        $container->register('phil_well_known.document.'.$path, $class)
            ->setArguments($arguments)
            ->addTag(self::DOCUMENT_TAG, array_filter(['path' => $path, 'route' => $route], static fn (?string $value): bool => null !== $value));
    }

    /**
     * @param WellKnownConfig['nodeinfo'] $config
     */
    private static function nodeInfo(ContainerBuilder $container, array $config): void
    {
        $provider = $config['provider'];

        if (null === $provider) {
            $provider = ConfiguredNodeInfoProvider::class;
            $software = $config['software'];
            $container->register($provider, $provider)->setArguments([
                new Definition(NodeInfoSoftware::class, [$software['name'], $software['version'], $software['repository'], $software['homepage']]),
                $config['open_registrations'],
                $config['metadata'],
            ]);
        }

        self::document($container, 'nodeinfo', NodeInfoDiscoveryDocument::class, [new Reference('router'), self::NODEINFO_ROUTE]);
        self::document($container, $config['path'], NodeInfoDocument::class, [new Reference($provider), $config['protocols'], $config['services']['inbound'], $config['services']['outbound']], self::NODEINFO_ROUTE);
    }

    /**
     * @param JsonDocumentConfig                    $config
     * @param callable(mixed): array<string, mixed> $check
     */
    private static function json(ContainerBuilder $container, array $config, string $path, callable $check, bool $cors): void
    {
        if (!$config['enabled']) {
            return;
        }

        $document = $config['document'];

        if (null === $document) {
            $file = $config['file'] ?? '';
            $document = self::checkFile($file, static fn (): array => $check(self::decode(self::read($container, $file), $file)));
        }

        $contentType = 'did.json' === $path ? (\array_key_exists('@context', $document) ? 'application/did+ld+json' : 'application/did+json') : self::JSON;

        self::document($container, $path, StaticDocument::class, [json_encode($document, DocumentResponses::FLAGS), $contentType, $cors]);
    }

    /**
     * @param WellKnownConfig['asset_links'] $config
     *
     * @return list<mixed>
     */
    private static function assetLinks(array $config): array
    {
        $statements = [];

        foreach ($config['android_apps'] as $app) {
            $statements[] = [
                'relation' => $app['relations'],
                'target' => ['namespace' => 'android_app', 'package_name' => $app['package_name'], 'sha256_cert_fingerprints' => $app['sha256_cert_fingerprints']],
            ];
        }

        return [...$statements, ...$config['statements']];
    }

    /**
     * Reads a file of the configuration when the container is built, and builds the container again when it changes.
     *
     * @throws InvalidArgumentException when the file cannot be read
     */
    private static function read(ContainerBuilder $container, string $file): string
    {
        $path = $container->getParameterBag()->resolveValue($file);
        $content = \is_string($path) && is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $content) {
            throw new InvalidArgumentException(\sprintf('The well-known document file "%s" cannot be read.', $file));
        }

        $container->addResource(new FileResource($path));

        return $content;
    }

    /**
     * @template T
     *
     * @param callable(): T $check
     *
     * @return T
     *
     * @throws InvalidArgumentException when the document of the file is refused
     */
    private static function checkFile(string $file, callable $check): mixed
    {
        try {
            return $check();
        } catch (InvalidValue $invalidValue) {
            throw new InvalidArgumentException(\sprintf('The well-known document file "%s": %s', $file, $invalidValue->getMessage()), $invalidValue->getCode(), previous: $invalidValue);
        }
    }

    /**
     * @throws InvalidArgumentException when the text is not JSON
     */
    private static function decode(string $json, string $file): mixed
    {
        try {
            return json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(\sprintf('The well-known document file "%s" is not JSON: %s.', $file, $jsonException->getMessage()), $jsonException->getCode(), previous: $jsonException);
        }
    }
}
