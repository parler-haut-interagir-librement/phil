<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;

/**
 * The braille of the page numbers, asked of the translator for a run of pages at a time.
 *
 * @internal
 */
final class PageNumbers
{
    private const int RUN = 64;

    /** @var array<int, string> */
    private array $cells = [];

    public function __construct(
        private readonly Translator $translator,
        private readonly string $language,
        private readonly Contraction $contraction,
    ) {
    }

    public function cells(int $number): string
    {
        if (!isset($this->cells[$number])) {
            $numbers = range($number, $number + self::RUN - 1);
            $braille = Translated::texts($this->translator, array_map(strval(...), $numbers), $this->language, $this->contraction);
            $this->cells += array_combine($numbers, $braille);
        }

        return $this->cells[$number];
    }
}
