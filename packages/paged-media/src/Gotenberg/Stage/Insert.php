<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Inserts the pages of another PDF after a page, `0` before the first.
 */
final readonly class Insert implements Stage
{
    public ?Pages $sourcePages;

    public function __construct(
        public InputFile $source,
        public int $at = 0,
        Pages|string|null $sourcePages = null,
    ) {
        Check::extension('The PDF to insert', $source, ['pdf']);

        if ($at < 0) {
            throw InvalidStage::because(\sprintf('Pages are inserted after page 0 or later; %d is not a page.', $at));
        }

        $this->sourcePages = \is_string($sourcePages) ? Pages::of($sourcePages) : $sourcePages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Insert;
    }

    #[Override]
    public function options(): array
    {
        return Check::set(['source' => $this->source->name, 'at' => $this->at, 'sourcePages' => $this->sourcePages?->value]);
    }

    #[Override]
    public function files(): array
    {
        return [$this->source];
    }
}
