<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Application;

use Phil\QrCode\Contract\Content;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\Exception\DataTooLong;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCode\Logo;
use Phil\QrCode\PngWriter;
use Phil\QrCode\QrCode;
use Phil\QrCode\SvgWriter;

/**
 * The QR codes of the application, with the defaults of its configuration: a text, a typed content or the array of
 * one, encoded and drawn as SVG, PNG or a `data:` URI. Each call may override the defaults:
 *
 * - `level`: `L`, `M`, `Q` or `H`;
 * - `quiet_zone`, `dark`, `light`, `size` (SVG) and `scale` (PNG);
 * - `logo`: the name of a configured logo, which keeps its zone and raises the level;
 * - `title`: the name of an SVG for screen readers, the content's label when empty.
 *
 * It keeps no state: the same service serves every request.
 */
final readonly class QrCodes
{
    /** The options a call may give. */
    public const array OPTIONS = ['level', 'quiet_zone', 'dark', 'light', 'size', 'scale', 'logo', 'title'];

    public function __construct(
        private QrCodeDefaults $defaults = new QrCodeDefaults(),
        private Logos $logos = new Logos(),
    ) {
    }

    /**
     * The typed content of an array, or the text or content given.
     *
     * @param string|Content|array<array-key, mixed> $content
     *
     * @throws InvalidContent
     */
    public function content(string|array|Content $content): string|Content
    {
        return \is_array($content) ? ContentFactory::fromArray($content) : $content;
    }

    /**
     * What a code holds, in a few words: the label of a typed content, or the text itself.
     *
     * @param string|Content|array<array-key, mixed> $content
     *
     * @throws InvalidContent
     */
    public function label(string|array|Content $content): string
    {
        $content = $this->content($content);

        return $content instanceof Content ? $content->label() : $content;
    }

    /**
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options
     *
     * @throws InvalidContent when the content cannot be written, or is longer than the configuration allows
     * @throws InvalidOption  when an option is unknown or out of its range
     * @throws DataTooLong    when the content does not fit in a QR code
     */
    public function encode(string|array|Content $content, array $options = []): QrCode
    {
        $options = $this->options($options);
        $content = $this->content($content);
        $payload = $content instanceof Content ? $content->payload() : $content;

        if (\strlen($payload) > $this->defaults->maxBytes) {
            throw InvalidContent::of('content of a QR code', 'at most '.$this->defaults->maxBytes.' bytes');
        }

        $level = ErrorCorrection::tryFrom($this->text($options, 'level', $this->defaults->level->value))
            ?? throw InvalidOption::of('level', 'L, M, Q or H', '"'.$this->text($options, 'level', '').'"');
        $logo = $this->logo($options);

        return QrCode::encode($payload, $level, logoZone: null === $logo ? 0.0 : $logo[1], eci: $this->defaults->eci, kanji: $this->defaults->kanji);
    }

    /**
     * The code as an SVG image, to write inline in a page.
     *
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options
     */
    public function svg(string|array|Content $content, array $options = []): string
    {
        $code = $this->encode($content, $options);
        $writer = new SvgWriter($this->number($options, 'quiet_zone', $this->defaults->quietZone), $this->text($options, 'dark', $this->defaults->dark), $this->text($options, 'light', $this->defaults->light), $this->number($options, 'size', $this->defaults->size));
        $title = $this->text($options, 'title', '');

        return $writer->write($code, '' === $title ? $this->label($content) : $title, $this->logo($options)[0] ?? null);
    }

    /**
     * The code as the bytes of a PNG file.
     *
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options
     */
    public function png(string|array|Content $content, array $options = []): string
    {
        $code = $this->encode($content, $options);
        $writer = new PngWriter($this->number($options, 'scale', $this->defaults->scale), $this->number($options, 'quiet_zone', $this->defaults->quietZone), $this->text($options, 'dark', $this->defaults->dark), $this->text($options, 'light', $this->defaults->light));

        return $writer->write($code, $this->logo($options)[0] ?? null);
    }

    /**
     * The code as a `data:` URI, of an SVG image or of a PNG one, for an `<img src>`.
     *
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options
     */
    public function dataUri(string|array|Content $content, array $options = [], bool $png = false): string
    {
        return $png
            ? 'data:image/png;base64,'.sodium_bin2base64($this->png($content, $options), \SODIUM_BASE64_VARIANT_ORIGINAL)
            : 'data:image/svg+xml;base64,'.sodium_bin2base64($this->svg($content, $options), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * The colours, quiet zone, scale and logo a call gives or the defaults, for a drawing of its own.
     *
     * @param array<string, mixed> $options
     *
     * @return array{quiet_zone: int, dark: string, light: string, logo: ?Logo}
     */
    public function drawing(array $options): array
    {
        $options = $this->options($options);

        return [
            'quiet_zone' => $this->number($options, 'quiet_zone', $this->defaults->quietZone),
            'dark' => $this->text($options, 'dark', $this->defaults->dark),
            'light' => $this->text($options, 'light', $this->defaults->light),
            'logo' => $this->logo($options)[0] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function options(array $options): array
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ([] !== $unknown) {
            throw InvalidOption::of('options of a QR code', 'among '.implode(', ', self::OPTIONS), implode(', ', $unknown));
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{Logo, float}|null
     */
    private function logo(array $options): ?array
    {
        $name = $this->text($options, 'logo', '');

        return '' === $name ? null : $this->logos->get($name);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function text(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? $default;

        return \is_string($value) ? $value : throw InvalidOption::of($key, 'a text', get_debug_type($value));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function number(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? $default;

        return \is_int($value) ? $value : throw InvalidOption::of($key, 'an integer', get_debug_type($value));
    }
}
