<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

use Phil\CommerceProductTemplate\Combination;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Print\PrintElementKind;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Layer;
use ValueError;

/**
 * One thing a design prints in a zone of a face, over its artwork, as a layer of its own in the zone's print file:
 *
 * - **image**: a fixed image in its box — an object of the base version, the empty purse of the convention version;
 * - **text**: the text of a text option, typed, picked or drawn, in the font and the rectangle the option imposes;
 * - **choice**: the image of the choice made on a choice or ink option — the tombstone chosen;
 * - **dice**: one image per die of a draw option, the result of each die picking the image of its slot.
 *
 * An element applies to the products whose values its `when` lists, by axis, and to the order lines whose choices its
 * `chosen` lists, by option: the glowing layer only when the glowing ink is chosen. It is printed in its ink, so a
 * glow-in-the-dark layer goes to a print file of its own. Nothing a buyer sends is an image: an element prints the
 * design's images, and a text the buyer typed.
 */
final readonly class PrintElement
{
    /**
     * @param array<array-key, string>    $images the image of each choice, by choice code
     * @param list<DiceSlot>              $slots
     * @param array<string, list<string>> $when   value codes by axis code
     * @param array<string, list<string>> $chosen choice codes by option code
     */
    private function __construct(
        public PrintElementKind $kind,
        public string $face,
        public string $zone,
        public ?string $option,
        public ?Box $box,
        public ?string $path,
        public array $images,
        public array $slots,
        public ?string $font,
        public Colour $colour,
        public float $maxSize,
        public float $minSize,
        public string $ink,
        public array $when,
        public array $chosen,
    ) {
    }

    /**
     * A fixed image in its box.
     *
     * @param string                      $path   relative to the store of images
     * @param array<string, list<string>> $when   the products it applies to, by axis values; every product when empty
     * @param array<string, list<string>> $chosen the lines it applies to, by choices made; every line when empty
     */
    public static function image(string $face, string $zone, string $path, Box $box, array $when = [], array $chosen = [], string $ink = Layer::DEFAULT_INK): self
    {
        self::checkPath($path);

        return self::checked(PrintElementKind::Image, $face, $zone, null, $box, $path, [], [], null, new Colour(0, 0, 0), 72.0, 6.0, $ink, $when, $chosen);
    }

    /**
     * The text of a text option, at the largest size from `$maxSize` down to `$minSize`, in points, at which it fits.
     *
     * @param Box|null                    $box    its rectangle in the zone; the rectangle of the option's print zone when `null`
     * @param string|null                 $font   the name of its font; the option's font when `null`
     * @param array<string, list<string>> $when
     * @param array<string, list<string>> $chosen
     */
    public static function text(string $face, string $zone, string $option, ?Box $box = null, Colour $colour = new Colour(0, 0, 0), float $maxSize = 72.0, float $minSize = 6.0, ?string $font = null, array $when = [], array $chosen = [], string $ink = Layer::DEFAULT_INK): self
    {
        if ($minSize <= 0 || $maxSize < $minSize || $maxSize > 1000) {
            throw InvalidValue::because(\sprintf('The text of the option %s is printed from a positive size up to a maximum at least as large, at most 1000 pt; %s to %s pt is not that.', $option, $minSize, $maxSize));
        }

        if (null !== $font && 1 !== preg_match('/^\S(?:.{0,126}\S)?$/uD', $font)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of a font: 1 to 128 characters, without spaces around.', $font));
        }

        return self::checked(PrintElementKind::Text, $face, $zone, $option, $box, null, [], [], $font, $colour, $maxSize, $minSize, $ink, $when, $chosen);
    }

    /**
     * The image of the choice made on a choice or ink option, in its box; a choice without an image prints nothing.
     *
     * @param array<array-key, string>    $images the image of each choice, by choice code; a code of digits only is
     *                                            an integer key
     * @param array<string, list<string>> $when
     * @param array<string, list<string>> $chosen
     */
    public static function choice(string $face, string $zone, string $option, Box $box, array $images, array $when = [], array $chosen = [], string $ink = Layer::DEFAULT_INK): self
    {
        if ([] === $images) {
            throw InvalidValue::because(\sprintf('The choice element of the option %s has the image of one choice at least.', $option));
        }

        foreach ($images as $choice => $image) {
            // A code of digits only is an integer key of the array: read as the string it was.
            $choice = (string) $choice;

            if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $choice)) {
                throw InvalidValue::because(\sprintf('"%s" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".', $choice));
            }

            self::checkPath($image);
        }

        ksort($images, \SORT_STRING);

        return self::checked(PrintElementKind::Choice, $face, $zone, $option, $box, null, $images, [], null, new Colour(0, 0, 0), 72.0, 6.0, $ink, $when, $chosen);
    }

    /**
     * One image per die of a draw option, the draw read as one result per slot, in order.
     *
     * @param list<DiceSlot>              $slots
     * @param array<string, list<string>> $when
     * @param array<string, list<string>> $chosen
     */
    public static function dice(string $face, string $zone, string $option, array $slots, array $when = [], array $chosen = [], string $ink = Layer::DEFAULT_INK): self
    {
        if ([] === $slots || \count($slots) > 64) {
            throw InvalidValue::because(\sprintf('The dice element of the option %s has 1 to 64 slots.', $option));
        }

        return self::checked(PrintElementKind::Dice, $face, $zone, $option, null, null, [], $slots, null, new Colour(0, 0, 0), 72.0, 6.0, $ink, $when, $chosen);
    }

    /**
     * Whether it applies to a product of these values on an order line with these choices.
     *
     * @param array<string, string> $values  value codes by axis code
     * @param array<string, string> $choices the code chosen on each choice or ink option, by option code
     */
    public function appliesTo(array $values, array $choices): bool
    {
        return array_all($this->when, static fn (array $codes, string $axis): bool => \in_array($values[$axis] ?? null, $codes, true))
            && array_all($this->chosen, static fn (array $codes, string $option): bool => \in_array($choices[$option] ?? null, $codes, true));
    }

    /**
     * The number of faces of each die of a dice element, in order.
     *
     * @return list<int>
     */
    public function sides(): array
    {
        return array_map(static fn (DiceSlot $slot): int => $slot->sides, $this->slots);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'face' => $this->face,
            'zone' => $this->zone,
            'option' => $this->option,
            'box' => self::boxToArray($this->box),
            'path' => $this->path,
            'images' => $this->images,
            'slots' => array_map(static fn (DiceSlot $slot): array => ['box' => self::boxToArray($slot->box), 'sides' => $slot->sides, 'images' => array_values($slot->images)], $this->slots),
            'font' => $this->font,
            'colour' => $this->colour->toHex(),
            'max_size' => $this->maxSize,
            'min_size' => $this->minSize,
            'ink' => $this->ink,
            'when' => $this->when,
            'chosen' => $this->chosen,
        ];
    }

    /**
     * @param array<array-key, mixed> $data as `toArray()` writes it
     *
     * @throws TemplateRefused for an array no code of the module wrote
     */
    public static function fromArray(array $data): self
    {
        try {
            $face = self::string($data, 'face');
            $zone = self::string($data, 'zone');
            $when = self::conditionArray($data['when'] ?? null);
            $chosen = self::conditionArray($data['chosen'] ?? null);
            $ink = self::string($data, 'ink');

            return match (PrintElementKind::from(self::string($data, 'kind'))) {
                PrintElementKind::Image => self::image($face, $zone, self::string($data, 'path'), self::box($data['box'] ?? null), $when, $chosen, $ink),
                PrintElementKind::Text => self::text(
                    $face,
                    $zone,
                    self::string($data, 'option'),
                    null === ($data['box'] ?? null) ? null : self::box($data['box']),
                    Colour::fromHex(self::string($data, 'colour')),
                    self::number($data, 'max_size'),
                    self::number($data, 'min_size'),
                    self::nullableString($data, 'font'),
                    $when,
                    $chosen,
                    $ink,
                ),
                PrintElementKind::Choice => self::choice($face, $zone, self::string($data, 'option'), self::box($data['box'] ?? null), self::stringMap($data['images'] ?? null), $when, $chosen, $ink),
                PrintElementKind::Dice => self::dice($face, $zone, self::string($data, 'option'), self::slots($data['slots'] ?? null), $when, $chosen, $ink),
            };
        } catch (ValueError|InvalidValue|InvalidLayout $error) {
            throw TemplateRefused::unreadable('An element of a print layout', $error->getMessage());
        }
    }

    /**
     * @internal a path of the store of images, as the elements and their slots hold them
     */
    public static function checkPath(string $path): void
    {
        if (\strlen($path) > 512 || 1 !== preg_match('#^(?!.*(?:^|/)\.{1,2}(?:/|$))[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$#D', $path)) {
            throw InvalidValue::because(\sprintf('"%s" is not a path of the store of images.', $path));
        }
    }

    /**
     * @param array<array-key, string>    $images
     * @param list<DiceSlot>              $slots
     * @param array<string, list<string>> $when
     * @param array<string, list<string>> $chosen
     */
    private static function checked(PrintElementKind $kind, string $face, string $zone, ?string $option, ?Box $box, ?string $path, array $images, array $slots, ?string $font, Colour $colour, float $maxSize, float $minSize, string $ink, array $when, array $chosen): self
    {
        foreach (['face' => $face, 'zone' => $zone, 'option' => $option] as $what => $code) {
            if (null !== $code && 1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code)) {
                throw InvalidValue::because(\sprintf('"%s" is not the code of a %s: up to 32 lowercase letters, digits or "_", starting with a letter.', $code, $what));
            }
        }

        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $ink)) {
            throw InvalidValue::because(\sprintf('"%s" is not the name of an ink: up to 32 lowercase letters, digits, "_" or "-", starting with a letter.', $ink));
        }

        try {
            $when = Combination::condition($when, 'The condition of a print element');
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidValue::because($invalidDefinition->getMessage());
        }

        foreach ($chosen as $code => $choices) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code) || [] === $choices) {
                throw InvalidValue::because('The choices a print element applies to name, by option code, a list of one choice code or more.');
            }

            foreach ($choices as $choice) {
                if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $choice)) {
                    throw InvalidValue::because(\sprintf('"%s" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".', $choice));
                }
            }
        }

        ksort($chosen, \SORT_STRING);

        return new self($kind, $face, $zone, $option, $box, $path, $images, $slots, $font, $colour, $maxSize, $minSize, $ink, $when, $chosen);
    }

    /**
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    private static function boxToArray(?Box $box): ?array
    {
        return $box instanceof Box ? ['x' => $box->x, 'y' => $box->y, 'width' => $box->width, 'height' => $box->height] : null;
    }

    private static function box(mixed $data): Box
    {
        if (!\is_array($data)) {
            throw InvalidValue::because('a box is an object');
        }

        return new Box(self::number($data, 'x'), self::number($data, 'y'), self::number($data, 'width'), self::number($data, 'height'));
    }

    /**
     * @return list<DiceSlot>
     */
    private static function slots(mixed $data): array
    {
        if (!\is_array($data)) {
            throw InvalidValue::because('slots is not a list');
        }

        $slots = [];

        foreach ($data as $slot) {
            if (!\is_array($slot) || !\is_int($slot['sides'] ?? null) || !\is_array($slot['images'] ?? null)) {
                throw InvalidValue::because('a slot is a box, a number of faces and its images');
            }

            $images = [];

            foreach (array_values($slot['images']) as $index => $image) {
                $images[$index + 1] = \is_string($image) ? $image : throw InvalidValue::because('an image is a path');
            }

            $slots[] = DiceSlot::of(self::box($slot['box'] ?? null), $slot['sides'], $images);
        }

        return $slots;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function conditionArray(mixed $data): array
    {
        if (!\is_array($data)) {
            throw InvalidValue::because('a condition is an object');
        }

        $condition = [];

        foreach ($data as $key => $codes) {
            if (!\is_string($key) || !\is_array($codes)) {
                throw InvalidValue::because('a condition lists codes by code');
            }

            $condition[$key] = array_values(array_map(static fn (mixed $code): string => \is_string($code) ? $code : throw InvalidValue::because('a code is a string'), $codes));
        }

        return $condition;
    }

    /**
     * @return array<array-key, string>
     */
    private static function stringMap(mixed $data): array
    {
        if (!\is_array($data)) {
            throw InvalidValue::because('images is not an object');
        }

        $map = [];

        foreach ($data as $key => $value) {
            $map[$key] = \is_string($value) ? $value : throw InvalidValue::because('an image is a path');
        }

        return $map;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function number(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : throw InvalidValue::because(\sprintf('%s is not a number', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw InvalidValue::because(\sprintf('%s is not a string', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw InvalidValue::because(\sprintf('%s is not a string', $key));
    }
}
