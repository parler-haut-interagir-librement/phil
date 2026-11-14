<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Http\CookieInventoryFactory;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookieListener;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The consent policy and the cookie inventory, read together at the build: the inventory's YAML file is parsed once,
 * each of its categories must be one the policy asks about, and a mistake in either fails the container build rather
 * than a request. The file is a resource of the container, which is built again when it changes.
 *
 * @phpstan-import-type ConsentConfig from RightsServices
 */
final class CookieServices
{
    private function __construct()
    {
    }

    /**
     * @param ConsentConfig                                   $consent
     * @param array{inventory: string|null, watch: bool|null} $cookies
     */
    public static function load(array $consent, array $cookies, ServicesConfigurator $services, ContainerBuilder $builder): void
    {
        $data = self::read($cookies['inventory'], $builder);
        $categories = [];

        foreach ($consent['categories'] as $name => $category) {
            $categories[] = [(string) $name, $category['necessary'], $category['labels'], $category['descriptions']];
        }

        try {
            $policy = new ConsentPolicy($consent['version'], array_map(static fn (array $arguments): ConsentCategory => new ConsentCategory(...$arguments), $categories));
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            throw new InvalidConfigurationException('phil_privacy.consent: '.$invalidPrivacyValue->getMessage(), $invalidPrivacyValue->getCode(), previous: $invalidPrivacyValue);
        }

        try {
            $inventory = CookieInventory::fromArray($data, $policy);
            CookieInventoryFactory::create($data, $consent['cookie_name'], $consent['lifetime']);
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            throw new InvalidConfigurationException('phil_privacy.cookies.inventory: '.$invalidPrivacyValue->getMessage(), $invalidPrivacyValue->getCode(), previous: $invalidPrivacyValue);
        }

        $services->set(ConsentPolicy::class)
            ->args([$consent['version'], array_map(
                static fn (array $arguments): Definition => new Definition(ConsentCategory::class, [...$arguments, $inventory->namesOf($arguments[0])]),
                $categories,
            )])
            ->public();

        $services->set(CookieInventory::class)
            ->factory(CookieInventoryFactory::create(...))
            ->args([$data, $consent['cookie_name'], $consent['lifetime']])
            ->public();

        if ($cookies['watch'] ?? ($builder->hasParameter('kernel.debug') && true === $builder->getParameter('kernel.debug'))) {
            $services->set(UndeclaredCookieListener::class)
                ->args([new Reference(CookieInventory::class), new Reference('logger')])
                ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function read(?string $path, ContainerBuilder $builder): array
    {
        if (null === $path && !$builder->hasParameter('kernel.project_dir')) {
            return [];
        }

        $file = $builder->getParameterBag()->resolveValue($path ?? '%kernel.project_dir%/config/privacy/cookies.yaml');

        if (!\is_string($file)) {
            throw new InvalidConfigurationException('phil_privacy.cookies.inventory is the path of a YAML file.');
        }

        $builder->addResource(new FileExistenceResource($file));

        if (!is_file($file)) {
            return [];
        }

        $builder->addResource(new FileResource($file));

        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $parseException) {
            throw new InvalidConfigurationException('phil_privacy.cookies.inventory: '.$parseException->getMessage(), $parseException->getCode(), previous: $parseException);
        }

        if (null === $data) {
            return [];
        }

        if (!\is_array($data)) {
            throw new InvalidConfigurationException('phil_privacy.cookies.inventory: the file maps each consent category to its cookies.');
        }

        return $data;
    }
}
