<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;

/**
 * What a request on a short link domain answers: a redirect to a location, a `410 Gone` for a link no longer in force,
 * or a `404 Not Found`. A redirect carries the visit it counted.
 */
final readonly class Resolution
{
    private function __construct(
        public int $status,
        public ?string $location = null,
        public ?ShortLinkVisit $visit = null,
    ) {
    }

    public static function redirect(string $location, RedirectStatus $status, ?ShortLinkVisit $visit = null): self
    {
        return new self($status->value, $location, $visit);
    }

    public static function gone(): self
    {
        return new self(410);
    }

    public static function notFound(): self
    {
        return new self(404);
    }

    public function isRedirect(): bool
    {
        return null !== $this->location;
    }
}
