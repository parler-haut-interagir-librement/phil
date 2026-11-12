<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\LocaleDimensions\Locale;
use Phil\Url\Url;

/**
 * Where a row points: another content root, or an absolute URL. Never both, never neither.
 *
 * - **A content root is named by its resource key and its identifier**, both the application's: the key
 *   names a kind of content (`article`, `page`), lowercase, at most 64 characters; the identifier is the
 *   root's own, as a string of at most 64 letters, digits, hyphens and underscores, which holds a UUID, a
 *   ULID or an integer. A locale may pin the language of the target; without one, the reader's language is
 *   followed. The link is resolved when it is rendered, so it follows the target when its slug changes.
 * - **A URL is a `Url` of `phil/url`**: absolute, `http` or `https`, normalised, without credentials — a link to
 *   another site; a link inside the application points to a content root.
 *
 * The shape is checked here; whether the target root exists is the application's question, asked when the
 * link is submitted or rendered.
 */
final readonly class Link
{
    private function __construct(
        public ?string $resourceKey,
        public ?string $resourceId,
        public ?Locale $locale,
        public ?Url $url,
    ) {
    }

    /**
     * @throws InvalidContent
     */
    public static function toContent(string $resourceKey, string $resourceId, ?Locale $locale = null): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $resourceKey)) {
            throw InvalidContent::because(\sprintf('"%s" is not a resource key: a lowercase letter, then at most 63 lowercase letters, digits or underscores.', $resourceKey));
        }

        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $resourceId)) {
            throw InvalidContent::because(\sprintf('"%s" is not a resource identifier: 1 to 64 letters, digits, hyphens or underscores.', $resourceId));
        }

        return new self($resourceKey, $resourceId, $locale, null);
    }

    public static function toUrl(Url $url): self
    {
        return new self(null, null, null, $url);
    }

    public function isToContent(): bool
    {
        return !$this->url instanceof Url;
    }
}
