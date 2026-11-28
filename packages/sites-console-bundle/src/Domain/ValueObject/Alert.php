<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Status\AlertKind;

/**
 * A site that needs attention: unreachable, unhealthy, or behind on a package.
 */
final readonly class Alert
{
    /**
     * @param string $subject the package of a version behind, else empty
     * @param string $detail  the error, or the version installed and the one expected
     */
    public function __construct(
        public AlertKind $kind,
        public string $siteId,
        public string $subject = '',
        public string $detail = '',
    ) {
    }

    /**
     * What makes two alerts the same one, from one pull to the next: the site, the kind and the subject.
     */
    public function key(): string
    {
        return $this->siteId.'|'.$this->kind->value.'|'.$this->subject;
    }
}
