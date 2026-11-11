<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * What makes a weak password easy to guess. Its value is stable: the translation key is `warning.<value>` in the
 * `phil_passwords` domain.
 */
enum Warning: string
{
    case StraightRow = 'straight_row';
    case ShortKeyboardPattern = 'short_keyboard_pattern';
    case RepeatedCharacter = 'repeated_character';
    case RepeatedPattern = 'repeated_pattern';
    case Sequence = 'sequence';
    case RecentYear = 'recent_year';
    case Date = 'date';
    case TopTen = 'top_ten';
    case TopHundred = 'top_hundred';
    case CommonPassword = 'common_password';
    case SimilarToCommon = 'similar_to_common';
    case SingleWord = 'single_word';
    case Name = 'name';
    case CommonName = 'common_name';
    case UserInput = 'user_input';

    public function translationKey(): string
    {
        return 'warning.'.$this->value;
    }
}
