<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application\Contract;

use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;

/**
 * What the site says of a WebFinger resource, autoconfigured with the `phil_well_known.webfinger_resolver` tag.
 *
 * A resolver returns `null` for a resource it does not know: when every resolver does, the query is a `404`, the same
 * answer as for a resource of a scheme the bundle does not read, so that WebFinger tells nothing more of the site's
 * accounts than the resolvers describe. The descriptors of several resolvers are merged in the order of their tags.
 */
interface WebFingerResolver
{
    public function resolve(WebFingerResource $resource): ?WebFingerDescriptor;
}
