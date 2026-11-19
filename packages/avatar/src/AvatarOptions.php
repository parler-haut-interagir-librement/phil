<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Style\InitialsStyle;

/**
 * How an avatar is drawn: its style, size in pixels, outline and palette, the name its initials come from, and the
 * label that names it to assistive technologies.
 *
 * Without a label, the name labels the avatar; without either, the avatar is decorative — hidden from assistive
 * technologies — as it should be beside a name already written out.
 */
final readonly class AvatarOptions
{
    public const int MIN_SIZE = 16;

    public const int MAX_SIZE = 1024;

    public const int MAX_LABEL_LENGTH = 200;

    public Palette $palette;

    /**
     * @param string  $style      the name of a style the generator knows: initials, shapes or pixels, or one of the
     *                            application's
     * @param int     $size       the width and height, in pixels, from 16 to 1024
     * @param ?string $name       the name to take initials from, such as the member's display name
     * @param ?string $label      the text naming the avatar, such as "Avatar of Ada Lovelace"
     * @param int     $maxLetters the most letters of the initials, from 1 to 3
     *
     * @throws InvalidAvatar
     */
    public function __construct(
        public string $style = InitialsStyle::NAME,
        public int $size = 64,
        public Shape $shape = Shape::Circle,
        ?Palette $palette = null,
        public ?string $name = null,
        public ?string $label = null,
        public int $maxLetters = 2,
    ) {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw InvalidAvatar::because(\sprintf('An avatar is %d to %d pixels wide.', self::MIN_SIZE, self::MAX_SIZE));
        }

        if (null !== $label && (!mb_check_encoding($label, 'UTF-8') || mb_strlen($label, 'UTF-8') > self::MAX_LABEL_LENGTH)) {
            throw InvalidAvatar::because(\sprintf('The label of an avatar is valid UTF-8, %d characters at most.', self::MAX_LABEL_LENGTH));
        }

        if ($maxLetters < 1 || $maxLetters > Initials::MAX_LETTERS) {
            throw InvalidAvatar::because(\sprintf('Initials hold 1 to %d letters.', Initials::MAX_LETTERS));
        }

        $this->palette = $palette ?? Palette::named('default');
    }

    /**
     * The text that names the avatar, or none for a decorative one: the label, else the name, trimmed.
     */
    public function accessibleName(): ?string
    {
        foreach ([$this->label, $this->name] as $text) {
            $text = null === $text ? '' : trim(mb_scrub($text, 'UTF-8'));

            if ('' !== $text) {
                return mb_substr($text, 0, self::MAX_LABEL_LENGTH, 'UTF-8');
            }
        }

        return null;
    }
}
