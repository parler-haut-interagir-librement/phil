<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Cuts each PDF into several, every so many pages or one per page selection; the parts come back in a ZIP, and the
 * stages after it run on each part.
 */
final readonly class Split implements Stage
{
    /**
     * @param list<Pages> $ranges
     */
    private function __construct(private ?int $every, private array $ranges)
    {
    }

    public static function every(int $pages): self
    {
        if ($pages < 1) {
            throw InvalidStage::because(\sprintf('A split cuts every 1 page or more; %d is not.', $pages));
        }

        return new self($pages, []);
    }

    public static function ranges(Pages|string ...$ranges): self
    {
        if ([] === $ranges) {
            throw InvalidStage::because('A split by ranges takes one page selection at least.');
        }

        return new self(null, array_values(array_map(static fn (Pages|string $range): Pages => $range instanceof Pages ? $range : Pages::of($range), $ranges)));
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Split;
    }

    #[Override]
    public function options(): array
    {
        return null === $this->every ? ['ranges' => array_map(strval(...), $this->ranges)] : ['every' => $this->every];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
