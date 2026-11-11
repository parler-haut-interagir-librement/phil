<?php

declare(strict_types=1);

namespace Phil\Highlight;

use Phil\Highlight\Exception\InvalidLineRanges;

/**
 * How a block is written line by line: each line in its own `<span class="hl-line">`, numbered from `$firstNumber`
 * when one is given, the lines of `$highlighted` marked, and, with `$annotations`, the `[!code ++]`, `[!code --]` and
 * `[!code highlight]` notes of its comments read and taken out.
 */
final readonly class LineOptions
{
    public LineRanges $highlighted;

    /**
     * @param int|null        $firstNumber the number of the first line, from 0; `null` writes no number
     * @param LineRanges|null $highlighted the lines marked, by their place in the block from 1, whatever the first number
     * @param bool            $annotations whether the notes of the comments mark their lines
     *
     * @throws InvalidLineRanges when the first number is negative or beyond `LineRanges::MAX_LINE`
     */
    public function __construct(
        public ?int $firstNumber = null,
        ?LineRanges $highlighted = null,
        public bool $annotations = false,
    ) {
        if (null !== $firstNumber && ($firstNumber < 0 || $firstNumber > LineRanges::MAX_LINE)) {
            throw InvalidLineRanges::firstNumber(LineRanges::MAX_LINE);
        }

        $this->highlighted = $highlighted ?? LineRanges::none();
    }

    /**
     * Numbered from `$firstNumber`, nothing marked, no annotation read.
     *
     * @throws InvalidLineRanges when the first number is negative or beyond `LineRanges::MAX_LINE`
     */
    public static function numbered(int $firstNumber = 1): self
    {
        return new self($firstNumber);
    }

    /**
     * @throws InvalidLineRanges when the first number is negative or beyond `LineRanges::MAX_LINE`
     */
    public function withFirstNumber(?int $firstNumber): self
    {
        return new self($firstNumber, $this->highlighted, $this->annotations);
    }

    public function withHighlighted(LineRanges $highlighted): self
    {
        return new self($this->firstNumber, $highlighted, $this->annotations);
    }

    public function withAnnotations(bool $annotations = true): self
    {
        return new self($this->firstNumber, $this->highlighted, $annotations);
    }
}
