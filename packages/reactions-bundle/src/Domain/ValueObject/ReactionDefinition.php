<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\ValueObject;

use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;

/**
 * A reaction a site offers: its code, the translation key of its label, and its face, a Unicode emoji or an image with
 * the translation key of its replacement text.
 *
 * A private reaction has no public counter and no names: only the member who chose it sees it pressed. A reaction shown
 * in the bar is `quick`; the others wait in the picker until someone chooses them.
 */
final readonly class ReactionDefinition
{
    public const string DEFAULT_DOMAIN = 'phil_reactions';

    private const string CODE = '/^[a-z][a-z0-9_+-]{0,63}$/D';

    private const string IMAGE = '#^(?:/(?!/)|https://|media:)\S{1,2048}$#D';

    private const int MAX_EMOJI_BYTES = 32;

    private function __construct(
        public string $code,
        public string $label,
        public ?string $emoji,
        public ?string $image,
        public ?string $imageAlt,
        public bool $isPrivate,
        public bool $quick,
        public string $domain,
    ) {
    }

    /**
     * @throws InvalidReaction when the code, the label or the emoji cannot be used
     */
    public static function unicode(string $code, string $label, string $emoji, bool $isPrivate = false, bool $quick = true, string $domain = self::DEFAULT_DOMAIN): self
    {
        if ('' === $emoji || \strlen($emoji) > self::MAX_EMOJI_BYTES) {
            throw InvalidReaction::because(\sprintf('The emoji of "%s" is 1 to %d bytes.', $code, self::MAX_EMOJI_BYTES));
        }

        return new self(self::code($code), self::text($label, $code), $emoji, null, null, $isPrivate, $quick, $domain);
    }

    /**
     * A reaction drawn by an image: a path from the site's root, an HTTPS address, or `media:` and a medium's identifier.
     *
     * @throws InvalidReaction when the code, the label, the image or its text cannot be used
     */
    public static function image(string $code, string $label, string $image, string $alt, bool $isPrivate = false, bool $quick = false, string $domain = self::DEFAULT_DOMAIN): self
    {
        if (1 !== preg_match(self::IMAGE, $image)) {
            throw InvalidReaction::because(\sprintf('The image of "%s" is a path from the root of the site, an HTTPS address or "media:" and an identifier.', $code));
        }

        return new self(self::code($code), self::text($label, $code), null, $image, self::text($alt, $code), $isPrivate, $quick, $domain);
    }

    /**
     * @throws InvalidReaction when it is not a code
     */
    public static function code(string $code): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidReaction::because(\sprintf('A reaction code is 1 to 64 lower-case letters, digits, "_", "+" or "-", starting with a letter; "%s" is not.', $code));
        }

        return $code;
    }

    /**
     * The same reaction, its image at the address where a browser finds it.
     */
    public function withImageAt(string $url): self
    {
        return new self($this->code, $this->label, $this->emoji, $url, $this->imageAlt, $this->isPrivate, $this->quick, $this->domain);
    }

    private static function text(string $text, string $code): string
    {
        if ('' === $text) {
            throw InvalidReaction::because(\sprintf('The label and the replacement text of "%s" are translation keys, and cannot be empty.', $code));
        }

        return $text;
    }
}
