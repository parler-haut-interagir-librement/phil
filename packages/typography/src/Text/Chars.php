<?php

declare(strict_types=1);

namespace Phil\Typography\Text;

/**
 * The characters the fixers write.
 */
final class Chars
{
    public const string NO_BREAK_SPACE = "\u{A0}";

    public const string NARROW_NO_BREAK_SPACE = "\u{202F}";

    public const string THIN_SPACE = "\u{2009}";

    public const string SOFT_HYPHEN = "\u{AD}";

    public const string ZERO_WIDTH_SPACE = "\u{200B}";

    public const string EN_DASH = '–';

    public const string EM_DASH = '—';

    public const string ELLIPSIS = '…';

    public const string APOSTROPHE = '’';

    public const string MULTIPLICATION = '×';

    /**
     * The spaces a fixer replaces, in a regular-expression character class: the ordinary ones and the no-break ones.
     */
    public const string SPACES = '\s\x{A0}\x{202F}\x{2009}';
}
