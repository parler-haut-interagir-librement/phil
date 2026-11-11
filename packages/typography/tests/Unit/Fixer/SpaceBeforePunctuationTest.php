<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\FrenchSpacing;
use Phil\Typography\Fixer\SpaceBeforePunctuation;
use Phil\Typography\Locale;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpaceBeforePunctuation::class)]
#[CoversClass(FrenchSpacing::class)]
final class SpaceBeforePunctuationTest extends TestCase
{
    private const string NB = "\u{A0}";

    private const string NNB = "\u{202F}";

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cases(): iterable
    {
        yield 'high punctuation, typed without space' => ['Quoi! Vraiment? Oui; non', 'Quoi'.self::NNB.'! Vraiment'.self::NNB.'? Oui'.self::NNB.'; non', 'Quoi! Vraiment? Oui; non'];
        yield 'high punctuation, typed with spaces' => ["Quoi ! Vraiment\u{A0}? Oui\u{2009}; non\u{202F}!", 'Quoi'.self::NNB.'! Vraiment'.self::NNB.'? Oui'.self::NNB.'; non'.self::NNB.'!', 'Quoi! Vraiment? Oui; non!'];
        yield 'two marks take one space' => ['Quoi ?! Non !!', 'Quoi'.self::NNB.'?! Non'.self::NNB.'!!', 'Quoi?! Non!!'];
        yield 'before closing marks' => ['(oui!) «non?» "a!" “b?” ‘c;’ d!… e?. f!, g?:', '(oui'.self::NNB.'!) «'.self::NB.'non'.self::NNB.'?'.self::NB.'» "a'.self::NNB.'!" “b'.self::NNB.'?” ‘c'.self::NNB.';’ d'.self::NNB.'!… e'.self::NNB.'?. f'.self::NNB.'!, g'.self::NNB.'?:', '(oui!) «'.self::NB.'non?'.self::NB.'» "a!" “b?” ‘c;’ d!… e?. f!, g?:'];
        yield 'not inside a word' => ['Yahoo!Japan a?b c;d', 'Yahoo!Japan a?b c;d', 'Yahoo!Japan a?b c;d'];
        yield 'not after an opening mark' => ['(! [? {; “! ‘? „; ‹:', '(! [? {; “! ‘? „; ‹:', '(! [? {; “! ‘? „; ‹:'];
        yield 'colon' => ['Note: voici. Ainsi :', 'Note'.self::NB.': voici. Ainsi'.self::NB.':', 'Note'.self::NB.': voici. Ainsi'.self::NB.':'];
        yield 'colon before a mark' => ['a:) b:» c:"', 'a'.self::NB.':) b'.self::NB.':'.self::NB.'» c'.self::NB.':"', 'a'.self::NB.':) b'.self::NB.':'.self::NB.'» c'.self::NB.':"'];
        yield 'colon in times and addresses' => ['12:30 https://example.com a:b', '12:30 https://example.com a:b', '12:30 https://example.com a:b'];
        yield 'guillemets' => ['«mot» « mot » «'.self::NNB.'mot'.self::NNB.'»', '«'.self::NB.'mot'.self::NB.'» «'.self::NB.'mot'.self::NB.'» «'.self::NB.'mot'.self::NB.'»', '«'.self::NB.'mot'.self::NB.'» «'.self::NB.'mot'.self::NB.'» «'.self::NB.'mot'.self::NB.'»'];
        yield 'empty guillemets' => ['«»', '«'.self::NB.'»', '«'.self::NB.'»'];
        yield 'lone guillemets at the edges' => ['« ', '« ', '« '];
        yield 'closing guillemet alone' => [' »', ' »', ' »'];
    }

    #[DataProvider('cases')]
    public function testTheSpacing(string $text, string $france, string $canada): void
    {
        self::assertSame([$france], new SpaceBeforePunctuation()->fix(Run::of($text))->texts);
        self::assertSame([$france], new SpaceBeforePunctuation(FrenchSpacing::France)->fix(Run::of($text))->texts);
        self::assertSame([$canada], new SpaceBeforePunctuation(FrenchSpacing::Canada)->fix(Run::of($text))->texts);
    }

    public function testTheSpacingSeesTheNeighbouringParts(): void
    {
        self::assertSame(['mot'.self::NNB, '!'], new SpaceBeforePunctuation()->fix(new Run(['mot', '!']))->texts);
        self::assertSame(['mot'.self::NNB, '!'], new SpaceBeforePunctuation()->fix(new Run(['mot ', '!']))->texts);
        self::assertSame(['«'.self::NB, 'mot'.self::NB, '»'], new SpaceBeforePunctuation()->fix(new Run(['«', 'mot', '»']))->texts);
    }

    public function testTheRulesOfALocale(): void
    {
        self::assertSame(FrenchSpacing::Canada, FrenchSpacing::for(Locale::parse('fr_CA')));
        self::assertSame(FrenchSpacing::France, FrenchSpacing::for(Locale::parse('fr')));
        self::assertSame(FrenchSpacing::France, FrenchSpacing::for(Locale::parse('fr_BE')));
        self::assertSame(FrenchSpacing::France, FrenchSpacing::for(Locale::parse('fr_CH')));
    }
}
