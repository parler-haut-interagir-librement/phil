<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Http;

use Phil\ModerationBundle\Application\PublicJournal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The public journal of the moderation: what was decided, on what type of content, under which role, why and when —
 * never the name of the moderator, never the author. It is the same for everyone, so a shared cache keeps it a minute.
 */
final readonly class JournalController
{
    public const string ROUTE = 'phil_moderation_journal';

    /**
     * @param positive-int $pageSize
     */
    public function __construct(
        private PublicJournal $journal,
        private Environment $twig,
        private string $layout,
        private int $pageSize,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page'));
        $total = $this->journal->count();

        $response = new Response($this->twig->render('@PhilModeration/journal.html.twig', [
            'layout' => $this->layout,
            'entries' => $this->journal->entries($this->pageSize, ($page - 1) * $this->pageSize),
            'page' => $page,
            'pages' => max(1, intdiv($total + $this->pageSize - 1, $this->pageSize)),
            'route' => self::ROUTE,
        ]));
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }
}
