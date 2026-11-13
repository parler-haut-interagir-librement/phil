<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf\Form;

/**
 * One box of a field on a page, ready to be written: its place, its appearance streams by state, the state it shows,
 * and the entries of its dictionary.
 *
 * @internal
 */
final readonly class Widget
{
    /**
     * @param array<string, string> $appearances each appearance's content stream, by state; one under `''` when the
     *                                           widget has no states
     * @param string|null           $state       the state shown, for a button
     * @param string                $entries     the dictionary entries of the field and widget, beside its place and
     *                                           appearances
     * @param string|null           $font        the resource name of the font its appearances use
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public array $appearances,
        public ?string $state,
        public string $entries,
        public ?string $font,
    ) {
    }

    /**
     * The appearance shown: the state's, or the only one.
     */
    public function shown(): string
    {
        return $this->appearances[$this->state ?? ''];
    }
}
