<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Generator;

/**
 * The orthographic syllabification of one language, written for this library from the published rules of the language,
 * from which `PatternGenerator` writes Liang patterns. The rules are deliberately cautious: where a rule of the language
 * depends on the meaning or the history of a word — a compound, a prefix, a diphthong that is sometimes two vowels —, no
 * break is allowed rather than a wrong one.
 */
final readonly class SyllableRules
{
    /**
     * @param string       $tag           the pattern tag, as in `Dictionary::LANGUAGES`
     * @param list<string> $vowels        the letters that carry a syllable
     * @param list<string> $consonants    the letters that do not
     * @param list<string> $units         the consonants written with several letters, never broken: `ch`, `sz`, `ng`
     * @param list<string> $onsets        the clusters that begin a syllable together: a break goes before them, never inside
     * @param list<string> $hiatus        the pairs of vowels that always belong to two syllables, broken between them
     * @param list<string> $syllabic      the consonants that carry a syllable after a word-initial consonant (Czech `vl-na`),
     *                                    for which a break after the first two letters is not forbidden
     * @param list<string> $silentEndings the endings that do not make a syllable of their own after a consonant (the
     *                                    Romanian final `i` of `lupi`), never broken off
     * @param list<string> $patterns      hand-written patterns: prefixes kept whole, the exceptions of the language
     */
    public function __construct(
        public string $tag,
        public Split $split,
        public array $vowels,
        public array $consonants,
        public array $units = [],
        public array $onsets = [],
        public array $hiatus = [],
        public array $syllabic = [],
        public array $silentEndings = [],
        public array $patterns = [],
    ) {
    }

    /**
     * The rules of every language written for this library, by tag.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $languages = [self::czech(), self::hungarian(), self::armenian(), self::indonesian(), self::latvian(), self::macedonian(), self::romanian()];

        return array_combine(array_map(static fn (self $rules): string => $rules->tag, $languages), $languages);
    }

    /**
     * Czech, after the rules of the Institute of the Czech Language for dividing words: a word breaks between its
     * syllables; one consonant between vowels goes to the next line; a group of consonants may be divided in several
     * ways, and here goes to the next line whole when it is a stop or a fricative followed by `r`, `l` or `ř`, its last
     * consonant alone otherwise. `ch` is one letter. A word-initial `r` or `l` between consonants carries a syllable
     * (`vl-na`, `Br-no`). Prefixes ending in a consonant are not broken from a vowel or a liquid that follows: the
     * clusters beginning with `d` are not counted as onsets, and `roz`, `bez`, `nad`, `pod` and `před` are kept whole.
     */
    private static function czech(): self
    {
        return new self(
            tag: 'cs',
            split: Split::BeforeLast,
            vowels: ['a', 'á', 'e', 'é', 'ě', 'i', 'í', 'o', 'ó', 'u', 'ú', 'ů', 'y', 'ý'],
            consonants: ['b', 'c', 'č', 'd', 'ď', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'ň', 'p', 'q', 'r', 'ř', 's', 'š', 't', 'ť', 'v', 'w', 'x', 'z', 'ž'],
            units: ['ch', 'dž'],
            onsets: [
                'pr', 'br', 'tr', 'kr', 'gr', 'fr', 'vr', 'hr', 'chr',
                'pl', 'bl', 'kl', 'gl', 'fl', 'vl', 'hl', 'chl',
                'př', 'bř', 'tř', 'kř', 'vř', 'hř',
            ],
            syllabic: ['l', 'r'],
            patterns: ['.ro2z', '.be2z', '.na2d', '.po2d', '.pře2d', '.vzk2'],
        );
    }

    /**
     * Hungarian, after the rules of the Hungarian Academy of Sciences: one consonant between vowels goes to the next
     * line; of several, only the last does (`temp-lom`, `prog-ram`); two vowels side by side belong to two syllables.
     * The digraphs `cs`, `dz`, `dzs`, `gy`, `ly`, `ny`, `sz`, `ty` and `zs` are single letters; a doubled digraph is
     * written short and restored at a break (`asszony`, `asz-szony`), which a soft hyphen cannot do, so it is never
     * broken. Compounds and prefixes break between their parts, which patterns cannot see: `meg`, `fel` and `szét` are
     * kept whole.
     */
    private static function hungarian(): self
    {
        $vowels = ['a', 'á', 'e', 'é', 'i', 'í', 'o', 'ó', 'ö', 'ő', 'u', 'ú', 'ü', 'ű'];

        return new self(
            tag: 'hu',
            split: Split::BeforeLast,
            vowels: $vowels,
            consonants: ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'],
            units: ['cs', 'dz', 'dzs', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'],
            hiatus: array_merge(...array_map(static fn (string $first): array => array_map(static fn (string $second): string => $first.$second, $vowels), $vowels)),
            patterns: ['c2cs', 'd2dz', 'g2gy', 'l2ly', 'n2ny', 's2sz', 't2ty', 'z2zs', '.me2g', '.fe2l', '.szé2t'],
        );
    }

    /**
     * Armenian, in the reformed orthography, after the rules of Armenian school grammars: one consonant between vowels
     * goes to the next line, and of several only the last does, with the `յ` that follows a consonant
     * (`գի-տու-թյուն`). `ու` is one vowel; `և` stands for a vowel and a consonant together and takes part in no break.
     * The unwritten vowel between word-initial consonants is not counted: no break follows the first two letters of a
     * word that begins with two consonants.
     */
    private static function armenian(): self
    {
        $consonants = ['բ', 'գ', 'դ', 'զ', 'թ', 'ժ', 'լ', 'խ', 'ծ', 'կ', 'հ', 'ձ', 'ղ', 'ճ', 'մ', 'յ', 'ն', 'շ', 'չ', 'պ', 'ջ', 'ռ', 'ս', 'վ', 'տ', 'ր', 'ց', 'փ', 'ք', 'ֆ'];

        return new self(
            tag: 'hy',
            split: Split::BeforeLast,
            vowels: ['ա', 'ե', 'է', 'ը', 'ի', 'ո', 'օ'],
            consonants: $consonants,
            onsets: array_values(array_map(static fn (string $consonant): string => $consonant.'յ', array_diff($consonants, ['յ']))),
        );
    }

    /**
     * Indonesian, after the general spelling guidelines of the Language Development and Cultivation Agency: one
     * consonant between vowels goes to the next line; of two, the second; of three or more, all but the first
     * (`in-stru-men`). `ng`, `ny`, `kh` and `sy` are single consonants. Two vowels side by side are broken between them,
     * except the diphthongs `ai`, `au`, `ei` and `oi`, and here `eu` and `ou`, which are sometimes one.
     */
    private static function indonesian(): self
    {
        return new self(
            tag: 'id',
            split: Split::AfterFirst,
            vowels: ['a', 'e', 'i', 'o', 'u'],
            consonants: ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'],
            units: ['kh', 'ng', 'ny', 'sy'],
            hiatus: ['aa', 'ae', 'ao', 'ea', 'ee', 'eo', 'ia', 'ie', 'ii', 'io', 'iu', 'oa', 'oe', 'oo', 'ua', 'ue', 'ui', 'uo', 'uu'],
        );
    }

    /**
     * Latvian, after the spelling rules of the Latvian Academy of Sciences: a word breaks between syllables; one
     * consonant between vowels goes to the next line, and of several at least one does — here the last. Diphthongs
     * (`ai`, `au`, `ei`, `ie`, `oi`, `ui`, and `o` itself) are never broken, nor `dz` and `dž`. Prefixes `aiz` and `pār`
     * are kept whole.
     */
    private static function latvian(): self
    {
        return new self(
            tag: 'lv',
            split: Split::BeforeLast,
            vowels: ['a', 'ā', 'e', 'ē', 'i', 'ī', 'o', 'u', 'ū'],
            consonants: ['b', 'c', 'č', 'd', 'f', 'g', 'ģ', 'h', 'j', 'k', 'ķ', 'l', 'ļ', 'm', 'n', 'ņ', 'p', 'r', 's', 'š', 't', 'v', 'z', 'ž'],
            units: ['dz', 'dž'],
            patterns: ['.ai2z', '.pā2r'],
        );
    }

    /**
     * Macedonian, after the orthography of the Macedonian literary language: one consonant between vowels goes to the
     * next line; of a group, all but the first. A syllabic `р` (`срце`) carries no written vowel and is not broken.
     * Prefixes `без`, `раз`, `под`, `над` and `пред` are kept whole.
     */
    private static function macedonian(): self
    {
        return new self(
            tag: 'mk',
            split: Split::AfterFirst,
            vowels: ['а', 'е', 'и', 'о', 'у'],
            consonants: ['б', 'в', 'г', 'д', 'ѓ', 'ж', 'з', 'ѕ', 'ј', 'к', 'л', 'љ', 'м', 'н', 'њ', 'п', 'р', 'с', 'т', 'ќ', 'ф', 'х', 'ц', 'ч', 'џ', 'ш'],
            patterns: ['.бе2з', '.ра2з', '.по2д', '.на2д', '.пре2д'],
        );
    }

    /**
     * Romanian, after the orthographic dictionary of the Romanian Academy: one consonant between vowels goes to the
     * next line; of two, the second, except a stop or `f`, `h`, `v` followed by `l` or `r`, which go together (`a-cru`);
     * of three or more, all but the first, except the groups `lpt`, `mpt`, `mpț`, `ncș`, `nct`, `ncț`, `ndv`, `rct`,
     * `rtf` and `stm`, broken after their second. `ch` and `gh` are single consonants. Vowels side by side are not broken
     * here, diphthongs and hiatus being written alike; a final `i` after a consonant is not a syllable (`lupi`).
     */
    private static function romanian(): self
    {
        $onsets = [];

        foreach (['b', 'c', 'd', 'f', 'g', 'h', 'p', 't', 'v', 'ch', 'gh'] as $first) {
            $onsets[] = $first.'l';
            $onsets[] = $first.'r';
        }

        return new self(
            tag: 'ro',
            split: Split::AfterFirst,
            vowels: ['a', 'ă', 'â', 'e', 'i', 'î', 'o', 'u'],
            consonants: ['b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'q', 'r', 's', 'ș', 'ş', 't', 'ț', 'ţ', 'v', 'w', 'x', 'z'],
            units: ['ch', 'gh'],
            onsets: $onsets,
            silentEndings: ['i'],
            patterns: ['l2p1t', 'm2p1t', 'm2p1ț', 'm2p1ţ', 'n2c1ș', 'n2c1ş', 'n2c1t', 'n2c1ț', 'n2c1ţ', 'n2d1v', 'r2c1t', 'r2t1f', 's2t1m'],
        );
    }
}
