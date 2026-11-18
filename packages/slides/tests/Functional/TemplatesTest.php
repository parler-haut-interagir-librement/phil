<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional;

use Override;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Slides\Tests\Functional\App\TestKernel;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * The audience and presenter templates drawn by Twig in a booted application, with the recipe `Button` of each kit —
 * PHIL UX and Shadcn —: the same buttons and links, with the same labels, actions, targets and states, in either.
 */
#[CoversClass(SlidesExtension::class)]
final class TemplatesTest extends KernelTestCase
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

        ::: step
        One point.
        :::

        {{skipCounting}}

        ---

        ## Third

        The end.
        MD;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $environment = $options['environment'] ?? 'test';
        \assert(\is_string($environment));

        return new TestKernel($environment, true);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function kits(): iterable
    {
        yield 'PHIL UX in a subdirectory' => ['test'];
        yield 'Shadcn' => [TestKernel::SHADCN];
    }

    #[DataProvider('kits')]
    public function testTheAudienceSeesNoNoteAndTheButtonsOfTheKit(string $kit): void
    {
        $html = $this->render($kit, "{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(source), presenter_url: '/talk/presenter'}) }}");
        $page = new Crawler($html);
        $c = 'phil--slides--deck';

        self::assertStringNotContainsString('organisers', $html);
        $deck = $page->filter('section.phil-slides');
        self::assertSame(['phil-slides', 'slide deck', 'The talk', 'en-CA', '4:3', $c], [$deck->attr('id'), $deck->attr('aria-roledescription'), $deck->attr('aria-label'), $deck->attr('lang'), $deck->attr('data-ratio'), $deck->attr('data-controller')]);
        self::assertSame('step', $deck->attr('data-'.$c.'-fragment-class-value'));
        self::assertSame('false', $deck->attr('data-'.$c.'-page-keys-value'));
        self::assertSame('Slide %number% of %count%', $deck->attr('data-'.$c.'-status-value'));
        self::assertSame('Slide %number% of %count%: %title%', $deck->attr('data-'.$c.'-titled-status-value'));
        self::assertSame('%number% / %count%', $deck->attr('data-'.$c.'-counter-value'));

        self::assertStringContainsString('<section id="slide-1" class="phil-slide invert" aria-roledescription="slide" aria-label="Slide 1 of 3 – Welcome" tabindex="-1" data-phil--slides--deck-target="slide">', $html);
        self::assertStringContainsString('<section id="slide-2" class="phil-slide invert lead"', $html);
        self::assertStringContainsString('<header class="phil-slide__header">PHIL</header>', $html);
        self::assertStringContainsString('<p class="ujo-paragraph">Hello &amp; welcome.</p>', $html);
        self::assertStringContainsString('<span class="phil-slide__footer-text">November</span><span class="phil-slide__number">1 / 3</span>', $html);
        self::assertStringNotContainsString('2 / 3</span></footer>', $html);
        self::assertStringContainsString('<span class="phil-slide__number">3 / 3</span>', $html);
        self::assertStringContainsString('<div class="ujo-container step">', $html);
        self::assertSame(3, substr_count($html, 'data-phil--slides--deck-target="slide"'));

        $controls = $page->filter('nav.phil-slides__controls');
        self::assertSame(['Slide controls', '', 'controls'], [$controls->attr('aria-label'), $controls->attr('hidden'), $controls->attr('data-'.$c.'-target')]);
        self::assertSame([
            ['a', null, 'Presenter view', $c.'#openPresenter', null, null, '/talk/presenter', 'phil-slides-presenter'],
            ['button', 'button', 'Previous', $c.'#previous', null, null, null, null],
            ['button', 'button', 'Next', $c.'#next', null, null, null, null],
            ['button', 'button', 'Full screen', $c.'#toggleFullscreen', 'false', 'fullscreen', null, null],
        ], self::controls($page, $c));
        self::assertSame('1 / 3', $controls->filter('.phil-slides__counter')->text());
        $this->assertTheKitDrewThem($kit, $page);
    }

    #[DataProvider('kits')]
    public function testTheAudienceTemplateTakesItsOptions(string $kit): void
    {
        $html = $this->render($kit, "{{ include('@PhilSlides/deck.html.twig', {deck: slides_deck(doc, {}, 'talk-7'), id: 'mine', keyboard: 'page'}) }}", 'fr');
        $page = new Crawler($html);

        self::assertStringContainsString('id="mine" class="phil-slides" aria-roledescription="présentation" aria-label="Diapositives" data-ratio="4:3"', $html);
        self::assertStringContainsString('data-phil--slides--deck-channel-value="talk-7"', $html);
        self::assertStringContainsString('data-phil--slides--deck-page-keys-value="true"', $html);
        self::assertStringContainsString('aria-label="Diapositive 1 sur 1" tabindex="-1"', $html);
        self::assertStringNotContainsString('phil-slides__presenter', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertStringNotContainsString('<footer', $html);
        self::assertStringNotContainsString(' lang=', $html);
        self::assertSame(['Précédente', 'Suivante', 'Plein écran'], array_column(self::controls($page, 'phil--slides--deck'), 2));
    }

    #[DataProvider('kits')]
    public function testThePresenterSeesEachSlideWithItsNotes(string $kit): void
    {
        $html = $this->render($kit, "{{ include('@PhilSlides/presenter.html.twig', {deck: slides_markup(source), audience_url: '/talk', notes_pdf_url: '/talk/notes.pdf', duration: 20}) }}");
        $page = new Crawler($html);
        $c = 'phil--slides--presenter';

        self::assertStringContainsString('data-controller="phil--slides--presenter"', $html);
        self::assertStringContainsString('data-phil--slides--presenter-duration-value="1200"', $html);
        self::assertStringContainsString('<h1 id="phil-slides-presenter-title" class="phil-slides-presenter__title">Presenter view – The talk</h1>', $html);
        self::assertStringContainsString('<p class="ujo-paragraph">Thank the <strong>organisers</strong>.</p>', $html);
        self::assertSame(2, substr_count($html, 'No notes for this slide.'));
        self::assertSame(3, substr_count($html, 'data-phil--slides--presenter-target="pair"'));
        self::assertSame(3, substr_count($html, 'data-phil--slides--presenter-target="slide"'));
        self::assertStringContainsString('aria-label="Notes of slide 2"', $html);
        self::assertStringContainsString('data-phil--slides--presenter-target="remaining"', $html);
        self::assertStringContainsString('<aside class="phil-slides-presenter__side" hidden', $html);

        self::assertSame([
            ['a', null, 'Open the audience window', $c.'#openAudience', null, null, '/talk', 'phil-slides-audience'],
            ['a', null, 'Download the PDF with the notes', null, null, null, '/talk/notes.pdf', null],
            ['button', 'button', 'Start', $c.'#toggleTimer', 'false', 'toggle', null, null],
            ['button', 'button', 'Reset', $c.'#resetTimer', null, null, null, null],
            ['button', 'button', 'Previous', $c.'#previous', null, null, null, null],
            ['button', 'button', 'Next', $c.'#next', null, null, null, null],
        ], self::controls($page, $c));
        $toggle = $page->filter('[data-'.$c.'-target="toggle"]');
        self::assertSame(['Start', 'Pause'], [$toggle->attr('data-start-label'), $toggle->attr('data-pause-label')]);
        self::assertSame('', $page->filter('.phil-slides-presenter__pdf a')->attr('download'));
        $this->assertTheKitDrewThem($kit, $page);
    }

    #[DataProvider('kits')]
    public function testThePresenterTemplateTakesItsOptions(string $kit): void
    {
        $html = $this->render($kit, "{{ include('@PhilSlides/presenter.html.twig', {deck: slides_deck(doc), id: 'speaker'}) }}", 'fr');

        self::assertStringContainsString('<h1 id="speaker-title" class="phil-slides-presenter__title">Vue présentateur</h1>', $html);
        self::assertStringContainsString('data-phil--slides--presenter-duration-value="0"', $html);
        self::assertStringNotContainsString('target="remaining"', $html);
        self::assertStringNotContainsString('phil-slides-presenter__audience', $html);
        self::assertStringNotContainsString('phil-slides-presenter__pdf', $html);
        self::assertStringContainsString('Aucune note pour cette diapositive.', $html);
        self::assertStringContainsString('aria-labelledby="speaker-next"', $html);
    }

    public function testBothKitsDrawTheSameControls(): void
    {
        $template = "{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(source), presenter_url: '/p'}) }}{{ include('@PhilSlides/presenter.html.twig', {deck: slides_markup(source), audience_url: '/a', notes_pdf_url: '/n', duration: 5}) }}";
        $phil = new Crawler($this->render('test', $template));
        $shadcn = new Crawler($this->render(TestKernel::SHADCN, $template));

        $both = static fn (Crawler $page): array => [...self::controls($page, 'phil--slides--deck'), ...self::controls($page, 'phil--slides--presenter')];
        // Four controls of the deck, six of the presenter, and the link to the PDF, which has no action, read with both.
        self::assertCount(11, $both($phil));
        self::assertSame($both($phil), $both($shadcn));
        self::assertSame(
            $phil->filter('[data-slot="button"]')->each(static fn (Crawler $button): string => $button->attr('data-variant').' '.$button->attr('data-size')),
            $shadcn->filter('[data-slot="button"]')->each(static fn (Crawler $button): string => $button->attr('data-variant').' '.$button->attr('data-size')),
        );
    }

    private function assertTheKitDrewThem(string $kit, Crawler $page): void
    {
        $buttons = $page->filter('[data-slot="button"]');
        self::assertCount($page->filter('button, .phil-slides__presenter a, .phil-slides-presenter__audience a, .phil-slides-presenter__pdf a')->count(), $buttons);

        $buttons->each(static function (Crawler $button) use ($kit): void {
            $classes = (string) $button->attr('class');
            $variant = (string) $button->attr('data-variant');

            if (TestKernel::SHADCN === $kit) {
                self::assertStringContainsString('inline-flex', $classes);
                self::assertStringContainsString(['secondary' => 'bg-secondary', 'outline' => 'border-border', 'default' => 'bg-primary'][$variant] ?? '?', $classes);
            } else {
                self::assertStringContainsString(['secondary' => 'isSecondary', 'outline' => 'isOutline', 'default' => 'isPrimary'][$variant] ?? '?', $classes);
                self::assertSame('a' === $button->nodeName(), str_contains($classes, 'isButton'));
            }
        });
    }

    /**
     * Each button and link of the controls: tag, type, label, action, pressed state, target, address and window.
     *
     * @return list<array{string, ?string, string, ?string, ?string, ?string, ?string, ?string}>
     */
    private static function controls(Crawler $page, string $controller): array
    {
        return array_values(array_filter(
            $page->filter('[data-slot="button"]')->each(static fn (Crawler $button): array => [
                $button->nodeName(),
                $button->attr('type'),
                trim($button->text()),
                $button->attr('data-action'),
                $button->attr('aria-pressed'),
                $button->attr('data-'.$controller.'-target'),
                $button->attr('href'),
                $button->attr('target'),
            ]),
            static fn (array $control): bool => null === $control[3] || str_starts_with($control[3], $controller.'#'),
        ));
    }

    private function render(string $kit, string $template, string $locale = 'en'): string
    {
        self::ensureKernelShutdown();
        self::bootKernel(['environment' => $kit]);
        $switcher = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);
        $switcher->setLocale($locale);
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate($template)->render([
            'source' => self::SOURCE,
            'doc' => new Document([new Block('a', 'paragraph', ['text' => 'Alone'])]),
        ]);
    }
}
