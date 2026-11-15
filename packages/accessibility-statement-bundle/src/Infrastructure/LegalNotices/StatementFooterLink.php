<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\LegalNotices;

use Override;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\StatementExtension;
use Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The accessibility statement among the legal links of `phil/legal-notices-bundle`, in the language of the request.
 * A site with no version published, or that did not import the route, has no page to link to, and no link.
 */
final readonly class StatementFooterLink implements FooterLinkProvider
{
    public function __construct(
        private StatementRepository $statements,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function footerLinks(): array
    {
        if (!$this->statements->latest() instanceof Statement) {
            return [];
        }

        try {
            return [['url' => $this->urls->generate(StatementController::ROUTE), 'label' => 'link', 'domain' => StatementExtension::DOMAIN]];
        } catch (RouteNotFoundException) {
            return [];
        }
    }
}
