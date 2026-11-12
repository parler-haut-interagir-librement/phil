<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

use Phil\SocialCards\Exception\InvalidShareMetadata;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCards\Text;
use Phil\SocialCards\TwitterCard;

/**
 * Checks the tags of a page as the sites that show links read them: the four tags Open Graph requires, absolute
 * addresses, a description, an image with its replacement text and its size, a card of X, the lengths the readers cut
 * at, and the canonical link agreeing with `og:url`.
 */
final class TagCheck
{
    /** Where most readers cut a title. */
    public const int TITLE_LENGTH = 90;

    /** Where most readers cut a description. */
    public const int DESCRIPTION_LENGTH = 200;

    /** The smallest image the readers show. */
    public const int MIN_IMAGE = 200;

    /**
     * @return list<Finding>
     */
    public static function check(PageTags $tags): array
    {
        $findings = [];

        foreach (['og:title', 'og:type', 'og:url', 'og:image'] as $required) {
            if (null === $tags->get($required)) {
                $findings[] = new Finding(Severity::Error, $required, \sprintf('The page has no %s, which Open Graph requires.', $required));
            }
        }

        foreach (['og:url', 'og:image'] as $address) {
            $value = $tags->get($address);

            if (null !== $value && !Text::isAbsoluteUrl($value)) {
                $findings[] = new Finding(Severity::Error, $address, \sprintf('The %s "%s" is not an absolute HTTP or HTTPS address.', $address, Text::excerpt($value)));
            }
        }

        return [...$findings, ...self::texts($tags), ...self::image($tags), ...self::others($tags)];
    }

    /**
     * Whether a finding is an error.
     *
     * @param list<Finding> $findings
     */
    public static function hasErrors(array $findings): bool
    {
        return array_any($findings, static fn (Finding $finding): bool => Severity::Error === $finding->severity);
    }

    /**
     * @return list<Finding>
     */
    private static function texts(PageTags $tags): array
    {
        $findings = [];
        $title = $tags->get('og:title');
        $description = $tags->get('og:description');

        if (null !== $title && mb_strlen($title) > self::TITLE_LENGTH) {
            $findings[] = new Finding(Severity::Warning, 'og:title', \sprintf('The og:title has %d characters; most readers cut it at %d.', mb_strlen($title), self::TITLE_LENGTH));
        }

        if (null === $description) {
            $findings[] = new Finding(Severity::Warning, 'og:description', 'The page has no og:description: readers show a link without a summary.');
        } elseif (mb_strlen($description) > self::DESCRIPTION_LENGTH) {
            $findings[] = new Finding(Severity::Warning, 'og:description', \sprintf('The og:description has %d characters; most readers cut it at %d.', mb_strlen($description), self::DESCRIPTION_LENGTH));
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private static function image(PageTags $tags): array
    {
        if (null === $tags->get('og:image')) {
            return [];
        }

        $findings = [];

        if (null === $tags->first('og:image:alt', 'twitter:image:alt')) {
            $findings[] = new Finding(Severity::Warning, 'og:image:alt', 'The image has no og:image:alt: those who cannot see it are not told what it shows.');
        }

        $width = $tags->get('og:image:width');
        $height = $tags->get('og:image:height');

        if (null === $width || null === $height) {
            $findings[] = new Finding(Severity::Warning, 'og:image:width', 'The image has no og:image:width and og:image:height: readers must fetch it before they draw the card.');
        } elseif (!ctype_digit($width) || !ctype_digit($height) || $width < self::MIN_IMAGE || $height < self::MIN_IMAGE) {
            $findings[] = new Finding(Severity::Warning, 'og:image:width', \sprintf('The image is declared %s×%s; readers show images of %d×%d pixels at least.', $width, $height, self::MIN_IMAGE, self::MIN_IMAGE));
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private static function others(PageTags $tags): array
    {
        $findings = [];
        $card = $tags->get('twitter:card');
        $url = $tags->get('og:url');
        $creator = $tags->get('fediverse:creator');

        if (null === $card) {
            $findings[] = new Finding(Severity::Warning, 'twitter:card', 'The page has no twitter:card: X shows the link as a small summary.');
        } elseif (null === TwitterCard::tryFrom($card) && !\in_array($card, ['app', 'player'], true)) {
            $findings[] = new Finding(Severity::Error, 'twitter:card', \sprintf('"%s" is not a card of X: summary, summary_large_image, app or player.', Text::excerpt($card)));
        }

        if (null !== $url && null !== $tags->canonical && rtrim($url, '/') !== rtrim($tags->canonical, '/')) {
            $findings[] = new Finding(Severity::Warning, 'og:url', \sprintf('The og:url "%s" is not the canonical link "%s".', Text::excerpt($url), Text::excerpt($tags->canonical)));
        }

        if (null !== $creator && !self::isFediverseHandle($creator)) {
            $findings[] = new Finding(Severity::Warning, 'fediverse:creator', \sprintf('"%s" is not an account on the fediverse, as @user@example.social.', Text::excerpt($creator)));
        }

        return $findings;
    }

    private static function isFediverseHandle(string $handle): bool
    {
        try {
            return str_starts_with($handle, '@') && $handle === ShareMetadata::fediverseHandle($handle);
        } catch (InvalidShareMetadata) {
            return false;
        }
    }
}
