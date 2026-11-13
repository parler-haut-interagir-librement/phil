<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Sets the rotation of pages, which viewers turn clockwise; the angle replaces the rotation a page has.
 */
final readonly class Rotate implements Stage
{
    public ?Pages $pages;

    public function __construct(public int $angle, Pages|string|null $pages = null)
    {
        if (0 !== $angle % 90) {
            throw InvalidStage::because(\sprintf('A rotation is a multiple of 90; %d is not.', $angle));
        }

        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Rotate;
    }

    #[Override]
    public function options(): array
    {
        return $this->pages instanceof Pages ? ['angle' => $this->angle, 'pages' => $this->pages->value] : ['angle' => $this->angle];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
