<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Infrastructure;

use Phil\Markup\Reader\Reading;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Slides\Tests\Unit\Support\Twig;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The print template and the functions of the bundle, rendered by Twig with the extension and the translations; the
 * audience and presenter templates, which draw the recipe `Button` of a kit, are rendered in a booted application.
 */
#[CoversClass(SlidesExtension::class)]
final class TemplatesTest extends TestCase
{
    public const string SOURCE = <<<'MD'
        ---
        title: The talk
        lang: en-CA
        paginate: true
        header: PHIL
        footer: November
        class: invert
        ---

        # Welcome

        Hello & welcome.

        ::: notes
        Thank the **organisers**.
        :::

        ---

        \page {.lead}

        ## Second

        ::: fragment
        One point.
        :::

        {{skipCounting}}

        ---

        ## Third

        The end.
        MD;

    public function testThePrintLeavesTheNotesOutUnlessAsked(): void
    {
        $without = $this->render("{{ include('@PhilSlides/print.html.twig', {deck: slides_markup(source), with_notes: false}) }}");
        $with = $this->render("{{ include('@PhilSlides/print.html.twig', {deck: slides_markup(source), with_notes: true}) }}");

        self::assertStringNotContainsString('organisers', $without);
        self::assertStringContainsString('<div class="phil-slides phil-slides-print" data-ratio="16:9">', $without);
        self::assertSame(3, substr_count($without, '<article class="phil-slides-print__page">'));
        self::assertStringNotContainsString('-target=', $without);
        self::assertStringContainsString('<div class="phil-slides phil-slides-print hasNotes" data-ratio="16:9">', $with);
        self::assertSame(1, substr_count($with, '<section class="phil-slides-print__notes">'));
        self::assertStringContainsString('<h2>Notes</h2>', $with);
        self::assertStringContainsString('organisers', $with);
    }

    public function testTheExtensionReadsEveryKindOfDocument(): void
    {
        $html = $this->render("{{ slides_deck(doc).count }}/{{ slides_deck(json).count }}/{{ slides_deck(reading).count }}/{{ slides_deck(reading, {}, 'x').id }}/{{ slides_markup('# A', 'markdown', 'y').id }}/{{ slides_markup('# A').id|length }}/{{ slides_fragment_class() }}/{{ slides_kit('Button') }}");

        self::assertSame('1/2/1/x/y/32/fragment/Button', $html);
        self::assertSame('step', Twig::environment('en', 'step')->createTemplate('{{ slides_fragment_class() }}')->render());
    }

    public function testTheRecipesOfTheKitAreNamedAfterTheirSubdirectory(): void
    {
        $ujo = new Ujo();
        $extension = static fn (?string $subdirectory): SlidesExtension => new SlidesExtension(new DeckReader(new Slicer(), $ujo), new SlideRenderer($ujo), 'fragment', $subdirectory);

        self::assertSame('ui:Button', $extension('ui')->kit('Button'));
        self::assertSame('ui:kit:Button', $extension('/ui/kit/')->kit('Button'));
        self::assertSame('Button', $extension('/')->kit('Button'));
        self::assertSame('Button', $extension(null)->kit('Button'));
        self::assertSame('Button', $extension('')->kit('Button'));
    }

    private function render(string $template, string $locale = 'en'): string
    {
        $reading = new Reading(new Document([new Block('a', 'paragraph', ['text' => 'Read'])]));

        return Twig::environment($locale)->createTemplate($template)->render([
            'source' => self::SOURCE,
            'doc' => new Document([new Block('a', 'paragraph', ['text' => 'Alone'])]),
            'json' => '{"blocks":[{"id":"a","type":"header","data":{"text":"A","level":2}},{"id":"b","type":"header","data":{"text":"B","level":2}}]}',
            'reading' => $reading,
        ]);
    }
}
