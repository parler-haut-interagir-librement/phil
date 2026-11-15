<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Twig;

use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of a site use of the statement, declared by attributes and run as a Twig runtime:
 *
 * - `phil_accessibility_statement_link()`, the link of a footer to the page, its text translated unless given, with
 *   attributes of the application's choice;
 * - `phil_accessibility_statement_path()`, the address of the page in the locale of the request;
 * - `|phil_accessibility_text`, a text of the statement in the locale of the request;
 * - `phil_accessibility_kit()`, the name under which a recipe of the kit is installed, for the templates of the bundle.
 */
final readonly class StatementExtension
{
    public const string DOMAIN = 'phil_accessibility_statement';

    private KitComponents $kit;

    public function __construct(
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
        private string $defaultLocale,
        private ?TranslatorInterface $translator = null,
        ?KitComponents $kit = null,
    ) {
        $this->kit = $kit ?? new KitComponents();
    }

    /**
     * `<a href="/accessibility">Accessibility</a>`; an attribute whose name is not a plain name, or `href`, is left out.
     *
     * @param array<array-key, mixed> $attributes
     */
    #[AsTwigFunction(name: 'phil_accessibility_statement_link', isSafe: ['html'])]
    public function link(?string $label = null, array $attributes = []): string
    {
        $html = '<a href="'.$this->escape($this->path()).'"';

        foreach ($attributes as $name => $value) {
            if (1 === preg_match('/^[a-z][a-z0-9-]{0,63}$/D', (string) $name) && 'href' !== $name && \is_scalar($value)) {
                $html .= ' '.$name.'="'.$this->escape((string) $value).'"';
            }
        }

        $label ??= $this->translator?->trans('link', domain: self::DOMAIN) ?? 'Accessibility';

        return $html.'>'.$this->escape($label).'</a>';
    }

    #[AsTwigFunction(name: 'phil_accessibility_statement_path')]
    public function path(): string
    {
        return $this->urls->generate(StatementController::ROUTE);
    }

    #[AsTwigFilter(name: 'phil_accessibility_text')]
    public function text(LocalizedText $text): string
    {
        return $text->in($this->requests->getCurrentRequest()?->getLocale() ?? $this->defaultLocale, $this->defaultLocale);
    }

    /**
     * `ui:Card:Title` for `Card:Title` when the recipes are installed in the subdirectory `ui`; a template calls the
     * recipe with `{% component (phil_accessibility_kit('Card:Title')) %}`.
     */
    #[AsTwigFunction(name: 'phil_accessibility_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
