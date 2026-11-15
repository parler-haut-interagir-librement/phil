<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit\Fixture;

use Phil\LegalNotices\Event\DocumentPublished;

/**
 * The versions announced, as "type number", taken once read.
 */
final class Announcements
{
    /**
     * @var list<string>
     */
    private array $seen = [];

    public function record(DocumentPublished $event): void
    {
        $this->seen[] = $event->type.' '.$event->version;
    }

    /**
     * @return list<string>
     */
    public function take(): array
    {
        $seen = $this->seen;
        $this->seen = [];

        return $seen;
    }
}
