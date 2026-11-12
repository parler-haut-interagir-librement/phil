<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Exception\InvalidCardTemplate;

/**
 * How the cards of a site look, declared once: the background — a colour, maybe an image drawn over it to cover the
 * card —, an accent band along the bottom edge, a logo in the bottom-left corner, the fonts and colours of the title and
 * subtitle, the margin, and the bounds the title is fitted within — a size in pixels that shrinks from the largest to
 * the smallest, and a number of lines.
 *
 * Every card is 1200×630 pixels, the size Open Graph and X show large.
 */
final readonly class CardTemplate
{
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /** The keys `fromArray()` reads. */
    public const array KEYS = ['title_font', 'subtitle_font', 'background', 'title_color', 'subtitle_color', 'accent', 'background_image', 'logo', 'padding', 'title_max_size', 'title_min_size', 'title_max_lines', 'subtitle_size', 'subtitle_max_lines'];

    public function __construct(
        public CardFont $titleFont,
        public ?CardFont $subtitleFont = null,
        public Color $background = new Color(255, 255, 255),
        public Color $titleColor = new Color(17, 24, 39),
        public Color $subtitleColor = new Color(75, 85, 99),
        public ?Color $accent = null,
        public ?CardImage $backgroundImage = null,
        public ?CardImage $logo = null,
        public int $padding = 80,
        public int $titleMaxSize = 64,
        public int $titleMinSize = 36,
        public int $titleMaxLines = 3,
        public int $subtitleSize = 32,
        public int $subtitleMaxLines = 2,
    ) {
        $this->bounded('padding', $padding, 0, 200);
        $this->bounded('largest title size', $titleMaxSize, 8, 200);
        $this->bounded('smallest title size', $titleMinSize, 8, $titleMaxSize);
        $this->bounded('number of title lines', $titleMaxLines, 1, 6);
        $this->bounded('subtitle size', $subtitleSize, 8, 120);
        $this->bounded('number of subtitle lines', $subtitleMaxLines, 1, 4);
    }

    /**
     * A template from an array of the keys of `KEYS`, as a configuration writes it: the fonts and images as paths,
     * the colours in hexadecimal, the sizes in pixels. Only `title_font` is required.
     *
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $unknown = array_diff(array_keys($config), self::KEYS);

        if ([] !== $unknown) {
            throw InvalidCardTemplate::because(\sprintf('A card template has no key "%s"; it reads %s.', implode('", "', $unknown), implode(', ', self::KEYS)));
        }

        $titleFont = self::string($config, 'title_font') ?? throw InvalidCardTemplate::because('A card template needs its "title_font".');
        $subtitleFont = self::string($config, 'subtitle_font');
        $accent = self::string($config, 'accent');
        $backgroundImage = self::string($config, 'background_image');
        $logo = self::string($config, 'logo');

        return new self(
            CardFont::fromFile($titleFont),
            null === $subtitleFont ? null : CardFont::fromFile($subtitleFont),
            Color::fromHex(self::string($config, 'background') ?? '#ffffff'),
            Color::fromHex(self::string($config, 'title_color') ?? '#111827'),
            Color::fromHex(self::string($config, 'subtitle_color') ?? '#4b5563'),
            null === $accent ? null : Color::fromHex($accent),
            null === $backgroundImage ? null : CardImage::fromFile($backgroundImage),
            null === $logo ? null : CardImage::fromFile($logo),
            self::integer($config, 'padding') ?? 80,
            self::integer($config, 'title_max_size') ?? 64,
            self::integer($config, 'title_min_size') ?? 36,
            self::integer($config, 'title_max_lines') ?? 3,
            self::integer($config, 'subtitle_size') ?? 32,
            self::integer($config, 'subtitle_max_lines') ?? 2,
        );
    }

    /**
     * The font of the subtitle: its own, else the title's.
     */
    public function subtitleFont(): CardFont
    {
        return $this->subtitleFont ?? $this->titleFont;
    }

    /**
     * What the look of a card depends on, hashed: the hashes of the fonts and images rather than their paths, the
     * colours, the sizes and the bounds.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', implode('|', [
            $this->titleFont->sha256,
            $this->subtitleFont()->sha256,
            $this->background->hex(),
            $this->titleColor->hex(),
            $this->subtitleColor->hex(),
            $this->accent?->hex() ?? '-',
            $this->backgroundImage->sha256 ?? '-',
            $this->logo->sha256 ?? '-',
            $this->padding,
            $this->titleMaxSize,
            $this->titleMinSize,
            $this->titleMaxLines,
            $this->subtitleSize,
            $this->subtitleMaxLines,
        ]));
    }

    private function bounded(string $name, int $value, int $minimum, int $maximum): void
    {
        if ($value < $minimum || $value > $maximum) {
            throw InvalidCardTemplate::because(\sprintf('The %s of a card is %d to %d; %d is not.', $name, $minimum, $maximum, $value));
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function string(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        if (null === $value || \is_string($value)) {
            return $value;
        }

        throw InvalidCardTemplate::because(\sprintf('The "%s" of a card template is a string; %s is given.', $key, get_debug_type($value)));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function integer(array $config, string $key): ?int
    {
        $value = $config[$key] ?? null;

        if (null === $value || \is_int($value)) {
            return $value;
        }

        throw InvalidCardTemplate::because(\sprintf('The "%s" of a card template is an integer; %s is given.', $key, get_debug_type($value)));
    }
}
