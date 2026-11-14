<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Adapter;

use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\CodeClasses;
use Phil\Markup\Adapter\Purifiers;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\MissingExtension;
use Phil\Markup\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The ammonia purifier where its extension is loaded, and the choice of a purifier everywhere. A line break tells
 * which purifier wrote an output: `<br>` for the extension, `<br />` for the sanitizer.
 */
#[CoversClass(AmmoniaPurifier::class)]
#[CoversClass(Purifiers::class)]
#[CoversClass(MissingExtension::class)]
#[CoversClass(SanitizerPurifier::class)]
#[CoversClass(CodeClasses::class)]
final class AmmoniaPurifierTest extends TestCase
{
    #[RequiresPhpExtension('ammonia')]
    public function testEachLevelKeepsWhatTheSanitizerKeeps(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0);

        self::assertSame('<p>a</p>', $purifier->purify('<p onclick="x" style="color:red">a<script>b</script><iframe>c</iframe><object>d</object></p>', Level::Forum));
        self::assertSame('<a>a</a><a href="/b">b</a><a href="mailto:a@b.ca">c</a><img src="/d.png"><img>', $purifier->purify('<a href="ftp://x">a</a><a href="/b">b</a><a href="mailto:a@b.ca">c</a><img src="/d.png"><img src="data:image/png;base64,AA==">', Level::Forum));
        self::assertSame('<del>a</del><sub>b</sub><sup>c</sup><code>d</code><u>e</u><s>f</s><em>g</em><i>h</i><b>i</b><br>', $purifier->purify('<del>a</del><sub>b</sub><sup>c</sup><code>d</code><u>e</u><s>f</s><em>g</em><i>h</i><b>i</b><br>', Level::Basic));
        self::assertSame('abcde', $purifier->purify('<p>a</p><ol><li>b</li></ol><pre>c</pre><kbd>x</kbd><blockquote><cite>d</cite></blockquote><h2>e</h2>', Level::Basic));
        self::assertSame('<p>a</p><ol><li>b</li></ol><pre>c</pre><hr><blockquote><cite>d</cite></blockquote>e', $purifier->purify('<p>a</p><ol><li>b</li></ol><pre>c</pre><hr><blockquote><cite>d</cite></blockquote><h2>e</h2>', Level::Forum));
        self::assertSame('<h2 id="e">e</h2><table><tbody><tr><td>x</td></tr></tbody></table>', $purifier->purify('<h2 id="e">e</h2><table><tr><td>x</td></tr></table><title>t</title>', Level::All));
        self::assertSame('<a href="https://e.x" title="t">a</a><br>', $purifier->purify('<a href="https://e.x" title="t" rel="x" id="i">a</a><br>', Level::Forum));
        self::assertSame('<a href="https://example.com/" rel="nofollow" target="_blank">a</a>', $purifier->purify('<a href="https://example.com/" rel="nofollow" target="_blank" referrerpolicy="unsafe-url">a</a><!-- c -->', Level::All));
        self::assertSame('<p>a<math display="block"><mi mathvariant="bold">x</mi><mfrac><mn>1</mn><mn>2</mn></mfrac></math></p>', $purifier->purify('<p>a<math display="block"><mi mathvariant="bold">x</mi><mfrac><mn>1</mn><mn>2</mn></mfrac></math></p>', Level::All));
        self::assertSame('<p>a</p>', $purifier->purify('<p>a<math display="block"><mi>x</mi></math></p>', Level::Forum));
    }

    /**
     * What the extension does not check is not kept: addresses other than `href` and `src`, styles.
     */
    #[RequiresPhpExtension('ammonia')]
    public function testOnlyCheckedAddressesAreKept(): void
    {
        self::assertSame(
            '<img src="mailto:a@b.ca"><blockquote>q</blockquote><video src="v.mp4"></video><p>p</p>',
            AmmoniaPurifier::defaultPolicies(minimumBytes: 0)->purify('<img src="mailto:a@b.ca" srcset="x.png 1x"><blockquote cite="https://e.x/">q</blockquote><video poster="p.png" src="v.mp4"></video><p style="color:red">p</p>', Level::All),
        );
    }

    #[RequiresPhpExtension('ammonia')]
    public function testCodeKeepsItsClasses(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0);
        $html = '<pre class="x"><code class="language-php  x language-c++"><span class="hl-keyword evil hl-attr-name">echo</span><span class="x">1</span></code></pre><p class="hl-keyword">a</p><code class="x">b</code>';

        self::assertSame('<pre><code class="language-php language-c++"><span class="hl-keyword hl-attr-name">echo</span><span>1</span></code></pre><p>a</p><code>b</code>', $purifier->purify($html, Level::All));
        self::assertSame('<pre><code class="language-php language-c++">echo1</code></pre><p>a</p><code>b</code>', $purifier->purify($html, Level::Forum));
        self::assertSame('<code class="language-php language-c++">echo1</code>a<code>b</code>', $purifier->purify($html, Level::Basic));
        self::assertSame('<code id="a" title="t" class="language-php">c</code><span lang="fr" class="hl-k">s</span><span>z</span><p>p</p>', $purifier->purify('<code id="a" title="t" class="language-php x">c</code><span lang="fr" class="hl-k y">s</span><span class="z">z</span><p class="language-x">p</p>', Level::All));
        self::assertSame('<b>a class="x"</b>', $purifier->purify('<b>a class="x"</b>', Level::Basic), 'Only the attribute of a tag is filtered.');
    }

    #[RequiresPhpExtension('ammonia')]
    public function testAShortHtmlGoesToTheSanitizer(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies();
        $long = str_repeat('a', AmmoniaPurifier::MINIMUM_BYTES - 4).'<br>';

        self::assertSame('a<br />', $purifier->purify('a<br>', Level::Basic));
        self::assertSame(substr($long, 1, -4).'<br />', $purifier->purify(substr($long, 1), Level::Basic));
        self::assertSame($long, $purifier->purify($long, Level::Basic));
        self::assertSame('a<br>', AmmoniaPurifier::defaultPolicies(minimumBytes: 5)->purify('a<br>', Level::Basic));
    }

    #[RequiresPhpExtension('ammonia')]
    public function testAnHtmlOfManyTagsGoesToTheSanitizer(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0);
        $tags = str_repeat('<br>', AmmoniaPurifier::MAXIMUM_TAGS);

        self::assertSame($tags, $purifier->purify($tags, Level::Basic));
        self::assertSame(str_repeat('<br />', AmmoniaPurifier::MAXIMUM_TAGS + 1), $purifier->purify($tags.'<br>', Level::Basic));
    }

    #[RequiresPhpExtension('ammonia')]
    public function testAnHtmlWithANoscriptGoesToTheSanitizer(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0);

        self::assertSame('a<br />', $purifier->purify('a<br><NoScript>b</noscript>', Level::Basic));
        self::assertSame('a<br>', $purifier->purify('a<br><script>b</script>', Level::Basic));
    }

    #[RequiresPhpExtension('ammonia')]
    public function testAnHtmlThatIsNotUtf8GoesToTheSanitizer(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0);

        self::assertSame('', $purifier->purify("<b>a\xff\xfeb</b><br>", Level::Basic));
        self::assertSame('<b>é</b><br>', $purifier->purify('<b>é</b><br>', Level::Basic));
    }

    /**
     * Images limited to some hosts are checked by the sanitizer, at the levels that keep images.
     */
    #[RequiresPhpExtension('ammonia')]
    public function testImagesOfLimitedHostsGoToTheSanitizer(): void
    {
        $html = '<img src="https://tracker.example/p.gif"><img src="https://cdn.example.com/a.png"><img src="/c.png"><br>';
        $restricted = AmmoniaPurifier::defaultPolicies(['cdn.example.com'], 0);

        foreach ([Level::Forum, Level::All] as $level) {
            self::assertSame('<img /><img src="https://cdn.example.com/a.png" /><img src="/c.png" /><br />', $restricted->purify($html, $level));
            self::assertSame('<img src="https://tracker.example/p.gif"><img src="https://cdn.example.com/a.png"><img src="/c.png"><br>', AmmoniaPurifier::defaultPolicies(minimumBytes: 0)->purify($html, $level));
            self::assertSame('<p>a</p><br>', $restricted->purify('<p>a</p><br>', $level));
        }

        self::assertSame('<br>', $restricted->purify('<img SRC="https://tracker.example/p.gif"><br>', Level::Basic));
        self::assertSame('<img /><br />', $restricted->purify('<img SRC="https://tracker.example/p.gif"><br>', Level::Forum));
    }

    /**
     * `CodeClasses::keep()` is called by `AmmoniaPurifier`, from outside its class, so it is public, and it needs no
     * extension.
     */
    public function testCodeClassesKeepOnlyTheirOwnClasses(): void
    {
        self::assertSame('language-php language-c++', CodeClasses::keep('code', "language-php\tx language-c++\n\rlanguage-"));
        self::assertNull(CodeClasses::keep('code', 'hl-keyword language-'));
        self::assertSame('hl-keyword hl-string', CodeClasses::keep('span', 'hl-keyword language-php hl-string x'));
        self::assertNull(CodeClasses::keep('span', 'language-php'));
    }

    /**
     * A class named `Builder` without the extension does not make the purifier available: both conditions are needed.
     */
    public function testAClassWithoutTheExtensionIsNotEnough(): void
    {
        if (\extension_loaded('ammonia')) {
            self::assertTrue(AmmoniaPurifier::isAvailable());

            return;
        }

        class_alias(stdClass::class, 'Switon\\Ammonia\\Builder');

        self::assertFalse(AmmoniaPurifier::isAvailable());
    }

    public function testTheChoiceFollowsTheExtension(): void
    {
        self::assertSame(\extension_loaded('ammonia'), AmmoniaPurifier::isAvailable());
        self::assertInstanceOf(AmmoniaPurifier::isAvailable() ? AmmoniaPurifier::class : SanitizerPurifier::class, Purifiers::defaultPolicies());
        self::assertInstanceOf(SanitizerPurifier::class, Purifiers::defaultPolicies(extension: false));

        $images = Purifiers::defaultPolicies([]);
        self::assertSame('<img />', $images->purify('<img src="https://tracker.example/p.gif">', Level::Forum), 'The hosts reach the purifier chosen.');
    }

    public function testWithoutTheExtensionItIsRefused(): void
    {
        if (\extension_loaded('ammonia')) {
            self::markTestSkipped('The extension is loaded.');
        }

        try {
            AmmoniaPurifier::defaultPolicies();
            self::fail('The purifier needs its extension.');
        } catch (MissingExtension $missingExtension) {
            self::assertSame('ammonia', $missingExtension->extension);
            self::assertSame('Phil\Markup\Adapter\AmmoniaPurifier needs the "ammonia" extension of PHP, which is not loaded.', $missingExtension->getMessage());
        }
    }
}
