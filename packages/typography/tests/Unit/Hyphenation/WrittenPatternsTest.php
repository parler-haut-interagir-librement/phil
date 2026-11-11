<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Hyphenation;

use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Tests\Generator\PatternGenerator;
use Phil\Typography\Tests\Generator\SyllableRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The patterns written for this library from the syllabification rules of each language: each word of each list breaks
 * exactly where the rules of its language allow — and nowhere else —, never closer to an end than the language's
 * minimum lengths, and the shipped files are those the rules give.
 */
#[CoversClass(Hyphenator::class)]
#[CoversClass(Dictionary::class)]
final class WrittenPatternsTest extends TestCase
{
    /**
     * Each language, to its words written with their breaks; a word written without one is not broken.
     */
    private const array WORDS = [
        'cs' => [
            'vo-da', 'kni-ha', 'mat-ka', 'ses-tra', 'ok-no', 'měs-to', 'ško-la', 'češ-ti-na', 'ze-le-ný', 'ka-len-dář',
            'po-čí-tač', 'hud-ba', 'chod-ba', 'slov-ník', 'pon-dě-lí', 'stře-da', 'čtvr-tek', 'pá-tek', 'so-bo-ta',
            'ne-dě-le', 'vl-na', 'Br-no', 'srd-ce', 'kr-tek', 'zmrz-li-na', 'rozum', 'roz-bít', 'bez-pe-čí', 'pod-rob-ný',
            'obrá-zek', 'za-hra-da', 'stu-dent', 'te-le-fon', 'pi-vo', 'koč-ka', 'mlé-ko', 'chléb', 'chrám', 'ochra-na',
            'ne-moc-ni-ce', 'uni-ver-zi-ta', 'me-tro', 'Vl-ta-va', 'Pl-zeň', 'tr-va-lý', 'do-brý', 'ves-ni-ce', 'člo-věk',
            'ja-zyk', 'vzkvé-tat', 'džun-gle', 'podat',
        ],
        'hu' => [
            'temp-lom', 'prog-ram', 'ke-rék', 'asz-tal', 'ab-lak', 'ma-gyar', 'Bu-da-pest', 'könyv', 'is-ko-la', 'ta-nár',
            'egészség', 'sze-re-tet', 'ba-rát-ság', 'gye-rek', 'ku-tya', 'ma-dár', 'vi-rág', 'or-szág', 'vá-ros',
            'csa-lád', 'fiú', 'le-ány', 'nyelv', 'sza-bad-ság', 'asszony', 'hosszú', 'meggy', 'al-ma-fa', 'ke-nyér',
            'csü-tör-tök', 'pén-tek', 'szom-bat', 'va-sár-nap', 'hét-fő', 'szer-da', 'ün-nep', 'le-vél', 'új-ság',
            'ta-vasz', 'aj-tó', 'au-tó', 'ta-xi', 'ki-a-dás', 'be-áll', 'megold', 'felad', 'szét-tép', 'szo-ba', 'ut-ca',
        ],
        'hy' => [
            'Հա-յաս-տան', 'Երևան', 'գիրք', 'դպրոց', 'ու-սու-ցիչ', 'աշա-կերտ', 'հա-մալ-սա-րան', 'գրա-կա-նու-թյուն',
            'լե-զու', 'մայ-րիկ', 'հայ-րիկ', 'եղ-բայր', 'քույր', 'ըն-կեր', 'քա-ղաք', 'ծա-ղիկ', 'լու-սին', 'սե-ղան',
            'աթոռ', 'պա-տու-հան', 'գրիչ', 'մա-տիտ', 'հա-մա-կար-գիչ', 'հե-ռա-խոս', 'եր-կիր', 'աշ-խարհ',
            'խա-ղա-ղու-թյուն', 'կյանք', 'տղա-մարդ', 'երե-խա', 'աղ-ջիկ', 'գի-տու-թյուն', 'պատ-մու-թյուն', 'կար-միր',
            'կա-նաչ', 'սպի-տակ', 'ամառ', 'գա-րուն', 'շա-բաթ', 'կի-րա-կի', 'ժա-մա-նակ', 'շնոր-հա-կա-լու-թյուն',
            'ժո-ղո-վուրդ', 'մայ-րա-քա-ղաք', 'պա-տաս-խան',
        ],
        'id' => [
            'ba-ha-sa', 'In-do-ne-sia', 'ma-kan', 'mi-num', 'ru-mah', 'se-ko-lah', 'in-stru-men', 'ul-tra', 'in-fra',
            'bang-sa', 'tung-gal', 'nya-nyi', 'ba-ngun', 'akhir', 'makh-luk', 'ma-sya-ra-kat', 'sya-rat', 'pan-tai',
            'ha-ri-mau', 'sa-at', 'ma-af', 'bu-ah', 'main', 'ker-ja', 'be-la-jar', 'pen-di-di-kan', 'pe-me-rin-tah',
            'ke-bu-da-ya-an', 'per-pus-ta-ka-an', 'man-di', 'som-bong', 'Ap-ril', 'kan-tor', 'ne-ga-ra', 'sa-yang',
            'ke-lu-ar-ga', 'se-pe-da', 'mo-bil', 'se-la-mat', 'te-ri-ma', 'ek-stra', 'ko-or-di-na-si', 'bi-a-sa',
            'bang-krut', 'kon-tras', 'teks', 'korps',
        ],
        'lv' => [
            'mā-ja', 'sko-la', 'grā-ma-ta', 'va-lo-da', 'lat-vie-šu', 'Lat-vi-ja', 'pil-sē-ta', 'sko-lo-tājs', 'stu-dents',
            'uni-ver-si-tā-te', 'darbs', 'mā-cī-ties', 'strā-dāt', 'ru-nāt', 'raks-tīt', 'la-sīt', 'ģi-me-ne', 'brā-lis',
            'draugs', 'mī-les-tī-ba', 'sau-le', 'mē-ness', 'zvaigz-ne', 'ūdens', 'mai-ze', 'piens', 'ga-ļa', 'pirm-die-na',
            'otr-die-na', 'treš-die-na', 'ce-turt-die-na', 'piekt-die-na', 'svēt-die-na', 'zie-ma', 'pa-va-sa-ris',
            'va-sa-ra', 'ru-dens', 'dzies-ma', 'me-dī-bas', 'aiziet', 'pār-nākt', 'lab-dien', 'pal-dies', 'da-tors',
            'te-le-fons',
        ],
        'mk' => [
            'Ма-ке-до-ни-ја', 'Скоп-је', 'ја-зик', 'мај-ка', 'тат-ко', 'сес-тра', 'брат', 'ку-ќа', 'учи-лиш-те',
            'учи-тел', 'кни-га', 'во-да', 'мле-ко', 'сон-це', 'ме-се-чи-на', 'ѕвез-да', 'по-не-дел-ник', 'втор-ник',
            'сре-да', 'чет-врток', 'пе-ток', 'са-бо-та', 'не-де-ла', 'зи-ма', 'про-лет', 'ле-то', 'есен', 'при-ја-тел',
            'љу-бов', 'ра-бо-та', 'се-ло', 'пла-ни-на', 'езе-ро', 'ре-ка', 'срце', 'безо-пас-но', 'разум', 'над-вор',
            'прет-се-да-тел', 'ком-пју-тер', 'те-ле-фон', 'уни-вер-зи-тет', 'сту-дент',
        ],
        'ro' => [
            'Ro-mâ-nia', 'lim-ba', 'car-te', 'ca-să', 'școa-lă', 'co-pil', 'fru-mos', 'ta-blou', 'acru', 'co-dru', 'ne-gru',
            'su-plu', 'as-tru', 'as-pru', 'ochi', 'unchi', 'lupi', 'porci', 'membri', 'sculp-tor', 'punc-tual', 'func-ție',
            'arc-tic', 'jert-fă', 'ast-mă', 'sand-viș', 'exa-men', 'fe-reas-tră', 'mun-te-le', 'pă-du-re', 'prie-ten',
            'fa-mi-lie', 'dra-gos-te', 'uni-ver-si-ta-te', 'luni', 'marți', 'mier-curi', 'vi-neri', 'sâm-bă-tă',
            'du-mi-ni-că', 'pri-mă-va-ră', 'toam-nă', 'iar-nă', 'fra-te-le', 'oraș',
        ],
    ];

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function languages(): iterable
    {
        foreach (self::WORDS as $tag => $words) {
            yield $tag => [$tag, $words];
        }
    }

    /**
     * @param list<string> $words
     */
    #[DataProvider('languages')]
    public function testEachWordBreaksWhereItsLanguageAllows(string $tag, array $words): void
    {
        $hyphenator = Hyphenator::for($tag, new Dictionary(null, Dictionary::EXPERIMENTAL));
        [, $left, $right] = Dictionary::LANGUAGES[$tag];

        self::assertGreaterThanOrEqual(40, \count($words));

        foreach ($words as $expected) {
            $word = str_replace('-', '', $expected);
            $breaks = $hyphenator->breaks($word);

            self::assertSame($expected, $hyphenator->hyphenate($word, '-'), $tag);

            foreach ($breaks as $break) {
                self::assertGreaterThanOrEqual($left, $break, $word);
                self::assertLessThanOrEqual(mb_strlen($word) - $right, $break, $word);
            }
        }
    }

    public function testTheShippedFilesAreThoseTheRulesGive(): void
    {
        $rules = SyllableRules::all();

        self::assertSame(array_keys(self::WORDS), array_keys($rules));

        foreach ($rules as $tag => $language) {
            self::assertStringEqualsFile(\dirname(__DIR__, 3).'/data/hyphenation/'.$tag.'.pat.txt', PatternGenerator::generate($language), $tag);
            self::assertSame([2, 2, 'MIT, written for this library'], \array_slice(Dictionary::LANGUAGES[$tag], 1), $tag);
            self::assertFileDoesNotExist(\dirname(__DIR__, 3).'/data/hyphenation/'.$tag.'.hyp.txt', $tag);
        }
    }
}
