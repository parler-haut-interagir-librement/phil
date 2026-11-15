<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * What the templates use of the documents, declared by attributes and run as a Twig runtime:
 *
 * - `phil_legal_notices_links()`, the links of a footer, as `FooterLinks` gives them; the template
 *   `@PhilLegalNotices/_links.html.twig` shows them as a list;
 * - `phil_legal_notices_kit()`, the name under which a recipe of the kit is installed, for the templates of the bundle.
 */
final readonly class LegalNoticesExtension
{
    private KitComponents $kit;

    public function __construct(
        private FooterLinks $links,
        ?KitComponents $kit = null,
    ) {
        $this->kit = $kit ?? new KitComponents();
    }

    /**
     * @return list<array{url: string, label: string, domain: string}>
     */
    #[AsTwigFunction(name: 'phil_legal_notices_links')]
    public function links(): array
    {
        return $this->links->links();
    }

    /**
     * `ui:Table:Row` for `Table:Row` when the recipes are installed in the subdirectory `ui`; a template calls the recipe
     * with `{% component (phil_legal_notices_kit('Table:Row')) %}`.
     */
    #[AsTwigFunction(name: 'phil_legal_notices_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
