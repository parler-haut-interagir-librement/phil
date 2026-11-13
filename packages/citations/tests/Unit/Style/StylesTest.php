<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Style;

use Phil\Citations\Citation;
use Phil\Citations\CitationProcessor;
use Phil\Citations\Cite;
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\Locator;
use Phil\Citations\RichText;
use Phil\Citations\Style\AbstractStyle;
use Phil\Citations\Style\AuthorDate;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;
use Phil\Citations\Style\Notes;
use Phil\Citations\Style\Numeric;
use Phil\Citations\Terms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractStyle::class)]
#[CoversClass(AuthorDate::class)]
#[CoversClass(Notes::class)]
#[CoversClass(Numeric::class)]
#[CoversClass(CitationProcessor::class)]
#[CoversClass(Entry::class)]
#[CoversClass(CitedItem::class)]
final class StylesTest extends TestCase
{
    /**
     * @return list<Citation>
     */
    public static function citations(): array
    {
        return [
            new Citation(new Cite('article', '47'), new Cite('book', '12-14')),
            Citation::of('chapter', 'edited', 'web'),
            Citation::of('anon', 'news', 'alpha', 'beta'),
            new Citation(new Cite('thesis', '3', Locator::Chapter, 'see', 'for more'), new Cite('who')),
            Citation::of('bce', 'four', 'nodate1', 'nodate2'),
            Citation::of('article', 'book', 'chapter', 'web', 'anon', 'alpha'),
            Citation::of('alpha', 'alpha'),
        ];
    }

    /**
     * @return iterable<string, array{CitationStyle, Terms, list<string>, list<string>}>
     */
    public static function styles(): iterable
    {
        yield 'AuthorDate in English' => [new AuthorDate(), Terms::english(), [
            '(Smith et al., 2020, p. 47; Knuth, 1984, pp. 12–14)',
            '(van Beethoven, 2019; Doe, 2019; Smith, 2020)',
            '(Annual Report: Figures, n.d.; A1 et al., 2021; Lee, 2018a, 2018b)',
            '(see Roy & Côté, 2015, chap. 3 for more; World Health Organization, forthcoming)',
            '(Plato, 375 BCE; One et al., 2022; Zed, n.d.-a, n.d.-b)',
            '(Smith et al., 2020; Knuth, 1984; van Beethoven, 2019; Smith, 2020; Annual Report: Figures, n.d.; Lee, 2018a)',
            '(Lee, 2018a)',
        ], [
            'A1, A2, A3, A4, A5, A6, A7, A8, A9, A10, A11, A12, A13, A14, A15, A16, A17, A18, A19, . . . A22. (2021, March 4). Why? Daily.',
            'Annual Report: Figures (No. 12). (n.d.). Agency.',
            'Doe, J. (Ed.). (2019). Readings. Pub.',
            'Knuth, D. E. (1984). The TeXbook: A Manual (2nd ed.). Addison-Wesley.',
            'Lee, A. (2018a). Alpha.',
            'Lee, A. (2018b). Beta.',
            'One, A., Two, B., Three, C., & Four, D. (2022, July). Four Authors. Mag.',
            'Plato. (375 BCE). Republic.',
            'Roy, M., & Côté, L. (2015). A Thesis (Vol. 2) [PhD thesis]. University.',
            'Smith, J. A. (2020, May 1). A Page. Example Site. https://example.org',
            'Smith, J. A., Dupont, J.-P., & World Health Organization. (2020). On Testing Café. Journal of Tests, 12(3), 45–67. https://doi.org/10.1000/xyz',
            'van Beethoven, L. (2019). Symphonies. In J. Doe & R. Roe (Eds.), Collected Works (pp. 1–10). Pub. https://example.org/c',
            'World Health Organization. (forthcoming). Health.',
            'Zed. (n.d.-a). First Undated.',
            'Zed. (n.d.-b). Second Undated.',
        ]];
        yield 'Notes in English' => [new Notes(), Terms::english(), [
            'John A. Smith, Jean-Paul Dupont, and World Health Organization, “On Testing Café,” Journal of Tests 12, no. 3 (May 2020): 47; Donald E. Knuth, The TeXbook: A Manual, 2nd ed. (Reading, MA: Addison-Wesley, 1984), 12–14.',
            'Ludwig van Beethoven, “Symphonies,” in Collected Works, ed. Jane Doe and Richard Roe (Paris: Pub, 2019); Jane Doe, ed., Readings (Pub, 2019); John A. Smith, “A Page,” Example Site, May 1, 2020, https://example.org.',
            'Annual Report: Figures (Agency); A1 et al., “Why?” Daily (March 4, 2021); Ann Lee, Alpha (2018); Ann Lee, Beta (2018).',
            'see Marie Roy and Luc Côté, A Thesis (University, 2015), chap. 3 for more; World Health Organization, Health (forthcoming).',
            'Plato, Republic (375 BCE); A. One et al., “Four Authors,” Mag (July 2022); Zed, First Undated; Zed, Second Undated.',
            'Smith, Dupont, and World Health Organization, “On Testing Café”; Knuth, The TeXbook; van Beethoven, “Symphonies”; Smith, “A Page”; Annual Report; Lee, Alpha.',
            'Lee, Alpha.',
        ], [
            'A1, A2, A3, A4, A5, A6, A7, et al. “Why?” Daily (March 4, 2021).',
            'Annual Report: Figures. Agency.',
            'Doe, Jane, ed. Readings. Pub, 2019.',
            'Knuth, Donald E. The TeXbook: A Manual. 2nd ed. Reading, MA: Addison-Wesley, 1984.',
            'Lee, Ann. Alpha. 2018.',
            'Lee, Ann. Beta. 2018.',
            'One, A., B. Two, C. Three, and D. Four. “Four Authors.” Mag (July 2022).',
            'Plato. Republic. 375 BCE.',
            'Roy, Marie, and Luc Côté. A Thesis. University, 2015.',
            'Smith, John A. “A Page.” Example Site, May 1, 2020. https://example.org.',
            'Smith, John A., Jean-Paul Dupont, and World Health Organization. “On Testing Café.” Journal of Tests 12, no. 3 (May 2020): 45–67. https://doi.org/10.1000/xyz.',
            'van Beethoven, Ludwig. “Symphonies.” In Collected Works, edited by Jane Doe and Richard Roe, 1–10. Paris: Pub, 2019. https://example.org/c.',
            'World Health Organization. Health. forthcoming.',
            'Zed. First Undated.',
            'Zed. Second Undated.',
        ]];
        yield 'Numeric in English' => [new Numeric(), Terms::english(), [
            '[1, p. 47], [2, pp. 12–14]',
            '[3]–[5]',
            '[6]–[9]',
            'see [10, chap. 3] for more, [11]',
            '[12]–[15]',
            '[1]–[3], [5], [6], [8]',
            '[8]',
        ], [
            '[1] J. A. Smith, J.-P. Dupont, and World Health Organization, “On Testing Café,” Journal of Tests, vol. 12, no. 3, pp. 45–67, May 2020, doi: 10.1000/xyz.',
            '[2] D. E. Knuth, The TeXbook: A Manual, 2nd ed. Reading, MA: Addison-Wesley, 1984.',
            '[3] L. van Beethoven, “Symphonies,” in Collected Works, J. Doe and R. Roe, Eds. Paris: Pub, 2019, pp. 1–10.',
            '[4] J. Doe, Ed., Readings. Pub, 2019.',
            '[5] J. A. Smith, “A Page,” Example Site. Accessed: January 2, 2021. [Online]. Available: https://example.org',
            '[6] Annual Report: Figures. Agency.',
            '[7] A1 et al., “Why?” Daily, Mar. 2021.',
            '[8] A. Lee, Alpha, 2018.',
            '[9] A. Lee, Beta, 2018.',
            '[10] M. Roy and L. Côté, A Thesis. University, 2015.',
            '[11] World Health Organization, Health, forthcoming.',
            '[12] Plato, Republic, 375 BCE.',
            '[13] A. One, B. Two, C. Three, and D. Four, “Four Authors,” Mag, July 2022.',
            '[14] Zed, First Undated.',
            '[15] Zed, Second Undated.',
        ]];
        yield 'AuthorDate in French' => [new AuthorDate(), Terms::french(), [
            '(Smith et al., 2020, p. 47; Knuth, 1984, p. 12–14)',
            '(van Beethoven, 2019; Doe, 2019; Smith, 2020)',
            '(Annual Report: Figures, s. d.; A1 et al., 2021; Lee, 2018a, 2018b)',
            '(see Roy et Côté, 2015, chap. 3 for more; World Health Organization, forthcoming)',
            '(Plato, 375 av. J.-C.; One et al., 2022; Zed, s. d.-a, s. d.-b)',
            '(Smith et al., 2020; Knuth, 1984; van Beethoven, 2019; Smith, 2020; Annual Report: Figures, s. d.; Lee, 2018a)',
            '(Lee, 2018a)',
        ], [
            'A1, A2, A3, A4, A5, A6, A7, A8, A9, A10, A11, A12, A13, A14, A15, A16, A17, A18, A19, . . . A22. (2021, 4 mars). Why? Daily.',
            'Annual Report: Figures (No. 12). (s. d.). Agency.',
            'Doe, J. (dir.). (2019). Readings. Pub.',
            'Knuth, D. E. (1984). The TeXbook: A Manual (2e éd.). Addison-Wesley.',
            'Lee, A. (2018a). Alpha.',
            'Lee, A. (2018b). Beta.',
            'One, A., Two, B., Three, C. et Four, D. (2022, juillet). Four Authors. Mag.',
            'Plato. (375 av. J.-C.). Republic.',
            'Roy, M. et Côté, L. (2015). A Thesis (vol. 2) [PhD thesis]. University.',
            'Smith, J. A. (2020, 1 mai). A Page. Example Site. https://example.org',
            'Smith, J. A., Dupont, J.-P. et World Health Organization. (2020). On Testing Café. Journal of Tests, 12(3), 45–67. https://doi.org/10.1000/xyz',
            'van Beethoven, L. (2019). Symphonies. Dans J. Doe et R. Roe (dir.), Collected Works (p. 1–10). Pub. https://example.org/c',
            'World Health Organization. (forthcoming). Health.',
            'Zed. (s. d.-a). First Undated.',
            'Zed. (s. d.-b). Second Undated.',
        ]];
        yield 'Notes in French' => [new Notes(), Terms::french(), [
            "John A. Smith, Jean-Paul Dupont et World Health Organization, «\u{a0}On Testing Café\u{a0}», Journal of Tests 12, no 3 (mai 2020)\u{a0}: 47; Donald E. Knuth, The TeXbook: A Manual, 2e éd. (Reading, MA\u{a0}: Addison-Wesley, 1984), 12–14.",
            "Ludwig van Beethoven, «\u{a0}Symphonies\u{a0}», dans Collected Works, dir. Jane Doe et Richard Roe (Paris\u{a0}: Pub, 2019); Jane Doe, dir., Readings (Pub, 2019); John A. Smith, «\u{a0}A Page\u{a0}», Example Site, 1er mai 2020, https://example.org.",
            "Annual Report: Figures (Agency); A1 et al., «\u{a0}Why?\u{a0}» Daily (4 mars 2021); Ann Lee, Alpha (2018); Ann Lee, Beta (2018).",
            'see Marie Roy et Luc Côté, A Thesis (University, 2015), chap. 3 for more; World Health Organization, Health (forthcoming).',
            "Plato, Republic (375 av. J.-C.); A. One et al., «\u{a0}Four Authors\u{a0}», Mag (juillet 2022); Zed, First Undated; Zed, Second Undated.",
            "Smith, Dupont et World Health Organization, «\u{a0}On Testing Café\u{a0}»; Knuth, The TeXbook; van Beethoven, «\u{a0}Symphonies\u{a0}»; Smith, «\u{a0}A Page\u{a0}»; Annual Report; Lee, Alpha.",
            'Lee, Alpha.',
        ], [
            "A1, A2, A3, A4, A5, A6, A7, et al. «\u{a0}Why?\u{a0}» Daily (4 mars 2021).",
            'Annual Report: Figures. Agency.',
            'Doe, Jane, dir. Readings. Pub, 2019.',
            "Knuth, Donald E. The TeXbook: A Manual. 2e éd. Reading, MA\u{a0}: Addison-Wesley, 1984.",
            'Lee, Ann. Alpha. 2018.',
            'Lee, Ann. Beta. 2018.',
            "One, A., B. Two, C. Three et D. Four. «\u{a0}Four Authors\u{a0}». Mag (juillet 2022).",
            'Plato. Republic. 375 av. J.-C.',
            'Roy, Marie et Luc Côté. A Thesis. University, 2015.',
            "Smith, John A. «\u{a0}A Page\u{a0}». Example Site, 1er mai 2020. https://example.org.",
            "Smith, John A., Jean-Paul Dupont et World Health Organization. «\u{a0}On Testing Café\u{a0}». Journal of Tests 12, no 3 (mai 2020)\u{a0}: 45–67. https://doi.org/10.1000/xyz.",
            "van Beethoven, Ludwig. «\u{a0}Symphonies\u{a0}». Dans Collected Works, sous la direction de Jane Doe et Richard Roe, 1–10. Paris\u{a0}: Pub, 2019. https://example.org/c.",
            'World Health Organization. Health. forthcoming.',
            'Zed. First Undated.',
            'Zed. Second Undated.',
        ]];
        yield 'Numeric in French' => [new Numeric(), Terms::french(), [
            '[1, p. 47], [2, p. 12–14]',
            '[3]–[5]',
            '[6]–[9]',
            'see [10, chap. 3] for more, [11]',
            '[12]–[15]',
            '[1]–[3], [5], [6], [8]',
            '[8]',
        ], [
            "[1] J. A. Smith, J.-P. Dupont et World Health Organization, «\u{a0}On Testing Café\u{a0}», Journal of Tests, vol. 12, no 3, p. 45–67, mai 2020, doi\u{a0}: 10.1000/xyz.",
            "[2] D. E. Knuth, The TeXbook: A Manual, 2e éd. Reading, MA\u{a0}: Addison-Wesley, 1984.",
            "[3] L. van Beethoven, «\u{a0}Symphonies\u{a0}», dans Collected Works, J. Doe et R. Roe, dir. Paris\u{a0}: Pub, 2019, p. 1–10.",
            '[4] J. Doe, dir., Readings. Pub, 2019.',
            "[5] J. A. Smith, «\u{a0}A Page\u{a0}», Example Site. Consulté le\u{a0}: 2 janvier 2021. [En ligne]. Disponible\u{a0}: https://example.org",
            '[6] Annual Report: Figures. Agency.',
            "[7] A1 et al., «\u{a0}Why?\u{a0}» Daily, mars 2021.",
            '[8] A. Lee, Alpha, 2018.',
            '[9] A. Lee, Beta, 2018.',
            '[10] M. Roy et L. Côté, A Thesis. University, 2015.',
            '[11] World Health Organization, Health, forthcoming.',
            '[12] Plato, Republic, 375 av. J.-C.',
            "[13] A. One, B. Two, C. Three et D. Four, «\u{a0}Four Authors\u{a0}», Mag, juillet 2022.",
            '[14] Zed, First Undated.',
            '[15] Zed, Second Undated.',
        ]];
    }

    /**
     * @param list<string> $citations
     * @param list<string> $bibliography
     */
    #[DataProvider('styles')]
    public function testTheStyleWritesCitationsAndBibliography(CitationStyle $style, Terms $terms, array $citations, array $bibliography): void
    {
        $rendered = new CitationProcessor($style, $terms)->render(Fixtures::references(), self::citations());

        self::assertSame($citations, array_map(static fn (RichText $text): string => $text->toText(), $rendered->citations));
        self::assertSame($bibliography, array_map(static fn (RichText $text): string => $text->toText(), $rendered->bibliography));
    }
}
