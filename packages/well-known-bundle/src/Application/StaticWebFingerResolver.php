<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application;

use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerLink;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;

/**
 * The resources of the `webfinger.resources` configuration: a few fixed accounts or pages, such as the site's own.
 *
 * @phpstan-type LinkConfig array{rel: string, type: ?string, href: ?string, template: ?string, titles: array<string, string>, properties: array<string, ?string>}
 * @phpstan-type ResourceConfig array{subject: ?string, aliases: list<string>, properties: array<string, ?string>, links: list<LinkConfig>}
 */
final readonly class StaticWebFingerResolver implements WebFingerResolver
{
    /** @var array<string, WebFingerDescriptor> */
    private array $descriptors;

    /**
     * @param array<string, ResourceConfig> $resources keyed by the resource, as `WebFingerResource::$uri` writes it
     */
    public function __construct(array $resources)
    {
        $descriptors = [];

        foreach ($resources as $uri => $resource) {
            $descriptors[$uri] = $this->describe($uri, $resource);
        }

        $this->descriptors = $descriptors;
    }

    /**
     * @param ResourceConfig $resource
     */
    private function describe(string $uri, array $resource): WebFingerDescriptor
    {
        return new WebFingerDescriptor(
            $resource['subject'] ?? $uri,
            $resource['aliases'],
            $resource['properties'],
            array_map(static fn (array $link): WebFingerLink => new WebFingerLink($link['rel'], $link['type'], $link['href'], $link['template'], $link['titles'], $link['properties']), $resource['links']),
        );
    }

    public function resolve(WebFingerResource $resource): ?WebFingerDescriptor
    {
        return $this->descriptors[$resource->uri] ?? null;
    }
}
