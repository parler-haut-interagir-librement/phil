<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Http;

use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The public page of the statement: the version in force at its own address, each version at an address that never
 * changes, with the list of the versions. A page may be kept by browsers and shared caches for `$maxAge` seconds. The
 * feedback channels are the route of a page, or the key of a form of `phil/forms-bundle`, then the statement's own.
 */
final readonly class StatementController
{
    public const string ROUTE = 'phil_accessibility_statement';

    public const string VERSION_ROUTE = 'phil_accessibility_statement_version';

    public const string TEMPLATE = '@PhilAccessibilityStatement/statement.html.twig';

    /** The versions the page lists, the newest first. */
    public const int MAX_VERSIONS = 100;

    public function __construct(
        private StatementRepository $statements,
        private Environment $twig,
        private string $layout,
        private string $wording,
        private ?string $feedbackRoute,
        private int $maxAge,
        private ?string $feedbackForm = null,
    ) {
    }

    public function current(): Response
    {
        return $this->show($this->statements->latest() ?? throw new NotFoundHttpException('No accessibility statement is published.'));
    }

    public function version(int $version): Response
    {
        return $this->show($this->statements->find($version) ?? throw new NotFoundHttpException('No such version of the accessibility statement.'));
    }

    private function show(Statement $statement): Response
    {
        $groups = [];

        foreach (NonConformityReason::cases() as $reason) {
            $nonConformities = $statement->withReason($reason);

            if ([] !== $nonConformities) {
                $groups[$reason->value] = $nonConformities;
            }
        }

        $html = $this->twig->render(self::TEMPLATE, [
            'layout' => $this->layout,
            'wording' => $this->wording,
            'feedback_route' => $this->feedbackRoute,
            'feedback_form' => $this->feedbackForm,
            'statement' => $statement,
            'groups' => $groups,
            'versions' => $this->statements->publications(self::MAX_VERSIONS),
        ]);

        return new Response($html, Response::HTTP_OK, ['Cache-Control' => 0 === $this->maxAge ? 'no-cache' : 'public, max-age='.$this->maxAge]);
    }
}
