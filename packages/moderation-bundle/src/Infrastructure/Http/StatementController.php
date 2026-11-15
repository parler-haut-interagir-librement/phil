<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Http;

use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\StatementsOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The statements of reasons, for the member they are addressed to: the list of theirs, and each one with the decision,
 * its grounds, the facts and the ways to contest it. A visitor meets the firewall, as with any denied page; a statement
 * addressed to someone else is not found. Nothing is cached.
 */
final readonly class StatementController
{
    public const string LIST_ROUTE = 'phil_moderation_statements';

    public const string SHOW_ROUTE = 'phil_moderation_statement';

    /**
     * @param positive-int $pageSize
     */
    public function __construct(
        private StatementsOfReasons $statements,
        private CurrentMember $currentMember,
        private Environment $twig,
        private string $layout,
        private int $pageSize,
    ) {
    }

    public function list(Request $request): Response
    {
        $this->member();
        $page = max(1, $request->query->getInt('page'));
        $total = $this->statements->countMine();

        return $this->render('@PhilModeration/statements.html.twig', [
            'statements' => $this->statements->allMine($this->pageSize, ($page - 1) * $this->pageSize),
            'page' => $page,
            'pages' => max(1, intdiv($total + $this->pageSize - 1, $this->pageSize)),
            'route' => self::LIST_ROUTE,
        ]);
    }

    public function show(string $id): Response
    {
        $this->member();
        $statement = $this->statements->mine($id);

        if (!$statement instanceof StatementOfReasons) {
            throw new NotFoundHttpException('No such statement of reasons.');
        }

        return $this->render('@PhilModeration/statement.html.twig', [
            'statement' => $statement,
            'redress' => $this->statements->redress(),
        ]);
    }

    private function member(): MemberId
    {
        return $this->currentMember->member() ?? throw new AccessDeniedException('A statement of reasons is for the member it is addressed to.');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context + [
            'layout' => $this->layout,
            'list_route' => self::LIST_ROUTE,
            'show_route' => self::SHOW_ROUTE,
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store, private']);
    }
}
