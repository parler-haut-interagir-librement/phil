<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Reading;

use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;

/**
 * Who reads the count of each outbound link: **the author of the link and the administrators, nobody else**. These
 * counts are exact, which is why no shared view, report or public board may hold them.
 */
final readonly class LinkCountReader
{
    private function __construct(
        private ?string $author,
    ) {
    }

    public static function administrator(): self
    {
        return new self(null);
    }

    public static function author(string $reference): self
    {
        return new self($reference);
    }

    public function maySee(DeclaredOutboundLink $link): bool
    {
        return null === $this->author || $link->author === $this->author;
    }

    /**
     * The counts of the links this reader may see, a link nobody clicked at zero.
     *
     * @param iterable<DeclaredOutboundLink> $links
     * @param array<string, int>             $counts by link reference
     *
     * @return array<string, int> by link reference
     */
    public function counts(iterable $links, array $counts): array
    {
        $visible = [];

        foreach ($links as $link) {
            if ($this->maySee($link)) {
                $visible[$link->reference] = $counts[$link->reference] ?? 0;
            }
        }

        return $visible;
    }
}
