<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * What would make a password harder to guess. Its value is stable: the translation key is `suggestion.<value>` in the
 * `phil_passwords` domain.
 */
enum Suggestion: string
{
    case UseWords = 'use_words';
    case NoSymbolsNeeded = 'no_symbols_needed';
    case AddWord = 'add_word';
    case LongerKeyboardPattern = 'longer_keyboard_pattern';
    case AvoidRepeats = 'avoid_repeats';
    case AvoidSequences = 'avoid_sequences';
    case AvoidRecentYears = 'avoid_recent_years';
    case AvoidDates = 'avoid_dates';
    case Capitalization = 'capitalization';
    case AllUppercase = 'all_uppercase';
    case ReversedWords = 'reversed_words';
    case Substitutions = 'substitutions';
    case AvoidPersonalInformation = 'avoid_personal_information';

    public function translationKey(): string
    {
        return 'suggestion.'.$this->value;
    }
}
