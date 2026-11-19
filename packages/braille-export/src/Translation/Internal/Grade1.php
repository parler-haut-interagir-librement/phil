<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Translation\Internal;

use Phil\BrailleExport\Exception\UntranslatableCharacter;
use Phil\BrailleExport\Translation\Braille;

/**
 * The letter-for-letter rules of one language: its letters, its capital signs, its numbers and its punctuation, and
 * the walk that applies them to a text.
 *
 * @internal
 */
final readonly class Grade1
{
    private const string FRENCH_PUNCTUATION = '{",":"2",";":"23",":":"25",".":"256","?":"26","!":"235","\"":"2356","«":"2356","»":"2356","“":"2356","”":"2356","(":"236",")":"356","\'":"3","’":"3","-":"36","‐":"36","–":"36 36","—":"36 36","…":"256 256 256","*":"35","@":"345"}';

    private const string ENGLISH_PUNCTUATION = '{",":"2",";":"23",":":"25",".":"256","?":"236","!":"235","\'":"3","’":"3","‘":"6 236","-":"36","‐":"36","–":"6 36","—":"5 6 36","(":"5 126",")":"5 345","[":"46 126","]":"46 345","{":"456 126","}":"456 345","“":"45 236","”":"45 356","\"":"6 2356","/":"456 34","\\\\":"456 16","&":"4 12346","@":"4 1","#":"456 1456","%":"46 356","*":"5 35","+":"5 235","=":"5 2356","<":"4 126",">":"4 345","$":"4 234","€":"4 15","£":"4 123","_":"46 36","~":"4 35","…":"256 256 256"}';

    private const string LETTERS = '{"a":"1","b":"12","c":"14","d":"145","e":"15","f":"124","g":"1245","h":"125","i":"24","j":"245","k":"13","l":"123","m":"134","n":"1345","o":"135","p":"1234","q":"12345","r":"1235","s":"234","t":"2345","u":"136","v":"1236","w":"2456","x":"1346","y":"13456","z":"1356"}';

    private const string FRENCH_ACCENTS = '{"à":"12356","â":"16","ç":"12346","è":"2346","é":"123456","ê":"126","ë":"1246","î":"146","ï":"12456","ô":"1456","ù":"23456","û":"156","ü":"1256","œ":"246"}';

    /** The accented letters of English braille: a modifier before the letter. */
    private const string ENGLISH_ACCENTS = '{"á":"45 34 1","à":"45 16 1","â":"45 146 1","ä":"45 25 1","ã":"45 3456 1","ç":"45 12346 14","é":"45 34 15","è":"45 16 15","ê":"45 146 15","ë":"45 25 15","í":"45 34 24","ì":"45 16 24","î":"45 146 24","ï":"45 25 24","ñ":"45 3456 1345","ó":"45 34 135","ò":"45 16 135","ô":"45 146 135","ö":"45 25 135","õ":"45 3456 135","ú":"45 34 136","ù":"45 16 136","û":"45 146 136","ü":"45 25 136","ý":"45 34 13456","ÿ":"45 25 13456","œ":"135 15","æ":"1 15","ß":"234 234"}';

    /** Letters the code of a language has no sign for, written as their base letters. */
    private const string FOLDED = '{"á":"a","ä":"a","ã":"a","å":"a","ā":"a","ć":"c","č":"c","ě":"e","ē":"e","í":"i","ì":"i","ī":"i","ñ":"n","ń":"n","ó":"o","ò":"o","ö":"o","õ":"o","ø":"o","ō":"o","ř":"r","ś":"s","š":"s","ú":"u","ū":"u","ů":"u","ý":"y","ÿ":"y","ź":"z","ż":"z","ž":"z","æ":"ae","ß":"ss","ł":"l","đ":"d"}';

    private const string DIGITS = '1234567890';

    /** The letters a to j, which a number may be followed by. */
    private const string DIGIT_LETTERS = 'abcdefghij';

    /** @var array<string, string> each capital letter, to its lower-case letter */
    private array $capitals;

    /**
     * @param array<string, string> $letters           each lower-case letter, to its cells
     * @param array<string, string> $digits            each digit, to its cells
     * @param array<string, string> $numberPunctuation the marks a number may hold between two digits, to their cells
     * @param array<string, string> $punctuation       each other character, to its cells
     * @param string                $letterAfterNumber the sign before a letter a to j that follows a number, if any
     */
    private function __construct(
        private string $language,
        private array $letters,
        private string $capital,
        private string $capitalWord,
        private string $numberSign,
        private array $digits,
        private array $numberPunctuation,
        private array $punctuation,
        private string $letterAfterNumber,
    ) {
        $capitals = [];

        foreach (array_keys($letters) as $letter) {
            $capitals[mb_strtoupper($letter, 'UTF-8')] = $letter;
        }

        $this->capitals = $capitals;
    }

    /**
     * French uncontracted braille, as the Code braille français uniformisé writes it: numbers in the Antoine notation
     * after dot 6.
     */
    public static function french(): self
    {
        $letters = self::compile([...self::decode(self::LETTERS), ...self::decode(self::FRENCH_ACCENTS)]);

        return new self(
            'fr',
            self::fold($letters),
            Braille::cell('46'),
            Braille::cells('46', '46'),
            Braille::cell('6'),
            self::compile(array_combine(str_split(self::DIGITS), ['16', '126', '146', '1456', '156', '1246', '12456', '1256', '246', '3456'])),
            self::compile([',' => '2', '.' => '256']),
            self::compile(self::decode(self::FRENCH_PUNCTUATION)),
            '',
        );
    }

    /**
     * English uncontracted braille, as Unified English Braille writes it: numbers as the letters a to j after the
     * numeric indicator, a letter a to j after a number preceded by the grade 1 indicator.
     */
    public static function english(): self
    {
        $letters = self::compile([...self::decode(self::LETTERS), ...self::decode(self::ENGLISH_ACCENTS)]);

        return new self(
            'en',
            self::fold($letters),
            Braille::cell('6'),
            Braille::cells('6', '6'),
            Braille::cell('3456'),
            self::compile(array_combine(str_split(self::DIGITS), ['1', '12', '14', '145', '15', '124', '1245', '125', '24', '245'])),
            self::compile([',' => '2', '.' => '256']),
            self::compile(self::decode(self::ENGLISH_PUNCTUATION)),
            Braille::cell('56'),
        );
    }

    /**
     * The braille of one line of text.
     *
     * @param bool $strict whether a character without braille stops the translation; it is left out otherwise
     *
     * @throws UntranslatableCharacter
     */
    public function translate(string $text, bool $strict): string
    {
        $characters = mb_str_split($text, 1, 'UTF-8');
        $count = \count($characters);
        $braille = '';
        $index = 0;

        while ($index < $count) {
            $character = $characters[$index];

            if (' ' === $character) {
                $braille .= Braille::BLANK;
                ++$index;
            } elseif (isset($this->digits[$character])) {
                $index = $this->number($characters, $index, $braille);
            } elseif (isset($this->letters[$character]) || isset($this->capitals[$character])) {
                $index = $this->word($characters, $index, $braille);
            } elseif (isset($this->punctuation[$character])) {
                $braille .= $this->punctuation[$character];
                ++$index;
            } elseif ($strict) {
                throw new UntranslatableCharacter($character, $this->language);
            } else {
                ++$index;
            }
        }

        return $braille;
    }

    /**
     * @param list<string> $characters
     */
    private function number(array $characters, int $index, string &$braille): int
    {
        $braille .= $this->numberSign;
        $count = \count($characters);

        while ($index < $count) {
            $character = $characters[$index];

            if (isset($this->digits[$character])) {
                $braille .= $this->digits[$character];
            } elseif (isset($this->numberPunctuation[$character], $characters[$index + 1], $this->digits[$characters[$index + 1]])) {
                $braille .= $this->numberPunctuation[$character];
            } else {
                break;
            }

            ++$index;
        }

        if ('' !== $this->letterAfterNumber && isset($characters[$index]) && str_contains(self::DIGIT_LETTERS, $characters[$index])) {
            $braille .= $this->letterAfterNumber;
        }

        return $index;
    }

    /**
     * A run of letters: the word sign when every letter of two or more is a capital, the capital sign before each
     * capital otherwise.
     *
     * @param list<string> $characters
     */
    private function word(array $characters, int $index, string &$braille): int
    {
        $lower = [];
        $capitals = [];
        $count = \count($characters);

        while ($index < $count) {
            $character = $characters[$index];

            if (isset($this->letters[$character])) {
                $lower[] = $character;
                $capitals[] = false;
            } elseif (isset($this->capitals[$character])) {
                $lower[] = $this->capitals[$character];
                $capitals[] = true;
            } else {
                break;
            }

            ++$index;
        }

        $wholeWord = \count($lower) > 1 && !\in_array(false, $capitals, true);

        if ($wholeWord) {
            $braille .= $this->capitalWord;
        }

        foreach ($lower as $position => $letter) {
            if (!$wholeWord && $capitals[$position]) {
                $braille .= $this->capital;
            }

            $braille .= $this->letters[$letter];
        }

        return $index;
    }

    /**
     * @return array<string, string>
     */
    private static function decode(string $json): array
    {
        /** @var array<string, string> */
        return json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array-key, string> $dots each character, to its dots, the cells of a sign separated by spaces
     *
     * @return array<string, string>
     */
    private static function compile(array $dots): array
    {
        $cells = [];

        foreach ($dots as $character => $sign) {
            $cells[(string) $character] = Braille::cells(...explode(' ', $sign));
        }

        return $cells;
    }

    /**
     * The letters, with the folded ones the table has no sign for written as their base letters.
     *
     * @param array<string, string> $letters
     *
     * @return array<string, string>
     */
    private static function fold(array $letters): array
    {
        foreach (self::decode(self::FOLDED) as $letter => $base) {
            $letters[$letter] ??= implode('', array_map(static fn (string $character): string => $letters[$character], str_split($base)));
        }

        return $letters;
    }
}
