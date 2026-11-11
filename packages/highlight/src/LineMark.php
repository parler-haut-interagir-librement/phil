<?php

declare(strict_types=1);

namespace Phil\Highlight;

/**
 * What a line of a block is marked as, by a highlighted range or by an annotation in one of its comments: the HTML
 * class of a mark is `hl-line-` and its value.
 */
enum LineMark: string
{
    /** A line drawn attention to: a highlighted range, or `[!code highlight]`. */
    case Highlighted = 'highlighted';
    /** A line added: `[!code ++]`. */
    case Inserted = 'inserted';
    /** A line removed: `[!code --]`. */
    case Deleted = 'deleted';

    /**
     * The mark an annotation names: `++`, `--` or `highlight`; `null` for any other word.
     */
    public static function fromAnnotation(string $word): ?self
    {
        return match ($word) {
            '++' => self::Inserted,
            '--' => self::Deleted,
            'highlight' => self::Highlighted,
            default => null,
        };
    }

    public function className(): string
    {
        return 'hl-line-'.$this->value;
    }
}
