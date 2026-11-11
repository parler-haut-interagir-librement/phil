<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * The pages of a site by section, maybe of one section only, and the files they make: one per section, or numbered
 * parts when a section holds more pages than a file may.
 */
final class Sitemap implements UrlContainer
{
    public const int MAX_PER_FILE = 50000;

    /** @var array<string, list<Url>> */
    private array $sections = [];

    /**
     * @param ?string $only the one section kept, or null for all of them
     */
    public function __construct(
        public readonly ?string $only = null,
    ) {
        if (null !== $only) {
            self::checkSection($only);
        }
    }

    /**
     * A section is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit, and
     * never ending in an underscore and digits: `news_1` is the name of a numbered part of `news`, so a section of that
     * name would take the place of the part.
     */
    public static function checkSection(string $section): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $section)) {
            throw InvalidSitemap::because(\sprintf('A section is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit; "%s" is not.', $section));
        }

        if (1 === preg_match('/_\d+$/', $section)) {
            throw InvalidSitemap::because(\sprintf('A section does not end in an underscore and digits, which name the numbered parts of a section; "%s" does.', $section));
        }

        return $section;
    }

    /**
     * The section named after a key of an application — a kind of content, a genre — that may end in an underscore
     * and digits, which no section does: that last underscore becomes a hyphen, `news_2026` gives `news-2026`; any
     * other key is its own section. Two keys give the same section only when one has the hyphen the other's
     * underscore became, so keys written without hyphens keep a section each.
     *
     * @throws InvalidSitemap when the section is not one by the other rules
     */
    public static function sectionNamed(string $key): string
    {
        return self::checkSection(preg_replace('/_(\d+)$/D', '-$1', $key) ?? $key);
    }

    /**
     * The section a file of `files()` belongs to: the name itself, or the section of a numbered part — `news` for
     * `news_1`.
     *
     * @throws InvalidSitemap when the name is neither a section nor a part of one
     */
    public static function sectionOfFile(string $name): string
    {
        return self::checkSection(1 === preg_match('/^(.+)_\d+$/D', $name, $part) ? $part[1] : $name);
    }

    public function add(Url $url, string $section): void
    {
        self::checkSection($section);

        if (null === $this->only || $this->only === $section) {
            $this->sections[$section][] = $url;
        }
    }

    /**
     * @return array<string, list<Url>>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * The files of the sitemap, by name: the section's own when it fits one file, "news_0", "news_1"… when it does not.
     *
     * @return array<string, list<Url>>
     */
    public function files(int $perFile = self::MAX_PER_FILE): array
    {
        if ($perFile < 1 || $perFile > self::MAX_PER_FILE) {
            throw InvalidSitemap::because(\sprintf('A file holds 1 to %d pages; %d is not.', self::MAX_PER_FILE, $perFile));
        }

        $files = [];

        foreach ($this->sections as $section => $urls) {
            $parts = array_chunk($urls, $perFile);

            foreach ($parts as $index => $part) {
                $files[1 === \count($parts) ? $section : $section.'_'.$index] = $part;
            }
        }

        return $files;
    }
}
