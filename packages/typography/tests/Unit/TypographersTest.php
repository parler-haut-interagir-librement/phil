<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\UnknownFixer;
use Phil\Typography\Html\Blocks;
use Phil\Typography\Html\Serializer;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Locale;
use Phil\Typography\Typographer;
use Phil\Typography\Typographers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Typographers::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Serializer::class)]
final class TypographersTest extends TestCase
{
    public function testOneTypographerPerLocale(): void
    {
        $typographers = new Typographers();

        self::assertSame($typographers->for(), $typographers->for('en'));
        self::assertSame($typographers->for('fr-ca'), $typographers->for(Locale::parse('fr_CA')));
        self::assertNotSame($typographers->for('fr'), $typographers->for('fr_CA'));
        self::assertSame("<p>“Hy\u{AD}phen\u{AD}ation”—yes</p>", $typographers->for()->fix('<p>"Hyphenation" - yes</p>'));
        self::assertSame("<p>«&nbsp;Oui&nbsp;»\u{202F}!</p>", $typographers->for('fr')->fix('<p>"Oui" !</p>'));
        self::assertSame('<p>«&nbsp;Oui&nbsp;»!</p>', $typographers->for('fr_CA')->fix('<p>"Oui" !</p>'));
    }

    public function testTheConfiguration(): void
    {
        $typographers = new Typographers('fr_CA', ['fr' => ['ellipsis'], 'de_CH' => ['smart_quotes']], ['p'], false, new Dictionary());

        self::assertSame('<p>"a"...</p>"b"…', $typographers->for()->fix('<p>"a"...</p>"b"...'));
        self::assertSame('"a"…', $typographers->for('fr_BE')->fix('"a"...'));
        self::assertSame('«a»...', $typographers->for('de_CH')->fix('"a"...'));
        self::assertSame('„Silbentrennungsalgorithmus“…', $typographers->for('de')->fix('"Silbentrennungsalgorithmus"...'));
    }

    public function testTheDictionaryGivenIsUsed(): void
    {
        $directory = sys_get_temp_dir().'/phil-typography-typographers-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/fr.pat.txt', '1a1 1b1');

        try {
            self::assertSame("ab\u{AD}a\u{AD}ba", new Typographers('fr', [], [], true, new Dictionary($directory))->for()->fixText('ababa'));
            self::assertSame("ab\u{AD}a\u{AD}ba", new Typographers('en', ['fr' => ['hyphen']], [], false, new Dictionary($directory))->for('fr')->fixText('ababa'));
        } finally {
            unlink($directory.'/fr.pat.txt');
            rmdir($directory);
        }
    }

    public function testAnUnknownFixerName(): void
    {
        $this->expectException(UnknownFixer::class);

        new Typographers('en', ['en' => ['kerning']])->for();
    }

    public function testAnInvalidDefault(): void
    {
        $this->expectException(InvalidLocale::class);

        new Typographers('not a locale');
    }

    public function testTheTypographersOfSixtyFourLocalesAreKeptThenBuiltAgain(): void
    {
        $typographers = new Typographers('en', [], Typographer::PROTECTED_TAGS, false);
        $kept = new ReflectionProperty(Typographers::class, 'typographers');
        $locales = array_map(static fn (int $number): string => \sprintf('en_%03d', $number), range(1, 64));

        foreach ($locales as $locale) {
            $typographers->for($locale);
        }

        self::assertSame($locales, array_keys((array) $kept->getValue($typographers)));

        $first = $typographers->for('en_001');
        self::assertSame($first, $typographers->for('en_001'));
        self::assertCount(64, (array) $kept->getValue($typographers));

        $typographers->for('en_065');
        self::assertSame(['en_065'], array_keys((array) $kept->getValue($typographers)));
        self::assertNotSame($first, $typographers->for('en_001'));
    }

    public function testEachPassageIsFixedInItsLanguage(): void
    {
        $typographers = new Typographers('fr', [], Typographer::PROTECTED_TAGS, false);

        self::assertSame('<p>a <span lang="fr-CA">b&nbsp;</span>: c</p>', $typographers->fixHtml('<p>a <span lang="fr-CA">b</span>: c</p>', 'fr_CA'), 'A passage in the language of the document is not one.');
        self::assertSame('<p>a <span lang="en">b</span>: c</p>', $typographers->fixHtml('<p>a <span lang="en">b</span>: c</p>'), 'The text before a passage is fixed apart from the text after it.');
        self::assertSame('<p>a <span lang="en">b</span> : c</p>', $typographers->fixHtml('<p>a <span lang="en">b</span> : c</p>'));

        self::assertSame(
            '<p>Il dit «&nbsp;bonjour&nbsp;»&nbsp;: <span lang="en">“hello” : <b>“there”</b></span> et <span lang="xx-Bad_">«&nbsp;x&nbsp;»</span> <span lang="fr">«&nbsp;oui&nbsp;»</span> <code lang="en">"c"</code></p><blockquote lang="en"><p>“quote”</p><p lang="fr">«&nbsp;non&nbsp;»</p></blockquote>',
            $typographers->fixHtml('<p>Il dit "bonjour" : <span lang="en">"hello" : <b>"there"</b></span> et <span lang="xx-Bad_">"x"</span> <span lang="fr">"oui"</span> <code lang="en">"c"</code></p><blockquote lang="en"><p>"quote"</p><p lang="fr">"non"</p></blockquote>'),
        );
        self::assertSame('<p lang="fr">«&nbsp;a&nbsp;»</p>', $typographers->fixHtml('<p lang="fr">"a"</p>', 'en'));
        self::assertSame('<abbr title="a&gt;b">b</abbr>', $typographers->fixHtml('<abbr title="a>b">b</abbr>', 'en'));
        self::assertSame('<p>“a”</p>', $typographers->fixHtml('<p>"a"</p>', Locale::parse('en_CA')));
    }
}
