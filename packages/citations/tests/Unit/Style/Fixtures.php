<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Style;

use Phil\Citations\CslJson;
use Phil\Citations\ReferenceList;
use Phil\Citations\Terms;

/**
 * The references the tests of the styles cite, one of each shape a style writes.
 */
final class Fixtures
{
    private const string JSON = <<<'JSON'
        [
            {"id": "article", "type": "article-journal", "title": "On Testing Café",
             "author": [{"family": "Smith", "given": "John A."}, {"family": "Dupont", "given": "Jean-Paul"}, {"literal": "World Health Organization"}],
             "container-title": "Journal of Tests", "volume": "12", "issue": "3", "page": "45-67", "DOI": "10.1000/xyz",
             "issued": {"date-parts": [[2020, 5]]}},
            {"id": "book", "type": "book", "title": "The TeXbook: A Manual", "author": [{"family": "Knuth", "given": "Donald E."}],
             "edition": "2", "publisher": "Addison-Wesley", "publisher-place": "Reading, MA", "issued": {"date-parts": [[1984]]}},
            {"id": "chapter", "type": "chapter", "title": "Symphonies", "author": [{"family": "Beethoven", "given": "Ludwig", "non-dropping-particle": "van"}],
             "editor": [{"family": "Doe", "given": "Jane"}, {"family": "Roe", "given": "Richard"}], "container-title": "Collected Works",
             "page": "1-10", "publisher": "Pub", "publisher-place": "Paris", "issued": {"date-parts": [[2019]]}, "URL": "https://example.org/c"},
            {"id": "edited", "type": "book", "title": "Readings", "editor": [{"family": "Doe", "given": "Jane"}], "publisher": "Pub",
             "issued": {"date-parts": [[2019]]}},
            {"id": "web", "type": "webpage", "title": "A Page", "author": [{"family": "Smith", "given": "John A."}],
             "container-title": "Example Site", "URL": "https://example.org", "issued": {"date-parts": [[2020, 5, 1]]},
             "accessed": {"date-parts": [[2021, 1, 2]]}},
            {"id": "anon", "type": "report", "title": "Annual Report: Figures", "number": "12", "publisher": "Agency"},
            {"id": "news", "type": "article-newspaper", "title": "Why?", "container-title": "Daily", "issued": {"date-parts": [[2021, 3, 4]]},
             "author": [{"family": "A1"}, {"family": "A2"}, {"family": "A3"}, {"family": "A4"}, {"family": "A5"}, {"family": "A6"},
                        {"family": "A7"}, {"family": "A8"}, {"family": "A9"}, {"family": "A10"}, {"family": "A11"}, {"family": "A12"},
                        {"family": "A13"}, {"family": "A14"}, {"family": "A15"}, {"family": "A16"}, {"family": "A17"}, {"family": "A18"},
                        {"family": "A19"}, {"family": "A20"}, {"family": "A21"}, {"family": "A22"}]},
            {"id": "alpha", "type": "book", "title": "Alpha", "author": [{"family": "Lee", "given": "Ann"}], "issued": {"date-parts": [[2018]]}},
            {"id": "beta", "type": "book", "title": "Beta", "author": [{"family": "Lee", "given": "Ann"}], "issued": {"date-parts": [[2018]]}},
            {"id": "thesis", "type": "thesis", "title": "A Thesis", "author": [{"family": "Roy", "given": "Marie"}, {"family": "Côté", "given": "Luc"}],
             "genre": "PhD thesis", "publisher": "University", "volume": "2", "issued": {"date-parts": [[2015]]}},
            {"id": "who", "type": "book", "title": "Health", "author": [{"literal": "World Health Organization"}], "issued": {"literal": "forthcoming"}},
            {"id": "bce", "type": "classic", "title": "Republic", "author": [{"family": "Plato"}], "issued": {"date-parts": [[-375]]}},
            {"id": "four", "type": "article-magazine", "title": "Four Authors", "container-title": "Mag", "issued": {"date-parts": [[2022, 7]]},
             "author": [{"family": "One", "given": "A."}, {"family": "Two", "given": "B."}, {"family": "Three", "given": "C."}, {"family": "Four", "given": "D."}]},
            {"id": "nodate1", "type": "document", "title": "First Undated", "author": [{"family": "Zed"}]},
            {"id": "nodate2", "type": "document", "title": "Second Undated", "author": [{"family": "Zed"}]}
        ]
        JSON;

    public static function references(): ReferenceList
    {
        return new ReferenceList(new CslJson()->decode(self::JSON));
    }

    /**
     * The English terms with other months or other ordinal suffixes.
     *
     * @param list<string>|null              $months
     * @param array<int|string, string>|null $ordinalSuffixes
     */
    public static function englishWith(?array $months = null, ?array $ordinalSuffixes = null): Terms
    {
        $english = Terms::english();

        return new Terms(
            $english->language,
            $english->and,
            $english->andSymbol,
            $english->serialComma,
            $english->etAl,
            $english->noDate,
            $english->editor,
            $english->editors,
            $english->editedBy,
            $english->translatedBy,
            $english->in,
            $english->edition,
            $english->volume,
            $english->issue,
            $english->pages,
            $english->accessed,
            $english->online,
            $english->available,
            $english->openQuote,
            $english->closeQuote,
            $english->punctuationInsideQuotes,
            $english->colon,
            $english->capitalizeAbbreviations,
            $english->beforeCommonEra,
            $months ?? $english->months,
            $english->locators,
            $ordinalSuffixes ?? $english->ordinalSuffixes,
        );
    }
}
