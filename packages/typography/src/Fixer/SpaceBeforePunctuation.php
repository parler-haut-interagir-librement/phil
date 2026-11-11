<?php

declare(strict_types=1);

namespace Phil\Typography\Fixer;

use Override;
use Phil\Typography\Text\Chars;
use Phil\Typography\Text\Run;

/**
 * The French spaces of high punctuation, by the rules of France or of Canada (see `FrenchSpacing`): whatever space the
 * author typed — none, an ordinary one, a no-break one — becomes the right one. Only punctuation that ends a word is
 * touched: the colon of `12:30` or of `https://` is left alone, and `?!` takes a single space.
 */
final readonly class SpaceBeforePunctuation implements Fixer
{
    private const string S = Chars::SPACES;

    private const string WORD = '([^'.self::S.'!?;:«“‘„‹(\[{])';

    private const string ENDS = '(?=['.self::S.']|$|[!?;:.,»"”’)\]…])';

    public function __construct(
        private FrenchSpacing $spacing = FrenchSpacing::France,
    ) {
    }

    #[Override]
    public function fix(Run $run): Run
    {
        $high = FrenchSpacing::France === $this->spacing ? Chars::NARROW_NO_BREAK_SPACE : '';
        $patterns = [
            '/'.self::WORD.'['.self::S.']*([!?;])'.self::ENDS.'/u' => '$1'.$high.'$2',
            '/'.self::WORD.'['.self::S.']*(:)'.self::ENDS.'/u' => '$1'.Chars::NO_BREAK_SPACE.'$2',
            '/«['.self::S.']*(?=[^'.self::S.'])/u' => '«'.Chars::NO_BREAK_SPACE,
            '/(?<=[^'.self::S.'«])['.self::S.']*»/u' => Chars::NO_BREAK_SPACE.'»',
        ];

        return $run->replace(static fn (string $text): string => preg_replace(array_keys($patterns), $patterns, $text) ?? $text);
    }
}
