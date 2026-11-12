<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * What a developer may have left wrong in an image, said while the site is developed: a text alternative that is the
 * name of its file, a word that only says it is an image, « image », « photo », or that starts by saying so,
 * « Photo of… », « Photo de… », in English or in French; and a width or a height missing, which lets the page shift as
 * the image loads. A decorative image, whose text alternative is empty, is only checked for its size.
 *
 * The name of the file is read from the address by `phil/url`, a path of the page as if under its own origin; an
 * inline image has none, nor has an address `phil/url` refuses.
 */
final readonly class ImageTextCheck
{
    /** The text alternative is the name of the file. */
    public const string FILE_NAME = 'file-name';

    /** The text alternative is a word that only says it is an image. */
    public const string GENERIC = 'generic';

    /** The text alternative starts by saying it is an image. */
    public const string REDUNDANT = 'redundant';

    /** The width or the height is missing. */
    public const string NO_SIZE = 'no-size';

    /** The words that only say it is an image, lowercase, in English and in French. */
    private const array GENERIC_WORDS = [
        'image', 'images', 'picture', 'photo', 'photograph', 'photographie', 'img', 'graphic', 'graphique',
        'illustration', 'icon', 'icône', 'icone', 'logo', 'banner', 'bannière', 'banniere', 'thumbnail', 'vignette',
        'screenshot', 'untitled', 'sans titre', 'placeholder',
    ];

    /** A text that starts by saying it is an image, then what it is of, in English or in French. */
    private const string REDUNDANT_START = "/^(?:(?:an?|the|une?|la|le|l['’])\\s*)?(?:image|picture|photo|photograph|photographie|illustration|screenshot|capture d['’]écran)s?\\s+(?:(?:of|showing|de|du|des)(?:\\s|$)|d['’])/u";

    /**
     * @return array<self::FILE_NAME|self::GENERIC|self::REDUNDANT|self::NO_SIZE, string> each problem found, said for the developer
     */
    public function problems(string $src, string $alt, mixed $width, mixed $height): array
    {
        $problems = [];
        $text = rtrim(mb_strtolower(trim($alt)), '.');

        if ('' !== $text && \in_array($this->words($text), $this->names($src), true)) {
            $problems[self::FILE_NAME] = \sprintf('The text alternative of the image "%s" is "%s", the name of its file: say what the image shows, or leave it empty if it is decorative.', $src, $alt);
        }

        if (\in_array($text, self::GENERIC_WORDS, true)) {
            $problems[self::GENERIC] = \sprintf('The text alternative of the image "%s" is "%s", a word that says only that it is an image: say what it shows, or leave it empty if it is decorative.', $src, $alt);
        } elseif (1 === preg_match(self::REDUNDANT_START, $text)) {
            $problems[self::REDUNDANT] = \sprintf('The text alternative of the image "%s" is "%s": screen readers already say it is an image, start with what it shows.', $src, $alt);
        }

        if (!$this->isSize($width) || !$this->isSize($height)) {
            $problems[self::NO_SIZE] = \sprintf('The image "%s" has no width or no height: give both, so that its space is reserved before it loads and the page does not shift.', $src);
        }

        return $problems;
    }

    /**
     * The name of the file in the forms a text alternative copies: whole, without its extension, without the digest
     * AssetMapper adds (`-3fA9c1d`) and a density or width suffix (`@2x`, `@480w`), each read as words.
     *
     * @return list<string>
     */
    private function names(string $src): array
    {
        $file = $this->fileName($src);

        if (null === $file) {
            return [];
        }

        $name = pathinfo($file, \PATHINFO_FILENAME);
        preg_match('/(?<base>.+?)(?<suffix>@\d+[xw])?(?:-[A-Za-z0-9_]{7})?$/', $name, $parts);

        return array_map($this->words(...), [$file, $name, ($parts['base'] ?? '').($parts['suffix'] ?? ''), $parts['base'] ?? '']);
    }

    /**
     * The last segment of the path, unescaped, read by `phil/url`, a path of the page as if under its own origin; null
     * for an address without a path, an inline image, or any other address `phil/url` refuses.
     */
    private function fileName(string $src): ?string
    {
        $src = str_replace(["\t", "\n", "\r"], '', $src);

        try {
            $segment = array_last(Url::fromString(1 === preg_match('/^[a-z][a-z0-9+.-]*:/i', $src) ? $src : 'https://localhost/'.$src, true)->segments());
        } catch (UrlException) {
            return null;
        }

        return null === $segment ? null : rawurldecode($segment);
    }

    /**
     * A name read as words: lowercase, its dashes, underscores, dots and spaces one space each.
     */
    private function words(string $text): string
    {
        return trim((string) preg_replace('/[\s._-]+/u', ' ', mb_strtolower($text)));
    }

    /**
     * Whether a width or a height is one: a positive integer, or the digits of one.
     */
    private function isSize(mixed $value): bool
    {
        return \is_int($value) ? $value > 0 : \is_string($value) && 1 === preg_match('/^[1-9]\d*$/D', $value);
    }
}
