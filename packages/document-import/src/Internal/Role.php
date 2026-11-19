<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Phil\DocumentImport\StyleRole;

/**
 * What a paragraph style makes of its paragraphs: a paragraph, a heading of a level — its value —, a quotation or
 * code.
 *
 * @internal
 */
enum Role: int
{
    case Paragraph = 0;
    case Heading1 = 1;
    case Heading2 = 2;
    case Heading3 = 3;
    case Heading4 = 4;
    case Heading5 = 5;
    case Heading6 = 6;
    case Quote = 7;
    case Code = 8;

    /**
     * The heading of a level, the levels past 6 written as 6 and those before 1 as 1.
     */
    public static function heading(int $level): self
    {
        return self::from(max(1, min(6, $level)));
    }

    /**
     * The heading of an outline level counted from 0, as office formats count them; `null` for the level 9 and
     * beyond, which is the body text.
     */
    public static function outline(int $level): ?self
    {
        return $level < 9 ? self::heading($level + 1) : null;
    }

    /**
     * The role a style the application's map names takes; `null` for a style whose paragraphs are left out.
     */
    public static function mapped(StyleRole $role): ?self
    {
        return match ($role) {
            StyleRole::Paragraph => self::Paragraph,
            StyleRole::Heading1 => self::Heading1,
            StyleRole::Heading2 => self::Heading2,
            StyleRole::Heading3 => self::Heading3,
            StyleRole::Heading4 => self::Heading4,
            StyleRole::Heading5 => self::Heading5,
            StyleRole::Heading6 => self::Heading6,
            StyleRole::Quote => self::Quote,
            StyleRole::Code => self::Code,
            StyleRole::Omit => null,
        };
    }
}
