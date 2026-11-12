<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Application\ImageMigration;
use Phil\UxKit\Application\ImageMigrationFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cases are those of real templates, their names and paths made neutral.
 */
#[CoversClass(ImageMigration::class)]
#[CoversClass(ImageMigrationFinding::class)]
final class ImageMigrationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function proposals(): iterable
    {
        yield 'a decorative icon of the layout' => [
            '<img src="{{ asset(\'images/icon.png\') }}" alt="" width="32" height="32">',
            '<twig:Image src="{{ asset(\'images/icon.png\') }}" alt="" :width="32" :height="32" />',
        ];
        yield 'a logo with its class, the attributes in any order' => [
            '<img class="site-logo" src="{{ asset(\'images/logo.svg\') }}" alt="" width="658" height="214">',
            '<twig:Image src="{{ asset(\'images/logo.svg\') }}" alt="" :width="658" :height="214" class="site-logo" />',
        ];
        yield 'the defaults of the recipe dropped' => [
            '<img src="{{ asset(_images ~ \'qr.svg\') }}" alt="The code of the address" width="64" height="64" loading="lazy" decoding="async">',
            '<twig:Image src="{{ asset(_images ~ \'qr.svg\') }}" alt="The code of the address" :width="64" :height="64" />',
        ];
        yield 'what differs from them kept' => [
            '<img src="/a.png" alt="A" width="10" height="10" loading="eager" decoding="sync" />',
            '<twig:Image src="/a.png" alt="A" :width="10" :height="10" loading="eager" decoding="sync" />',
        ];
        yield 'a size printed by Twig' => [
            '<img src="{{ image }}" alt="{{ imageAlt ?? label }}" width="{{ size }}" height="{{ size * 2 }}">',
            '<twig:Image src="{{ image }}" alt="{{ imageAlt ?? label }}" :width="size" :height="size * 2" />',
        ];
        yield 'densities and sizes as they are' => [
            '<img src="/1x.png" srcset="/2x.png 2x, /3x.png 3x" sizes="50vw" alt="A" width="200" height="49">',
            '<twig:Image src="/1x.png" srcset="/2x.png 2x, /3x.png 3x" sizes="50vw" alt="A" :width="200" :height="49" />',
        ];
        yield 'the attributes the recipe passes on, after the props' => [
            '<img id="hero" data-turbo="false" aria-describedby="note" title="T" crossorigin="anonymous" referrerpolicy="no-referrer" itemprop="image" draggable="false" src="/a.png" alt="A" width="1" height="1">',
            '<twig:Image src="/a.png" alt="A" :width="1" :height="1" id="hero" data-turbo="false" aria-describedby="note" title="T" crossorigin="anonymous" referrerpolicy="no-referrer" itemprop="image" draggable="false" />',
        ];
        yield 'single quotes and no quotes' => [
            "<IMG SRC='/a.png' ALT='A' WIDTH=10 HEIGHT=20>",
            '<twig:Image src="/a.png" alt="A" :width="10" :height="20" />',
        ];
        yield 'a Twig string in double quotes, kept between single quotes' => [
            '<img src=\'{{ asset("a.png") }}\' alt="A" width="1" height="1">',
            '<twig:Image src=\'{{ asset("a.png") }}\' alt="A" :width="1" :height="1" />',
        ];
        yield 'a self-closing tag spaced oddly, an attribute without value' => [
            '<img src="/a.png" alt="A" width="1" height="1" data-zoom / >',
            '<twig:Image src="/a.png" alt="A" :width="1" :height="1" data-zoom="" />',
        ];
        yield 'fetchpriority low, deferred' => [
            '<img src="/a.png" alt="A" width="1" height="1" fetchpriority="low">',
            '<twig:Image src="/a.png" alt="A" :width="1" :height="1" :deferred="true" />',
        ];
        yield 'a size printed over two lines' => [
            "<img src=\"/a.png\" alt=\"A\" width=\"{{ size\n * 2 }}\" height=\"1\">",
            "<twig:Image src=\"/a.png\" alt=\"A\" :width=\"size\n * 2\" :height=\"1\" />",
        ];
        yield 'a closing slash on a line of its own' => [
            "<img src=\"/a.png\" alt=\"A\" width=\"1\" height=\"1\"\n/>",
            '<twig:Image src="/a.png" alt="A" :width="1" :height="1" />',
        ];
        yield 'an inline image' => [
            '<img src="data:image/png;base64,AAAA" alt="A" width="1" height="1">',
            '<twig:Image src="data:image/png;base64,AAAA" alt="A" :width="1" :height="1" />',
        ];
    }

    #[DataProvider('proposals')]
    public function testAnImageWithItsTextAndSizeIsProposed(string $element, string $proposal): void
    {
        $findings = new ImageMigration()->find($element);

        self::assertCount(1, $findings);
        self::assertSame($proposal, $findings[0]->proposal);
        self::assertSame([], $findings[0]->notes);
        self::assertSame($element, $findings[0]->original);
    }

    public function testAMainImageIsProposedWithItsPriorityAndSaidSo(): void
    {
        $finding = new ImageMigration()->find('<img src="/a.png" alt="A" width="1" height="1" loading="eager" fetchpriority="high">')[0];

        self::assertSame('<twig:Image src="/a.png" alt="A" :width="1" :height="1" :priority="true" />', $finding->proposal);
        self::assertSame(['The image becomes the main image of the page: preloaded, fetched first and loaded eagerly.'], $finding->notes);
    }

    public function testASizeInPixelsLosesItsUnitAndIsSaidSo(): void
    {
        $finding = new ImageMigration()->find('<img width="42px" height="42" alt="Logo" src="{{ asset(\'img/logo.svg\') }}">')[0];

        self::assertSame('<twig:Image :width="42" :height="42" alt="Logo" src="{{ asset(\'img/logo.svg\') }}" />', $finding->proposal);
        self::assertSame(['The width loses its unit: a size of an image is in pixels.'], $finding->notes);

        $finding = new ImageMigration()->find('<img width="42" height="42px" alt="Logo" src="/a.svg">')[0];
        self::assertSame(['The height loses its unit: a size of an image is in pixels.'], $finding->notes);
    }

    public function testAnImageOfAnotherHostIsProposedAndItsHostToList(): void
    {
        $note = 'The image is on another host: list its host under phil_ux_kit.image.hosts, or the recipe refuses it.';

        foreach (['https://cdn.example.org/a.png', '//cdn.example.org/a.png', 'HTTP://cdn.example.org/a.png'] as $src) {
            $finding = new ImageMigration()->find('<img src="'.$src.'" alt="A" width="1" height="1">')[0];

            self::assertSame('<twig:Image src="'.$src.'" alt="A" :width="1" :height="1" />', $finding->proposal);
            self::assertSame([$note], $finding->notes, $src);
        }

        foreach (['/a.png', 'a.png', 'DATA:image/png;base64,AA', '{{ asset("https.png") }}', 'images/https://a.png'] as $src) {
            self::assertSame([], new ImageMigration()->find("<img src='".$src."' alt=\"A\" width=\"1\" height=\"1\">")[0]->notes, $src);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function leftAsTheyAre(): iterable
    {
        yield 'a remote image without height' => [
            '<img src="https://images.example.org/footer.svg" width="250" alt="The footer" /  >',
            [
                'The image is on another host: list its host under phil_ux_kit.image.hosts, or the recipe refuses it.',
                'The image has no height: give its size, so that its space is kept while it loads.',
            ],
        ];
        yield 'a logo without alt or size' => [
            '<img src="{{ asset("images/logo_" ~ app.request.locale ~ ".svg") }}">',
            [
                'The image has no text alternative: write its alt, or alt="" for a decorative image.',
                'The image has no width: give its size, so that its space is kept while it loads.',
                'The image has no height: give its size, so that its space is kept while it loads.',
            ],
        ];
        yield 'no src' => [
            '<img alt="A" width="1" height="1">',
            ['The image has no src.'],
        ];
        yield 'a die drawn at random, without size' => [
            '<img src="{{ asset(\'img/dice/\' ~ die.value ~ \'.png\') }}" alt="{{ die.value }}" class="dice" />',
            [
                'The image has no width: give its size, so that its space is kept while it loads.',
                'The image has no height: give its size, so that its space is kept while it loads.',
            ],
        ];
        yield 'attributes printed whole' => [
            '<img {{ attributes.defaults({class: \'card-image\'}).without(\'src\') }} src="/a.svg" alt="{{ alt }}" />',
            ['The tag holds a Twig block or prints its attributes: rewrite it by hand.'],
        ];
        yield 'a Twig block in the tag' => [
            '<img src="/a.png" alt="A"{% if width %} width="{{ width }}"{% endif %} height="1">',
            ['The tag holds a Twig block or prints its attributes: rewrite it by hand.'],
        ];
        yield 'an inline style, an event' => [
            '<img src="/a.png" alt="A" width="1" height="1" style="float: left" onerror="this.remove()">',
            ['The attribute style has no place in the recipe.', 'The attribute onerror has no place in the recipe.'],
        ];
        yield 'a size in per cent' => [
            '<img src="/a.png" alt="A" width="100%" height="1">',
            ['The width "100%" is no number of pixels: give the size of the image.'],
        ];
        yield 'a size printed with a string in double quotes' => [
            '<img src="/a.png" alt="A" width=\'{{ sizes["w"] }}\' height="1">',
            ['The width "{{ sizes["w"] }}" is no number of pixels: give the size of the image.'],
        ];
        yield 'a size printed twice' => [
            '<img src="/a.png" alt="A" width="{{ a }}{{ b }}" height="1">',
            ['The width "{{ a }}{{ b }}" is no number of pixels: give the size of the image.'],
        ];
        yield 'a size that only ends with a number' => [
            '<img src="/a.png" alt="A" width="x42" height="1">',
            ['The width "x42" is no number of pixels: give the size of the image.'],
        ];
        yield 'a size printed, then a unit' => [
            '<img src="/a.png" alt="A" width="{{ w }}px" height="1">',
            ['The width "{{ w }}px" is no number of pixels: give the size of the image.'],
        ];
        yield 'a fetchpriority the recipe does not know' => [
            '<img src="/a.png" alt="A" width="1" height="1" fetchpriority="auto">',
            ['The attribute fetchpriority has no place in the recipe.'],
        ];
    }

    /**
     * @param list<string> $notes
     */
    #[DataProvider('leftAsTheyAre')]
    public function testAnImageMissingWhatTheRecipeNeedsIsLeftWithWhatStandsInItsWay(string $element, array $notes): void
    {
        $findings = new ImageMigration()->find($element);

        self::assertCount(1, $findings);
        self::assertNull($findings[0]->proposal);
        self::assertSame($notes, $findings[0]->notes);
        self::assertSame($element, $findings[0]->original);
    }

    public function testAPictureOfSourcesIsProposedWithThemAsExpressions(): void
    {
        $template = <<<'TWIG'
            <picture>
                <source type="image/avif" srcset="{{ asset(_images ~ '1x/logo.avif') }}, {{ asset(_images ~ '2x/logo.avif') }} 2x">
                <source type="image/webp" srcset="{{ asset('images/main.webp') }}" media="(min-width: 40rem)" sizes="50vw">
                <source srcset="/plain.png" width="10" height="5">
                <img src="{{ asset('images/main.png') }}" alt="{{ 'home.illustration'|trans }}" width="2906" height="1317" decoding="async">
            </picture>
            TWIG;

        $findings = new ImageMigration()->find($template);

        self::assertCount(1, $findings);
        self::assertSame($template, $findings[0]->original);
        self::assertSame(
            '<twig:Picture src="{{ asset(\'images/main.png\') }}" alt="{{ \'home.illustration\'|trans }}" :width="2906" :height="1317" :sources="['
            ."{type: 'image/avif', srcset: (asset(_images ~ '1x/logo.avif')) ~ ', ' ~ (asset(_images ~ '2x/logo.avif')) ~ ' 2x'}, "
            ."{type: 'image/webp', srcset: asset('images/main.webp'), media: '(min-width: 40rem)', sizes: '50vw'}, "
            ."{srcset: '/plain.png', width: '10', height: '5'}"
            .']" />',
            $findings[0]->proposal,
        );
        self::assertSame([], $findings[0]->notes);
    }

    public function testAPictureWithoutSourcesIsAPictureAllTheSame(): void
    {
        self::assertSame(
            '<twig:Picture src="/a.png" alt="A" :width="1" :height="1" />',
            new ImageMigration()->find('<PICTURE class="x"><img src="/a.png" alt="A" width="1" height="1"></PICTURE >')[0]->proposal,
        );
    }

    public function testAStringOfASourceKeepsItsQuotesAndBackslashesEscaped(): void
    {
        self::assertStringContainsString(
            "srcset: '/l\\'a\\\\b.png'",
            (string) new ImageMigration()->find('<picture><source srcset="/l\'a\\b.png"><img src="/a.png" alt="A" width="1" height="1"></picture>')[0]->proposal,
        );
        self::assertStringContainsString(
            "type: ''",
            (string) new ImageMigration()->find('<picture><source type="" srcset="/b.png"><img src="/a.png" alt="A" width="1" height="1"></picture>')[0]->proposal,
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function picturesLeftAsTheyAre(): iterable
    {
        yield 'a carousel slide without the size of its image' => [
            '<picture><source type="image/webp" srcset="{{ asset(\'images/history/1998.webp\') }}" /><source type="image/png" srcset="{{ asset(\'images/history/1998.png\') }}" /><img src="{{ asset(\'images/history/1998.png\') }}" class="d-block w-100" alt="The site in 1998" /></picture>',
            [
                'The image has no width: give its size, so that its space is kept while it loads.',
                'The image has no height: give its size, so that its space is kept while it loads.',
            ],
        ];
        yield 'a source printed with a string in double quotes' => [
            '<picture><source type="image/webp" srcset="{{ asset("img/logo.webp") }}"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The srcset of a source holds a Twig block or a double quote: rewrite it by hand.'],
        ];
        yield 'a source with a Twig block in its value' => [
            '<picture><source srcset="{% if a %}/a.webp{% endif %}"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The srcset of a source holds a Twig block or a double quote: rewrite it by hand.'],
        ];
        yield 'a source with a comment in its value' => [
            '<picture><source srcset="/a.webp {# two #}"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The srcset of a source holds a Twig block or a double quote: rewrite it by hand.'],
        ];
        yield 'a source printing its attributes' => [
            '<picture><source {{ attributes }}><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['A source holds a Twig block or prints its attributes: rewrite it by hand.'],
        ];
        yield 'a source with an attribute of its own' => [
            '<picture><source srcset="/a.webp" class="x"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The attribute class of a source has no place in the recipe.'],
        ];
        yield 'two sources printing their attributes' => [
            '<picture><source {{ one }}><source {{ two }}><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['A source holds a Twig block or prints its attributes: rewrite it by hand.', 'A source holds a Twig block or prints its attributes: rewrite it by hand.'],
        ];
        yield 'a source with two attributes of its own' => [
            '<picture><source class="x" id="y" srcset="/a.webp"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The attribute class of a source has no place in the recipe.', 'The attribute id of a source has no place in the recipe.'],
        ];
        yield 'a source with two values holding blocks' => [
            '<picture><source srcset="{% if a %}/a.webp{% endif %}" media="{% if b %}(min-width: 1px){% endif %}"><img src="/logo.png" alt="Logo" width="1" height="1"></picture>',
            ['The srcset of a source holds a Twig block or a double quote: rewrite it by hand.', 'The media of a source holds a Twig block or a double quote: rewrite it by hand.'],
        ];
        yield 'two images' => [
            '<picture><img src="/a.png" alt="A" width="1" height="1"><img src="/b.png" alt="B" width="1" height="1"></picture>',
            ['A picture holds its sources and one img, and nothing else: rewrite it by hand.'],
        ];
        yield 'no image' => [
            '<picture><source srcset="/a.webp"></picture>',
            ['A picture holds its sources and one img, and nothing else: rewrite it by hand.'],
        ];
        yield 'a block around the sources' => [
            '<picture>{% if webp %}<source srcset="/a.webp">{% endif %}<img src="/a.png" alt="A" width="1" height="1"></picture>',
            ['A picture holds its sources and one img, and nothing else: rewrite it by hand.'],
        ];
        yield 'an image printing its attributes' => [
            '<picture><source srcset="/a.webp"><img {{ attributes }}></picture>',
            ['The tag holds a Twig block or prints its attributes: rewrite it by hand.'],
        ];
    }

    /**
     * @param list<string> $notes
     */
    #[DataProvider('picturesLeftAsTheyAre')]
    public function testAPictureMissingWhatTheRecipeNeedsIsLeftAsItIs(string $element, array $notes): void
    {
        $findings = new ImageMigration()->find($element);

        self::assertCount(1, $findings, 'its img is not taken apart');
        self::assertNull($findings[0]->proposal);
        self::assertSame($notes, $findings[0]->notes);
    }

    public function testTheElementsOfATemplateAreFoundInOrderWithTheirLines(): void
    {
        $template = "{# An <img> mentioned in a comment, and a <picture> too. #}\n"
            ."<header>\n"
            ."    <img src=\"/logo.png\" alt=\"\" width=\"32\" height=\"32\">\n"
            ."</header>\n"
            ."<!-- <img src=\"/old.png\"> -->\n"
            ."<picture>\n"
            ."    <source srcset=\"/a.webp\">\n"
            ."    <img src=\"/a.png\"\n"
            ."         alt=\"A\" width=\"1\" height=\"1\">\n"
            ."</picture>\n"
            ."<p>Text <img src=\"/b.png\" alt=\"B\"></p>\n";

        $findings = new ImageMigration()->find($template);

        self::assertSame([3, 6, 11], array_map(static fn (ImageMigrationFinding $finding): int => $finding->line, $findings));
        self::assertSame([strpos($template, '<img src="/logo.png"'), strpos($template, "<picture>\n"), strpos($template, '<img src="/b.png"')], array_map(static fn (ImageMigrationFinding $finding): int => $finding->offset, $findings));
        self::assertSame('<img src="/logo.png" alt="" width="32" height="32">', $findings[0]->original);
        self::assertSame('<twig:Picture src="/a.png" alt="A" :width="1" :height="1" :sources="[{srcset: \'/a.webp\'}]" />', $findings[1]->proposal);
        self::assertNull($findings[2]->proposal);
    }

    public function testABlockOrAnExpressionHoldingAGreaterThanStaysInsideItsTag(): void
    {
        foreach ([
            '<img src="/a.png" alt="A"{% if count > 1 %} width="1"{% endif %} height="1">',
            '<img {{ count > 1 ? "x" : "y" }} src="/a.png" alt="A" width="1" height="1">',
            '<picture{% if a > b %} class="x"{% endif %}><img src="/a.png" alt="A" width="1" height="1"></picture>',
        ] as $element) {
            $findings = new ImageMigration()->find('<p>'.$element.'</p>');

            self::assertCount(1, $findings, $element);
            self::assertSame($element, $findings[0]->original);
        }
    }

    public function testAnExpressionWithoutSpacesIsReadWhole(): void
    {
        self::assertStringContainsString(
            ':sources="[{srcset: x}]"',
            (string) new ImageMigration()->find('<picture><source srcset="{{x}}"><img src="/a.png" alt="A" width="1" height="1"></picture>')[0]->proposal,
        );
    }

    public function testATemplateStartingWithANewLineCountsIt(): void
    {
        self::assertSame(2, new ImageMigration()->find("\n<img src=\"/a.png\">")[0]->line);
        self::assertSame(1, new ImageMigration()->find("<img src=\"/a.png\">\n")[0]->line);
    }

    public function testAnImageAfterAPictureIsFoundAlone(): void
    {
        $findings = new ImageMigration()->find('<img src="/0.png" alt="" width="1" height="1"><picture><img src="/1.png" alt="" width="1" height="1"></picture><img src="/2.png" alt="" width="1" height="1">');

        self::assertCount(3, $findings);
        self::assertStringStartsWith('<twig:Image src="/0.png"', (string) $findings[0]->proposal);
        self::assertStringStartsWith('<twig:Picture src="/1.png"', (string) $findings[1]->proposal);
        self::assertStringStartsWith('<twig:Image src="/2.png"', (string) $findings[2]->proposal);
    }

    public function testATagHoldingAGreaterThanInATwigExpressionIsReadWhole(): void
    {
        $finding = new ImageMigration()->find('<img src="/a.png" alt="{{ count > 1 ? \'Many\' : \'One\' }}" width="1" height="1">')[0];

        self::assertSame('<twig:Image src="/a.png" alt="{{ count > 1 ? \'Many\' : \'One\' }}" :width="1" :height="1" />', $finding->proposal);
    }

    public function testApplyingRewritesTheProposalsAndLeavesTheRest(): void
    {
        $template = "<div>\n    <img src=\"/a.png\" alt=\"A\" width=\"1\" height=\"1\">\n    <img src=\"/b.png\">\n    <picture><source srcset=\"/c.webp\"><img src=\"/c.png\" alt=\"C\" width=\"2\" height=\"2\"></picture>\n</div>\n";
        $migration = new ImageMigration();

        self::assertSame(
            "<div>\n    <twig:Image src=\"/a.png\" alt=\"A\" :width=\"1\" :height=\"1\" />\n    <img src=\"/b.png\">\n    <twig:Picture src=\"/c.png\" alt=\"C\" :width=\"2\" :height=\"2\" :sources=\"[{srcset: '/c.webp'}]\" />\n</div>\n",
            $migration->apply($template, $migration->find($template)),
        );
    }

    public function testApplyingFindingsOfAnotherTemplateChangesNothing(): void
    {
        $migration = new ImageMigration();
        $findings = $migration->find('<img src="/a.png" alt="A" width="1" height="1">');

        self::assertSame('<p><img src="/a.png" alt="A" width="1" height="1"></p>', $migration->apply('<p><img src="/a.png" alt="A" width="1" height="1"></p>', $findings));
        self::assertSame('<img src="/z.png" alt="Z" width="1" height="1">', $migration->apply('<img src="/z.png" alt="Z" width="1" height="1">', $findings));
    }

    public function testNothingIsFoundWhereThereIsNoImage(): void
    {
        self::assertSame([], new ImageMigration()->find('<p>An <imgx> and <twig:Image src="/a.png" alt="A" :width="1" :height="1" /></p>'));
        self::assertSame('<p>Nothing</p>', new ImageMigration()->apply('<p>Nothing</p>', []));
    }
}
