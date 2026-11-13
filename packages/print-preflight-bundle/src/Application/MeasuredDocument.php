<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application;

use JsonException;
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\FontStyle;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Exception\InvalidDocument;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;
use ValueError;

/**
 * A document described by numbers, as a script on a page laid out for print measures it, read into the model of
 * phil/print-preflight. Lengths are in the document's `unit` — CSS pixels by default —, frames measured from the top
 * left corner of the trim box. See doc/measured-documents.md for the shape.
 */
final readonly class MeasuredDocument
{
    public function __construct(
        private ?FontResolver $fonts = null,
    ) {
    }

    public function fromJson(string $json): Document
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidMeasurement::because('The measured document is not valid JSON: '.$jsonException->getMessage());
        }

        return $this->fromArray(\is_array($data) ? $data : []);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function fromArray(array $data): Document
    {
        $unit = self::string($data, 'unit', 'the document', 'px');

        try {
            return new Document(
                array_map(fn (array $page): Page => $this->page($page, $unit), $this->objects($data, 'pages', 'the document')),
                array_map($this->font(...), $this->objects($data, 'fonts', 'the document', [])),
            );
        } catch (InvalidDocument $invalidDocument) {
            throw InvalidMeasurement::because($invalidDocument->getMessage());
        }
    }

    /**
     * @param array<array-key, mixed> $page
     */
    private function page(array $page, string $unit): Page
    {
        return new Page(
            new Size(Lengths::in(self::number($page, 'width', 'a page'), $unit), Lengths::in(self::number($page, 'height', 'a page'), $unit)),
            Lengths::in(self::number($page, 'bleed', 'a page', 0.0), $unit),
            array_map(static fn (array $image): PlacedImage => self::image($image, $unit), $this->objects($page, 'images', 'a page', [])),
            array_map(static fn (array $box): Box => new Box(
                self::string($box, 'id', 'a box'),
                self::frame($box, $unit, 'a box'),
                null === ($box['colourSpace'] ?? null) ? null : self::colourSpace($box, 'a box'),
                self::bool($box, 'bleeds', 'a box', false),
                self::optionalNumber($box, 'inkCoverage', 'a box'),
                self::bool($box, 'overprint', 'a box', false),
                self::optionalLength($box, 'lineWidth', 'a box', $unit),
                self::bool($box, 'transparency', 'a box', false),
            ), $this->objects($page, 'boxes', 'a page', [])),
        );
    }

    /**
     * @param array<array-key, mixed> $image
     */
    private static function image(array $image, string $unit): PlacedImage
    {
        $scaled = isset($image['scaledWidth']) || isset($image['scaledHeight'])
            ? new Size(Lengths::in(self::number($image, 'scaledWidth', 'an image'), $unit), Lengths::in(self::number($image, 'scaledHeight', 'an image'), $unit))
            : null;

        return new PlacedImage(
            self::string($image, 'id', 'an image'),
            self::frame($image, $unit, 'an image'),
            (int) self::number($image, 'naturalWidth', 'an image'),
            (int) self::number($image, 'naturalHeight', 'an image'),
            null === ($image['colourSpace'] ?? null) ? ColourSpace::Rgb : self::colourSpace($image, 'an image'),
            self::bool($image, 'bleeds', 'an image', false),
            $scaled,
            self::optionalNumber($image, 'inkCoverage', 'an image'),
            self::bool($image, 'transparency', 'an image', false),
        );
    }

    /**
     * @param array<array-key, mixed> $font
     */
    private function font(array $font): UsedFont
    {
        $embedded = self::bool($font, 'embedded', 'a font', true);
        $subset = self::bool($font, 'subset', 'a font', false);

        if (!isset($font['family'])) {
            return new UsedFont(self::string($font, 'name', 'a font'), new EmbeddingPermissions((int) self::number($font, 'fsType', 'a font', 0.0)), $embedded, $subset);
        }

        if (!$this->fonts instanceof FontResolver) {
            throw InvalidMeasurement::because('A font named by its family needs a font resolver, such as the catalogue of phil/fonts-bundle; name it with "name" and "fsType" otherwise.');
        }

        $style = FontStyle::tryFrom(self::string($font, 'style', 'a font', 'normal'))
            ?? throw InvalidMeasurement::because('The style of a font is normal, italic or oblique.');

        return $this->fonts->resolve(self::string($font, 'family', 'a font'), (int) self::number($font, 'weight', 'a font', 400.0), $style, $embedded, $subset);
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private static function frame(array $element, string $unit, string $what): Rectangle
    {
        return new Rectangle(
            Lengths::in(self::number($element, 'x', $what), $unit),
            Lengths::in(self::number($element, 'y', $what), $unit),
            Lengths::in(self::number($element, 'width', $what), $unit),
            Lengths::in(self::number($element, 'height', $what), $unit),
        );
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private static function colourSpace(array $element, string $what): ColourSpace
    {
        try {
            return ColourSpace::from(self::string($element, 'colourSpace', $what));
        } catch (ValueError) {
            throw InvalidMeasurement::because(\sprintf('The colour space of %s is gray, rgb, cmyk or spot.', $what));
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<mixed>|null        $default
     *
     * @return list<array<array-key, mixed>>
     */
    private function objects(array $data, string $field, string $what, ?array $default = null): array
    {
        $value = $data[$field] ?? $default;

        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn (mixed $item): bool => !\is_array($item))) {
            throw self::field($field, $what, 'a list of objects');
        }

        /** @var list<array<array-key, mixed>> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function number(array $data, string $field, string $what, ?float $default = null): float
    {
        $value = $data[$field] ?? $default;

        return \is_int($value) || \is_float($value) ? $value : throw self::field($field, $what, 'a number');
    }

    /**
     * A number the measuring script may leave out, or give as `null`, when it does not know it.
     *
     * @param array<array-key, mixed> $data
     */
    private static function optionalNumber(array $data, string $field, string $what): ?float
    {
        return null === ($data[$field] ?? null) ? null : self::number($data, $field, $what);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function optionalLength(array $data, string $field, string $what, string $unit): ?Length
    {
        $value = self::optionalNumber($data, $field, $what);

        return null === $value ? null : Lengths::in($value, $unit);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $field, string $what, ?string $default = null): string
    {
        $value = $data[$field] ?? $default;

        return \is_string($value) ? $value : throw self::field($field, $what, 'a string');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function bool(array $data, string $field, string $what, bool $default): bool
    {
        $value = $data[$field] ?? $default;

        return \is_bool($value) ? $value : throw self::field($field, $what, 'true or false');
    }

    private static function field(string $field, string $what, string $type): InvalidMeasurement
    {
        return InvalidMeasurement::because(\sprintf('The "%s" of %s must be %s.', $field, $what, $type));
    }
}
