<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

/**
 * The character formatting a UJO text keeps, as the opening tag `phil/ujo` writes; the order of the cases is the
 * order in which they nest.
 *
 * @internal
 */
enum Mark: string
{
    case Bold = '<b>';
    case Italic = '<i>';
    case Underline = '<u class="cdx-underline">';
    case Strikethrough = '<s class="cdx-strikethrough">';
    case Marker = '<mark class="cdx-marker">';
    case Code = '<code class="inline-code">';
    case Superscript = '<sup>';
    case Subscript = '<sub>';

    /**
     * The opening tags of a set of marks, in nesting order, after the link that holds them when there is one.
     *
     * @param list<Mark> $marks
     *
     * @return list<string>
     */
    public static function tags(array $marks, ?string $link = null): array
    {
        $tags = null === $link ? [] : ['<a href="'.htmlspecialchars($link, \ENT_QUOTES).'">'];

        foreach (self::cases() as $mark) {
            if (\in_array($mark, $marks, true)) {
                $tags[] = $mark->value;
            }
        }

        return $tags;
    }
}
