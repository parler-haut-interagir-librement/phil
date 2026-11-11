<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional\App;

use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerLink;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;

/**
 * A resolver of the accounts of the site, as an ActivityPub bundle adds one: it knows alice, and adds a link to the
 * site's own account.
 */
final readonly class ProfileResolver implements WebFingerResolver
{
    public function resolve(WebFingerResource $resource): ?WebFingerDescriptor
    {
        return match ($resource->uri) {
            'acct:alice@example.com' => new WebFingerDescriptor('acct:alice@example.com', ['https://example.com/@alice'], links: [new WebFingerLink('self', 'application/activity+json', 'https://example.com/users/alice')]),
            'acct:site@example.com' => new WebFingerDescriptor('acct:site@example.com', links: [new WebFingerLink('self', 'application/activity+json', 'https://example.com/actor')]),
            default => null,
        };
    }
}
