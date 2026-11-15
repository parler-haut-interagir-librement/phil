<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Site;

use Override;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The site of the configuration, `default` unless it names another: what a standalone site needs.
 */
final readonly class ConfiguredSite implements CurrentSite
{
    public function __construct(
        private string $key = Site::DEFAULT,
    ) {
    }

    #[Override]
    public function site(): Site
    {
        return Site::of($this->key);
    }
}
