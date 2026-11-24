<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Render;

use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Render\HtmlRenderer;
use Phil\SeoCheck\Render\JsonRenderer;
use Phil\SeoCheck\Render\TextRenderer;
use Phil\SeoCheck\Report;
use Phil\SeoCheck\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextRenderer::class)]
#[CoversClass(JsonRenderer::class)]
#[CoversClass(HtmlRenderer::class)]
final class RenderersTest extends TestCase
{
    private function report(string $locale = 'en'): Report
    {
        return new Report([
            new Finding('canonical', Severity::Error, new Message('canonical.missing', ['expected' => 'https://example.org/a?x=1&y=<2>'])),
            new Finding('image-alt', Severity::Warning, new Message('image-alt.missing', ['src' => '/a.png']), '/a.png?"x"'),
            new Finding('meta-description', Severity::Info, new Message('meta-description.short', ['length' => 12, 'min' => 50])),
        ], locale: $locale);
    }

    public function testTheTextHasALinePerPoint(): void
    {
        self::assertSame(
            "3 point(s) to correct.\n"
            ."[error] canonical: The page names no canonical address: add a canonical link to https://example.org/a?x=1&y=<2>.\n"
            ."[warning] image-alt: The image /a.png has no alt attribute: describe it, or give it alt=\"\" when it is only decoration.\n"
            ."[info] meta-description: The meta description has 12 characters: below 50, it says little of the page.\n",
            new TextRenderer()->render($this->report()),
        );
        self::assertSame("Aucun point à corriger.\n", new TextRenderer()->render(new Report(locale: 'fr')));
    }

    public function testTheJsonIsTheArrayOfTheReport(): void
    {
        $json = new JsonRenderer()->render($this->report('fr'));

        self::assertSame($this->report('fr')->toArray(), json_decode($json, true, 512, \JSON_THROW_ON_ERROR));
        self::assertStringContainsString('"expected": "https://example.org/a?x=1&y=<2>"', $json);
        self::assertStringContainsString('d’adresse canonique', $json);
        self::assertStringContainsString("\n    \"locale\": \"fr\",", $json);
    }

    public function testTheJsonSubstitutesInvalidUtf8(): void
    {
        $report = new Report([new Finding('image-alt', Severity::Warning, new Message('image-alt.missing', ['src' => "\xff"]), "\xff")]);

        self::assertStringContainsString('"subject": "�"', new JsonRenderer()->render($report));
    }

    public function testTheHtmlIsAnEscapedListForAnAdministration(): void
    {
        self::assertSame(
            '<section class="seo-check-report" lang="fr-CA"><h2>Contrôle de référencement</h2><p>3 point(s) à corriger.</p><ul>'
            .'<li class="isError" data-rule="canonical"><strong>Erreur</strong> La page ne nomme pas d’adresse canonique : ajoutez un lien canonique vers https://example.org/a?x=1&amp;y=&lt;2&gt;.</li>'
            .'<li class="isWarning" data-rule="image-alt"><strong>Avertissement</strong> L’image /a.png n’a pas d’attribut alt : décrivez-la, ou donnez-lui alt=&quot;&quot; si elle n’est qu’une décoration. <code>/a.png?&quot;x&quot;</code></li>'
            .'<li class="isInfo" data-rule="meta-description"><strong>Information</strong> La meta description a 12 caractères : sous 50, elle dit peu de la page.</li>'
            .'</ul></section>',
            new HtmlRenderer()->render($this->report('fr_CA')),
        );
    }

    public function testAnEmptyReportSaysSo(): void
    {
        self::assertSame(
            '<section class="seo-check-report" lang="en"><h2>SEO check</h2><p>No point to correct.</p></section>',
            new HtmlRenderer()->render(new Report()),
        );
    }

    public function testTheHtmlEscapesTheLocaleAndInvalidText(): void
    {
        $html = new HtmlRenderer()->render(new Report([new Finding('a"b', Severity::Info, new Message("x\xff'"))], locale: '"><x'));

        self::assertStringContainsString('lang="&quot;&gt;&lt;x"', $html);
        self::assertStringContainsString('data-rule="a&quot;b"', $html);
        self::assertStringContainsString("x\u{FFFD}&apos;", $html);
    }
}
