<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A sub-unit is named whenever the currency has decimals, and sub-units are only said then: the cast only
            // serves the `?string` of the property. The formatter answers false for a number no rule set can spell,
            // which the check on digits right after turns into the same refusal as the empty string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$one = \\(string\\) \\$name->subunitOne;',
                    '\\$words = \\(string\\) \\$this->formatter\\(\\$ruleSet\\)->format\\(\\$number\\);',
                    // The noun of a currency is valid UTF-8, and the first byte of the decomposed text is read with `??`.
                    '\\$decomposed = \\(string\\) Normalizer::normalize\\(\\$noun, Normalizer::FORM_D\\);',
                ],
            ],
            // French answers for the zero of a count in its own override, which its tests cover and this line, the
            // one of the other languages, is not linked to.
            'ProtectedVisibility' => [
                'ignoreSourceCodeByRegex' => ['protected function isSingular\\(int \\$count\\): bool'],
            ],
            // The plain ordinal and the plural one exist together or the plural one alone in the ICU data; when the
            // plain one is checked instead, the same rule set is asked for after it.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['if \\(!\\$gender instanceof Gender && \\\\in_array\\(\'%spellout-ordinal\\\'\\.\\$suffix, \\$this->ruleSets, true\\)\\) \\{'],
            ],
        ],
    ],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        // Numbers in words in the six languages, quoted as examples.
        'words' => [
            'cinq', 'dólares', 'eine', 'eins', 'einundzwanzig', 'einundzwanzigste', 'millón', 'moins', 'terceras',
            'unième', 'veintiuna', 'veintiuno', 'veintiún', 'vigésimo', 'vingt', 'zéro',
        ],
    ],
];
