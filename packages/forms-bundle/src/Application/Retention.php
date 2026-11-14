<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

use Phil\FormsBundle\Domain\Repository\FormRepository;
use Psr\Clock\ClockInterface;

/**
 * Deletes the entries whose time is over, as each form's retention says: personal information is kept no longer
 * than its purpose needs.
 */
final readonly class Retention
{
    public function __construct(
        private FormRepository $forms,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many entries were deleted
     */
    public function purge(int $limit): int
    {
        $expired = $this->forms->expired($this->clock->now(), $limit);

        if ([] !== $expired) {
            $this->forms->remove(...$expired);
        }

        return \count($expired);
    }
}
