<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Infrastructure;

use Override;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\NoPresentedDecks;
use Phil\Slides\Application\Presentation;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\Tests\Unit\Support\Twig;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

/**
 * The presenter routes without a booted application: the role checked first, the deck looked for after, the PDF only
 * with `phil/paged-media-bundle`.
 */
#[CoversClass(PresenterController::class)]
#[CoversClass(Presentation::class)]
#[CoversClass(NoPresentedDecks::class)]
final class PresenterControllerTest extends TestCase
{
    public function testWithoutAnAuthorizationCheckerEveryoneIsRefused(): void
    {
        $decks = $this->decks();

        try {
            $this->controller($decks, null)->view('talk');
            self::fail('The view should be refused.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame('The presenter view is for the speaker.', $accessDeniedException->getMessage());
        }

        $this->expectException(AccessDeniedException::class);

        try {
            $this->controller($decks, null)->notesPdf('talk');
        } finally {
            self::assertSame([], $decks->asked);
        }
    }

    public function testTheConfiguredRoleIsAskedForBeforeTheDeck(): void
    {
        $decks = $this->decks();
        $checker = $this->checker(false);

        try {
            $this->controller($decks, $checker, role: 'ROLE_SPEAKER')->view('talk');
            self::fail('The view should be refused.');
        } catch (AccessDeniedException) {
            self::assertSame(['ROLE_SPEAKER'], $checker->asked);
            self::assertSame([], $decks->asked);
        }
    }

    public function testTheSpeakerSeesTheViewWithoutTheLinkToThePdfWhenThereIsNone(): void
    {
        $decks = $this->decks();
        $response = $this->controller($decks, $this->checker(true))->view('talk');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame(['talk'], $decks->asked);
        self::assertSame('layout:Presenter view – Talk|/talks/1|300|', (string) $response->getContent());
    }

    public function testTheSpeakerSeesTheLinkToThePdfWhenThereIsOne(): void
    {
        $response = $this->controller($this->decks(), $this->checker(true), pdf: $this->pdf())->view('talk');

        self::assertSame('layout:Presenter view – Talk|/talks/1|300|/presenter/talk/notes.pdf', (string) $response->getContent());
    }

    public function testThePdfHoldsTheNotesAndIsDownloaded(): void
    {
        $response = $this->controller($this->decks('event:2026'), $this->checker(true), pdf: $this->pdf())->notesPdf('event:2026');

        self::assertSame('%PDF with notes', $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=event-2026-notes.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testThePdfIsNotFoundWithoutPagedMedia(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('The PDF of a deck needs phil/paged-media-bundle.');

        $this->controller($this->decks(), $this->checker(true))->notesPdf('talk');
    }

    public function testADeckNobodyHasIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No such deck.');

        $this->controller(new NoPresentedDecks(), $this->checker(true))->view('talk');
    }

    public function testAPresentationLastsAtMostADay(): void
    {
        $deck = new Deck('talk', []);

        self::assertSame(0, new Presentation($deck)->duration);
        self::assertNull(new Presentation($deck)->audienceUrl);
        self::assertSame(Presentation::MAX_DURATION, new Presentation($deck, null, 1_440)->duration);

        foreach ([-1, 1_441] as $duration) {
            try {
                new Presentation($deck, null, $duration);
                self::fail('The duration should be refused.');
            } catch (InvalidOption $exception) {
                self::assertSame(\sprintf('A talk lasts 0 to 1440 minutes, not %d.', $duration), $exception->getMessage());
            }
        }
    }

    private function controller(PresentedDecks $decks, ?AuthorizationCheckerInterface $checker, string $role = 'ROLE_SLIDES_PRESENTER', ?DeckPdf $pdf = null): PresenterController
    {
        $twig = Twig::environment();
        $twig->setLoader(new ChainLoader([
            new ArrayLoader([
                'layout.html.twig' => 'layout:{% block title %}{% endblock %}|{% block main %}{% endblock %}',
                '@PhilSlides/presenter_page.html.twig' => '{% extends layout %}{% block title %}{{ "presenter.title"|trans({}, "phil_slides") }} – {{ deck.settings.title }}{% endblock %}{% block main %}{{ audience_url }}|{{ duration * 60 }}|{{ notes_pdf_url }}{% endblock %}',
            ]),
            $twig->getLoader(),
        ]));

        return new PresenterController($decks, $twig, new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                TestCase::assertSame(PresenterController::NOTES_PDF_ROUTE, $name);
                TestCase::assertIsString($parameters['deck'] ?? null);

                return '/presenter/'.$parameters['deck'].'/notes.pdf';
            }

            #[Override]
            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            #[Override]
            public function getContext(): RequestContext
            {
                return $this->context;
            }
        }, $checker, $role, 'layout.html.twig', $pdf);
    }

    /**
     * @return PresentedDecks&object{asked: list<string>}
     */
    private function decks(string $id = 'talk'): PresentedDecks
    {
        return new class($id) implements PresentedDecks {
            /** @var list<string> */
            public array $asked = [];

            public function __construct(private readonly string $id)
            {
            }

            #[Override]
            public function find(string $id): ?Presentation
            {
                $this->asked[] = $id;

                return $id === $this->id ? new Presentation(new DeckReader(new Slicer(), new Ujo())->fromMarkup("---\ntitle: Talk\n---\n\n# A\n\n::: notes\nSecret\n:::")->withId($id), '/talks/1', 5) : null;
            }
        };
    }

    /**
     * @return AuthorizationCheckerInterface&object{asked: list<mixed>}
     */
    private function checker(bool $granted): AuthorizationCheckerInterface
    {
        return new class($granted) implements AuthorizationCheckerInterface {
            /** @var list<mixed> */
            public array $asked = [];

            public function __construct(private readonly bool $granted)
            {
            }

            #[Override]
            public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
            {
                $this->asked[] = $attribute;

                return $this->granted;
            }
        };
    }

    private function pdf(): DeckPdf
    {
        return new DeckPdf(new PagedMedia(engine: new class implements PrintEngine {
            #[Override]
            public function pdf(PrintableHtml $page): string
            {
                return str_contains($page->body, 'Secret') ? '%PDF with notes' : '%PDF';
            }
        }), new DeckPrint(Twig::environment()));
    }
}
