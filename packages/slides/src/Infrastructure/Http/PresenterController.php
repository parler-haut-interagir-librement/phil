<?php

declare(strict_types=1);

namespace Phil\Slides\Infrastructure\Http;

use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\Presentation;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The routes of `config/routes.php`, for the speaker only: the presenter view of a deck, with its notes, and the PDF
 * of the deck with its notes. Both ask for the role `phil_slides.presenter_role` before anything else, so a visitor
 * learns nothing of which decks exist; without an authorization checker they are refused to everyone. The deck comes
 * from the application's PresentedDecks; the PDF needs `phil/paged-media-bundle`. Neither page is kept by a cache.
 */
final readonly class PresenterController
{
    public const string VIEW_ROUTE = 'phil_slides_presenter';

    public const string NOTES_PDF_ROUTE = 'phil_slides_presenter_notes_pdf';

    /** The identifier of a deck in an address, as Deck accepts it. */
    public const string DECK = '[A-Za-z0-9_.:-]{1,128}';

    private const array HEADERS = ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow'];

    public function __construct(
        private PresentedDecks $decks,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ?AuthorizationCheckerInterface $authorization,
        private string $role,
        private string $layout,
        private ?DeckPdf $pdf = null,
    ) {
    }

    public function view(string $deck): Response
    {
        $presentation = $this->presentation($deck);

        return new Response($this->twig->render('@PhilSlides/presenter_page.html.twig', [
            'layout' => $this->layout,
            'deck' => $presentation->deck,
            'audience_url' => $presentation->audienceUrl,
            'duration' => $presentation->duration,
            'notes_pdf_url' => $this->pdf instanceof DeckPdf ? $this->urls->generate(self::NOTES_PDF_ROUTE, ['deck' => $deck]) : null,
        ]), Response::HTTP_OK, self::HEADERS);
    }

    public function notesPdf(string $deck): Response
    {
        $presentation = $this->presentation($deck);

        if (!$this->pdf instanceof DeckPdf) {
            throw new NotFoundHttpException('The PDF of a deck needs phil/paged-media-bundle.');
        }

        $filename = strtr($deck, ':', '-').'-notes.pdf';
        $output = $this->pdf->render($presentation->deck, true, $filename);

        return new Response($output->contents, Response::HTTP_OK, [
            ...self::HEADERS,
            'Content-Type' => $output->contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function presentation(string $deck): Presentation
    {
        if (!$this->authorization instanceof AuthorizationCheckerInterface || !$this->authorization->isGranted($this->role)) {
            throw new AccessDeniedException('The presenter view is for the speaker.');
        }

        return $this->decks->find($deck) ?? throw new NotFoundHttpException('No such deck.');
    }
}
