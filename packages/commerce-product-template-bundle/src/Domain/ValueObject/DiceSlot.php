<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PrintCompositor\Box;

/**
 * One die of a dice element and where its object goes: the box in its zone, the number of faces of the die, and the
 * image of each result — a table of six objects for a d6, the result picking the one printed.
 */
final readonly class DiceSlot
{
    /**
     * @param array<int, string> $images the path of the image of each result, from 1 to `$sides`
     */
    private function __construct(
        public Box $box,
        public int $sides,
        public array $images,
    ) {
    }

    /**
     * @param array<int, string> $images the path of the image of each result, from 1 to `$sides`, every result once,
     *                                   relative to the store of images
     */
    public static function of(Box $box, int $sides, array $images): self
    {
        if ($sides < 2 || $sides > DiceCode::MAX_SIDES) {
            throw InvalidValue::because(\sprintf('A die has 2 to %d faces, not %d.', DiceCode::MAX_SIDES, $sides));
        }

        ksort($images);

        if (array_keys($images) !== range(1, $sides)) {
            throw InvalidValue::because(\sprintf('A d%d slot has one image for each result from 1 to %d.', $sides, $sides));
        }

        foreach ($images as $image) {
            PrintElement::checkPath($image);
        }

        return new self($box, $sides, $images);
    }

    /**
     * The image of the result.
     */
    public function image(int $result): string
    {
        return $this->images[$result] ?? throw InvalidValue::because(\sprintf('A d%d has no result %d.', $this->sides, $result));
    }
}
