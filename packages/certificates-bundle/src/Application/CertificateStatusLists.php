<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;

/**
 * The issuer's status lists of revocation, as the certificates fill them: each list of the layout sets the bits of
 * its revoked certificates. A list of another name is asked of the application's own lists, when it has some.
 */
final readonly class CertificateStatusLists implements StatusLists
{
    public function __construct(
        private CertificateRepository $certificates,
        private StatusListLayout $layout,
        private ?StatusLists $others = null,
    ) {
    }

    public function find(string $list): ?StatusListContent
    {
        $range = $this->layout->range($list);

        if (null === $range) {
            return $this->others?->find($list);
        }

        [$from, $to] = $range;

        return new StatusListContent(array_map(static fn (int $index): int => $index - $from, $this->certificates->revokedIndexes($from, $to)));
    }
}
