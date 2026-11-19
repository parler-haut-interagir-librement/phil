<?php

declare(strict_types=1);

namespace Phil\BrailleExport;

/**
 * How much a text is shortened when it becomes braille: letter for letter, or with the contractions of the language.
 */
enum Contraction: string
{
    /** Letter for letter: grade 1, « braille intégral ». */
    case Uncontracted = 'uncontracted';

    /** With the contractions and abbreviations of the language: grade 2, « braille abrégé ». */
    case Contracted = 'contracted';
}
