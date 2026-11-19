<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;

/**
 * A visit counted: which link, by its identifier and its code, on which domain, in which locale, and the class of its
 * visitor — a person, a robot, a prefetch, a link preview —; nothing else of the visitor, neither its address nor its
 * `User-Agent`.
 */
final readonly class ShortLinkVisit
{
    public function __construct(
        public int $linkId,
        public string $code,
        public string $host,
        public ?string $locale,
        public VisitorClass $visitor = VisitorClass::Human,
    ) {
    }
}
