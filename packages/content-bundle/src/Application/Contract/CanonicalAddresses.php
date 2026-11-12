<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Contract;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Url\Url;

/**
 * The absolute address a content is read at in one locale: what `PagePublished` and `PageRetired` carry. The bundle
 * implements it with `ContentCanonicalAddresses` when the router is there (TwigBundle with FrameworkBundle); without
 * an implementation, no page event is dispatched.
 */
interface CanonicalAddresses
{
    /**
     * `null` when the content has no address in the locale: no current route, or its kind mounted on no route.
     */
    public function of(ContentReference $content, Locale $locale): ?Url;
}
