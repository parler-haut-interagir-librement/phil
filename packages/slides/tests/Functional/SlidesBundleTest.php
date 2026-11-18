<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional;

use Override;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\PhilSlidesBundle;
use Phil\Slides\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

#[CoversClass(PhilSlidesBundle::class)]
final class SlidesBundleTest extends KernelTestCase
{
    private const string SOURCE = <<<'MD'
        ---
        title: La présentation
        ---

        # Bienvenue

        ::: step
        Un point.
        :::

        ::: notes
        Remercier les **bénévoles**.
        :::

        ---

        ## Suite

        Fin.
        MD;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testTheAudienceAndThePresenterAreRenderedInTheLocale(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $audience = $twig->createTemplate("{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(source)}) }}")->render(['source' => self::SOURCE]);
        $presenter = $twig->createTemplate("{{ include('@PhilSlides/presenter.html.twig', {deck: slides_markup(source)}) }}")->render(['source' => self::SOURCE]);

        self::assertStringNotContainsString('bénévoles', $audience);
        self::assertStringContainsString('aria-label="La présentation" data-ratio="4:3"', $audience);
        self::assertStringContainsString('data-phil--slides--deck-fragment-class-value="step"', $audience);
        self::assertStringContainsString('aria-label="Diapositive 1 sur 2 – Bienvenue"', $audience);
        self::assertStringContainsString('<div class="ujo-container step">', $audience);
        self::assertStringContainsString('Remercier les <strong>bénévoles</strong>.', $presenter);
        self::assertStringContainsString('Vue présentateur – La présentation', $presenter);
    }

    public function testTheDeckIsPrintedThroughPagedMedia(): void
    {
        $reader = self::getContainer()->get(DeckReader::class);
        $pdf = self::getContainer()->get(DeckPdf::class);
        self::assertInstanceOf(DeckReader::class, $reader);
        self::assertInstanceOf(DeckPdf::class, $pdf);
        self::assertInstanceOf(DeckPrint::class, self::getContainer()->get(DeckPrint::class));

        $deck = $reader->fromMarkup(self::SOURCE);
        $public = $pdf->render($deck)->contents;
        $noted = $pdf->render($deck, true)->contents;

        self::assertStringContainsString('@page { size: 720pt 540pt; margin: 0 }', $public);
        self::assertStringContainsString('<title>La présentation</title>', $public);
        self::assertStringNotContainsString('bénévoles', $public);
        self::assertStringContainsString('@page { size: 612pt 792pt; margin: 36pt }', $noted);
        self::assertStringContainsString('bénévoles', $noted);
        self::assertSame(2, substr_count($public, '<article class="phil-slides-print__page">'));
    }

    public function testTheControllersAreMappedForAssetMapper(): void
    {
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        self::assertNotNull($mapper->getAsset('@phil/slides/src/deck_controller.js'));
        self::assertNotNull($mapper->getAsset('@phil/slides/src/presenter_controller.js'));
        self::assertNotNull($mapper->getAsset('@phil/slides/styles/slides.css'));
    }
}
