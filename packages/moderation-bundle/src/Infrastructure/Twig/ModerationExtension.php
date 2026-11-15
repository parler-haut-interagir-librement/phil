<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Twig;

use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\CurrentMemberRestrictions;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of a site use of the moderation, declared by attributes and run as a Twig runtime:
 *
 * - `phil_moderation_reasons()`, the codes of the reasons a report form offers;
 * - `phil_moderation_report_token()`, the CSRF token of the report and hide forms;
 * - `phil_moderation_may_hide()`, whether the current member is trusted to hide a content;
 * - `phil_moderation_suspended()`, whether the incident mode suspends a restriction now for the current member — an
 *   established member is spared from the restrictions the configuration lists —, always `false` without
 *   `phil/incident-mode`;
 * - `phil_moderation_kit()`, the name under which a recipe of the kit is installed.
 */
final readonly class ModerationExtension
{
    private KitComponents $kit;

    public function __construct(
        private Reasons $reasons,
        private TemporaryHides $hides,
        private CsrfTokenManagerInterface $csrfTokens,
        private ?CurrentMemberRestrictions $restrictions = null,
        ?KitComponents $kit = null,
    ) {
        $this->kit = $kit ?? new KitComponents();
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction(name: 'phil_moderation_reasons')]
    public function reasons(): array
    {
        return $this->reasons->all();
    }

    #[AsTwigFunction(name: 'phil_moderation_report_token')]
    public function reportToken(): string
    {
        return $this->csrfTokens->getToken(ReportController::CSRF_TOKEN_ID)->getValue();
    }

    #[AsTwigFunction(name: 'phil_moderation_may_hide')]
    public function mayHide(): bool
    {
        return $this->hides->mayHide();
    }

    #[AsTwigFunction(name: 'phil_moderation_suspended')]
    public function suspended(string $restriction): bool
    {
        return $this->restrictions?->appliesNow($restriction) ?? false;
    }

    /**
     * `ui:Button` for `Button` when the recipes are installed in the subdirectory `ui`; a template calls the recipe with
     * `{% component (phil_moderation_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_moderation_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
