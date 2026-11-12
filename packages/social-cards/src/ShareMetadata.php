<?php

declare(strict_types=1);

namespace Phil\SocialCards;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Phil\SocialCards\Exception\InvalidShareMetadata;

/**
 * What a page says of itself when it is shared: its title and description, its canonical address, its image, its Open
 * Graph type, its locale and the locales it is also written in, the site it belongs to, its author and dates, and the
 * accounts of the site and of the author on X and on the fediverse.
 *
 * The texts are put on one line and cut — the title at 300 characters, the description at 1,000 —, so that content
 * from an editor never breaks a page; an address, a locale or a handle that is not one is refused.
 */
final readonly class ShareMetadata
{
    public const int MAX_TITLE = 300;

    public const int MAX_DESCRIPTION = 1000;

    public const int MAX_NAME = 200;

    public const int MAX_ALTERNATES = 50;

    /** The keys `fromArray()` reads. */
    public const array KEYS = ['title', 'url', 'description', 'image', 'type', 'locale', 'alternate_locales', 'site_name', 'author', 'published_time', 'modified_time', 'twitter_site', 'twitter_creator', 'fediverse_creator'];

    public string $title;

    public string $url;

    public ?string $description;

    public ?string $locale;

    /** @var list<string> */
    public array $alternateLocales;

    public ?string $siteName;

    public ?string $author;

    public ?string $twitterSite;

    public ?string $twitterCreator;

    public ?string $fediverseCreator;

    /**
     * @param list<string> $alternateLocales
     */
    public function __construct(
        string $title,
        string $url,
        ?string $description = null,
        public ?ShareImage $image = null,
        public ShareType $type = ShareType::Website,
        ?string $locale = null,
        array $alternateLocales = [],
        ?string $siteName = null,
        ?string $author = null,
        public ?DateTimeImmutable $publishedTime = null,
        public ?DateTimeImmutable $modifiedTime = null,
        ?string $twitterSite = null,
        ?string $twitterCreator = null,
        ?string $fediverseCreator = null,
    ) {
        $this->title = Text::line($title, self::MAX_TITLE) ?? throw InvalidShareMetadata::because('A shared page needs a title.');
        $this->url = Text::absoluteUrl($url, 'address of the page');
        $this->description = Text::line($description, self::MAX_DESCRIPTION);
        $this->locale = null === $locale ? null : self::locale($locale);
        $this->alternateLocales = $this->alternates($alternateLocales, $this->locale);
        $this->siteName = Text::line($siteName, self::MAX_NAME);
        $this->author = Text::line($author, self::MAX_NAME);
        $this->twitterSite = null === $twitterSite ? null : self::twitterHandle($twitterSite);
        $this->twitterCreator = null === $twitterCreator ? null : self::twitterHandle($twitterCreator);
        $this->fediverseCreator = null === $fediverseCreator ? null : self::fediverseHandle($fediverseCreator);
    }

    /**
     * The metadata from an array of the keys of `KEYS`, as a configuration or a template writes them: `image` an
     * address, a `ShareImage` or an array of `url`, `alt`, `width`, `height` and `type`; `type` a `ShareType` or its
     * value; the dates a `DateTimeInterface` or a string PHP reads. A null value is a missing one.
     *
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $unknown = array_diff(array_keys($values), self::KEYS);

        if ([] !== $unknown) {
            throw InvalidShareMetadata::because(\sprintf('The share metadata has no key "%s"; it reads %s.', implode('", "', $unknown), implode(', ', self::KEYS)));
        }

        $type = $values['type'] ?? ShareType::Website;

        return new self(
            self::string($values, 'title') ?? throw InvalidShareMetadata::because('A shared page needs a title.'),
            self::string($values, 'url') ?? throw InvalidShareMetadata::because('A shared page needs its address.'),
            self::string($values, 'description'),
            self::image($values['image'] ?? null),
            $type instanceof ShareType ? $type : (\is_string($type) ? ShareType::tryFrom($type) : null) ?? throw InvalidShareMetadata::because(\sprintf('The type of a shared page is one of "%s".', implode('", "', array_column(ShareType::cases(), 'value')))),
            self::string($values, 'locale'),
            self::strings($values, 'alternate_locales'),
            self::string($values, 'site_name'),
            self::string($values, 'author'),
            self::date($values, 'published_time'),
            self::date($values, 'modified_time'),
            self::string($values, 'twitter_site'),
            self::string($values, 'twitter_creator'),
            self::string($values, 'fediverse_creator'),
        );
    }

    /**
     * A locale as Open Graph writes it: the language in lower case, the script capitalised and the region in upper
     * case, joined by underscores — `fr`, `fr_CA`, `zh_Hant_TW`; a hyphen is read as an underscore.
     */
    public static function locale(string $locale): string
    {
        if (1 !== preg_match('/^([a-z]{2,3})(?:[_-]([a-z]{4}))?(?:[_-]([a-z]{2}|\d{3}))?$/i', trim($locale), $parts)) {
            throw InvalidShareMetadata::because(\sprintf('"%s" is not a locale: a language of 2 or 3 letters, maybe a script and a region, as fr_CA.', Text::excerpt($locale)));
        }

        return implode('_', array_filter([strtolower($parts[1]), ucfirst(strtolower($parts[2] ?? '')), strtoupper($parts[3] ?? '')], static fn (string $part): bool => '' !== $part));
    }

    /**
     * An account on X, `@` and 1 to 15 letters, digits or underscores; the `@` may be left out.
     */
    public static function twitterHandle(string $handle): string
    {
        $handle = ltrim(trim($handle), '@');

        if (1 !== preg_match('/^[A-Za-z0-9_]{1,15}$/', $handle)) {
            throw InvalidShareMetadata::because(\sprintf('"%s" is not an account on X: 1 to 15 letters, digits or underscores.', Text::excerpt($handle)));
        }

        return '@'.$handle;
    }

    /**
     * An account on the fediverse, `@user@example.social`; the first `@` may be left out.
     */
    public static function fediverseHandle(string $handle): string
    {
        $handle = ltrim(trim($handle), '@');

        if (\strlen($handle) > 255 || 1 !== preg_match('/^[A-Za-z0-9_.\-]{1,64}@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/', $handle)) {
            throw InvalidShareMetadata::because(\sprintf('"%s" is not an account on the fediverse: a user and a domain, as @user@example.social.', Text::excerpt($handle)));
        }

        return '@'.$handle;
    }

    /**
     * @param list<string> $locales
     *
     * @return list<string>
     */
    private function alternates(array $locales, ?string $locale): array
    {
        if (\count($locales) > self::MAX_ALTERNATES) {
            throw InvalidShareMetadata::because(\sprintf('A page names %d alternate locales at most; %d are given.', self::MAX_ALTERNATES, \count($locales)));
        }

        $alternates = array_unique(array_map(self::locale(...), $locales));

        return array_values(array_filter($alternates, static fn (string $alternate): bool => $alternate !== $locale));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if (null === $value || \is_string($value)) {
            return $value;
        }

        throw InvalidShareMetadata::because(\sprintf('The share metadata "%s" is a string; %s is given.', $key, get_debug_type($value)));
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private static function strings(array $values, string $key): array
    {
        $value = $values[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn (mixed $item): bool => !\is_string($item))) {
            throw InvalidShareMetadata::because(\sprintf('The share metadata "%s" is a list of strings.', $key));
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function date(array $values, string $key): ?DateTimeImmutable
    {
        $value = $values[$key] ?? null;

        if (null === $value || $value instanceof DateTimeInterface) {
            return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : null;
        }

        if (\is_string($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception $exception) {
                throw InvalidShareMetadata::because(\sprintf('The share metadata "%s" is not a date PHP reads: "%s".', $key, Text::excerpt($value)), $exception);
            }
        }

        throw InvalidShareMetadata::because(\sprintf('The share metadata "%s" is a date; %s is given.', $key, get_debug_type($value)));
    }

    private static function image(mixed $image): ?ShareImage
    {
        if (null === $image || $image instanceof ShareImage) {
            return $image;
        }

        if (\is_string($image)) {
            return new ShareImage($image);
        }

        if (!\is_array($image)) {
            throw InvalidShareMetadata::because(\sprintf('The image of a shared page is an address, a ShareImage or an array; %s is given.', get_debug_type($image)));
        }

        $unknown = array_diff(array_keys($image), ['url', 'alt', 'width', 'height', 'type']);
        $width = $image['width'] ?? null;
        $height = $image['height'] ?? null;

        if ([] !== $unknown || (null !== $width && !\is_int($width)) || (null !== $height && !\is_int($height))) {
            throw InvalidShareMetadata::because('The image of a shared page has a string "url", "alt" and "type" and an integer "width" and "height".');
        }

        return new ShareImage(
            self::string($image, 'url') ?? throw InvalidShareMetadata::because('The image of a shared page needs its "url".'),
            self::string($image, 'alt'),
            $width,
            $height,
            self::string($image, 'type'),
        );
    }
}
